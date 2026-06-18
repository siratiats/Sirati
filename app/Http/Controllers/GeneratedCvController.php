<?php

namespace App\Http\Controllers;

use App\Http\Resources\GeneratedCvResource;
use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Services\AtsScoringService;
use App\Services\OpenAiCvService;
use ArPHP\I18N\Arabic;
use Dompdf\Dompdf;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;
use UnexpectedValueException;

class GeneratedCvController extends Controller
{
    public function indexApi()
    {
        return GeneratedCvResource::collection(
            GeneratedCv::latest()->limit(50)->get()
        );
    }

    public function create()
    {
        return view('generated-cvs.create');
    }

    public function store(Request $request, OpenAiCvService $openAi, AtsScoringService $scorer)
    {
        $generatedCv = $this->createGeneratedCv($this->validatedPayload($request), $openAi, $scorer);

        return redirect()->route('generated-cvs.show', $generatedCv);
    }

    public function storeApi(Request $request, OpenAiCvService $openAi, AtsScoringService $scorer)
    {
        $generatedCv = $this->createGeneratedCv($this->validatedPayload($request), $openAi, $scorer);

        return (new GeneratedCvResource($generatedCv))
            ->response()
            ->setStatusCode(201);
    }

    public function storeFromAnalysisApi(CvAnalysis $analysis, Request $request, OpenAiCvService $openAi, AtsScoringService $scorer)
    {
        $overrides = $request->validate([
            'full_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:160'],
            'language' => ['nullable', 'in:ar,en'],
        ]);

        $generatedCv = $this->createGeneratedCv(
            $this->payloadFromAnalysis($analysis, $overrides),
            $openAi,
            $scorer
        );

        return (new GeneratedCvResource($generatedCv))
            ->response()
            ->setStatusCode(201);
    }

    public function show(GeneratedCv $generatedCv)
    {
        return view('generated-cvs.show', compact('generatedCv'));
    }

    public function showApi(GeneratedCv $generatedCv)
    {
        return new GeneratedCvResource($generatedCv);
    }

    public function downloadPdf(GeneratedCv $generatedCv)
    {
        $pdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
        ]);

        $pdfData = [
            'name' => $this->formatPdfText($generatedCv->full_name, $generatedCv->language),
            'targetJobTitle' => $this->formatPdfText($generatedCv->target_job_title, $generatedCv->language),
            'content' => $this->formatPdfText($generatedCv->generated_markdown, $generatedCv->language),
        ];

        $pdf->loadHtml(view('generated-cvs.pdf', compact('generatedCv', 'pdfData'))->render(), 'UTF-8');
        $pdf->setPaper('a4');
        $pdf->render();

        $filename = 'sirati-cv-'.Str::slug($generatedCv->full_name).'-'.$generatedCv->id.'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function formatPdfText(string $text, string $language): string
    {
        if ($language !== 'ar') {
            return $text;
        }

        $arabic = new Arabic();

        return collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn (string $line) => $arabic->utf8Glyphs($line, 90, false, true))
            ->implode("\n");
    }

    private function validatedPayload(Request $request): array
    {
        return array_merge([
            'email' => null,
            'phone' => null,
            'linkedin' => null,
            'location' => null,
            'summary_input' => null,
            'certifications_input' => null,
        ], $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:160'],
            'target_job_title' => ['required', 'string', 'max:160'],
            'language' => ['required', 'in:ar,en'],
            'summary_input' => ['nullable', 'string', 'max:2000'],
            'skills_input' => ['required', 'string', 'max:3000'],
            'experience_input' => ['required', 'string', 'min:80', 'max:12000'],
            'education_input' => ['required', 'string', 'max:3000'],
            'certifications_input' => ['nullable', 'string', 'max:3000'],
        ]));
    }

    private function createGeneratedCv(array $validated, OpenAiCvService $openAi, AtsScoringService $scorer): GeneratedCv
    {
        $aiStatus = 'not_configured';
        $aiOutput = null;
        $aiError = null;
        $markdown = $this->localTemplate($validated);

        if ($openAi->isConfigured()) {
            try {
                $aiOutput = $openAi->generateCv($validated);
                $markdown = (string) ($aiOutput['cv_markdown'] ?? $markdown);
                $aiStatus = 'completed';
            } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
                $aiStatus = 'failed';
                $aiError = $exception->getMessage();
            } catch (Throwable $exception) {
                $aiStatus = 'failed';
                $aiError = $exception->getMessage();
            }
        }

        $score = $scorer->score($markdown, $validated['target_job_title']);

        return GeneratedCv::create([
            ...$validated,
            'generated_markdown' => $markdown,
            'form_payload' => $validated,
            'ai_status' => $aiStatus,
            'ai_output' => $aiOutput,
            'ai_error' => $aiError,
            'score_total' => $score['total'],
            'grade' => $score['grade'],
            'criteria' => $score['criteria'],
        ]);
    }

    private function payloadFromAnalysis(CvAnalysis $analysis, array $overrides): array
    {
        $resumeText = $analysis->resume_text;
        $skills = array_values(array_unique(array_filter([
            ...($analysis->keywords_found ?? []),
            ...array_slice($analysis->keywords_missing ?? [], 0, 5),
        ])));

        return [
            'full_name' => $this->filledValue($overrides['full_name'] ?? null)
                ?? $this->guessFullName($resumeText),
            'email' => $this->filledValue($overrides['email'] ?? null)
                ?? $this->firstMatch('/[\w.+-]+@[\w-]+\.\w+/u', $resumeText),
            'phone' => $this->filledValue($overrides['phone'] ?? null)
                ?? $this->firstMatch('/\+?\d[\d\s\-().]{7,}\d/u', $resumeText),
            'linkedin' => $this->filledValue($overrides['linkedin'] ?? null)
                ?? $this->firstMatch('/linkedin\.com\/in\/[^\s|]+/iu', $resumeText),
            'location' => $this->filledValue($overrides['location'] ?? null),
            'target_job_title' => $analysis->target_job_title,
            'language' => $this->filledValue($overrides['language'] ?? null)
                ?? $this->detectLanguage($resumeText),
            'summary_input' => data_get($analysis->ai_feedback, 'rewritten_summary'),
            'skills_input' => $skills ? implode(', ', $skills) : $analysis->target_job_title,
            'experience_input' => $this->analysisExperienceInput($analysis),
            'education_input' => $this->sectionSnippet($resumeText, '/\b(education|university|college|degree|bachelor|master|phd|التعليم|جامعة|بكالوريوس|ماجستير|دكتوراه)\b/iu')
                ?? 'غير محدد في النص الأصلي',
            'certifications_input' => $this->sectionSnippet($resumeText, '/\b(certif|license|credential|award|الشهادات|شهادات|اعتماد|جوائز)\b/iu'),
        ];
    }

    private function analysisExperienceInput(CvAnalysis $analysis): string
    {
        $quickWins = $analysis->quick_wins ?? [];

        if ($quickWins === []) {
            return $analysis->resume_text;
        }

        return $analysis->resume_text."\n\nتحسينات مطلوبة:\n- ".implode("\n- ", $quickWins);
    }

    private function guessFullName(string $resumeText): string
    {
        $firstLine = collect(preg_split('/\R/u', $resumeText) ?: [])
            ->map(fn (string $line) => trim($line))
            ->first(fn (string $line) => $line !== '' && mb_strlen($line) <= 160);

        return $firstLine ?: 'صاحب السيرة';
    }

    private function firstMatch(string $pattern, string $text): ?string
    {
        preg_match($pattern, $text, $matches);

        return $matches[0] ?? null;
    }

    private function detectLanguage(string $text): string
    {
        return preg_match('/\p{Arabic}/u', $text) ? 'ar' : 'en';
    }

    private function sectionSnippet(string $text, string $headingPattern): ?string
    {
        $lines = preg_split('/\R/u', $text) ?: [];

        foreach ($lines as $index => $line) {
            if (! preg_match($headingPattern, $line)) {
                continue;
            }

            $snippet = array_slice($lines, $index, 7);

            return trim(implode("\n", $snippet)) ?: null;
        }

        return null;
    }

    private function filledValue(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function localTemplate(array $data): string
    {
        $linkedin = $data['linkedin'] ? " | {$data['linkedin']}" : '';
        $location = $data['location'] ? " | {$data['location']}" : '';

        if ($data['language'] === 'ar') {
            $summary = $data['summary_input'] ?: "متخصص في {$data['target_job_title']} مع خبرة عملية في تنفيذ مشاريع تقنية ونتائج قابلة للقياس.";
            $certifications = $data['certifications_input'] ? "\n\n## الشهادات والدورات\n{$data['certifications_input']}" : '';

            return <<<MARKDOWN
# {$data['full_name']}
{$data['target_job_title']}
{$data['email']} | {$data['phone']}{$linkedin}{$location}

## الملخص المهني
{$summary}

## المهارات الأساسية
{$data['skills_input']}

## الخبرات العملية
{$data['experience_input']}

## التعليم
{$data['education_input']}{$certifications}
MARKDOWN;
        }

        $summary = $data['summary_input'] ?: "Professional {$data['target_job_title']} with hands-on experience and measurable impact across relevant projects.";
        $certifications = $data['certifications_input'] ? "\n\n## Certifications\n{$data['certifications_input']}" : '';

        return <<<MARKDOWN
# {$data['full_name']}
{$data['target_job_title']}
{$data['email']} | {$data['phone']}{$linkedin}{$location}

## Professional Summary
{$summary}

## Core Skills
{$data['skills_input']}

## Experience
{$data['experience_input']}

## Education
{$data['education_input']}{$certifications}
MARKDOWN;
    }
}
