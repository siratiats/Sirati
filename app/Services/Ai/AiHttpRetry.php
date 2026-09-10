<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * HTTP-layer retry for AI provider calls.
 *
 * Transient 429 / 5xx / connection errors are absorbed here (honouring
 * Retry-After). A wall-clock budget — not the attempt count — is what keeps
 * the wait inside the job timeout so the DeepInfra fallback still has a
 * window. Job-level $tries is reserved for genuine failures.
 */
final class AiHttpRetry
{
    public const TIMES = AiTimeouts::HTTP_RETRY_TIMES;

    public const BASE_DELAY_MS = 500;

    public const MAX_DELAY_MS = AiTimeouts::HTTP_MAX_DELAY_MS;

    public static function configure(
        PendingRequest $request,
        int $budgetSeconds = AiTimeouts::HTTP_BUDGET_SECONDS,
        int $attemptTimeoutSeconds = 30,
    ): PendingRequest {
        $deadlineNs = hrtime(true) + max(1, $budgetSeconds) * 1_000_000_000;

        return $request->retry(
            times: self::TIMES,
            sleepMilliseconds: function (int $attempt, Throwable $exception) use ($deadlineNs): int {
                $sleep = self::sleepMilliseconds($attempt, $exception);
                $remainingMs = (int) max(0, ($deadlineNs - hrtime(true)) / 1_000_000);

                return min($sleep, $remainingMs);
            },
            when: function (Throwable $exception) use ($deadlineNs, $attemptTimeoutSeconds): bool {
                if (! self::shouldRetry($exception)) {
                    return false;
                }

                $remainingSeconds = ($deadlineNs - hrtime(true)) / 1_000_000_000;

                return $remainingSeconds >= $attemptTimeoutSeconds;
            },
            throw: true,
        );
    }

    public static function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        $status = $exception->response?->status();

        return $status === 429 || ($status !== null && $status >= 500 && $status <= 599);
    }

    public static function sleepMilliseconds(int $attempt, Throwable $exception): int
    {
        $fromHeader = self::retryAfterMilliseconds($exception);
        if ($fromHeader !== null) {
            return min($fromHeader, self::MAX_DELAY_MS);
        }

        return min(self::BASE_DELAY_MS * max(1, $attempt), self::MAX_DELAY_MS);
    }

    private static function retryAfterMilliseconds(Throwable $exception): ?int
    {
        if (! $exception instanceof RequestException) {
            return null;
        }

        $header = $exception->response?->header('Retry-After');
        if (! is_string($header) || $header === '') {
            return null;
        }

        if (is_numeric($header)) {
            return max(0, (int) $header) * 1000;
        }

        $timestamp = strtotime($header);

        return $timestamp === false ? null : max(0, ($timestamp - time()) * 1000);
    }
}
