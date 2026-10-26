<?php

namespace App\Services;

use App\Services\Ai\AiTimeouts;
use App\Support\QueuedJobTimeouts;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
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

        $this->assertQueueBacklogIsFresh();
    }

    /**
     * Fail closed (AGENTS.md rule 7) when the oldest pending job has waited
     * longer than max_age_seconds, or when the queue cannot be read at all.
     */
    private function assertQueueBacklogIsFresh(): void
    {
        $connectionName = $this->queueConnection();
        $connection = config("queue.connections.{$connectionName}");

        if (! is_array($connection) || ! isset($connection['driver'])) {
            throw new RuntimeException("Queue health check: connection [{$connectionName}] is not configured.");
        }

        try {
            [$queueSize, $oldestAgeSeconds] = match ($connection['driver']) {
                'database' => $this->databaseQueueAge($connection),
                'redis' => $this->redisQueueAge($connection),
                default => throw new RuntimeException(
                    "Queue health check: unsupported driver [{$connection['driver']}] for connection [{$connectionName}]."
                ),
            };
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Queue health check: unable to read queue on connection [{$connectionName}].",
                0,
                $exception,
            );
        }

        if ($oldestAgeSeconds > $this->maxAgeSeconds()) {
            throw new RuntimeException(
                "Queue [{$connectionName}] oldest pending job age ({$oldestAgeSeconds}s) exceeds max age ({$this->maxAgeSeconds()}s); queue size is {$queueSize}."
            );
        }
    }

    /**
     * @return array{0: int, 1: int} [queue size, oldest pending job age in seconds]
     */
    private function databaseQueueAge(array $connection): array
    {
        $table = (string) ($connection['table'] ?? 'jobs');
        $queue = (string) ($connection['queue'] ?? 'default');
        $dbConnection = $connection['connection'] ?? null;

        // Pending only, mirroring the Redis list: reserved rows are running
        // (up to the 180s job timeout) and future rows are delayed, not stuck.
        $query = DB::connection($dbConnection)->table($table)
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->getTimestamp());

        $queueSize = (int) $query->count();

        if ($queueSize === 0) {
            return [0, 0];
        }

        $oldest = (clone $query)->orderBy('created_at')->first(['created_at']);

        if ($oldest === null) {
            throw new RuntimeException('Queue health check: database queue size is non-zero but no job row was readable.');
        }

        return [$queueSize, max(0, now()->getTimestamp() - (int) $oldest->created_at)];
    }

    /**
     * @return array{0: int, 1: int} [queue size, oldest pending job age in seconds]
     */
    private function redisQueueAge(array $connection): array
    {
        $redisConnection = (string) ($connection['connection'] ?? 'default');
        $queue = (string) ($connection['queue'] ?? 'default');
        $key = 'queues:'.$queue;

        $redis = Redis::connection($redisConnection);

        $queueSize = (int) $redis->llen($key);

        if ($queueSize <= 0) {
            return [0, 0];
        }

        $payload = $redis->lindex($key, 0);

        if (! is_string($payload) || $payload === '') {
            throw new RuntimeException('Queue health check: Redis queue is non-empty but its oldest job payload was not readable.');
        }

        $decoded = json_decode($payload, true);
        $createdAt = is_array($decoded) ? ($decoded['createdAt'] ?? null) : null;

        if (! is_numeric($createdAt)) {
            throw new RuntimeException('Queue health check: Redis queue oldest job payload has no push time (createdAt).');
        }

        return [$queueSize, max(0, now()->getTimestamp() - (int) $createdAt)];
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
