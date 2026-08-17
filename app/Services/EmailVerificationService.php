<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    public const CODE_LENGTH = 6;

    public function cacheKey(User $user): string
    {
        return 'email_verification_code:'.$user->id;
    }

    public function throttleKey(User $user): string
    {
        return 'email_verification_throttle:'.$user->id;
    }

    /**
     * Generate a 6-digit OTP, store it, and email it to the user.
     * Throttled to once per 60 seconds per user.
     */
    public function send(User $user, bool $force = false): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        if (! $force && Cache::has($this->throttleKey($user))) {
            throw ValidationException::withMessages([
                'email' => 'يرجى الانتظار دقيقة قبل إعادة إرسال رمز التحقق.',
            ]);
        }

        $code = $this->generateCode();
        $ttlMinutes = (int) config('auth.verification.expire', 15);

        Cache::put($this->cacheKey($user), $code, now()->addMinutes($ttlMinutes));
        Cache::put($this->throttleKey($user), true, now()->addSeconds(60));

        $user->notify(new VerifyEmailCode($code, $user->id));
    }

    /**
     * Validate the OTP and mark the email as verified.
     */
    public function verify(User $user, string $code): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $expected = Cache::get($this->cacheKey($user));

        if (! is_string($expected) || ! hash_equals($expected, $code)) {
            throw ValidationException::withMessages([
                'code' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
            ]);
        }

        $user->markEmailAsVerified();
        Cache::forget($this->cacheKey($user));
        Cache::forget($this->throttleKey($user));
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
}
