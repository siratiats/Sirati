<?php

namespace App\Exceptions;

use App\Services\I18n\ServerText;
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
     * @param  string  $messageKey  catalog label (#12): `message` follows the app language,
     *                              message_ar / message_en are kept for older app builds
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private readonly string $feature,
        private readonly string $messageKey,
        private readonly array $params = [],
        private readonly array $meta = [],
    ) {
        parent::__construct(self::STATUS, ServerText::t($messageKey, $params));
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        /** @var SubscriptionGate $gate */
        $gate = app(SubscriptionGate::class);

        return response()->json([
            'message' => ServerText::t($this->messageKey, $this->params),
            'error' => 'subscription_required',
            'code' => 'subscription_required',
            'feature' => $this->feature,
            'paywall' => [
                'title' => ServerText::t('serverPaywallTitle'),
                'title_ar' => ServerText::t('serverPaywallTitle', [], 'ar'),
                'title_en' => ServerText::t('serverPaywallTitle', [], 'en'),
                'message' => ServerText::t($this->messageKey, $this->params),
                'message_ar' => ServerText::t($this->messageKey, $this->params, 'ar'),
                'message_en' => ServerText::t($this->messageKey, $this->params, 'en'),
                'cta' => ServerText::t('serverPaywallCta'),
                'cta_ar' => ServerText::t('serverPaywallCta', [], 'ar'),
                'cta_en' => ServerText::t('serverPaywallCta', [], 'en'),
                ...$this->meta,
                'trial_days' => $gate->trialDays(),
                'currency' => $gate->currency(),
                'plans' => $gate->plans(),
            ],
        ], self::STATUS);
    }
}
