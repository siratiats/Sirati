<?php

namespace App\Support;

use App\Services\I18n\ServerText;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * One fail-closed policy for every security and billing gate (AGENTS.md rule 7).
 *
 * Only local development and the test suite may run without required
 * configuration. Everywhere else a missing or invalid setting stops the
 * request with a 503 and a critical log, never a permissive fallback.
 */
final class FailClosed
{
    public static function allowsMissingConfiguration(): bool
    {
        return app()->environment('local', 'testing');
    }

    public static function unavailable(string $feature, string $configuration, ?string $message = null): never
    {
        // Log the key only; configuration values may contain credentials.
        Log::critical($feature.' unavailable: required configuration is missing or invalid.', [
            'configuration' => $configuration,
            'environment' => app()->environment(),
        ]);

        throw new HttpException(503, $message ?? app(ServerText::class)->get('serverErrorUnavailable'));
    }
}
