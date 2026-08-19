<?php

namespace App\Jobs;

use App\Contracts\CvAiProvider;
use App\Enums\AiStatus;
use App\Exceptions\AiRefusalException;
use App\Models\GeneratedCv;
use App\Services\Ai\CachedCvAiProvider;
use App\Services\AtsScoringService;
use App\Services\ErrorReporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Throwable;

class GenerateCvContentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30];

    public function __construct(public readonly int $generatedCvId)
    {
        $this->onQueue('ai');
    }

    public function handle(CvAiProvider $provider, AtsScoringService $scorer): void
    {
        $generatedCv = GeneratedCv::find($this->generatedCvId);
        if ($generatedCv === null) {
            return;
        }

        $startedAt = hrtime(true);

        $generatedCv->update([
            'ai_status' => AiStatus::Processing,
            'ai_error' => null,
        ]);

        if (! $provider->isConfigured()) {
            $exception = new RuntimeException('AI provider is not configured.');
            $generatedCv->update([
                'ai_status' => AiStatus::Failed,
                'ai_error' => $exception->getMessage(),
            ]);
            $this->reportAiFailure($exception, $startedAt, $generatedCv->user_id);

            return;
        }

        if ($generatedCv->user !== null) {
            Auth::setUser($generatedCv->user);
        }

        try {
            $output = $provider->generateCv($generatedCv->form_payload ?? []);
            $markdown = (string) ($output['cv_markdown'] ?? $generatedCv->generated_markdown);
            $score = $scorer->score($markdown, $generatedCv->target_job_title);

            $generatedCv->update([
                'generated_markdown' => $markdown,
                'ai_status' => AiStatus::Completed,
                'ai_output' => $output,
                'ai_error' => null,
                'score_total' => $score['total'],
                'grade' => $score['grade'],
                'criteria' => $score['criteria'],
            ]);
        } catch (AiRefusalException $exception) {
            $generatedCv->update([
                'ai_status' => AiStatus::Failed,
                'ai_error' => $exception->getMessage(),
            ]);
            $this->reportAiFailure($exception, $startedAt, $generatedCv->user_id);
        } catch (Throwable $exception) {
            // Stay in processing so clients keep polling until Laravel
            // exhausts retries and failed() marks the record.
            $this->reportAiFailure($exception, $startedAt, $generatedCv->user_id);

            throw $exception;
        } finally {
            Auth::forgetGuards();
        }
    }

    public function failed(?Throwable $exception): void
    {
        GeneratedCv::whereKey($this->generatedCvId)->update([
            'ai_status' => AiStatus::Failed->value,
            'ai_error' => $exception?->getMessage() ?? 'AI content job failed.',
        ]);
    }

    private function reportAiFailure(Throwable $exception, int $startedAt, ?int $userId): void
    {
        $provider = CachedCvAiProvider::activeProvider();

        app(ErrorReporter::class)->captureAiFailure(
            exception: $exception,
            operation: 'generate_cv',
            model: CachedCvAiProvider::modelForProvider($provider),
            durationMs: (int) max(0, round((hrtime(true) - $startedAt) / 1_000_000)),
            userId: $userId,
        );
    }
}
