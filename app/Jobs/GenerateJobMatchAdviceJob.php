<?php

namespace App\Jobs;

use App\Contracts\CvAiProvider;
use App\Enums\AiStatus;
use App\Models\JobMatch;
use App\Services\Ai\AiTimeouts;
use App\Services\Ai\JobMatchResultGuard;
use App\Services\AtsScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GenerateJobMatchAdviceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = AiTimeouts::JOB_SECONDS;

    public function __construct(public readonly int $jobMatchId)
    {
        $this->onQueue(config('services.cv_ai.queue', 'default'));
    }

    public function handle(CvAiProvider $provider, AtsScoringService $scorer): void
    {
        // A duplicate queue delivery must not replace advice already selected by a user.
        $claimed = JobMatch::whereKey($this->jobMatchId)->where('ai_status', AiStatus::Queued->value)
            ->update(['ai_status' => AiStatus::Processing->value]);
        if (! $claimed) {
            return;
        }
        $match = JobMatch::findOrFail($this->jobMatchId);
        $previousUser = Auth::user();
        try {
            if (! $provider->isConfigured()) {
                throw new RuntimeException('AI provider is not configured.');
            }
            Auth::setUser($match->user);
            $raw = $provider->jobMatchAdvice($match->cv_snapshot, $match->job_description_input, $match->language);
            // Keep the structured snapshot so the guard can validate appliable targets.
            $feedback = app(JobMatchResultGuard::class)->sanitize(
                $raw,
                $match->cv_snapshot,
                [...$scorer->extractJobTerms($match->job_description_input), $match->job_description_input],
            );
            $match->update(['ai_status' => AiStatus::Completed, 'ai_feedback' => $feedback, 'ai_error' => null]);
        } catch (Throwable $exception) {
            $this->failed($exception);
        } finally {
            if ($previousUser !== null) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        JobMatch::whereKey($this->jobMatchId)->whereIn('ai_status', [AiStatus::Queued->value, AiStatus::Processing->value])->update([
            'ai_status' => AiStatus::Failed->value,
            'ai_error' => 'Job Match advice is unavailable.',
        ]);
        Log::warning('Job Match advice failed.', ['job_match_id' => $this->jobMatchId, 'exception' => $exception ? $exception::class : null]);
    }
}
