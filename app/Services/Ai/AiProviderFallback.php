<?php

namespace App\Services\Ai;

use App\Exceptions\AiRefusalException;
use App\Exceptions\AiTruncationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One-level provider fallback. Prevents OpenAI ↔ DeepInfra recursion when
 * both are unhealthy in the same job attempt.
 */
final class AiProviderFallback
{
    private static int $depth = 0;

    /**
     * @template T
     *
     * @param  callable(): T  $primary
     * @param  (callable(): T)|null  $secondary
     * @return T
     */
    public static function attempt(callable $primary, ?callable $secondary, string $warning): mixed
    {
        try {
            return $primary();
        } catch (AiTruncationException|AiRefusalException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            if ($secondary === null || self::$depth > 0) {
                throw $exception;
            }

            self::$depth++;
            try {
                Log::warning($warning, ['error' => $exception->getMessage()]);

                return $secondary();
            } finally {
                self::$depth--;
            }
        }
    }
}
