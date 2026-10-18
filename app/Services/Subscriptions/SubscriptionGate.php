<?php

namespace App\Services\Subscriptions;

use App\Exceptions\SubscriptionRequiredException;
use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     */
    public function isEnforced(): bool
    {
        return (bool) config('monetization.enabled', false);
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
        return $this->hasFullAccess($user)
            || (bool) config('monetization.free.download', false);
    }

    public function allowsEnhancements(?User $user): bool
    {
        return $this->hasFullAccess($user)
            || (bool) config('monetization.free.enhancements', false);
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

        if (! app()->environment('local', 'testing')) {
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

    public function assertCanJobMatch(User $user): void
    {
        $this->assertJobMatchConfigured();
        $limit = $this->jobMatchDailyLimit();

        // Never use hasFullAccess(): the general rollout switch is not a bypass.
        if (! $this->isSubscribed($user)) {
            throw new SubscriptionRequiredException(
                feature: 'job_match',
                messageAr: 'مطابقة السيرة الذاتية مع الوظيفة متاحة للمشتركين. اشترك لاكتشاف نقاط القوة والفجوات وتحسين سيرتك.',
                messageEn: 'Job Match is for subscribers. Subscribe to discover strengths, gaps and suggested CV edits.',
            );
        }

        if ($this->jobMatchesUsedToday($user) >= $limit) {
            throw new HttpException(
                429,
                'Daily Job Match limit reached. Try again after the quota resets.',
                headers: ['Retry-After' => (string) max(1, (int) now()->diffInSeconds(now()->addDay()->startOfDay()))],
            );
        }
    }

    private function assertJobMatchConfigured(): void
    {
        if (app()->environment('local', 'testing')) {
            return;
        }

        $enabled = config('monetization.enabled');
        if ((! is_bool($enabled) && ! is_string($enabled) && ! is_int($enabled))
            || filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== true) {
            $this->jobMatchConfigurationUnavailable('monetization.enabled');
        }

        $secret = config('services.revenuecat.webhook_secret');
        if (! is_string($secret) || trim($secret) === '') {
            $this->jobMatchConfigurationUnavailable('services.revenuecat.webhook_secret');
        }
    }

    private function jobMatchConfigurationUnavailable(string $key): never
    {
        // Log the key only; configuration values may contain credentials.
        Log::critical('Job Match unavailable: required configuration is missing or invalid.', [
            'configuration' => $key,
            'environment' => app()->environment(),
        ]);

        throw new HttpException(503, 'Job Match is temporarily unavailable.');
    }

    public function assertCanDownload(?User $user): void
    {
        if ($this->allowsDownload($user)) {
            return;
        }

        throw new SubscriptionRequiredException(
            feature: 'cv_download',
            messageAr: 'المعاينة مجانية. لتحميل السيرة كملف PDF نظيف بدون علامة مائية، فعّل الاشتراك.',
            messageEn: 'Preview is free. Subscribe to download a clean, watermark-free PDF.',
            meta: [
                'benefits_ar' => [
                    'تحميل PDF بدون علامة مائية',
                    'تحسين النصوص بالذكاء الاصطناعي',
                    'التحليل الكامل مع كل التوصيات',
                    'عدد غير محدود من السير الذاتية',
                ],
                'benefits_en' => [
                    'Watermark-free PDF download',
                    'AI text enhancement',
                    'Full analysis with every recommendation',
                    'Unlimited CVs',
                ],
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
            messageAr: 'تحسين النصوص بالذكاء الاصطناعي متاح للمشتركين. اشترك لإعادة صياغة خبراتك بلغة يفهمها نظام التوظيف.',
            messageEn: 'AI enhancement is for subscribers. Subscribe to rewrite your experience in ATS-friendly language.',
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
            messageAr: 'وصلت إلى حد الخطة المجانية ('.$limit.' سيرة في اليوم). اشترك لإنشاء عدد غير محدود من السير الذاتية.',
            messageEn: 'You reached the free plan limit ('.$limit.' CV per day). Subscribe for unlimited CVs.',
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
            messageAr: 'وصلت إلى حد الخطة المجانية ('.$limit.' تحليل في اليوم). اشترك لتحليل عدد غير محدود من السير.',
            messageEn: 'You reached the free plan limit ('.$limit.' analyses per day). Subscribe for unlimited analyses.',
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
                'currency' => $currency,
                'price_per_month' => round($price / $months, 2),
            ];
        }, (array) config('monetization.plans', [])));
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
        $full = $this->hasFullAccess($user);

        $state = [
            'enforced' => $this->isEnforced(),
            'is_subscribed' => $subscribed,
            'plan_id' => $user?->subscription_plan_id,
            'expires_at' => $user?->premium_until?->toISOString(),
            'can_download' => $this->allowsDownload($user),
            'can_enhance' => $this->allowsEnhancements($user),
            'can_view_full_analysis' => $this->allowsFullAnalysis($user),
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
