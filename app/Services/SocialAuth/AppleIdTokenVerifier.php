<?php

namespace App\Services\SocialAuth;

use Illuminate\Support\Str;

/**
 * Sign in with Apple ID tokens. Besides the JWKS checks, the token must
 * carry sha256(nonce) of the raw nonce the app generated for this attempt,
 * so a token captured elsewhere can't be replayed here.
 */
class AppleIdTokenVerifier extends JwksIdTokenVerifier
{
    protected function provider(): string
    {
        return 'apple';
    }

    protected function jwksUrl(): string
    {
        return 'https://appleid.apple.com/auth/keys';
    }

    protected function issuers(): array
    {
        return ['https://appleid.apple.com'];
    }

    protected function audiences(): array
    {
        return array_values(array_filter((array) config('services.apple.client_ids', []), 'is_string'));
    }

    protected function identityFrom(object $claims, array $context): VerifiedIdentity
    {
        $rawNonce = $context['nonce'] ?? null;
        if (! is_string($rawNonce) || $rawNonce === '' || ! is_string($claims->nonce ?? null)
            || ! hash_equals(hash('sha256', $rawNonce), $claims->nonce)) {
            throw SocialAuthException::invalidToken('apple', 'Nonce missing or not the one this attempt sent.');
        }

        $email = is_string($claims->email ?? null) && $claims->email !== ''
            ? Str::lower(trim($claims->email))
            : null;
        // Apple sends booleans as JSON booleans or as the strings "true"/"false".
        $verified = ($claims->email_verified ?? false) === true || ($claims->email_verified ?? null) === 'true';
        // Apple never puts the name in the token; the app passes it on the first sign-in only.
        $name = is_string($context['name'] ?? null) ? trim($context['name']) : '';

        return new VerifiedIdentity(
            provider: 'apple',
            subject: $claims->sub,
            email: $email,
            emailVerified: $verified,
            name: $name !== '' ? Str::limit($name, 255, '') : null,
            audience: is_string($claims->aud) ? $claims->aud : null,
        );
    }
}
