<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'location',
        'job_title_id',
        'job_title_other',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_premium' => 'boolean',
            'premium_until' => 'datetime',
        ];
    }

    public function isPremium(): bool
    {
        if ($this->premium_until !== null) {
            return $this->premium_until->isFuture();
        }

        return (bool) $this->is_premium;
    }

    public function grantSubscription(
        \DateTimeInterface|string $expiresAt,
        string $planId = 'pro',
        string $provider = 'revenuecat',
        ?string $externalId = null,
    ): void {
        $expires = is_string($expiresAt) ? \Illuminate\Support\Carbon::parse($expiresAt) : $expiresAt;

        $this->forceFill([
            'is_premium' => true,
            'premium_until' => $expires,
            'subscription_plan_id' => $planId,
            'subscription_provider' => $provider,
            'subscription_external_id' => $externalId,
        ])->save();
    }

    public function revokeSubscription(): void
    {
        $this->forceFill([
            'is_premium' => false,
            'premium_until' => \Illuminate\Support\Carbon::now()->subMinute(),
        ])->save();
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function cvAnalyses(): HasMany
    {
        return $this->hasMany(CvAnalysis::class);
    }

    public function generatedCvs(): HasMany
    {
        return $this->hasMany(GeneratedCv::class);
    }

    public function mobileNotifications(): HasMany
    {
        return $this->hasMany(MobileNotification::class);
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(UserFcmToken::class);
    }

    public function notificationPreference(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function notificationDecisions(): HasMany
    {
        return $this->hasMany(NotificationDecision::class);
    }
}
