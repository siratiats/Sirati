<?php

namespace App\Services\SocialAuth;

interface IdTokenVerifier
{
    /**
     * @param  array<string, mixed>  $context  provider-specific inputs (e.g. Apple's nonce)
     *
     * @throws SocialAuthException
     */
    public function verify(string $idToken, array $context = []): VerifiedIdentity;
}
