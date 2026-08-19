<?php

namespace App\Http\Controllers;

use App\Contracts\CvAiProvider;
use App\Enums\AiStatus;
use App\Http\Resources\CvAnalysisResource;
use App\Jobs\GenerateCvAdviceJob;
use App\Models\CvAnalysis;
use App\Services\Ai\CachedCvAiProvider;
use App\Services\AtsScoringService;
use App\Services\CvTextExtractor;
use App\Services\ErrorReporter;
use App\Support\Idempotency;
use App\Support\SignedRecordAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class CvAnalysisController extends Controller
{
    public function indexApi(Request $request)
    {
        $perPage = min(50, max(1, (int) $request->integer('per_page', 20)));

        return CvAnalysisResource::collection(
            $request->user()->cvAnalyses()->latest()->paginate($perPage)
        );
    }

    public function create(Request $request)
    {
        return view('analyses.create', [
            'demo' => $request->boolean('demo') ? $this->demoCv() : [],
        ]);
    }

    public function store(Request $request, CvTextExtractor $extractor, AtsScoringService $scorer, CvAiProvider $openAi)
    {
        $analysis = $this->createAnalysis($request, $extractor, $scorer, $openAi);

        return redirect()->to(SignedRecordAccess::temporaryUrl('analyses.show', [
            'analysis' => $analysis,
        ]));
    }

    public function storeApi(Request $request, CvTextExtractor $extractor, AtsScoringService $scorer, CvAiProvider $openAi)
    {
        $existing = Idempotency::find($request, CvAnalysis::query());
        if ($existing instanceof CvAnalysis) {
            return new CvAnalysisResource($existing);
        }

        $queueAi = $request->header('X-Sirati-Async') === '1';
        $analysis = $this->createAnalysis($request, $extractor, $scorer, $openAi, $queueAi);

        if ($analysis->wasRecentlyCreated && $analysis->ai_status === AiStatus::Queued) {
            GenerateCvAdviceJob::dispatch($analysis->id);
        }

        // TODO: Remove the synchronous compatibility path only after old app
        // installs that omit X-Sirati-Async have drained from production.

        return (new CvAnalysisResource($analysis))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, CvAnalysis $analysis)
    {
        SignedRecordAccess::authorize($request, $analysis);

        return view('analyses.show', compact('analysis'));
    }

    public function showApi(Request $request, CvAnalysis $analysis)
    {
        $this->authorizeApiAccess($request, $analysis);

        return new CvAnalysisResource($analysis);
    }

    private function createAnalysis(
        Request $request,
        CvTextExtractor $extractor,
        AtsScoringService $scorer,
        CvAiProvider $openAi,
        bool $queueAi = false,
    ): CvAnalysis {
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
        $aiStatus = AiStatus::NotConfigured;
        $aiFeedback = null;
        $aiError = null;

        if ($openAi->isConfigured()) {
            if ($queueAi) {
                $aiStatus = AiStatus::Queued;
            } else {
                $startedAt = hrtime(true);

                try {
                    $aiFeedback = $openAi->analysisAdvice($score, $extracted['text'], $validated['target_job_title']);
                    $aiStatus = AiStatus::Completed;
                } catch (Throwable $exception) {
                    $aiStatus = AiStatus::Failed;
                    $aiError = $exception->getMessage();
                    $provider = CachedCvAiProvider::activeProvider();

                    app(ErrorReporter::class)->captureAiFailure(
                        exception: $exception,
                        operation: 'analysis_advice',
                        model: CachedCvAiProvider::modelForProvider($provider),
                        durationMs: (int) max(0, round((hrtime(true) - $startedAt) / 1_000_000)),
                        userId: $request->user()?->id,
                    );
                }
            }
        }

        $analysis = Idempotency::firstOrCreate($request, CvAnalysis::query(), function () use ($request, $validated, $extracted, $score, $aiStatus, $aiFeedback, $aiError) {
            return CvAnalysis::create([
                'user_id' => $request->user()?->id,
                'idempotency_key' => Idempotency::key($request),
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
        });

        assert($analysis instanceof CvAnalysis);

        return $analysis;
    }

    private function authorizeApiAccess(Request $request, CvAnalysis $analysis): void
    {
        abort_unless($analysis->user_id === $request->user()->id, 404);
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
