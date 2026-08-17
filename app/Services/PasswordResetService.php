<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public const CODE_LENGTH = 6;

    public function cacheKey(User $user): string
    {
        return 'password_reset_code:'.$user->id;
    }

    public function throttleKey(User $user): string
    {
        return 'password_reset_throttle:'.$user->id;
    }

    /**
     * Send a 6-digit reset OTP if the user exists.
     * Callers should not reveal whether the email is registered.
     */
    public function send(User $user, bool $force = false): void
    {
        if (! $force && Cache::has($this->throttleKey($user))) {
            throw ValidationException::withMessages([
                'email' => 'يرجى الانتظار دقيقة قبل إعادة إرسال رمز الاستعادة.',
            ]);
        }

        $code = $this->generateCode();
        $ttlMinutes = min(
            (int) config('auth.passwords.users.expire', 60),
            (int) config('auth.verification.expire', 15),
        );

        Cache::put($this->cacheKey($user), $code, now()->addMinutes(max(1, $ttlMinutes)));
        Cache::put($this->throttleKey($user), true, now()->addSeconds(60));

        $user->notify(new PasswordResetCode($code, $user->id));
    }

    /**
     * Validate OTP and set a new password. Revokes all API tokens.
     */
    public function reset(User $user, string $code, string $password): void
    {
        $expected = Cache::get($this->cacheKey($user));

        if (! is_string($expected) || ! hash_equals($expected, $code)) {
            throw ValidationException::withMessages([
                'code' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
            ]);
        }

        $user->password = $password;
        $user->save();

        // Invalidate sessions / mobile tokens after a password reset.
        $user->tokens()->delete();

        Cache::forget($this->cacheKey($user));
        Cache::forget($this->throttleKey($user));
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
}
