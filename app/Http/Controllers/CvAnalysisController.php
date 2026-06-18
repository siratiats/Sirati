<?php

namespace App\Http\Controllers;

use App\Http\Resources\CvAnalysisResource;
use App\Models\CvAnalysis;
use App\Services\AtsScoringService;
use App\Services\CvTextExtractor;
use App\Services\OpenAiCvService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

class CvAnalysisController extends Controller
{
    public function indexApi()
    {
        return CvAnalysisResource::collection(
            CvAnalysis::latest()->limit(50)->get()
        );
    }

    public function create(Request $request)
    {
        return view('analyses.create', [
            'demo' => $request->boolean('demo') ? $this->demoCv() : [],
        ]);
    }

    public function store(Request $request, CvTextExtractor $extractor, AtsScoringService $scorer, OpenAiCvService $openAi)
    {
        $analysis = $this->createAnalysis($request, $extractor, $scorer, $openAi);

        return redirect()->route('analyses.show', $analysis);
    }

    public function storeApi(Request $request, CvTextExtractor $extractor, AtsScoringService $scorer, OpenAiCvService $openAi)
    {
        $analysis = $this->createAnalysis($request, $extractor, $scorer, $openAi);

        return (new CvAnalysisResource($analysis))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CvAnalysis $analysis)
    {
        return view('analyses.show', compact('analysis'));
    }

    public function showApi(CvAnalysis $analysis)
    {
        return new CvAnalysisResource($analysis);
    }

    private function createAnalysis(Request $request, CvTextExtractor $extractor, AtsScoringService $scorer, OpenAiCvService $openAi): CvAnalysis
    {
        $validated = $request->validate([
            'target_job_title' => ['required', 'string', 'max:160'],
            'resume_text' => ['nullable', 'string'],
            'resume_file' => ['nullable', 'file', 'max:5120', 'mimes:pdf,txt'],
        ]);

        if (! $request->filled('resume_text') && ! $request->hasFile('resume_file')) {
            throw ValidationException::withMessages([
                'resume_text' => 'الصق نص السيرة أو ارفع ملف PDF/TXT للبدء.',
            ]);
        }

        $extracted = $extractor->extract($request);
        $score = $scorer->score($extracted['text'], $validated['target_job_title']);
        $aiStatus = 'not_configured';
        $aiFeedback = null;
        $aiError = null;

        if ($openAi->isConfigured()) {
            try {
                $aiFeedback = $openAi->analysisAdvice($score, $extracted['text'], $validated['target_job_title']);
                $aiStatus = 'completed';
            } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
                $aiStatus = 'failed';
                $aiError = $exception->getMessage();
            }
        }

        return CvAnalysis::create([
            'target_job_title' => $validated['target_job_title'],
            'original_filename' => $extracted['filename'],
            'input_method' => $extracted['input_method'],
            'resume_text' => $extracted['text'],
            'score_total' => $score['total'],
            'grade' => $score['grade'],
            'job_match' => $score['job_match'],
            'criteria' => $score['criteria'],
            'strengths' => $score['strengths'],
            'weaknesses' => $score['weaknesses'],
            'keywords_found' => $score['keywords_found'],
            'keywords_missing' => $score['keywords_missing'],
            'quick_wins' => $score['quick_wins'],
            'ai_status' => $aiStatus,
            'ai_feedback' => $aiFeedback,
            'ai_error' => $aiError,
        ]);
    }

    private function demoCv(): array
    {
        return [
            'target_job_title' => 'Laravel Backend Developer',
            'resume_text' => <<<'CV'
Salem Sayer
Laravel Backend Developer
salem@example.com | +966 59 189 0300 | linkedin.com/in/salem

Summary
Backend Developer with 5+ years of experience building Laravel API platforms, SQL dashboards, and backend integrations for business teams.

Skills
PHP, Laravel, API, SQL, Git, Agile, Scrum, Backend, JavaScript, Reporting

Experience
Backend Developer, Sirati Labs, 2021 - 2025
- Developed Laravel APIs used by 25 internal users across operations and support.
- Improved reporting speed by 35% by optimizing SQL queries and dashboard endpoints.
- Built API integrations that reduced manual data entry by 20%.
- Managed release planning with agile workflows and Git version control.

Education
Bachelor of Computer Science, 2020

Certifications
AWS Certified Cloud Practitioner
CV,
        ];
    }
}
