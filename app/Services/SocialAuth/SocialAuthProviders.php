<?php

namespace App\Services\SocialAuth;

use Illuminate\Contracts\Container\Container;

/** The third-party sign-in providers the mobile API accepts. */
class SocialAuthProviders
{
    /** @var array<string, class-string<IdTokenVerifier>> */
    private const VERIFIERS = [
        'google' => GoogleIdTokenVerifier::class,
    ];

    public function __construct(private readonly Container $container) {}

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::VERIFIERS);
    }

    public static function label(string $provider): string
    {
        return match ($provider) {
            'google' => 'Google',
            'apple' => 'Apple',
            default => $provider,
        };
    }

    public function verifier(string $provider): IdTokenVerifier
    {
        return $this->container->make(self::VERIFIERS[$provider]);
    }
}
