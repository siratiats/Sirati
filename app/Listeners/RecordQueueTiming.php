<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordQueueTiming
{
    /** @var array<int, array{started_at: float, wait_ms: int|null, attempt: int}> */
    private array $starts = [];

    public function handleProcessing(JobProcessing $event): void
    {
        $startedAt = microtime(true);
        $attempt = $this->attemptNumber($event->job);
        $createdAt = $attempt > 1 ? null : $this->pushTime($event);

        $this->starts[spl_object_id($event->job)] = [
            'started_at' => $startedAt,
            'attempt' => $attempt,
            'wait_ms' => $attempt > 1 || $createdAt === null
                ? null
                : max(0, (int) round(($startedAt - $createdAt) * 1000)),
        ];
    }

    public function handleProcessed(JobProcessed $event): void
    {
        $this->record($event->connectionName, $event->job, 'processed');
    }

    public function handleFailed(JobFailed $event): void
    {
        $this->record($event->connectionName, $event->job, 'failed');
    }

    private function record(string $connectionName, object $job, string $status): void
    {
        $key = spl_object_id($job);
        $entry = $this->starts[$key] ?? null;
        unset($this->starts[$key]);

        Log::info('Queue job timing', [
            'job' => $job->resolveName(),
            'queue' => $job->getQueue(),
            'connection' => $connectionName,
            'wait_ms' => $entry['wait_ms'] ?? null,
            'run_ms' => $entry === null
                ? null
                : max(0, (int) round((microtime(true) - $entry['started_at']) * 1000)),
            'status' => $status,
            'attempt' => $entry['attempt'] ?? $this->attemptNumber($job),
        ]);
    }

    private function attemptNumber(object $job): int
    {
        try {
            if (method_exists($job, 'attempts')) {
                $attempts = $job->attempts();
                if (is_numeric($attempts) && (int) $attempts > 0) {
                    return (int) $attempts;
                }
            } else {
                return $this->attemptFromRawBody($job);
            }
        } catch (Throwable) {
            return 1;
        }

        return $this->attemptFromRawBody($job);
    }

    private function attemptFromRawBody(object $job): int
    {
        try {
            $raw = $job->getRawBody();
        } catch (Throwable) {
            return 1;
        }

        if (! is_string($raw) || $raw === '') {
            return 1;
        }

        $decoded = json_decode($raw, true);
        $attempts = is_array($decoded) ? ($decoded['attempts'] ?? null) : null;

        return is_numeric($attempts) && (int) $attempts >= 0 ? (int) $attempts + 1 : 1;
    }

    private function pushTime(JobProcessing $event): ?float
    {
        try {
            $raw = $event->job->getRawBody();
        } catch (Throwable) {
            return null;
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        $createdAt = is_array($decoded) ? ($decoded['createdAt'] ?? null) : null;

        return is_numeric($createdAt) ? (float) $createdAt : null;
    }
}
