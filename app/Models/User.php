<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
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
            'admin_premium_until' => 'datetime',
        ];
    }

    /** Access sold through the App Store (RevenueCat). */
    public const STORE_PROVIDER = 'revenuecat';

    /** Access an admin granted by hand. */
    public const ADMIN_PROVIDER = 'admin';

    public function isPremium(): bool
    {
        if ($this->premium_until !== null) {
            return $this->premium_until->isFuture();
        }

        return (bool) $this->is_premium;
    }

    /**
     * Grants access from one source without ever shortening access from the
     * other: the later of the store period and a running admin grant is the
     * access in effect (`premium_until` / `subscription_*`), and an admin
     * grant is also remembered on its own (`admin_premium_until`) so that a
     * store expiry, refund or transfer cannot erase it.
     *
     * Access that is not the store's counts as an admin grant whatever its
     * provider says: 'admin', the legacy 'admin_override', or a name someone
     * set by hand (production had 'manual'). Access with no end date already
     * covers any store period.
     */
    public function grantSubscription(
        \DateTimeInterface|string $expiresAt,
        string $planId = 'pro',
        string $provider = self::STORE_PROVIDER,
        ?string $externalId = null,
    ): void {
        $expires = is_string($expiresAt) ? Carbon::parse($expiresAt) : Carbon::instance($expiresAt);

        if ($provider === self::ADMIN_PROVIDER) {
            $this->forceFill(['admin_premium_until' => $expires, 'admin_plan_id' => $planId]);
            if ($this->hasStoreAccessBeyond($expires)) {
                $this->save();

                return;
            }
        } elseif ($provider === self::STORE_PROVIDER) {
            $this->rememberNonStoreAccess();
            if ($this->hasNonStoreAccessUntil($expires)) {
                $this->save();

                return;
            }
            if ($this->activeAdminUntil()?->greaterThanOrEqualTo($expires)) {
                $this->applyAdminAccess();
                $this->save();

                return;
            }
        }

        $this->forceFill([
            'is_premium' => true,
            'premium_until' => $expires,
            'subscription_plan_id' => $planId,
            'subscription_provider' => $provider,
            'subscription_external_id' => $externalId,
        ])->save();
    }

    /**
     * Ends the store side of access (expiry, refund, transfer). Access from
     * any other source is not the store's to take: it is left as it is, and an
     * admin grant that is still running takes over from the store period.
     */
    public function revokeStoreSubscription(): void
    {
        if ($this->subscription_provider !== self::STORE_PROVIDER) {
            return;
        }
        if ($this->activeAdminUntil() !== null) {
            $this->applyAdminAccess();
        } else {
            $this->forceFill([
                'is_premium' => false,
                'premium_until' => Carbon::now()->subMinute(),
            ]);
        }
        $this->save();
    }

    /** Admin revoke: ends all access, including any remembered admin grant. */
    public function revokeSubscription(): void
    {
        $this->forceFill([
            'is_premium' => false,
            'premium_until' => Carbon::now()->subMinute(),
            'admin_premium_until' => null,
            'admin_plan_id' => null,
        ])->save();
    }

    /**
     * Whether the access in effect comes from a source other than the store
     * and lasts at least until $until (access with no end date always does).
     */
    public function hasNonStoreAccessUntil(Carbon $until): bool
    {
        return $this->subscription_provider !== self::STORE_PROVIDER
            && $this->isPremium()
            && ($this->premium_until === null || $this->premium_until->greaterThanOrEqualTo($until));
    }

    /**
     * Remembers running, dated access that is not the store's as the admin
     * grant, so that a store period taking over hands it back when it ends.
     */
    private function rememberNonStoreAccess(): void
    {
        if ($this->subscription_provider !== self::STORE_PROVIDER && $this->premium_until?->isFuture() === true) {
            $this->forceFill([
                'admin_premium_until' => $this->premium_until,
                'admin_plan_id' => $this->subscription_plan_id,
            ]);
        }
    }

    private function activeAdminUntil(): ?Carbon
    {
        $until = $this->admin_premium_until;

        return $until !== null && $until->isFuture() ? $until : null;
    }

    private function hasStoreAccessBeyond(Carbon $until): bool
    {
        return $this->subscription_provider === self::STORE_PROVIDER
            && $this->isPremium()
            && $this->premium_until?->greaterThan($until) === true;
    }

    private function applyAdminAccess(): void
    {
        if ($this->subscription_provider !== self::STORE_PROVIDER
            && $this->premium_until?->equalTo($this->admin_premium_until)) {
            return;
        }
        $this->forceFill([
            'is_premium' => true,
            'premium_until' => $this->admin_premium_until,
            'subscription_plan_id' => $this->admin_plan_id ?? 'admin_grant',
            'subscription_provider' => self::ADMIN_PROVIDER,
            'subscription_external_id' => null,
        ]);
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

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** False for accounts created through Google/Apple that never set one. */
    public function hasPassword(): bool
    {
        return $this->password !== null && $this->password !== '';
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function notificationDecisions(): HasMany
    {
        return $this->hasMany(NotificationDecision::class);
    }
}
