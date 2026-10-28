<?php

namespace App\Services\SocialAuth;

use Illuminate\Support\Str;

class GoogleIdTokenVerifier extends JwksIdTokenVerifier
{
    protected function provider(): string
    {
        return 'google';
    }

    protected function jwksUrl(): string
    {
        return 'https://www.googleapis.com/oauth2/v3/certs';
    }

    protected function issuers(): array
    {
        return ['accounts.google.com', 'https://accounts.google.com'];
    }

    protected function audiences(): array
    {
        return array_values(array_filter((array) config('services.google.client_ids', []), 'is_string'));
    }

    protected function identityFrom(object $claims, array $context): VerifiedIdentity
    {
        $email = is_string($claims->email ?? null) && $claims->email !== ''
            ? Str::lower(trim($claims->email))
            : null;
        $verified = ($claims->email_verified ?? false) === true || ($claims->email_verified ?? null) === 'true';
        $name = is_string($claims->name ?? null) ? trim($claims->name) : '';

        return new VerifiedIdentity(
            provider: 'google',
            subject: $claims->sub,
            email: $email,
            emailVerified: $verified,
            name: $name !== '' ? $name : null,
        );
    }
}
