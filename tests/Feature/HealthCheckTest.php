<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_is_not_successful_when_database_is_unreachable(): void
    {
        config([
            'app.debug' => false,
            'health.queue.enabled' => false,
            'database.default' => 'health_unreachable',
            'database.connections.health_unreachable' => [
                'driver' => 'sqlite',
                'database' => base_path('tmp/health-database-does-not-exist.sqlite'),
                'prefix' => '',
            ],
        ]);

        DB::purge('health_unreachable');

        $this->get('/up')->assertServerError();
    }

    public function test_health_endpoint_is_not_successful_without_a_queue_worker_heartbeat(): void
    {
        $this->configurePersistentQueueHealth();

        $this->get('/up')->assertServerError();
    }

    public function test_health_endpoint_accepts_a_fresh_queue_worker_heartbeat(): void
    {
        $this->configurePersistentQueueHealth();
        Cache::put(config('health.queue.heartbeat_key'), now()->getTimestamp(), 120);

        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_fails_when_retry_after_is_not_above_job_timeout(): void
    {
        config([
            'app.debug' => false,
            'health.queue.enabled' => false,
            'queue.connections.database.retry_after' => 30,
        ]);

        $this->get('/up')->assertServerError();
    }

    public function test_health_endpoint_fails_when_worker_timeout_is_below_job_timeout(): void
    {
        config([
            'app.debug' => false,
            'health.queue.enabled' => false,
            'queue.worker_timeout' => 30,
        ]);

        $this->get('/up')->assertServerError();
    }

    public function test_health_endpoint_fails_when_deepinfra_generate_timeout_exceeds_fallback_budget(): void
    {
        config([
            'app.debug' => false,
            'health.queue.enabled' => false,
            'services.deepinfra.generate_timeout' => 150,
        ]);

        $this->get('/up')->assertServerError();
    }

    public function test_health_endpoint_fails_when_openai_timeout_exceeds_http_budget(): void
    {
        config([
            'app.debug' => false,
            'health.queue.enabled' => false,
            'services.openai.timeout' => 80,
        ]);

        $this->get('/up')->assertServerError();
    }

    public function test_health_endpoint_accepts_a_fresh_redis_queue_oldest_job(): void
    {
        $this->configureRedisQueueHealth();
        $this->mockRedisQueue(3, $this->redisPayload(now()->getTimestamp() - 5));

        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_accepts_an_empty_redis_queue(): void
    {
        $this->configureRedisQueueHealth();
        $this->mockRedisQueue(0, null);

        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_fails_when_redis_queue_oldest_job_is_stale(): void
    {
        $this->configureRedisQueueHealth();
        $this->mockRedisQueue(2, $this->redisPayload(now()->getTimestamp() - 3600));

        $this->get('/up')->assertServerError();
    }

    public static function redisQueueSizeProvider(): array
    {
        return [
            'two jobs' => [2],
            'three jobs' => [3],
            'seven jobs' => [7],
        ];
    }

    #[DataProvider('redisQueueSizeProvider')]
    public function test_health_endpoint_fails_when_only_redis_queue_head_is_stale(int $size): void
    {
        $this->configureRedisQueueHealth();
        // Head (index 0, oldest) is stale; any newer tail jobs are irrelevant.
        $this->mockRedisQueue($size, $this->redisPayload(now()->getTimestamp() - 3600));

        $this->get('/up')->assertServerError();
    }

    #[DataProvider('redisQueueSizeProvider')]
    public function test_health_endpoint_passes_when_only_redis_queue_tail_is_stale(int $size): void
    {
        $this->configureRedisQueueHealth();
        // Head (index 0, oldest) is fresh, so the check passes regardless of
        // the tail; the mock only ever returns the head via lindex.
        $this->mockRedisQueue($size, $this->redisPayload(now()->getTimestamp() - 5));

        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_fails_closed_when_redis_queue_cannot_be_read(): void
    {
        $this->configureRedisQueueHealth();

        $redis = Mockery::mock();
        $redis->shouldReceive('llen')->andThrow(new RuntimeException('redis down'));
        Redis::shouldReceive('connection')->andReturn($redis);

        $this->get('/up')->assertServerError();
    }

    public function test_health_endpoint_fails_closed_when_redis_oldest_job_has_no_push_time(): void
    {
        $this->configureRedisQueueHealth();
        $this->mockRedisQueue(1, json_encode(['uuid' => 'x', 'data' => ['command' => 'secret']]));

        $this->get('/up')->assertServerError();
    }

    public function test_health_endpoint_fails_when_database_queue_oldest_job_is_stale(): void
    {
        $this->configurePersistentQueueHealth();
        Cache::put(config('health.queue.heartbeat_key'), now()->getTimestamp(), 120);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['createdAt' => now()->getTimestamp() - 3600, 'data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->getTimestamp() - 3600,
            'created_at' => now()->getTimestamp() - 3600,
        ]);

        $this->get('/up')->assertServerError();
    }

    public function test_health_endpoint_accepts_a_fresh_database_queue_oldest_job(): void
    {
        $this->configurePersistentQueueHealth();
        Cache::put(config('health.queue.heartbeat_key'), now()->getTimestamp(), 120);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['createdAt' => now()->getTimestamp(), 'data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->getTimestamp(),
            'created_at' => now()->getTimestamp(),
        ]);

        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_ignores_an_old_reserved_database_job_that_is_still_running(): void
    {
        $this->configurePersistentQueueHealth();
        Cache::put(config('health.queue.heartbeat_key'), now()->getTimestamp(), 120);

        foreach ([180, 3600, 86400] as $ageSeconds) {
            DB::table('jobs')->delete();
            DB::table('jobs')->insert([
                'queue' => 'default',
                'payload' => json_encode(['createdAt' => now()->getTimestamp() - $ageSeconds, 'data' => []]),
                'attempts' => 1,
                'reserved_at' => now()->getTimestamp(),
                'available_at' => now()->getTimestamp() - $ageSeconds,
                'created_at' => now()->getTimestamp() - $ageSeconds,
            ]);

            $this->get('/up')->assertOk();
        }
    }

    public function test_health_endpoint_ignores_a_future_delayed_database_job(): void
    {
        $this->configurePersistentQueueHealth();
        Cache::put(config('health.queue.heartbeat_key'), now()->getTimestamp(), 120);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['createdAt' => now()->getTimestamp() - 3600, 'data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->getTimestamp() + 3600,
            'created_at' => now()->getTimestamp() - 3600,
        ]);

        $this->get('/up')->assertOk();
    }

    private function configureRedisQueueHealth(): void
    {
        config([
            'app.debug' => false,
            'health.queue.enabled' => true,
            'health.queue.connection' => 'health_redis_queue',
            'health.queue.heartbeat_cache_store' => null,
            'health.queue.heartbeat_key' => 'test:health:queue-heartbeat',
            'health.queue.max_age_seconds' => 60,
            'queue.connections.health_redis_queue' => [
                'driver' => 'redis',
                'connection' => 'default',
                'queue' => 'default',
                'retry_after' => 240,
                'block_for' => 5,
            ],
        ]);

        Cache::put('test:health:queue-heartbeat', now()->getTimestamp(), 120);
    }

    private function mockRedisQueue(int $size, ?string $headPayload): void
    {
        $redis = Mockery::mock();
        $redis->shouldReceive('llen')->with('queues:default')->andReturn($size);
        if ($size > 0) {
            $redis->shouldReceive('lindex')->with('queues:default', 0)->andReturn($headPayload);
        }
        Redis::shouldReceive('connection')->with('default')->andReturn($redis);
    }

    private function redisPayload(int $createdAt): string
    {
        return json_encode([
            'uuid' => 'test-uuid',
            'displayName' => 'App\\Jobs\\ArbitraryJob',
            'data' => ['command' => 'arbitrary-secret-value'],
            'createdAt' => $createdAt,
        ]);
    }

    private function createJobsTableIfMissing(): void
    {
        if (Schema::hasTable('jobs')) {
            return;
        }

        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function configurePersistentQueueHealth(): void
    {
        config([
            'app.debug' => false,
            'health.queue.enabled' => true,
            'health.queue.connection' => 'health_database_queue',
            'health.queue.heartbeat_cache_store' => null,
            'health.queue.heartbeat_key' => 'test:health:queue-heartbeat',
            'health.queue.max_age_seconds' => 60,
            'queue.connections.health_database_queue' => [
                'driver' => 'database',
                'connection' => null,
                'table' => 'jobs',
                'queue' => 'default',
                'retry_after' => 90,
            ],
        ]);

        Cache::forget(config('health.queue.heartbeat_key'));
        $this->createJobsTableIfMissing();
    }
}
