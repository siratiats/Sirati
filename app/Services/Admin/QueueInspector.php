<?php

namespace App\Services\Admin;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only view of the configured queue for the admin dashboard.
 *
 * Every method degrades to "unavailable" instead of throwing: a broken Redis
 * or a missing table must never turn an admin page into a 500.
 */
class QueueInspector
{
    public function connectionName(): string
    {
        return (string) (config('health.queue.connection') ?: config('queue.default'));
    }

    public function driver(): ?string
    {
        $driver = config("queue.connections.{$this->connectionName()}.driver");

        return is_string($driver) ? $driver : null;
    }

    /**
     * Queue names worth showing: the connection's default queue, the queues
     * our jobs are dispatched on, and (database driver) any queue with rows.
     *
     * @return list<string>
     */
    public function knownQueues(): array
    {
        $names = [
            (string) config("queue.connections.{$this->connectionName()}.queue", 'default'),
            (string) config('services.cv_ai.queue', 'default'),
            (string) config('cv_pdf.queue', 'default'),
        ];

        return array_values(array_unique(array_filter($names, fn ($name) => $name !== '')));
    }

    /**
     * @return array{available: bool, connection: string, driver: ?string, inline: bool, pending: ?int, oldest_age_seconds: ?int, failed: ?int, failed_24h: ?int, queues: list<array>, heartbeat_age_seconds: ?int, error: ?string}
     */
    public function summary(): array
    {
        $driver = $this->driver();
        $base = [
            'available' => false,
            'connection' => $this->connectionName(),
            'driver' => $driver,
            'inline' => in_array($driver, ['sync', 'deferred', 'background', 'null'], true),
            'pending' => null,
            'oldest_age_seconds' => null,
            'failed' => $this->failedCount(),
            'failed_24h' => $this->failedCount(now()->subDay()),
            'queues' => [],
            'heartbeat_age_seconds' => $this->heartbeatAge(),
            'error' => null,
        ];

        if ($base['inline']) {
            return array_merge($base, ['available' => true, 'pending' => 0, 'oldest_age_seconds' => 0]);
        }

        try {
            $queues = match ($driver) {
                'database' => $this->databaseQueues(),
                'redis' => $this->redisQueues(),
                default => null,
            };
        } catch (Throwable $exception) {
            report($exception);

            return array_merge($base, ['error' => 'Queue could not be read.']);
        }

        if ($queues === null) {
            return array_merge($base, ['error' => "Driver [{$driver}] is not inspectable."]);
        }

        $pending = array_sum(array_column($queues, 'pending'));
        $ages = array_filter(array_column($queues, 'oldest_age_seconds'), fn ($age) => $age !== null);

        return array_merge($base, [
            'available' => true,
            'pending' => $pending,
            'oldest_age_seconds' => $ages === [] ? 0 : max($ages),
            'queues' => $queues,
        ]);
    }

    /**
     * @return list<array{name: string, pending: int, delayed: int, reserved: int, oldest_age_seconds: ?int}>
     */
    private function databaseQueues(): array
    {
        $connection = config("queue.connections.{$this->connectionName()}");
        $table = (string) ($connection['table'] ?? 'jobs');
        $db = DB::connection($connection['connection'] ?? null);
        $now = now()->getTimestamp();

        $rows = $db->table($table)
            ->selectRaw('queue')
            ->selectRaw('sum(case when reserved_at is null and available_at <= ? then 1 else 0 end) as pending', [$now])
            ->selectRaw('sum(case when reserved_at is null and available_at > ? then 1 else 0 end) as delayed', [$now])
            ->selectRaw('sum(case when reserved_at is not null then 1 else 0 end) as reserved')
            ->selectRaw('min(case when reserved_at is null and available_at <= ? then created_at end) as oldest', [$now])
            ->groupBy('queue')
            ->get()
            ->keyBy('queue');

        $names = array_values(array_unique(array_merge($this->knownQueues(), $rows->keys()->all())));

        return array_map(function (string $name) use ($rows, $now): array {
            $row = $rows->get($name);
            $oldest = $row?->oldest;

            return [
                'name' => $name,
                'pending' => (int) ($row->pending ?? 0),
                'delayed' => (int) ($row->delayed ?? 0),
                'reserved' => (int) ($row->reserved ?? 0),
                'oldest_age_seconds' => $oldest === null ? null : max(0, $now - (int) $oldest),
            ];
        }, $names);
    }

    /**
     * Redis lists are RPUSHed, so index 0 is the oldest pending job.
     *
     * @return list<array{name: string, pending: int, delayed: int, reserved: int, oldest_age_seconds: ?int}>
     */
    private function redisQueues(): array
    {
        $connection = config("queue.connections.{$this->connectionName()}");
        $redis = Redis::connection((string) ($connection['connection'] ?? 'default'));
        $now = now()->getTimestamp();

        return array_map(function (string $name) use ($redis, $now): array {
            $key = 'queues:'.$name;
            $pending = (int) $redis->llen($key);
            $oldestAge = null;

            if ($pending > 0) {
                $payload = json_decode((string) $redis->lindex($key, 0), true);
                $createdAt = is_array($payload) ? ($payload['createdAt'] ?? null) : null;
                $oldestAge = is_numeric($createdAt) ? max(0, $now - (int) $createdAt) : null;
            }

            return [
                'name' => $name,
                'pending' => $pending,
                'delayed' => (int) $redis->zcard($key.':delayed'),
                'reserved' => (int) $redis->zcard($key.':reserved'),
                'oldest_age_seconds' => $oldestAge,
            ];
        }, $this->knownQueues());
    }

    public function failedJobsAvailable(): bool
    {
        try {
            return $this->failedTableConfigured()
                && Schema::connection($this->failedConnection())->hasTable($this->failedTable());
        } catch (Throwable) {
            return false;
        }
    }

    public function failedCount(?Carbon $since = null): ?int
    {
        if (! $this->failedJobsAvailable()) {
            return null;
        }

        try {
            return (int) $this->failedQuery()
                ->when($since, fn ($query) => $query->where('failed_at', '>=', $since))
                ->count();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @return list<array{id: string, numeric_id: int, job: string, queue: string, connection: string, exception: string, failed_at: ?Carbon}>|null
     */
    public function failedJobs(int $limit = 25, ?string $jobClassContains = null, ?Carbon $since = null): ?array
    {
        if (! $this->failedJobsAvailable()) {
            return null;
        }

        try {
            $hasUuid = Schema::connection($this->failedConnection())->hasColumn($this->failedTable(), 'uuid');
            $rows = $this->failedQuery()
                ->when($since, fn ($query) => $query->where('failed_at', '>=', $since))
                ->when($jobClassContains, fn ($query) => $query->where('payload', 'like', '%'.$jobClassContains.'%'))
                ->orderByDesc('failed_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return $rows->map(function ($row) use ($hasUuid): array {
            $payload = json_decode((string) $row->payload, true);

            return [
                'id' => (string) ($hasUuid && $row->uuid ? $row->uuid : $row->id),
                'numeric_id' => (int) $row->id,
                'job' => is_array($payload) ? (string) ($payload['displayName'] ?? $payload['job'] ?? 'Unknown job') : 'Unknown job',
                'queue' => (string) $row->queue,
                'connection' => (string) $row->connection,
                'exception' => self::firstLine((string) $row->exception),
                'failed_at' => $row->failed_at ? Carbon::parse($row->failed_at) : null,
            ];
        })->all();
    }

    public static function firstLine(string $text, int $limit = 220): string
    {
        $line = trim(strtok($text, "\n") ?: '');

        return mb_strlen($line) > $limit ? mb_substr($line, 0, $limit - 1).'…' : $line;
    }

    private function heartbeatAge(): ?int
    {
        try {
            $store = config('health.queue.heartbeat_cache_store');
            $value = Cache::store($store ?: null)->get((string) config('health.queue.heartbeat_key'));
        } catch (Throwable) {
            return null;
        }

        return is_numeric($value) ? max(0, now()->getTimestamp() - (int) $value) : null;
    }

    private function failedTableConfigured(): bool
    {
        return in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true);
    }

    private function failedConnection(): ?string
    {
        $connection = config('queue.failed.database');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    private function failedTable(): string
    {
        return (string) config('queue.failed.table', 'failed_jobs');
    }

    private function failedQuery()
    {
        return DB::connection($this->failedConnection())->table($this->failedTable());
    }
}
