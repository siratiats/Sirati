<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | While this is explicitly false the app behaves exactly as it did before: every user
    | downloads, enhances and reads the full analysis. Turn it on only after the
    | App Store subscriptions are live and RevenueCat is delivering webhooks,
    | otherwise paying users would be locked out of features they bought.
    |
    */

    // Only an explicit true/false decides. Unset, empty or malformed fails
    // closed outside local/testing (SubscriptionGate::isEnforced), so keep
    // invalid values as they are and never default to false here.
    'enabled' => env('MONETIZATION_ENABLED'),

    // Job Match is always premium-only, independently of the master switch.
    // Do not cast here: an empty, malformed or non-positive limit must fail closed.
    'job_match' => [
        'daily_limit' => env('JOB_MATCH_DAILY_LIMIT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | What the free tier keeps
    |--------------------------------------------------------------------------
    |
    | The free experience is deliberately generous on *discovery* (preview, ATS
    | score, job board) and closed on *delivery* (clean PDF, AI rewriting). The
    | user must feel the value before the paywall, not after it.
    |
    */

    'free' => [
        // Watermarked HTML preview of the CV.
        'preview' => true,

        // Clean PDF download.
        'download' => false,

        // AI field / job-description rewriting.
        'enhancements' => false,

        // Jobs tab stays free forever - it is the weekly return hook.
        'jobs' => true,

        // New CV generations allowed per calendar day. Null disables the quota.
        'daily_cv_generations' => (int) env('FREE_DAILY_CV_GENERATIONS', 1),

        // New analyses allowed per calendar day. Null disables the quota.
        'daily_analyses' => (int) env('FREE_DAILY_ANALYSES', 2),

        // How much of a finished analysis a free user can read. The score is
        // always visible; the fixes are what they pay for.
        'analysis' => [
            'strengths' => 2,
            'weaknesses' => 2,
            'keywords_found' => 5,
            'keywords_missing' => 0,
            'quick_wins' => 1,
            'ai_feedback' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plans shown on the paywall
    |--------------------------------------------------------------------------
    |
    | product_id must match the App Store Connect product identifier exactly.
    | The prices here are for display only - Apple is the merchant of record and
    | the storefront price it returns always wins.
    |
    */

    'currency' => env('SUBSCRIPTION_CURRENCY', 'SAR'),

    'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 3),

    'plans' => [
        [
            'key' => 'monthly',
            'product_id' => env('APPLE_PRODUCT_MONTHLY', 'com.sirati.pro.monthly'),
            'period' => 'P1M',
            'period_months' => 1,
            'price' => 19.99,
            'name_ar' => 'اشتراك شهري',
            'name_en' => 'Monthly',
            'badge_ar' => null,
            'badge_en' => null,
            'is_default' => false,
        ],
        [
            'key' => 'semiannual',
            'product_id' => env('APPLE_PRODUCT_SEMIANNUAL', 'com.sirati.pro.semiannual'),
            'period' => 'P6M',
            'period_months' => 6,
            'price' => 99.00,
            'name_ar' => 'اشتراك ٦ أشهر',
            'name_en' => '6 Months',
            'badge_ar' => 'الأكثر توفيراً',
            'badge_en' => 'Best value',
            'is_default' => true,
        ],
        [
            'key' => 'annual',
            'product_id' => env('APPLE_PRODUCT_ANNUAL', 'com.sirati.pro.annual'),
            'period' => 'P1Y',
            'period_months' => 12,
            'price' => 179.00,
            'name_ar' => 'اشتراك سنوي',
            'name_en' => 'Annual',
            'badge_ar' => null,
            'badge_en' => null,
            'is_default' => false,
        ],
    ],
];
