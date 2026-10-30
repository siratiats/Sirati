<?php

namespace App\Services\Subscriptions;

use App\Exceptions\SubscriptionRequiredException;
use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\I18n\ServerText;
use App\Support\FailClosed;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Single source of truth for "is this allowed on the free plan?".
 *
 * Every paywall decision in the app routes through here so the rules can be
 * read, tested and changed in one place instead of drifting across controllers.
 */
class SubscriptionGate
{
    /**
     * Master switch for existing features. Job Match always requires premium.
     *
     * Only an explicit MONETIZATION_ENABLED decides. Unset, empty or malformed
     * is off in local/testing and a 503 everywhere else, never free access.
     */
    public function isEnforced(): bool
    {
        $enabled = $this->enforcementSetting();
        if ($enabled !== null) {
            return $enabled;
        }

        if (FailClosed::allowsMissingConfiguration()) {
            return false;
        }

        FailClosed::unavailable('Monetization', 'monetization.enabled');
    }

    /** The explicit boolean switch, or null when unset, empty or malformed. */
    private function enforcementSetting(): ?bool
    {
        $enabled = config('monetization.enabled');
        if (is_bool($enabled)) {
            return $enabled;
        }

        if (! is_int($enabled) && (! is_string($enabled) || trim($enabled) === '')) {
            return null;
        }

        return filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Enforcement for snapshots that must not break account access: a broken
     * switch reads as enforced, and the gated action itself returns the 503.
     */
    private function enforcedForSnapshot(): bool
    {
        return $this->enforcementSetting() ?? ! FailClosed::allowsMissingConfiguration();
    }

    public function isSubscribed(?User $user): bool
    {
        return $user !== null && $user->isPremium();
    }

    /**
     * A paid account, or any account while enforcement is off.
     */
    public function hasFullAccess(?User $user): bool
    {
        return ! $this->isEnforced() || $this->isSubscribed($user);
    }

    public function allowsDownload(?User $user): bool
    {
        return $this->hasFullAccess($user) || $this->freeAllowsDownload();
    }

    public function allowsEnhancements(?User $user): bool
    {
        return $this->hasFullAccess($user) || $this->freeAllowsEnhancements();
    }

    private function freeAllowsDownload(): bool
    {
        return (bool) config('monetization.free.download', false);
    }

    private function freeAllowsEnhancements(): bool
    {
        return (bool) config('monetization.free.enhancements', false);
    }

    public function allowsFullAnalysis(?User $user): bool
    {
        return $this->hasFullAccess($user);
    }

    /**
     * Free previews carry a watermark so the value is visible but the artefact
     * is not employer-ready.
     */
    public function shouldWatermark(?User $user): bool
    {
        return ! $this->allowsDownload($user);
    }

    // ---------------------------------------------------------------- quotas

    public function dailyCvLimit(): ?int
    {
        $limit = config('monetization.free.daily_cv_generations');

        return $limit === null ? null : max(0, (int) $limit);
    }

    public function dailyAnalysisLimit(): ?int
    {
        $limit = config('monetization.free.daily_analyses');

        return $limit === null ? null : max(0, (int) $limit);
    }

    public function jobMatchDailyLimit(): int
    {
        $configured = config('monetization.job_match.daily_limit');
        $limit = is_int($configured) || is_string($configured)
            ? filter_var($configured, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        if ($limit !== false) {
            return $limit;
        }

        if (! FailClosed::allowsMissingConfiguration()) {
            $this->jobMatchConfigurationUnavailable('monetization.job_match.daily_limit');
        }

        return 20;
    }

    public function cvGenerationsUsedToday(User $user): int
    {
        return GeneratedCv::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    public function analysesUsedToday(User $user): int
    {
        return CvAnalysis::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    public function jobMatchesUsedToday(User $user): int
    {
        $start = now()->startOfDay();

        return DB::table('job_matches')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $start->copy()->addDay())
            ->count();
    }

    // ------------------------------------------------------------ assertions

    /** Access to existing matches and replays never depends on quota usage. */
    public function assertCanAccessJobMatch(User $user): void
    {
        $this->assertJobMatchConfigured();
        // Validate required configuration even when no new attempt is requested.
        $this->jobMatchDailyLimit();

        // Never use hasFullAccess(): the general rollout switch is not a bypass.
        if (! $this->isSubscribed($user)) {
            throw new SubscriptionRequiredException(
                feature: 'job_match',
                messageKey: 'serverPaywallJobMatch',
            );
        }
    }

    /** Only a new attempt checks quota, under the controller's per-user lock. */
    public function assertCanJobMatch(User $user, bool $checkQuota = true): void
    {
        $this->assertCanAccessJobMatch($user);

        if ($checkQuota && $this->jobMatchesUsedToday($user) >= $this->jobMatchDailyLimit()) {
            throw new HttpException(
                429,
                ServerText::t('serverJobMatchDailyLimit'),
                headers: ['Retry-After' => (string) max(1, (int) now()->diffInSeconds(now()->addDay()->startOfDay()))],
            );
        }
    }

    private function assertJobMatchConfigured(): void
    {
        if (FailClosed::allowsMissingConfiguration()) {
            return;
        }

        if ($this->enforcementSetting() !== true) {
            $this->jobMatchConfigurationUnavailable('monetization.enabled');
        }

        $secret = config('services.revenuecat.webhook_secret');
        if (! is_string($secret) || trim($secret) === '') {
            $this->jobMatchConfigurationUnavailable('services.revenuecat.webhook_secret');
        }
    }

    private function jobMatchConfigurationUnavailable(string $key): never
    {
        FailClosed::unavailable('Job Match', $key);
    }

    public function assertCanDownload(?User $user): void
    {
        if ($this->allowsDownload($user)) {
            return;
        }

        throw new SubscriptionRequiredException(
            feature: 'cv_download',
            messageKey: 'serverPaywallDownload',
            meta: [
                'benefits' => $this->benefits(),
                'benefits_ar' => $this->benefits('ar'),
                'benefits_en' => $this->benefits('en'),
            ],
        );
    }

    public function assertCanEnhance(?User $user): void
    {
        if ($this->allowsEnhancements($user)) {
            return;
        }

        throw new SubscriptionRequiredException(
            feature: 'ai_enhancement',
            messageKey: 'serverPaywallEnhance',
        );
    }

    public function assertCanGenerateCv(?User $user): void
    {
        if ($this->hasFullAccess($user)) {
            return;
        }

        $limit = $this->dailyCvLimit();
        if ($user === null || $limit === null) {
            return;
        }

        $used = $this->cvGenerationsUsedToday($user);
        if ($used < $limit) {
            return;
        }

        throw new SubscriptionRequiredException(
            feature: 'daily_cv_quota',
            messageKey: 'serverPaywallDailyCvLimit',
            params: ['limit' => $limit],
            meta: [
                'limit' => $limit,
                'used' => $used,
                'resets_at' => now()->addDay()->startOfDay()->toISOString(),
            ],
        );
    }

    public function assertCanAnalyze(?User $user): void
    {
        if ($this->hasFullAccess($user)) {
            return;
        }

        $limit = $this->dailyAnalysisLimit();
        if ($user === null || $limit === null) {
            return;
        }

        $used = $this->analysesUsedToday($user);
        if ($used < $limit) {
            return;
        }

        throw new SubscriptionRequiredException(
            feature: 'daily_analysis_quota',
            messageKey: 'serverPaywallDailyAnalysisLimit',
            params: ['limit' => $limit],
            meta: [
                'limit' => $limit,
                'used' => $used,
                'resets_at' => now()->addDay()->startOfDay()->toISOString(),
            ],
        );
    }

    // --------------------------------------------------------------- catalog

    public function currency(): string
    {
        return (string) config('monetization.currency', 'SAR');
    }

    public function trialDays(): int
    {
        return (int) config('monetization.trial_days', 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function plans(): array
    {
        $currency = $this->currency();

        return array_values(array_map(static function (array $plan) use ($currency): array {
            $months = max(1, (int) ($plan['period_months'] ?? 1));
            $price = (float) ($plan['price'] ?? 0);

            return [
                ...$plan,
                ...self::planLabels($plan),
                'currency' => $currency,
                'price_per_month' => round($price / $months, 2),
            ];
        }, (array) config('monetization.plans', [])));
    }

    /**
     * Plan names and badges from the label catalog (#12): `name`/`badge` follow
     * the app language, the _ar/_en pairs serve older app builds.
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, ?string>
     */
    private static function planLabels(array $plan): array
    {
        $labels = [];
        foreach (['name', 'badge'] as $field) {
            $key = $plan[$field.'_key'] ?? null;
            $labels[$field] = $key === null ? null : ServerText::t($key);
            $labels[$field.'_ar'] = $key === null ? null : ServerText::t($key, [], 'ar');
            $labels[$field.'_en'] = $key === null ? null : ServerText::t($key, [], 'en');
        }

        return $labels;
    }

    /** @return list<string> */
    private function benefits(?string $language = null): array
    {
        return array_map(fn (string $key) => ServerText::t($key, [], $language), [
            'serverPaywallBenefitPdf',
            'serverPaywallBenefitEnhance',
            'serverPaywallBenefitAnalysis',
            'serverPaywallBenefitUnlimited',
        ]);
    }

    /**
     * Snapshot of the caller's entitlement, embedded in /auth/me so the app can
     * draw the right buttons before it ever hits a 402.
     *
     * @return array<string, mixed>
     */
    public function stateFor(?User $user): array
    {
        $jobMatchAvailable = false;
        $jobMatchLimit = 0;
        try {
            $jobMatchLimit = $this->jobMatchDailyLimit();
            $this->assertJobMatchConfigured();
            $jobMatchAvailable = true;
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 503) {
                throw $exception;
            }
            // Validation already logged critically. Keep auth/catalogue usable
            // with Job Match disabled; the action itself still throws a 503.
        }
        $jobMatchesUsed = $user !== null ? $this->jobMatchesUsedToday($user) : 0;
        $subscribed = $this->isSubscribed($user);
        $enforced = $this->enforcedForSnapshot();
        $full = ! $enforced || $subscribed;

        $state = [
            'enforced' => $enforced,
            'is_subscribed' => $subscribed,
            'plan_id' => $user?->subscription_plan_id,
            'expires_at' => $user?->premium_until?->toISOString(),
            'can_download' => $full || $this->freeAllowsDownload(),
            'can_enhance' => $full || $this->freeAllowsEnhancements(),
            'can_view_full_analysis' => $full,
            'can_job_match' => $jobMatchAvailable && $subscribed && $jobMatchesUsed < $jobMatchLimit,
            'jobs_free' => (bool) config('monetization.free.jobs', true),
            'trial_days' => $this->trialDays(),
            'currency' => $this->currency(),
        ];

        $state['quota'] = [
            'cv' => [
                'limit' => $full ? null : $this->dailyCvLimit(),
                'used' => $user !== null && ! $full ? $this->cvGenerationsUsedToday($user) : 0,
            ],
            'analysis' => [
                'limit' => $full ? null : $this->dailyAnalysisLimit(),
                'used' => $user !== null && ! $full ? $this->analysesUsedToday($user) : 0,
            ],
            'job_match' => [
                'limit' => $jobMatchLimit,
                'used' => $jobMatchesUsed,
                'resets_at' => now()->addDay()->startOfDay()->toISOString(),
            ],
        ];

        return $state;
    }
}
