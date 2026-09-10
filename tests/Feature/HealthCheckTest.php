<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
    }
}
