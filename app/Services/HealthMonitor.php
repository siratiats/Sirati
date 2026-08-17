<?php

namespace App\Services;

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
