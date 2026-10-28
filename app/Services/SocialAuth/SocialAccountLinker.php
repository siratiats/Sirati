<?php

namespace App\Services\SocialAuth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Finds the user behind a verified provider identity: by the linked
 * subject first, then by the verified email, else a new account.
 */
class SocialAccountLinker
{
    public function userFor(VerifiedIdentity $identity): User
    {
        try {
            return $this->resolve($identity);
        } catch (UniqueConstraintViolationException) {
            // A parallel request linked or created the same identity first.
            return $this->resolve($identity);
        }
    }

    /** The linked user for this identity, or null when it was never linked. */
    public function linkedUser(VerifiedIdentity $identity): ?User
    {
        return SocialAccount::query()
            ->where('provider', $identity->provider)
            ->where('provider_subject', $identity->subject)
            ->first()
            ?->user;
    }

    private function resolve(VerifiedIdentity $identity): User
    {
        return DB::transaction(function () use ($identity): User {
            $linked = $this->linkedUser($identity);
            if ($linked !== null) {
                return $linked;
            }

            if (! $identity->emailVerified || $identity->email === null) {
                throw SocialAuthException::emailUnverified($identity->provider);
            }

            $user = User::query()->whereRaw('LOWER(email) = ?', [$identity->email])->lockForUpdate()->first();

            if ($user === null) {
                $user = User::create([
                    'name' => Str::limit($identity->name ?? Str::before($identity->email, '@'), 255, ''),
                    'email' => $identity->email,
                    'password' => null,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            } elseif (! $user->hasVerifiedEmail()) {
                // Whoever registered this address never proved they own it; the
                // provider just did. Drop their password and sessions so an
                // account squatted on someone else's email can't be taken over.
                $user->tokens()->delete();
                $user->forceFill([
                    'password' => null,
                    'email_verified_at' => now(),
                ])->save();
            }

            $user->socialAccounts()->create([
                'provider' => $identity->provider,
                'provider_subject' => $identity->subject,
                'email' => $identity->email,
            ]);

            return $user;
        });
    }
}
