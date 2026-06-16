<?php

namespace App\Http\Controllers;

use App\Models\GeneratedCv;
use App\Services\AtsScoringService;
use App\Services\OpenAiCvService;
use Dompdf\Dompdf;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use UnexpectedValueException;

class GeneratedCvController extends Controller
{
    public function create()
    {
        return view('generated-cvs.create');
    }

    public function store(Request $request, OpenAiCvService $openAi, AtsScoringService $scorer)
    {
        $validated = $request->validate([
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
        ]);

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
            }
        }

        $score = $scorer->score($markdown, $validated['target_job_title']);

        $generatedCv = GeneratedCv::create([
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

        return redirect()->route('generated-cvs.show', $generatedCv);
    }

    public function show(GeneratedCv $generatedCv)
    {
        return view('generated-cvs.show', compact('generatedCv'));
    }

    public function downloadPdf(GeneratedCv $generatedCv)
    {
        $pdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
        ]);

        $pdf->loadHtml(view('generated-cvs.pdf', compact('generatedCv'))->render(), 'UTF-8');
        $pdf->setPaper('a4');
        $pdf->render();

        $filename = 'sirati-cv-'.Str::slug($generatedCv->full_name).'-'.$generatedCv->id.'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function localTemplate(array $data): string
    {
        $summary = $data['summary_input'] ?: "Professional {$data['target_job_title']} with hands-on experience and measurable impact across relevant projects.";
        $certifications = $data['certifications_input'] ? "\n\n## Certifications\n{$data['certifications_input']}" : '';
        $linkedin = $data['linkedin'] ? " | {$data['linkedin']}" : '';
        $location = $data['location'] ? " | {$data['location']}" : '';

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
