<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return response()->json([
            'data' => [
                'plans' => $gate->plans(),
                'currency' => $gate->currency(),
                'trial_days' => $gate->trialDays(),
                'enforced' => $gate->isEnforced(),
                'access' => $gate->stateFor($user),
                'free_plan' => [
                    'title_ar' => 'الخطة المجانية',
                    'title_en' => 'Free plan',
                    'includes_ar' => [
                        'تصفح الوظائف والإشعارات — مجاني دائماً',
                        'درجة ATS ونتيجة التحليل',
                        'معاينة السيرة قبل التحميل',
                        ($gate->dailyCvLimit() ?? 0).' سيرة و'.($gate->dailyAnalysisLimit() ?? 0).' تحليل في اليوم',
                    ],
                    'includes_en' => [
                        'Job board and alerts — free forever',
                        'ATS score and analysis result',
                        'CV preview before download',
                        ($gate->dailyCvLimit() ?? 0).' CV and '.($gate->dailyAnalysisLimit() ?? 0).' analyses per day',
                    ],
                    'excludes_ar' => [
                        'تحميل PDF بدون علامة مائية',
                        'تحسين النصوص بالذكاء الاصطناعي',
                        'التوصيات الكاملة ('.(int) ($freeAnalysis['quick_wins'] ?? 1).' فقط مجاناً)',
                    ],
                    'excludes_en' => [
                        'Watermark-free PDF download',
                        'AI text enhancement',
                        'Full recommendations ('.(int) ($freeAnalysis['quick_wins'] ?? 1).' shown free)',
                    ],
                ],
            ],
        ]);
    }
}
