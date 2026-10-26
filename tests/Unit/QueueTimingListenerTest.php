<?php

namespace Tests\Unit;

use App\Listeners\RecordQueueTiming;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class QueueTimingListenerTest extends TestCase
{
    /** @var list<array{message: string, context: array}> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->logged = [];
        $logged = &$this->logged;
        Log::shouldReceive('info')
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context];
            });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public static function waitOffsetProvider(): array
    {
        return [
            'no wait' => [0],
            'one second' => [1],
            'a few seconds' => [7],
            'a minute' => [60],
            'an hour' => [3600],
            'a day' => [86400],
        ];
    }

    #[DataProvider('waitOffsetProvider')]
    public function test_wait_ms_matches_any_push_time_offset(int $offsetSeconds): void
    {
        $secret = 'secret-'.bin2hex(random_bytes(8));
        $listener = new RecordQueueTiming;
        $job = $this->jobWithPayload($this->payload(time() - $offsetSeconds, $secret));

        $listener->handleProcessing(new JobProcessing('redis', $job));
        $listener->handleProcessed(new JobProcessed('redis', $job));

        $this->assertCount(1, $this->logged);
        $context = $this->logged[0]['context'];
        $this->assertSame('processed', $context['status']);
        $this->assertSame('App\\Jobs\\ArbitraryJob', $context['job']);
        $this->assertSame('default', $context['queue']);
        $this->assertSame('redis', $context['connection']);
        $this->assertIsInt($context['wait_ms']);
        $this->assertGreaterThanOrEqual($offsetSeconds * 1000, $context['wait_ms']);
        // Generous upper bound only for test-machine scheduling jitter.
        $this->assertLessThan($offsetSeconds * 1000 + 10000, $context['wait_ms']);
        $this->assertIsInt($context['run_ms']);
        $this->assertGreaterThanOrEqual(0, $context['run_ms']);
        $this->assertStringNotContainsString($secret, json_encode($this->logged));
    }

    public function test_run_ms_grows_with_elapsed_processing_time_and_failed_status_is_logged(): void
    {
        $secret = 'secret-'.bin2hex(random_bytes(8));
        $listener = new RecordQueueTiming;
        $job = $this->jobWithPayload($this->payload(time() - 3, $secret));

        $listener->handleProcessing(new JobProcessing('database', $job));
        usleep(30000);
        $listener->handleFailed(new JobFailed('database', $job, new RuntimeException('boom')));

        $this->assertCount(1, $this->logged);
        $context = $this->logged[0]['context'];
        $this->assertSame('failed', $context['status']);
        $this->assertSame('database', $context['connection']);
        $this->assertGreaterThanOrEqual(20, $context['run_ms']);
        $this->assertGreaterThanOrEqual(3000, $context['wait_ms']);
        $this->assertStringNotContainsString($secret, json_encode($this->logged));
        $this->assertStringNotContainsString('boom payload data', json_encode($context));
    }

    public function test_missing_push_time_does_not_crash_and_wait_ms_is_null(): void
    {
        $secret = 'secret-'.bin2hex(random_bytes(8));
        $listener = new RecordQueueTiming;
        $payload = $this->payload(null, $secret);
        $job = $this->jobWithPayload($payload);

        $listener->handleProcessing(new JobProcessing('redis', $job));
        $listener->handleProcessed(new JobProcessed('redis', $job));

        $this->assertCount(1, $this->logged);
        $context = $this->logged[0]['context'];
        $this->assertNull($context['wait_ms']);
        $this->assertIsInt($context['run_ms']);
        $this->assertSame('processed', $context['status']);
        $this->assertStringNotContainsString($secret, json_encode($this->logged));
    }

    public function test_malformed_payload_does_not_crash_and_payload_data_never_reaches_the_log(): void
    {
        foreach (['not-json-at-all', '', '{"createdAt":"not-a-number","data":{"x":"leak-me"}}'] as $raw) {
            $this->logged = [];
            $listener = new RecordQueueTiming;
            $job = $this->jobWithPayload($raw);

            $listener->handleProcessing(new JobProcessing('redis', $job));
            $listener->handleProcessed(new JobProcessed('redis', $job));

            $this->assertCount(1, $this->logged, "raw payload: {$raw}");
            $this->assertNull($this->logged[0]['context']['wait_ms']);
            $this->assertStringNotContainsString('leak-me', json_encode($this->logged));
            $this->assertArrayNotHasKey('data', $this->logged[0]['context']);
        }
    }

    public function test_processed_without_processing_has_null_timings_and_does_not_crash(): void
    {
        $listener = new RecordQueueTiming;
        $job = $this->jobWithPayload($this->payload(time(), 'secret'));

        $listener->handleProcessed(new JobProcessed('redis', $job));

        $this->assertCount(1, $this->logged);
        $this->assertNull($this->logged[0]['context']['wait_ms']);
        $this->assertNull($this->logged[0]['context']['run_ms']);
    }

    public function test_future_push_time_clamps_wait_ms_to_zero(): void
    {
        $listener = new RecordQueueTiming;
        $job = $this->jobWithPayload($this->payload(time() + 3600, 'secret'));

        $listener->handleProcessing(new JobProcessing('redis', $job));
        $listener->handleProcessed(new JobProcessed('redis', $job));

        $this->assertSame(0, $this->logged[0]['context']['wait_ms']);
    }

    public function test_real_dispatcher_logs_exactly_one_timing_line_per_job(): void
    {
        $job = $this->jobWithPayload($this->payload(time() - 5, 'secret'), 1);

        Event::dispatch(new JobProcessing('redis', $job));
        Event::dispatch(new JobProcessed('redis', $job));

        $timing = array_values(array_filter(
            $this->logged,
            fn (array $entry): bool => $entry['message'] === 'Queue job timing',
        ));

        $this->assertCount(1, $timing);
        $this->assertIsInt($timing[0]['context']['wait_ms']);
        $this->assertSame(1, $timing[0]['context']['attempt']);
    }

    public static function retryAttemptProvider(): array
    {
        return [
            'second attempt' => [2],
            'third attempt' => [3],
            'fifth attempt' => [5],
        ];
    }

    #[DataProvider('retryAttemptProvider')]
    public function test_retry_attempt_has_null_wait_and_records_attempt(int $attempt): void
    {
        $listener = new RecordQueueTiming;
        $job = $this->jobWithPayload($this->payload(time() - 3600, 'secret'), $attempt);

        $listener->handleProcessing(new JobProcessing('redis', $job));
        $listener->handleProcessed(new JobProcessed('redis', $job));

        $this->assertCount(1, $this->logged);
        $this->assertNull($this->logged[0]['context']['wait_ms']);
        $this->assertSame($attempt, $this->logged[0]['context']['attempt']);
        $this->assertIsInt($this->logged[0]['context']['run_ms']);
    }

    public function test_first_attempt_computes_wait_and_records_attempt_one(): void
    {
        $listener = new RecordQueueTiming;
        $job = $this->jobWithPayload($this->payload(time() - 7, 'secret'), 1);

        $listener->handleProcessing(new JobProcessing('redis', $job));
        $listener->handleProcessed(new JobProcessed('redis', $job));

        $this->assertCount(1, $this->logged);
        $this->assertIsInt($this->logged[0]['context']['wait_ms']);
        $this->assertGreaterThanOrEqual(7000, $this->logged[0]['context']['wait_ms']);
        $this->assertSame(1, $this->logged[0]['context']['attempt']);
    }

    private function payload(?int $createdAt, string $secret): string
    {
        $payload = [
            'uuid' => 'test-uuid',
            'displayName' => 'App\\Jobs\\ArbitraryJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => ['command' => $secret, 'nested' => ['value' => $secret]],
        ];

        if ($createdAt !== null) {
            $payload['createdAt'] = $createdAt;
        }

        return json_encode($payload);
    }

    private function jobWithPayload(string $rawBody, int $attempts = 1): Job
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('attempts')->andReturn($attempts);
        $job->shouldReceive('payload')->andReturn([]);
        $job->shouldReceive('getRawBody')->andReturn($rawBody);
        $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\ArbitraryJob');
        $job->shouldReceive('getQueue')->andReturn('default');

        return $job;
    }
}
