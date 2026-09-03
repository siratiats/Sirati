<?php

namespace App\Services;

use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use App\Services\Cv\CvMarkdownRenderer;
use App\Support\CvMarkdownIdentityBlock;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

class CvTemplateRenderer
{
    public function __construct(
        private readonly CvMarkdownRenderer $markdownRenderer,
    ) {}

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

    public function renderHtml(GeneratedCv $generatedCv, ?string $templateKey = null): string
    {
        $language = $generatedCv->language === 'en' ? 'en' : 'ar';
        $template = $this->resolve($templateKey, $language);
        $view = $this->viewFor($template);
        $pdfData = [
            'name' => $this->formatPdfText($generatedCv->full_name, $language),
            'targetJobTitle' => $this->formatPdfText($generatedCv->target_job_title, $language),
            'contacts' => array_values(array_filter([
                $this->formatPdfText($generatedCv->email, $language),
                $this->formatPdfText($generatedCv->phone, $language),
                $this->formatPdfText($generatedCv->linkedin, $language),
                $this->formatPdfText($generatedCv->location, $language),
            ], fn (string $value): bool => trim($value) !== '')),
            'contentHtml' => $this->markdownRenderer->render(
                $this->bodyMarkdown($generatedCv),
                $language,
                $generatedCv->id,
            ),
        ];

        return view($view, [
            'generatedCv' => $generatedCv,
            'pdfData' => $pdfData,
            'cv' => $this->viewModel($generatedCv, $template),
            'template' => $template,
        ])->render();
    }

    public function downloadResponse(GeneratedCv $generatedCv, ?string $templateKey = null)
    {
        try {
            $html = $this->renderHtml($generatedCv, $templateKey);
        } catch (\Throwable $exception) {
            Log::warning('CV template render fallback used', [
                'generated_cv_id' => $generatedCv->id,
                'template' => $templateKey,
                'error' => $exception->getMessage(),
            ]);

            $html = $this->renderHtml($generatedCv);
        }

        $language = $generatedCv->language === 'en' ? 'en' : 'ar';
        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new \RuntimeException('Unable to create mPDF temp directory.');
        }

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
        ]);
        $pdf->autoScriptToLang = true;
        $pdf->autoLangToFont = true;
        $pdf->SetDirectionality($language === 'en' ? 'ltr' : 'rtl');
        $pdf->WriteHTML($html);

        $filename = 'sirati-cv-'.Str::slug($generatedCv->full_name).'-'.$generatedCv->id.'.pdf';

        return response($pdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function viewModel(GeneratedCv $generatedCv, CvTemplate $template): array
    {
        $language = $generatedCv->language === 'en' ? 'en' : 'ar';
        $resolved = $generatedCv->cvDocument()->resolve($language);

        return [
            'direction' => $language === 'en' ? 'ltr' : 'rtl',
            'language' => $language,
            'labels' => [
                'ats_score' => $this->formatPdfText(
                    $language === 'en' ? 'ATS score' : 'نتيجة ATS',
                    $language,
                ),
            ],
            'candidate' => [
                'full_name' => $resolved->fullName !== '' ? $resolved->fullName : $generatedCv->full_name,
                'email' => $resolved->email ?? $generatedCv->email,
                'phone' => $resolved->phone ?? $generatedCv->phone,
                'linkedin' => $resolved->linkedin ?? $generatedCv->linkedin,
                'location' => $resolved->location !== '' ? $resolved->location : $generatedCv->location,
                'target_job_title' => $resolved->headline !== '' ? $resolved->headline : $generatedCv->target_job_title,
            ],
            'summary' => $resolved->summary !== '' ? $resolved->summary : $generatedCv->summary_input,
            'sections' => [
                'skills' => $resolved->skills !== '' ? $resolved->skills : $generatedCv->skills_input,
                'experience' => $resolved->experience !== '' ? $resolved->experience : $generatedCv->experience_input,
                'education' => $resolved->education !== '' ? $resolved->education : $generatedCv->education_input,
                'certifications' => $resolved->certifications !== '' ? $resolved->certifications : $generatedCv->certifications_input,
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

    private function formatPdfText(?string $text, string $language): string
    {
        return $this->markdownRenderer->shapeText($text, $language);
    }

    private function bodyMarkdown(GeneratedCv $generatedCv): string
    {
        return CvMarkdownIdentityBlock::strip(
            (string) $generatedCv->generated_markdown,
            (string) $generatedCv->full_name,
            (string) $generatedCv->target_job_title,
            [
                (string) $generatedCv->email,
                (string) $generatedCv->phone,
                (string) $generatedCv->linkedin,
            ],
        );
    }
}
