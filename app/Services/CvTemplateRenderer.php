<?php

namespace App\Services;

use App\Cv\LegacySectionParser;
use App\Cv\ResolvedCvDocument;
use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\Cv\CvMarkdownRenderer;
use App\Support\CvMarkdownIdentityBlock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

class CvTemplateRenderer
{
    /**
     * Bump when template HTML, fonts, or renderer behaviour changes so a
     * cached blob from a previous deploy cannot outlive the new code.
     */
    public const RENDER_VERSION = '3';

    public function __construct(
        private readonly CvMarkdownRenderer $markdownRenderer,
        private readonly ?EntitlementService $entitlements = null,
    ) {}

    private function entitlementService(): EntitlementService
    {
        return $this->entitlements ?? app(EntitlementService::class);
    }

    public function resolve(?string $templateKey, string $language): CvTemplate
    {
        $requested = $templateKey !== null && trim($templateKey) !== ''
            ? CvTemplate::query()
                ->active()
                ->where(function ($query) use ($templateKey): void {
                    $query->where('slug', $templateKey);

                    if (ctype_digit($templateKey)) {
                        $query->orWhere('id', (int) $templateKey);
                    }
                })
                ->first()
            : null;

        if ($requested !== null && $requested->supportsLanguage($language)) {
            return $requested;
        }

        if ($requested !== null) {
            Log::info('CV template language fallback used', [
                'template_id' => $requested->id,
                'template_slug' => $requested->slug,
                'language' => $language,
            ]);
        }

        $default = CvTemplate::query()
            ->active()
            ->where('is_default', true)
            ->ordered()
            ->first();

        if ($default !== null && $default->supportsLanguage($language)) {
            return $default;
        }

        return $this->fallbackTemplate();
    }

    public function renderHtml(
        GeneratedCv $generatedCv,
        ?string $templateKey = null,
        bool $watermark = false,
        ?string $languageOverride = null,
        bool $forExport = false,
    ): string {
        $language = ($languageOverride ?? $generatedCv->language) === 'en' ? 'en' : 'ar';
        $template = $this->resolve($templateKey, $language);
        $view = $this->viewFor($template);
        $cv = $this->viewModel($generatedCv, $template, $watermark, $language, $forExport);
        $pdfData = [
            'name' => $cv['candidate']['name'],
            'targetJobTitle' => $cv['candidate']['targetJobTitle'],
            'contacts' => $cv['candidate']['contacts'],
            'contentHtml' => $cv['contentHtml'],
        ];

        return view($view, [
            'generatedCv' => $generatedCv,
            'pdfData' => $pdfData,
            'cv' => $cv,
            'template' => $template,
        ])->render();
    }

    public function downloadResponse(
        GeneratedCv $generatedCv,
        ?string $templateKey = null,
        ?User $user = null,
        ?string $languageOverride = null,
    ) {
        $language = ($languageOverride ?? $generatedCv->language) === 'en' ? 'en' : 'ar';
        $template = $this->resolve($templateKey, $language);

        $effectiveUser = $user ?? $generatedCv->user;

        // Enforce premium entitlement at export boundary (SIRATI-49)
        if ($template->isPremium() && ! $this->entitlementService()->canExportTemplate($effectiveUser, $template)) {
            if (request()?->wantsJson()) {
                return response()->json([
                    'message' => 'هذا القالب متاح للمشتركين فقط. يرجى الترقية لتحميل السيرة الذاتية بهذا القالب.',
                    'error' => 'premium_template_locked',
                    'template' => $template->slug,
                ], 403);
            }
            abort(403, 'هذا القالب متاح للمشتركين فقط. يرجى الترقية لتحميل السيرة الذاتية بهذا القالب.');
        }

        // Entitled export downloads are never watermarked
        $pdfContent = $this->renderPdfBlob($generatedCv, $template, $language);

        $slug = Str::slug($generatedCv->full_name) ?: 'candidate';
        $filename = 'sirati-cv-'.$slug.'-'.$generatedCv->id.'.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function renderPdfBlob(
        GeneratedCv $generatedCv,
        CvTemplate $template,
        string $language,
    ): string {
        $updatedAt = $generatedCv->updated_at;
        $updatedKey = $updatedAt instanceof \DateTimeInterface
            ? $updatedAt->format('U.u')
            : (string) $updatedAt;

        $cacheKey = 'cv-pdf-blob:'.hash('sha256', implode('|', [
            self::RENDER_VERSION,
            (string) $generatedCv->id,
            $updatedKey,
            $template->slug,
            $language,
            (string) ($generatedCv->ai_status?->value ?? ''),
        ]));

        $cached = Cache::remember($cacheKey, 3600, function () use ($generatedCv, $template, $language): string {
            return base64_encode($this->renderPdfBlobUncached($generatedCv, $template, $language));
        });

        if (str_starts_with($cached, '%PDF-')) {
            return $cached;
        }

        $decoded = base64_decode($cached, true);

        return $decoded !== false ? $decoded : $cached;
    }

    private function renderPdfBlobUncached(
        GeneratedCv $generatedCv,
        CvTemplate $template,
        string $language,
    ): string {
        try {
            $html = $this->renderHtml($generatedCv, $template->slug, false, $language, true);
        } catch (\Throwable $exception) {
            Log::warning('CV template render fallback used', [
                'generated_cv_id' => $generatedCv->id,
                'template' => $template->slug,
                'error' => $exception->getMessage(),
            ]);

            $html = $this->renderHtml($generatedCv, null, false, $language, true);
        }

        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new \RuntimeException('Unable to create mPDF temp directory.');
        }
        $fontCacheDir = $tempDir.DIRECTORY_SEPARATOR.'ttfontdata';
        if (! is_dir($fontCacheDir) && ! mkdir($fontCacheDir, 0755, true) && ! is_dir($fontCacheDir)) {
            throw new \RuntimeException('Unable to create mPDF font cache directory.');
        }

        $cv = $this->viewModel($generatedCv, $template, false, $language, true);
        $defaultFont = $language === 'ar' ? 'ibmplexsansarabic' : 'dejavusans';

        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontDirs[] = resource_path('fonts/ibm-plex-sans-arabic');

        $fontData = (new FontVariables)->getDefaults()['fontdata'];
        $fontData['ibmplexsansarabic'] = [
            'R' => 'IBMPlexSansArabic-Regular.ttf',
            'B' => 'IBMPlexSansArabic-Bold.ttf',
            'useOTL' => 0xFF,
            'useKashida' => 75,
        ];

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
            'default_font' => $defaultFont,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_left' => 15,
            'margin_right' => 15,
        ]);
        $pdf->autoScriptToLang = false;
        $pdf->autoLangToFont = false;
        $pdf->SetDirectionality($language === 'en' ? 'ltr' : 'rtl');

        // Document Metadata (SIRATI-45 AC 47)
        $docTitle = trim($cv['candidate']['name'].' - '.$cv['candidate']['target_job_title']);
        $pdf->SetTitle($docTitle !== '' ? $docTitle : 'Curriculum Vitae');
        $pdf->SetAuthor($cv['candidate']['name'] ?: 'Sirati User');
        $pdf->SetSubject('Curriculum Vitae');
        $pdf->SetCreator('Sirati CV Platform');

        $css = '';
        $body = $html;
        if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $matches) > 0) {
            $css = implode("\n", $matches[1]);
            $body = (string) preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        }

        if (trim($css) !== '') {
            $pdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
        }
        $pdf->WriteHTML($body, HTMLParserMode::HTML_BODY);

        return $pdf->Output('', 'S');
    }

    public function viewModel(
        GeneratedCv $generatedCv,
        CvTemplate $template,
        bool $watermark = false,
        ?string $languageOverride = null,
        bool $forExport = false,
    ): array {
        $language = ($languageOverride ?? $generatedCv->language) === 'en' ? 'en' : 'ar';
        $resolved = $generatedCv->cvDocument()->resolve($language);

        $fullName = $resolved->fullName !== '' ? $resolved->fullName : (string) $generatedCv->full_name;
        $headline = $resolved->headline !== '' ? $resolved->headline : (string) $generatedCv->target_job_title;
        $email = $resolved->email ?? $generatedCv->email;
        $phone = $resolved->phone ?? $generatedCv->phone;
        $linkedin = $resolved->linkedin ?? $generatedCv->linkedin;
        $location = $resolved->location !== '' ? $resolved->location : (string) $generatedCv->location;

        $contacts = array_values(array_filter([
            $this->formatPdfText($email, $language),
            $this->formatPdfText($phone, $language),
            $this->formatPdfText($linkedin, $language),
            $this->formatPdfText($location, $language),
        ], fn (string $value): bool => trim($value) !== ''));

        $bodyMarkdown = $this->bodyMarkdown($generatedCv, $resolved, $language);
        $contentHtml = $this->markdownRenderer->render(
            $bodyMarkdown,
            $language,
            $generatedCv->id,
        );

        $structuredSections = $this->stripEditorialFromSections($resolved->toStructuredSections());

        return [
            'show_internal_score' => ! $forExport,
            'direction' => $language === 'en' ? 'ltr' : 'rtl',
            'language' => $language,
            'labels' => [
                'ats_score' => $this->formatPdfText(
                    $language === 'en' ? 'ATS score' : 'نتيجة ATS',
                    $language,
                ),
            ],
            'candidate' => [
                'name' => $this->formatPdfText($fullName, $language),
                'full_name' => $this->formatPdfText($fullName, $language),
                'targetJobTitle' => $this->formatPdfText($headline, $language),
                'target_job_title' => $this->formatPdfText($headline, $language),
                'email' => $this->formatPdfText($email, $language),
                'phone' => $this->formatPdfText($phone, $language),
                'linkedin' => $this->formatPdfText($linkedin, $language),
                'location' => $this->formatPdfText($location, $language),
                'contacts' => $contacts,
            ],
            'contentHtml' => $contentHtml,
            'structured_sections' => $structuredSections,
            'sections_data' => $structuredSections,
            'section_order' => $resolved->sectionOrder,
            'is_watermarked' => $watermark,
            'watermark_text' => $language === 'en' ? 'SIRATI PREVIEW · FOR EVALUATION ONLY' : 'معاينة سيرتي · للاطلاع فقط',
            'summary' => $resolved->summary !== '' ? $resolved->summary : $generatedCv->summary_input,
            'sections' => [
                'skills' => $resolved->skills !== '' ? $resolved->skills : $generatedCv->skills_input,
                'experience' => $resolved->experience !== '' ? $resolved->experience : $generatedCv->experience_input,
                'education' => $resolved->education !== '' ? $resolved->education : $generatedCv->education_input,
                'certifications' => $resolved->certifications !== '' ? $resolved->certifications : $generatedCv->certifications_input,
                'languages' => $resolved->languages,
                'projects' => $resolved->projects,
                'generated_markdown' => $generatedCv->generated_markdown,
            ],
            'score' => [
                'total' => $generatedCv->score_total,
                'grade' => $generatedCv->grade,
            ],
            'template' => [
                'id' => $template->getKey(),
                'name' => $template->displayName($language),
                'slug' => $template->slug,
                'is_premium' => $template->isPremium(),
                'colors' => $template->color_tokens ?: [],
                'config' => $template->config_json ?: [],
            ],
        ];
    }

    private function viewFor(CvTemplate $template): string
    {
        $slugView = 'generated-cvs.templates.'.$template->slug;
        if (view()->exists($slugView)) {
            return $slugView;
        }

        $renderers = config('cv_templates.renderers', []);

        return $renderers[$template->renderer_key]
            ?? $renderers[config('cv_templates.default_renderer')]
            ?? 'generated-cvs.pdf';
    }

    private function fallbackTemplate(): CvTemplate
    {
        $template = new CvTemplate(config('cv_templates.fallback_template'));
        $template->exists = false;
        $template->is_active = true;
        $template->is_default = true;

        return $template;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function stripEditorialFromSections(array $sections): array
    {
        foreach ($sections as &$section) {
            if (isset($section['content']) && is_string($section['content'])) {
                $section['content'] = LegacySectionParser::stripEditorial($section['content']);
            }
            if (isset($section['entries']) && is_array($section['entries'])) {
                foreach ($section['entries'] as &$entry) {
                    if (isset($entry['description']) && is_string($entry['description'])) {
                        $entry['description'] = LegacySectionParser::stripEditorial($entry['description']);
                    }
                    if (isset($entry['bullets']) && is_array($entry['bullets'])) {
                        $entry['bullets'] = array_values(array_filter(
                            array_map(
                                fn (mixed $bullet): string => is_string($bullet) ? LegacySectionParser::stripEditorial($bullet) : '',
                                $entry['bullets'],
                            ),
                            fn (string $bullet): bool => $bullet !== '',
                        ));
                    }
                }
                unset($entry);
            }
        }
        unset($section);

        return $sections;
    }

    private function formatPdfText(?string $text, string $language): string
    {
        return $this->markdownRenderer->shapeText($text, $language);
    }

    private function bodyMarkdown(GeneratedCv $generatedCv, ?ResolvedCvDocument $resolved = null, ?string $language = null): string
    {
        $markdown = (string) $generatedCv->generated_markdown;
        if (trim($markdown) === '' && $resolved !== null) {
            $markdown = $resolved->toMarkdown();
        }

        $fullName = $resolved?->fullName ?: (string) $generatedCv->full_name;
        $headline = $resolved?->headline ?: (string) $generatedCv->target_job_title;
        $contacts = array_filter([
            $resolved?->email ?? (string) $generatedCv->email,
            $resolved?->phone ?? (string) $generatedCv->phone,
            $resolved?->linkedin ?? (string) $generatedCv->linkedin,
        ]);

        return CvMarkdownIdentityBlock::strip(
            $markdown,
            $fullName,
            $headline,
            array_values($contacts),
        );
    }
}
