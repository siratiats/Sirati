<?php

namespace App\Services\SocialAuth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifies an RS256 ID token against the provider's published JWKS:
 * signature, issuer, audience, expiry and subject. The key set is cached
 * and refetched at most once a minute when a token names an unknown key.
 */
abstract class JwksIdTokenVerifier implements IdTokenVerifier
{
    private const LEEWAY_SECONDS = 60;

    private const DEFAULT_JWKS_TTL = 3600;

    abstract protected function provider(): string;

    abstract protected function jwksUrl(): string;

    /** @return list<string> */
    abstract protected function issuers(): array;

    /** @return list<string> accepted `aud` values; empty means the provider is not configured */
    abstract protected function audiences(): array;

    /** @param array<string, mixed> $context */
    abstract protected function identityFrom(object $claims, array $context): VerifiedIdentity;

    public function verify(string $idToken, array $context = []): VerifiedIdentity
    {
        $audiences = $this->audiences();
        if ($audiences === []) {
            throw SocialAuthException::notConfigured($this->provider(), 'No accepted client IDs are configured.');
        }

        $kid = $this->keyIdOf($idToken);
        $jwks = $this->keySet(refresh: false);
        if (! $this->hasKey($jwks, $kid)) {
            $jwks = $this->keySet(refresh: true);
        }
        if (! $this->hasKey($jwks, $kid)) {
            throw SocialAuthException::invalidToken($this->provider(), 'Token signed with an unknown key.');
        }

        $claims = $this->decode($idToken, $jwks, $kid);

        if (! in_array($claims->iss ?? null, $this->issuers(), true)) {
            throw SocialAuthException::invalidToken($this->provider(), 'Unexpected issuer.');
        }

        $tokenAudiences = is_array($claims->aud ?? null) ? $claims->aud : [$claims->aud ?? null];
        if (array_intersect($tokenAudiences, $audiences) === []) {
            throw SocialAuthException::invalidToken($this->provider(), 'Token was issued for another client.');
        }

        // php-jwt only checks `exp` when present; an ID token without one is never accepted.
        if (! isset($claims->exp) || ! is_numeric($claims->exp)) {
            throw SocialAuthException::invalidToken($this->provider(), 'Token has no expiry.');
        }

        if (! is_string($claims->sub ?? null) || $claims->sub === '') {
            throw SocialAuthException::invalidToken($this->provider(), 'Token has no subject.');
        }

        return $this->identityFrom($claims, $context);
    }

    private function keyIdOf(string $idToken): string
    {
        $segments = explode('.', $idToken);
        if (count($segments) !== 3) {
            throw SocialAuthException::invalidToken($this->provider(), 'Malformed token.');
        }

        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($segments[0]));
        } catch (Throwable) {
            throw SocialAuthException::invalidToken($this->provider(), 'Malformed token header.');
        }

        if (! is_object($header) || ($header->alg ?? null) !== 'RS256' || ! is_string($header->kid ?? null)) {
            throw SocialAuthException::invalidToken($this->provider(), 'Unsupported token header.');
        }

        return $header->kid;
    }

    /** @param array{keys?: mixed} $jwks */
    private function hasKey(array $jwks, string $kid): bool
    {
        foreach ((array) ($jwks['keys'] ?? []) as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $kid) {
                return true;
            }
        }

        return false;
    }

    /** @param array{keys?: mixed} $jwks */
    private function decode(string $idToken, array $jwks, string $kid): object
    {
        $previousLeeway = JWT::$leeway;
        $previousTimestamp = JWT::$timestamp;
        JWT::$leeway = self::LEEWAY_SECONDS;
        JWT::$timestamp = now()->getTimestamp();

        try {
            $keys = JWK::parseKeySet($jwks, 'RS256');

            return JWT::decode($idToken, $keys[$kid]);
        } catch (Throwable $e) {
            throw SocialAuthException::invalidToken($this->provider(), 'Token rejected: '.$e->getMessage());
        } finally {
            JWT::$leeway = $previousLeeway;
            JWT::$timestamp = $previousTimestamp;
        }
    }

    /** @return array{keys?: mixed} */
    private function keySet(bool $refresh): array
    {
        $cacheKey = 'social-auth:jwks:'.$this->provider();

        if (! $refresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        } elseif (! Cache::add($cacheKey.':refreshed', true, 60)) {
            // A token with a random kid must not turn every request into a fetch.
            $cached = Cache::get($cacheKey);

            return is_array($cached) ? $cached : [];
        }

        try {
            $response = Http::timeout(5)->acceptJson()->get($this->jwksUrl());
        } catch (Throwable $e) {
            throw SocialAuthException::providerUnavailable($this->provider(), 'Key set fetch failed: '.$e->getMessage());
        }

        $jwks = $response->successful() ? $response->json() : null;
        if (! is_array($jwks) || ! is_array($jwks['keys'] ?? null)) {
            throw SocialAuthException::providerUnavailable($this->provider(), 'Key set fetch returned HTTP '.$response->status().'.');
        }

        Cache::put($cacheKey, $jwks, $this->ttlFrom((string) $response->header('Cache-Control')));

        return $jwks;
    }

    private function ttlFrom(string $cacheControl): int
    {
        if (preg_match('/max-age=(\d+)/', $cacheControl, $m) === 1) {
            return max(300, min((int) $m[1], 86400));
        }

        return self::DEFAULT_JWKS_TTL;
    }
}
