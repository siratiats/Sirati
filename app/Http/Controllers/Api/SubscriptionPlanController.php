<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\I18n\ServerText;
use App\Services\Subscriptions\SubscriptionGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The paywall catalogue. Public so the app can render pricing before sign-up.
 *
 * Prices here are for display only. Apple is the merchant of record, so the
 * storefront price StoreKit returns for the product_id always wins.
 */
class SubscriptionPlanController extends Controller
{
    public function index(Request $request, SubscriptionGate $gate): JsonResponse
    {
        // Public route, so resolve the bearer token explicitly: a signed-in
        // caller should see their own entitlement, not the anonymous default.
        $user = $request->user('sanctum');
        $freeAnalysis = (array) config('monetization.free.analysis', []);
        $access = $gate->stateFor($user);

        return response()->json([
            'data' => [
                'plans' => $gate->plans(),
                'currency' => $gate->currency(),
                'trial_days' => $gate->trialDays(),
                // From the snapshot, so a broken switch never blocks the catalogue.
                'enforced' => $access['enforced'],
                'access' => $access,
                'free_plan' => $this->freePlan($gate, (int) ($freeAnalysis['quick_wins'] ?? 1)),
            ],
        ]);
    }

    /**
     * What the free plan includes, from the label catalog (#12); the _ar/_en
     * pairs serve older app builds.
     *
     * @return array<string, mixed>
     */
    private function freePlan(SubscriptionGate $gate, int $freeRecommendations): array
    {
        $limits = ['cvs' => $gate->dailyCvLimit() ?? 0, 'analyses' => $gate->dailyAnalysisLimit() ?? 0];
        $includes = fn (?string $language) => [
            ServerText::t('serverPlanFreeIncludesJobs', [], $language),
            ServerText::t('serverPlanFreeIncludesAts', [], $language),
            ServerText::t('serverPlanFreeIncludesPreview', [], $language),
            ServerText::t('serverPlanFreeIncludesDailyLimits', $limits, $language),
        ];
        $excludes = fn (?string $language) => [
            ServerText::t('serverPaywallBenefitPdf', [], $language),
            ServerText::t('serverPaywallBenefitEnhance', [], $language),
            ServerText::t('serverPlanFreeExcludesRecommendations', ['count' => $freeRecommendations], $language),
        ];

        return [
            'title' => ServerText::t('serverPlanFreeTitle'),
            'title_ar' => ServerText::t('serverPlanFreeTitle', [], 'ar'),
            'title_en' => ServerText::t('serverPlanFreeTitle', [], 'en'),
            'includes' => $includes(null),
            'includes_ar' => $includes('ar'),
            'includes_en' => $includes('en'),
            'excludes' => $excludes(null),
            'excludes_ar' => $excludes('ar'),
            'excludes_en' => $excludes('en'),
        ];
    }
}
