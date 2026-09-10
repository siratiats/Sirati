<?php

namespace Tests\Unit;

use App\Services\Ai\AiHttpRetry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

class AiHttpRetryTest extends TestCase
{
    public function test_retries_connection_errors_rate_limits_and_server_errors_only(): void
    {
        $this->assertTrue(AiHttpRetry::shouldRetry(new ConnectionException('reset')));

        foreach ([429, 500, 502, 503, 504] as $status) {
            $this->assertTrue(
                AiHttpRetry::shouldRetry($this->requestException($status)),
                "HTTP {$status} must be retried.",
            );
        }

        foreach ([400, 401, 403, 404, 422] as $status) {
            $this->assertFalse(
                AiHttpRetry::shouldRetry($this->requestException($status)),
                "HTTP {$status} must not be retried.",
            );
        }

        $this->assertFalse(AiHttpRetry::shouldRetry(new RuntimeException('application error')));
    }

    public function test_sleep_honours_retry_after_delta_and_caps_the_wait(): void
    {
        $this->assertSame(
            2000,
            AiHttpRetry::sleepMilliseconds(1, $this->requestException(429, ['Retry-After' => '2'])),
        );

        $this->assertSame(
            AiHttpRetry::MAX_DELAY_MS,
            AiHttpRetry::sleepMilliseconds(1, $this->requestException(429, ['Retry-After' => '30'])),
        );

        $this->assertSame(
            AiHttpRetry::BASE_DELAY_MS,
            AiHttpRetry::sleepMilliseconds(1, $this->requestException(500)),
        );
        $this->assertSame(
            AiHttpRetry::BASE_DELAY_MS * 2,
            AiHttpRetry::sleepMilliseconds(2, $this->requestException(500)),
        );
    }

    public function test_wall_clock_budget_skips_retry_when_remaining_time_is_below_attempt_timeout(): void
    {
        Sleep::fake();
        $url = 'https://ai-retry.test/budget-'.bin2hex(random_bytes(4));

        Http::fake([
            $url => Http::sequence()
                ->push(['error' => 'rate limited'], 429, ['Retry-After' => '0'])
                ->push(['ok' => true], 200),
        ]);

        try {
            AiHttpRetry::configure(Http::acceptJson(), budgetSeconds: 1, attemptTimeoutSeconds: 30)
                ->get($url)
                ->throw();
            $this->fail('Expected the 429 to be thrown once the budget cannot fit another attempt.');
        } catch (RequestException $exception) {
            $this->assertSame(429, $exception->response->status());
        }

        Http::assertSentCount(1);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function requestException(int $status, array $headers = []): RequestException
    {
        $url = 'https://ai-retry.test/status-'.$status.'-'.bin2hex(random_bytes(4));

        Http::fake([
            $url => Http::response(['error' => 'test'], $status, $headers),
        ]);

        try {
            Http::get($url)->throw();
        } catch (RequestException $exception) {
            return $exception;
        }

        $this->fail("Expected HTTP {$status} to throw RequestException.");
    }
}
