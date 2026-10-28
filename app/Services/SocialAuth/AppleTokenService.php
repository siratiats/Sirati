<?php

namespace App\Services\SocialAuth;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Apple's token endpoints, authenticated with a client secret signed by the
 * team's Sign in with Apple key (.p8). Sign-in exchanges the authorization
 * code for a refresh token so account deletion can revoke it (App Store
 * guideline 5.1.1(v)).
 */
class AppleTokenService
{
    private const TOKEN_URL = 'https://appleid.apple.com/auth/token';

    private const REVOKE_URL = 'https://appleid.apple.com/auth/revoke';

    /** Throws (503) unless every value needed to talk to Apple is set. */
    public function assertConfigured(): void
    {
        $missing = array_keys(array_filter([
            'APPLE_CLIENT_IDS' => (array) config('services.apple.client_ids', []) === [],
            'APPLE_TEAM_ID' => ! filled(config('services.apple.team_id')),
            'APPLE_KEY_ID' => ! filled(config('services.apple.key_id')),
            'APPLE_PRIVATE_KEY' => $this->privateKey() === null,
        ]));

        if ($missing !== []) {
            throw SocialAuthException::notConfigured('apple', 'Missing or unreadable: '.implode(', ', $missing).'.');
        }
    }

    /** The refresh token for a one-time authorization code from the app. */
    public function exchange(string $authorizationCode, string $clientId): ?string
    {
        $this->assertConfigured();

        $response = $this->post(self::TOKEN_URL, [
            'client_id' => $clientId,
            'client_secret' => $this->clientSecret($clientId),
            'code' => $authorizationCode,
            'grant_type' => 'authorization_code',
        ]);

        if ($response === null || $response->serverError()) {
            throw SocialAuthException::providerUnavailable('apple', 'Token endpoint unreachable.');
        }
        if (! $response->successful()) {
            throw SocialAuthException::invalidToken('apple', 'Authorization code rejected: '.($response->json('error') ?? $response->status()));
        }

        $refreshToken = $response->json('refresh_token');

        return is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null;
    }

    /** Best effort: deleting the Sirati account must not wait on Apple. */
    public function revoke(string $refreshToken, string $clientId): bool
    {
        try {
            $this->assertConfigured();
            $response = $this->post(self::REVOKE_URL, [
                'client_id' => $clientId,
                'client_secret' => $this->clientSecret($clientId),
                'token' => $refreshToken,
                'token_type_hint' => 'refresh_token',
            ]);
        } catch (Throwable $e) {
            $response = null;
            Log::error('Apple token revocation could not run.', ['error' => $e->getMessage()]);
        }

        if ($response?->successful()) {
            return true;
        }

        Log::error('Apple token revocation failed.', ['status' => $response?->status()]);

        return false;
    }

    private function post(string $url, array $form): ?\Illuminate\Http\Client\Response
    {
        try {
            return Http::asForm()->acceptJson()->timeout(10)->post($url, $form);
        } catch (Throwable) {
            return null;
        }
    }

    private function clientSecret(string $clientId): string
    {
        $now = now()->getTimestamp();

        return JWT::encode([
            'iss' => (string) config('services.apple.team_id'),
            'iat' => $now,
            'exp' => $now + 300,
            'aud' => 'https://appleid.apple.com',
            'sub' => $clientId,
        ], $this->privateKey(), 'ES256', (string) config('services.apple.key_id'));
    }

    /** The .p8 contents, from APPLE_PRIVATE_KEY or the file at APPLE_PRIVATE_KEY_PATH. */
    private function privateKey(): ?string
    {
        $key = config('services.apple.private_key');
        $path = config('services.apple.private_key_path');
        if (! filled($key) && filled($path) && is_readable($path)) {
            $key = file_get_contents($path);
        }
        if (! is_string($key) || $key === '') {
            return null;
        }

        // .env values often carry the PEM with literal "\n".
        $key = str_replace('\n', "\n", trim($key));
        $parsed = openssl_pkey_get_private($key);
        if ($parsed === false || (openssl_pkey_get_details($parsed)['type'] ?? null) !== OPENSSL_KEYTYPE_EC) {
            return null;
        }

        return $key;
    }
}
