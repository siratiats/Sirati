<?php

namespace App\Services;

use App\Cv\LegacySectionParser;
use App\Cv\ResolvedCvDocument;
use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\Cv\CvMarkdownRenderer;
use App\Services\Cv\LogicalTextMpdf;
use App\Support\CvMarkdownIdentityBlock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToReadFile;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;

class CvTemplateRenderer
{
    /**
     * Bump when template HTML, fonts, or renderer behaviour changes so a
     * stored PDF from a previous deploy cannot outlive the new code.
     */
    public const RENDERER_VERSION = '8';

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
        $cv = $this->viewModel($generatedCv, $template, $watermark, $language, $forExport);

        return $this->renderView($generatedCv, $template, $cv);
    }

    private function renderView(GeneratedCv $generatedCv, CvTemplate $template, array $cv): string
    {
        $pdfData = [
            'name' => $cv['candidate']['name'],
            'targetJobTitle' => $cv['candidate']['targetJobTitle'],
            'contacts' => $cv['candidate']['contacts'],
            'contentHtml' => $cv['contentHtml'],
        ];

        return view($this->viewFor($template), [
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
        [$pdfContent, $metrics] = $this->pdfArtifact($generatedCv, $template, $language);
        Log::info('CV PDF download', ['cv_id' => $generatedCv->id, ...$metrics, 'bytes' => strlen($pdfContent)]);

        $slug = Str::slug($generatedCv->full_name) ?: 'candidate';
        $filename = 'sirati-cv-'.$slug.'-'.$generatedCv->id.'.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($pdfContent),
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function renderPdfBlob(
        GeneratedCv $generatedCv,
        CvTemplate $template,
        string $language,
    ): string {
        return $this->pdfArtifact($generatedCv, $template, $language)[0];
    }

    public function deletePdfArtifacts(int $generatedCvId): void
    {
        Cache::lock('cv-pdf-files:'.$generatedCvId, 180)->block(180, function () use ($generatedCvId): void {
            if (! Storage::disk('cv_pdfs')->deleteDirectory('cv-pdfs/'.$generatedCvId)) {
                throw new \RuntimeException('Unable to delete CV PDF artifacts.');
            }
        });
    }

    /** @return array{string, array{cache: string, render_ms?: float}} */
    private function pdfArtifact(GeneratedCv $generatedCv, CvTemplate $template, string $language): array
    {
        $fingerprint = $this->pdfFingerprint($generatedCv, $template, $language);
        $directory = 'cv-pdfs/'.$generatedCv->id;
        $path = $directory.'/'.$fingerprint.'.pdf';
        $disk = Storage::disk('cv_pdfs');
        $cached = $this->readStoredPdf($path);
        if ($cached !== null) {
            return [$cached, ['cache' => 'hit']];
        }

        return Cache::lock('cv-pdf:'.$generatedCv->id.':'.$fingerprint, 180)->block(180, function () use ($generatedCv, $template, $language, $disk, $directory, $path, $fingerprint): array {
            // A second waiter must recheck after the first renderer publishes.
            $cached = $this->readStoredPdf($path);
            if ($cached !== null) {
                return [$cached, ['cache' => 'hit']];
            }

            $started = hrtime(true);
            $pdf = $this->renderPdfBlobUncached($generatedCv, $template, $language);
            $renderMs = (hrtime(true) - $started) / 1_000_000;
            $group = explode('-', $fingerprint, 2)[0];

            // Publish atomically, then prune only this template/language group.
            Cache::lock('cv-pdf-files:'.$generatedCv->id, 180)->block(180, function () use ($generatedCv, $template, $language, $fingerprint, $disk, $directory, $path, $group, $pdf): void {
                // An edit during rendering must not evict its replacement.
                $currentCv = $generatedCv->exists ? $generatedCv->fresh() : $generatedCv;
                $currentTemplate = $template->exists ? $template->fresh() : $template;
                if ($currentCv === null || $currentTemplate === null || $this->pdfFingerprint($currentCv, $currentTemplate, $language) !== $fingerprint) {
                    return;
                }
                $temporary = $path.'.'.Str::uuid().'.tmp';
                try {
                    $disk->put($temporary, $pdf, ['visibility' => 'private']);
                    $disk->move($temporary, $path);
                } finally {
                    $disk->delete($temporary);
                }
                foreach ($disk->files($directory) as $older) {
                    if ($older !== $path && str_starts_with(basename($older), $group.'-') && str_ends_with($older, '.pdf')) {
                        $disk->delete($older);
                    }
                }
            });

            return [$pdf, ['cache' => 'miss', 'render_ms' => $renderMs]];
        });
    }

    private function readStoredPdf(string $path): ?string
    {
        $disk = Storage::disk('cv_pdfs');
        try {
            return $disk->get($path);
        } catch (UnableToReadFile $exception) {
            if ($disk->exists($path)) {
                throw $exception;
            }

            return null;
        }
    }

    private function renderPdfBlobUncached(
        GeneratedCv $generatedCv,
        CvTemplate $template,
        string $language,
    ): string {
        $cv = $this->viewModel($generatedCv, $template, false, $language, true);
        try {
            $html = $this->renderView($generatedCv, $template, $cv);
        } catch (\Throwable $exception) {
            Log::warning('CV template render fallback used', [
                'generated_cv_id' => $generatedCv->id,
                'template' => $template->slug,
                'error' => $exception->getMessage(),
            ]);

            $html = $this->renderView($generatedCv, $this->resolve(null, $language), $cv);
        }

        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new \RuntimeException('Unable to create mPDF temp directory.');
        }
        $fontCacheDir = $tempDir.DIRECTORY_SEPARATOR.'ttfontdata';
        if (! is_dir($fontCacheDir) && ! mkdir($fontCacheDir, 0755, true) && ! is_dir($fontCacheDir)) {
            throw new \RuntimeException('Unable to create mPDF font cache directory.');
        }

        $defaultFont = $language === 'ar' ? 'ibmplexsansarabic' : 'freesans';

        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontDirs[] = resource_path('fonts/ibm-plex-sans-arabic');

        $fontData = (new FontVariables)->getDefaults()['fontdata'];
        // Keep Latin source letters instead of font GSUB ff/ffi ligatures in
        // both languages. Retain shaping for Arabic and the other scripts.
        foreach ($fontData as &$font) {
            $font['useOTL'] = ($font['useOTL'] ?? 0) & ~0x01;
        }
        unset($font);
        $fontData['ibmplexsansarabic'] = [
            'R' => 'IBMPlexSansArabic-Regular.ttf',
            'B' => 'IBMPlexSansArabic-Bold.ttf',
            'useOTL' => 0xFE,
            'useKashida' => 75,
        ];

        $pdf = new LogicalTextMpdf([
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
        // FreeSans has no Arabic glyphs; let candidate-typed Arabic in an English CV fall back to DejaVu.
        $pdf->useSubstitutions = $language === 'en';
        $pdf->SetDirectionality($language === 'en' ? 'ltr' : 'rtl');

        // Document Metadata (SIRATI-45 AC 47)
        $docTitle = trim($cv['candidate']['name'].' - '.$cv['candidate']['target_job_title']);
        $pdf->SetTitle($docTitle !== '' ? $docTitle : 'Curriculum Vitae');
        $pdf->SetAuthor($cv['candidate']['name'] ?: 'Siratie User');
        $pdf->SetSubject('Curriculum Vitae');
        $pdf->SetCreator('Siratie CV Platform');

        $css = '';
        $body = $html;
        if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $matches) > 0) {
            $css = implode("\n", $matches[1]);
            $body = (string) preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        }

        if (trim($css) !== '') {
            $pdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
        }
        $pdf->WriteHTML($this->markdownRenderer->preparePdfHtml($body, $language), HTMLParserMode::HTML_BODY);

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
            $this->formatPdfText($phone, $language),
            $this->formatPdfText($email, $language),
            $this->formatPdfText($linkedin, $language),
            $this->formatPdfText($location, $language),
        ], fn (string $value): bool => trim($value) !== ''));

        $bodyMarkdown = $this->bodyMarkdown($generatedCv, $resolved, $language);
        $contentHtml = $this->markdownRenderer->render(
            $bodyMarkdown,
            $language,
            $generatedCv->id,
        );

        $structuredSections = $this->arrangeSections(
            $this->stripEditorialFromSections($resolved->toStructuredSections()),
            $template,
            $language,
        );

        return [
            'style' => $this->styleFor($template),
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
                'photo' => $this->photoDataUri($generatedCv),
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

    /**
     * Cache key of a rendered PDF: everything that changes the file.
     */
    public function pdfFingerprint(GeneratedCv $generatedCv, CvTemplate $template, string $language): string
    {
        $photoPath = $generatedCv->user?->avatar_path;
        $photo = ['path' => $photoPath];
        $disk = Storage::disk('public');
        clearstatcache();
        if ($photoPath !== null && $disk->exists($photoPath)) {
            $photo['size'] = $disk->size($photoPath);
            $photo['mtime'] = $disk->lastModified($photoPath);
            // Detect replacements even at the same size within one timestamp tick.
            if ($photo['size'] <= 2 * 1024 * 1024) {
                $photo['sha256'] = hash('sha256', $disk->get($photoPath));
            }
        }

        // A stable group lets pruning retain other templates and languages.
        $group = hash('sha256', $template->slug.'|'.$language);

        $attributes = [];
        foreach (['id', 'user_id', 'full_name', 'email', 'phone', 'linkedin', 'location', 'target_job_title', 'language', 'summary_input', 'skills_input', 'experience_input', 'education_input', 'certifications_input', 'generated_markdown', 'document', 'form_payload', 'ai_status', 'score_total', 'grade', 'updated_at'] as $key) {
            $value = $generatedCv->getAttribute($key);
            $attributes[$key] = $value instanceof \DateTimeInterface ? $value->format('U.u') : $value;
        }

        $inputs = [
            'renderer' => self::RENDERER_VERSION,
            'cv' => $attributes,
            'document' => $generatedCv->cvDocument()->toArray(),
            'resolved' => $generatedCv->cvDocument()->resolve($language),
            'template' => [
                'id' => $template->getKey(),
                'slug' => $template->slug,
                'renderer_key' => $template->renderer_key,
                'name_ar' => $template->name_ar,
                'name_en' => $template->name_en,
                'is_premium' => $template->isPremium(),
                'color_tokens' => $template->color_tokens ?: [],
                'config_json' => $template->config_json ?: [],
                'updated_at' => $template->updated_at?->format('U.u'),
            ],
            'template_defaults' => config('cv_templates'),
            'language' => $language,
            'photo' => $photo,
        ];

        // Model hydration and JSON object key ordering must not change identity.
        $canonical = function (mixed $value) use (&$canonical): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($canonical, $value);
        };
        $normalized = json_decode(json_encode($inputs, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        return $group.'-'.hash('sha256', json_encode($canonical($normalized), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * The owner's profile photo, inline so both the HTML preview and mPDF can
     * show it without fetching a URL. Null without a photo, or when the file
     * is gone or too large to embed.
     */
    private function photoDataUri(GeneratedCv $generatedCv): ?string
    {
        $path = $generatedCv->user?->avatar_path;
        $disk = Storage::disk('public');
        if ($path === null || ! $disk->exists($path) || $disk->size($path) > 2 * 1024 * 1024) {
            return null;
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
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
     * Admin-controlled look: config defaults < color tokens < config_json.style.
     *
     * @return array<string, mixed>
     */
    public function styleFor(CvTemplate $template): array
    {
        $style = config('cv_templates.style_defaults', []);
        $colors = $template->color_tokens ?: [];
        if (! empty($colors['primary'])) {
            $style['name_color'] = $style['heading_color'] = $style['rule_color'] = $colors['primary'];
        }
        if (! empty($colors['accent'])) {
            $style['accent_color'] = $colors['accent'];
        }

        $overrides = $template->config_json['style'] ?? [];
        foreach (is_array($overrides) ? $overrides : [] as $key => $value) {
            if (array_key_exists($key, $style) && $value !== null && $value !== '') {
                $style[$key] = $value;
            }
        }

        foreach ($style as $key => $value) {
            if (str_ends_with($key, '_color') && ! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value)) {
                $style[$key] = config('cv_templates.style_defaults.'.$key);
            } elseif (str_ends_with($key, '_size')) {
                $style[$key] = max(6, min(40, (float) $value));
            } elseif (str_starts_with($key, 'uppercase_') || str_starts_with($key, 'show_')) {
                $style[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $style;
    }

    /**
     * Apply the template's section order, hidden sections, and title overrides.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function arrangeSections(array $sections, CvTemplate $template, string $language): array
    {
        $config = $template->config_json ?: [];
        $order = array_values(array_filter((array) ($config['section_order'] ?? []), 'is_string'))
            ?: config('cv_templates.section_order', []);
        $hidden = (array) ($config['hidden_sections'] ?? []);
        $titles = (array) ($config['section_titles'] ?? []);
        $defaultTitles = $language === 'en' ? config('cv_templates.section_titles_en', []) : [];

        $arranged = [];
        foreach ($sections as $index => $section) {
            $group = str_starts_with((string) ($section['id'] ?? ''), 'custom_')
                ? 'custom_sections'
                : (string) ($section['key'] ?? '');
            if (in_array($group, $hidden, true)) {
                continue;
            }

            $override = trim((string) ($titles[$group][$language] ?? ''));
            if ($override !== '') {
                $section['title'] = $override;
            } elseif (isset($defaultTitles[$group])) {
                $section['title'] = $defaultTitles[$group];
            }

            $position = array_search($group, $order, true);
            $arranged[] = [$position === false ? PHP_INT_MAX : $position, $index, $section];
        }

        usort($arranged, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(fn (array $item): array => $item[2], $arranged);
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
