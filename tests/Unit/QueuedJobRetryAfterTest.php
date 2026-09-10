<?php

namespace Tests\Unit;

use App\Support\QueuedJobTimeouts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

class QueuedJobRetryAfterTest extends TestCase
{
    public function test_every_queued_job_timeout_is_below_every_persistent_retry_after(): void
    {
        $jobs = QueuedJobTimeouts::jobClasses();
        $this->assertNotEmpty($jobs, 'Expected queued jobs under app/Jobs.');

        $connections = QueuedJobTimeouts::persistentConnectionNames();
        $this->assertNotEmpty($connections, 'Expected persistent queue connections with retry_after.');

        $maxTimeout = QueuedJobTimeouts::maxTimeoutSeconds();

        foreach ($jobs as $class) {
            $this->assertTrue(is_subclass_of($class, ShouldQueue::class), $class);
            $timeout = QueuedJobTimeouts::timeoutSeconds($class);
            $this->assertGreaterThan(0, $timeout, "{$class} must have a positive timeout.");

            foreach ($connections as $name) {
                $retryAfter = (int) config("queue.connections.{$name}.retry_after");
                $this->assertGreaterThan(
                    $timeout,
                    $retryAfter,
                    "{$class} timeout ({$timeout}s) must be < {$name} retry_after ({$retryAfter}s).",
                );
            }
        }

        foreach ($connections as $name) {
            $retryAfter = (int) config("queue.connections.{$name}.retry_after");
            $this->assertGreaterThan(
                $maxTimeout,
                $retryAfter,
                "Connection [{$name}] retry_after ({$retryAfter}s) must exceed the longest job timeout ({$maxTimeout}s).",
            );
        }
    }

    public function test_shipped_queue_defaults_exceed_the_longest_job_timeout(): void
    {
        $maxTimeout = QueuedJobTimeouts::maxTimeoutSeconds();

        $this->assertSame(240, (int) config('queue.connections.database.retry_after'));
        $this->assertSame(240, (int) config('queue.connections.redis.retry_after'));
        $this->assertGreaterThan($maxTimeout, 240);
    }

    public function test_discovery_is_stable_across_repeated_calls(): void
    {
        $this->assertSame(QueuedJobTimeouts::jobClasses(), QueuedJobTimeouts::jobClasses());
        $this->assertSame(QueuedJobTimeouts::persistentConnectionNames(), QueuedJobTimeouts::persistentConnectionNames());
        $this->assertSame(QueuedJobTimeouts::maxTimeoutSeconds(), QueuedJobTimeouts::maxTimeoutSeconds());
    }
}
