<?php

namespace App\Services\Ai;

/**
 * Last successful AI provider/model in this PHP process.
 *
 * {@see CachedCvAiProvider} looks up the cache under the configured wrapper
 * identity (so a DeepInfra miss retries DeepInfra) and stores under whoever
 * actually served. Without this, a DeepInfra hang + OpenAI fallback would
 * cache OpenAI's output under a DeepInfra key for 24h and poison bake-off data.
 */
final class AiCallContext
{
    private static ?string $provider = null;

    private static ?string $model = null;

    public static function record(string $provider, string $model): void
    {
        self::$provider = $provider;
        self::$model = $model;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    public static function pull(): array
    {
        $provider = self::$provider;
        $model = self::$model;
        self::clear();

        return [$provider, $model];
    }

    public static function clear(): void
    {
        self::$provider = null;
        self::$model = null;
    }
}
