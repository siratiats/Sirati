<?php

namespace App\Services\SocialAuth;

use App\Services\I18n\ServerText;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Every way a third-party sign-in can fail. Each carries the HTTP status
 * the mobile API answers with; none of them ever yields a session.
 */
class SocialAuthException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const PROVIDER_UNAVAILABLE = 'provider_unavailable';

    public const INVALID_TOKEN = 'invalid_token';

    public const EMAIL_UNVERIFIED = 'email_unverified';

    private function __construct(
        public readonly string $reason,
        public readonly string $provider,
        string $detail,
    ) {
        parent::__construct($detail);
    }

    public static function notConfigured(string $provider, string $detail): self
    {
        return new self(self::NOT_CONFIGURED, $provider, $detail);
    }

    public static function providerUnavailable(string $provider, string $detail): self
    {
        return new self(self::PROVIDER_UNAVAILABLE, $provider, $detail);
    }

    public static function invalidToken(string $provider, string $detail): self
    {
        return new self(self::INVALID_TOKEN, $provider, $detail);
    }

    public static function emailUnverified(string $provider): self
    {
        return new self(self::EMAIL_UNVERIFIED, $provider, 'The provider did not confirm the email address.');
    }

    public function report(): void
    {
        $context = ['provider' => $this->provider, 'reason' => $this->reason, 'detail' => $this->getMessage()];

        if ($this->reason === self::NOT_CONFIGURED) {
            Log::critical('Social sign-in called but the provider is not configured in ['.app()->environment().']. Failing closed.', $context);
        } elseif ($this->reason === self::PROVIDER_UNAVAILABLE) {
            Log::error('Social sign-in provider key set unavailable.', $context);
        } else {
            Log::info('Social sign-in rejected.', $context);
        }
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->userMessage(), 'reason' => $this->reason], $this->status());
    }

    public function status(): int
    {
        return match ($this->reason) {
            self::NOT_CONFIGURED, self::PROVIDER_UNAVAILABLE => 503,
            self::INVALID_TOKEN => 401,
            self::EMAIL_UNVERIFIED => 422,
        };
    }

    public function userMessage(): string
    {
        $label = SocialAuthProviders::label($this->provider);

        return ServerText::t(match ($this->reason) {
            self::NOT_CONFIGURED, self::PROVIDER_UNAVAILABLE => 'serverAuthSocialUnavailable',
            self::INVALID_TOKEN => 'serverAuthSocialInvalidToken',
            self::EMAIL_UNVERIFIED => 'serverAuthSocialEmailUnverified',
        }, ['provider' => $label]);
    }
}
