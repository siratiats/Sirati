<?php

namespace App\Services\SocialAuth;

/** The claims of an ID token whose signature, issuer, audience and expiry were checked. */
final class VerifiedIdentity
{
    public function __construct(
        public readonly string $provider,
        public readonly string $subject,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $name = null,
    ) {}
}
