<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\Subscriptions\SubscriptionGate;

/** The session/user JSON every mobile sign-in endpoint answers with. */
trait BuildsMobileAuthPayload
{
    protected function tokenPayload(User $user, ?string $deviceName): array
    {
        $token = $user->createToken($deviceName ?: 'sirati-mobile')->plainTextToken;

        return [
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->userPayload($user),
            ],
        ];
    }

    protected function userPayload(User $user): array
    {
        $jobTitle = $user->relationLoaded('jobTitle') ? $user->jobTitle : $user->jobTitle()->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'location' => $user->location,
            'avatar_url' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
            'job_title_id' => $user->job_title_id,
            'job_title_other' => $user->job_title_other,
            'job_title' => $jobTitle ? [
                'id' => $jobTitle->id,
                'slug' => $jobTitle->slug,
                'name_ar' => $jobTitle->name_ar,
                'name_en' => $jobTitle->name_en,
                'category' => $jobTitle->category,
            ] : null,
            'email_verified' => $user->hasVerifiedEmail(),
            // False for Google/Apple accounts that never set a password.
            'has_password' => $user->hasPassword(),
            // Lets the app offer re-authentication with the right provider (e.g. to delete the account).
            'social_providers' => $user->socialAccounts()->distinct()->orderBy('provider')->pluck('provider')->values()->all(),
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            // Lets the app draw locked/unlocked buttons without first hitting a 402.
            'subscription' => app(SubscriptionGate::class)->stateFor($user),
            'created_at' => $user->created_at?->toISOString(),
        ];
    }
}
