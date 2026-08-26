<?php

namespace App\Jobs;

use App\Contracts\CvAiProvider;
use App\Enums\AiStatus;
use App\Exceptions\AiRefusalException;
use App\Models\CvAnalysis;
use App\Services\Ai\CachedCvAiProvider;
use App\Services\AtsScoringService;
use App\Services\ErrorReporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Throwable;

class GenerateCvAdviceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30];

    public function __construct(public readonly int $analysisId)
    {
        $this->onQueue(config('services.cv_ai.queue', 'default'));
    }

    public function handle(CvAiProvider $provider, AtsScoringService $scorer): void
    {
        $analysis = CvAnalysis::find($this->analysisId);
        if ($analysis === null) {
            return;
        }

        $startedAt = hrtime(true);

        $analysis->update([
            'ai_status' => AiStatus::Processing,
            'ai_error' => null,
        ]);

        if (! $provider->isConfigured()) {
            $exception = new RuntimeException('AI provider is not configured.');
            $analysis->update([
                'ai_status' => AiStatus::Failed,
                'ai_error' => $exception->getMessage(),
            ]);
            $this->reportAiFailure($exception, $startedAt, $analysis->user_id);

            return;
        }

        if ($analysis->user !== null) {
            Auth::setUser($analysis->user);
        }

        try {
            $score = $scorer->score(
                $analysis->resume_text,
                $analysis->target_job_title,
            );

            $feedback = $provider->analysisAdvice(
                $score,
                $analysis->resume_text,
                $analysis->target_job_title,
            );

            $analysis->update([
                'ai_status' => AiStatus::Completed,
                'ai_feedback' => $feedback,
                'ai_error' => null,
            ]);
        } catch (AiRefusalException $exception) {
            $analysis->update([
                'ai_status' => AiStatus::Failed,
                'ai_error' => $exception->getMessage(),
            ]);
            $this->reportAiFailure($exception, $startedAt, $analysis->user_id);
        } catch (Throwable $exception) {
            // Stay in processing so clients keep polling until Laravel
            // exhausts retries and failed() marks the record.
            $this->reportAiFailure($exception, $startedAt, $analysis->user_id);

            throw $exception;
        } finally {
            Auth::forgetGuards();
        }
    }

    public function failed(?Throwable $exception): void
    {
        CvAnalysis::whereKey($this->analysisId)->update([
            'ai_status' => AiStatus::Failed->value,
            'ai_error' => $exception?->getMessage() ?? 'AI advice job failed.',
        ]);
    }

    private function reportAiFailure(Throwable $exception, int $startedAt, ?int $userId): void
    {
        $provider = CachedCvAiProvider::activeProvider();

        app(ErrorReporter::class)->captureAiFailure(
            exception: $exception,
            operation: 'analysis_advice',
            model: CachedCvAiProvider::modelForProvider($provider),
            durationMs: (int) max(0, round((hrtime(true) - $startedAt) / 1_000_000)),
            userId: $userId,
        );
    }
}
