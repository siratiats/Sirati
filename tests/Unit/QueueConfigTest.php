<?php

namespace Tests\Unit;

use App\Services\Ai\AiTimeouts;
use Tests\TestCase;

class QueueConfigTest extends TestCase
{
    public function test_every_connection_with_retry_after_exceeds_the_longest_job_timeout(): void
    {
        $connections = config('queue.connections');
        $this->assertIsArray($connections);

        $checked = 0;
        foreach ($connections as $name => $connection) {
            if (! is_array($connection) || ! array_key_exists('retry_after', $connection)) {
                continue;
            }
            $checked++;
            $this->assertGreaterThan(
                AiTimeouts::JOB_SECONDS,
                (int) $connection['retry_after'],
                "Connection [{$name}] retry_after must exceed AiTimeouts::JOB_SECONDS.",
            );
        }

        $this->assertGreaterThan(0, $checked, 'Expected at least one connection with retry_after.');
    }

    public function test_redis_block_for_is_a_positive_int(): void
    {
        $blockFor = config('queue.connections.redis.block_for');

        $this->assertIsInt($blockFor);
        $this->assertGreaterThan(0, $blockFor);
    }

    public function test_database_retry_after_exceeds_the_longest_job_timeout(): void
    {
        $this->assertGreaterThan(
            AiTimeouts::JOB_SECONDS,
            (int) config('queue.connections.database.retry_after'),
        );
        $this->assertGreaterThan(
            AiTimeouts::JOB_SECONDS,
            (int) config('queue.connections.redis.retry_after'),
        );
    }
}
