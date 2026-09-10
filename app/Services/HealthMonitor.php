<?php

namespace App\Services;

use App\Services\Ai\AiTimeouts;
use App\Support\QueuedJobTimeouts;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class HealthMonitor
{
    public function recordQueueHeartbeat(Looping $event): void
    {
        if (! $this->queueCheckRequired() || $event->connectionName !== $this->queueConnection()) {
            return;
        }

        try {
            $this->cache()->put(
                $this->heartbeatKey(),
                now()->getTimestamp(),
                max(1, $this->maxAgeSeconds() * 2),
            );
        } catch (Throwable $exception) {
            // A monitoring write must not stop an otherwise healthy worker.
            report($exception);
        }
    }

    public function assertHealthy(): void
    {
        DB::connection()->select('select 1');

        $this->assertQueueRetryAfterExceedsJobTimeouts();
        $this->assertAiTimeoutsFitInsideJobTimeout();

        if (! $this->queueCheckRequired()) {
            return;
        }

        $lastHeartbeat = $this->cache()->get($this->heartbeatKey());

        if (! is_numeric($lastHeartbeat)) {
            throw new RuntimeException('Queue worker heartbeat is missing.');
        }

        if (now()->getTimestamp() - (int) $lastHeartbeat > $this->maxAgeSeconds()) {
            throw new RuntimeException('Queue worker heartbeat is stale.');
        }
    }

    /**
     * Fail closed when a persistent connection would re-release a still-running job.
     *
     * Production can override retry_after via env; a passing local suite does
     * not prove the deployed value. /up is the call site that catches that.
     */
    private function assertQueueRetryAfterExceedsJobTimeouts(): void
    {
        $maxTimeout = QueuedJobTimeouts::maxTimeoutSeconds();
        $workerTimeout = (int) config('queue.worker_timeout');

        if ($workerTimeout < $maxTimeout) {
            throw new RuntimeException(
                "QUEUE_WORKER_TIMEOUT ({$workerTimeout}s) must be >= the longest job timeout ({$maxTimeout}s)."
            );
        }

        foreach (QueuedJobTimeouts::persistentConnectionNames() as $name) {
            $retryAfter = (int) config("queue.connections.{$name}.retry_after");
            if ($retryAfter <= $maxTimeout) {
                throw new RuntimeException(
                    "Queue connection [{$name}] retry_after ({$retryAfter}s) must exceed the longest job timeout ({$maxTimeout}s)."
                );
            }
            if ($retryAfter <= $workerTimeout) {
                throw new RuntimeException(
                    "Queue connection [{$name}] retry_after ({$retryAfter}s) must exceed QUEUE_WORKER_TIMEOUT ({$workerTimeout}s)."
                );
            }
        }
    }

    /**
     * Fail closed when a production env knob would let one job attempt overrun
     * GenerateCv* $timeout. A passing local suite does not prove the deployed
     * DEEPINFRA_GENERATE_TIMEOUT / OPENAI_TIMEOUT / ANTHROPIC_TIMEOUT.
     */
    private function assertAiTimeoutsFitInsideJobTimeout(): void
    {
        $generateTimeout = (int) config(
            'services.deepinfra.generate_timeout',
            AiTimeouts::DEEPINFRA_GENERATE_TIMEOUT,
        );
        $deepinfraTimeout = (int) config('services.deepinfra.timeout', 45);
        $openaiTimeout = (int) config('services.openai.timeout', 30);
        $anthropicTimeout = (int) config('services.anthropic.timeout', 30);

        if ($generateTimeout > AiTimeouts::FALLBACK_BUDGET_SECONDS) {
            throw new RuntimeException(
                "DEEPINFRA_GENERATE_TIMEOUT ({$generateTimeout}s) must be <= fallback budget (".AiTimeouts::FALLBACK_BUDGET_SECONDS.'s).'
            );
        }

        if ($deepinfraTimeout > AiTimeouts::FALLBACK_BUDGET_SECONDS) {
            throw new RuntimeException(
                "DEEPINFRA_TIMEOUT ({$deepinfraTimeout}s) must be <= fallback budget (".AiTimeouts::FALLBACK_BUDGET_SECONDS.'s).'
            );
        }

        if ($openaiTimeout > AiTimeouts::HTTP_BUDGET_SECONDS) {
            throw new RuntimeException(
                "OPENAI_TIMEOUT ({$openaiTimeout}s) must be <= HTTP budget (".AiTimeouts::HTTP_BUDGET_SECONDS.'s).'
            );
        }

        if ($anthropicTimeout > AiTimeouts::HTTP_BUDGET_SECONDS) {
            throw new RuntimeException(
                "ANTHROPIC_TIMEOUT ({$anthropicTimeout}s) must be <= HTTP budget (".AiTimeouts::HTTP_BUDGET_SECONDS.'s).'
            );
        }

        $worstCase = AiTimeouts::worstCaseJobAttemptSeconds();
        if ($worstCase >= AiTimeouts::JOB_SECONDS) {
            throw new RuntimeException(
                "AI timeout stack worst case ({$worstCase}s) must be below GenerateCv job timeout (".AiTimeouts::JOB_SECONDS.'s).'
            );
        }
    }

    private function queueCheckRequired(): bool
    {
        if (! config('health.queue.enabled', true)) {
            return false;
        }

        $driver = config("queue.connections.{$this->queueConnection()}.driver");

        return ! in_array($driver, ['sync', 'deferred', 'background', 'null'], true);
    }

    private function queueConnection(): string
    {
        return (string) config('health.queue.connection', config('queue.default'));
    }

    private function heartbeatKey(): string
    {
        return (string) config('health.queue.heartbeat_key');
    }

    private function maxAgeSeconds(): int
    {
        return max(1, (int) config('health.queue.max_age_seconds', 120));
    }

    private function cache()
    {
        $store = config('health.queue.heartbeat_cache_store');

        return filled($store) ? Cache::store($store) : Cache::store();
    }
}
