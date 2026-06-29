<?php

namespace App\Services;

use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use ArPHP\I18N\Arabic;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CvTemplateRenderer
{
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
            'content' => $this->formatPdfText($generatedCv->generated_markdown, $language),
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
        $pdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
        ]);

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

        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('a4');
        $pdf->render();

        $filename = 'sirati-cv-'.Str::slug($generatedCv->full_name).'-'.$generatedCv->id.'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function viewModel(GeneratedCv $generatedCv, CvTemplate $template): array
    {
        $language = $generatedCv->language === 'en' ? 'en' : 'ar';

        return [
            'direction' => $language === 'en' ? 'ltr' : 'rtl',
            'language' => $language,
            'candidate' => [
                'full_name' => $generatedCv->full_name,
                'email' => $generatedCv->email,
                'phone' => $generatedCv->phone,
                'linkedin' => $generatedCv->linkedin,
                'location' => $generatedCv->location,
                'target_job_title' => $generatedCv->target_job_title,
            ],
            'summary' => $generatedCv->summary_input,
            'sections' => [
                'skills' => $generatedCv->skills_input,
                'experience' => $generatedCv->experience_input,
                'education' => $generatedCv->education_input,
                'certifications' => $generatedCv->certifications_input,
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
        $text = (string) $text;
        if ($language !== 'ar') {
            return $text;
        }

        $arabic = new Arabic();

        return collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn (string $line) => $arabic->utf8Glyphs($line, 90, false, true))
            ->implode("\n");
    }
}
