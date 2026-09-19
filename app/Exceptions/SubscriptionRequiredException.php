<?php

namespace App\Exceptions;

use App\Services\Subscriptions\SubscriptionGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a free account reaches a paid boundary.
 *
 * 402 is used deliberately: 401/403 already mean "log in" and "not yours" in
 * this API, and the app must be able to tell a paywall apart from an auth
 * failure without parsing message strings.
 */
final class SubscriptionRequiredException extends HttpException
{
    public const STATUS = 402;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private readonly string $feature,
        private readonly string $messageAr,
        private readonly string $messageEn,
        private readonly array $meta = [],
    ) {
        parent::__construct(self::STATUS, $messageAr);
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        /** @var SubscriptionGate $gate */
        $gate = app(SubscriptionGate::class);

        return response()->json([
            'message' => $this->messageAr,
            'error' => 'subscription_required',
            'code' => 'subscription_required',
            'feature' => $this->feature,
            'paywall' => [
                'title_ar' => 'اشترك لتفتح هذه الميزة',
                'title_en' => 'Subscribe to unlock',
                'message_ar' => $this->messageAr,
                'message_en' => $this->messageEn,
                'cta_ar' => 'عرض الخطط',
                'cta_en' => 'See plans',
                ...$this->meta,
                'trial_days' => $gate->trialDays(),
                'currency' => $gate->currency(),
                'plans' => $gate->plans(),
            ],
        ], self::STATUS);
    }
}
