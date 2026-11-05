<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionAuditLog;
use App\Models\SubscriptionWebhookEvent;
use App\Models\User;
use App\Services\Subscriptions\RevenueCatSubscriberSync;
use App\Services\Subscriptions\RevenueCatUnavailableException;
use App\Support\FailClosed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SubscriptionWebhookController extends Controller
{
    /** Event types that grant or keep access until the event's expiry. */
    private const EXPIRY_REQUIRED = [
        'INITIAL_PURCHASE',
        'RENEWAL',
        'PRODUCT_CHANGE',
        'UNCANCELLATION',
        'NON_RENEWING_PURCHASE',
        'CANCELLATION',
    ];

    public function __construct(private readonly RevenueCatSubscriberSync $subscriberSync) {}

    public function handleRevenueCat(Request $request): JsonResponse
    {
        // 1. Verify shared secret
        $secret = config('services.revenuecat.webhook_secret');
        if (! is_string($secret) || trim($secret) === '') {
            if (! FailClosed::allowsMissingConfiguration()) {
                FailClosed::unavailable('RevenueCat webhook', 'services.revenuecat.webhook_secret', 'Webhook secret unconfigured');
            }
        } else {
            $authHeader = (string) $request->header('Authorization', '');
            $customHeader = (string) $request->header('X-RevenueCat-Secret', '');

            $expectedBearer = 'Bearer '.$secret;
            $matchesBearer = hash_equals($expectedBearer, $authHeader) || hash_equals((string) $secret, $authHeader);
            $matchesCustom = hash_equals((string) $secret, $customHeader);

            if (! $matchesBearer && ! $matchesCustom) {
                return response()->json(['message' => 'Unauthorized webhook signature'], 401);
            }
        }

        $payload = $request->all();
        $event = $payload['event'] ?? $payload;

        $eventId = (string) ($event['id'] ?? '');
        if ($eventId === '') {
            return response()->json(['message' => 'Missing event id'], 422);
        }

        // 2. Idempotency check: duplicate event protection
        $existing = SubscriptionWebhookEvent::where('event_id', $eventId)->first();
        if ($existing) {
            return response()->json([
                'message' => 'Event already processed',
                'event_id' => $eventId,
                'status' => 'duplicate',
            ], 200);
        }

        $type = (string) ($event['type'] ?? '');
        $appUserId = (string) ($event['app_user_id'] ?? $event['original_app_user_id'] ?? '');

        // Policy: an App Store subscription stays with the Siratie account that
        // bought it (RevenueCat restore behaviour "Keep with original App User
        // ID"); the app tells a different account it belongs to another one.
        // A TRANSFER therefore means the RevenueCat project is misconfigured:
        // alert loudly, then make every involved account match RevenueCat so
        // access follows the store. If RevenueCat cannot be read the event is
        // not recorded and the 503 makes RevenueCat deliver it again.
        if ($type === 'TRANSFER') {
            $from = array_values(array_filter((array) ($event['transferred_from'] ?? []), 'is_scalar'));
            $to = array_values(array_filter((array) ($event['transferred_to'] ?? []), 'is_scalar'));
            Log::critical('RevenueCat webhook: TRANSFER received; set restore behaviour to "Keep with original App User ID". Reconciling the accounts with RevenueCat.', [
                'event_id' => $eventId,
                'transferred_from' => $from,
                'transferred_to' => $to,
            ]);

            $ids = array_map('intval', RevenueCatSubscriberSync::siratieUserIds([...$from, ...$to]));
            try {
                // A fixed order makes a retry after a part-way failure repeat
                // the same steps; accounts already reconciled are left as they are.
                foreach (User::query()->whereIn('id', $ids)->orderBy('id')->get() as $involved) {
                    $this->subscriberSync->sync($involved, "transfer {$eventId}");
                }
            } catch (RevenueCatUnavailableException $e) {
                Log::error('RevenueCat webhook: TRANSFER not reconciled; RevenueCat will retry.', [
                    'event_id' => $eventId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'RevenueCat unavailable; retry later',
                    'event_id' => $eventId,
                ], 503);
            }

            SubscriptionWebhookEvent::create([
                'event_id' => $eventId,
                'event_type' => $type,
                'app_user_id' => $appUserId,
                'payload' => $payload,
                'processed_at' => Carbon::now(),
            ]);

            return response()->json([
                'message' => 'Transfer reconciled with RevenueCat',
                'event_id' => $eventId,
                'status' => 'transfer_reconciled',
            ], 200);
        }

        // 3. Resolve user: the app user id first, then its aliases. Only
        // canonical Siratie ids are looked up, as integers, so an alias such as
        // "4abc" or "4.0" can never be coerced into account 4 by the database.
        $aliases = is_array($event['aliases'] ?? null) ? array_values($event['aliases']) : [];
        $user = null;
        foreach (RevenueCatSubscriberSync::siratieUserIds([$appUserId, ...$aliases]) as $candidateId) {
            $user = User::query()->find((int) $candidateId);
            if ($user) {
                break;
            }
        }

        if (! $user) {
            Log::warning("RevenueCat webhook: User not found for app_user_id [{$appUserId}]", [
                'event_id' => $eventId,
                'type' => $type,
            ]);

            SubscriptionWebhookEvent::create([
                'event_id' => $eventId,
                'event_type' => $type,
                'app_user_id' => $appUserId,
                'payload' => $payload,
                'processed_at' => Carbon::now(),
            ]);

            return response()->json([
                'message' => 'Event recorded but user not found',
                'event_id' => $eventId,
            ], 200);
        }

        $previousExpiry = $user->premium_until;
        $productId = (string) ($event['product_id'] ?? 'pro');
        $externalId = (string) ($event['original_transaction_id'] ?? $eventId);

        // 4. Resolve expiration timestamp from event. Never invent one: an
        // event that would grant or keep access without an expiry grants nothing.
        $expirationMs = $event['expiration_at_ms'] ?? $event['expires_date_ms'] ?? null;
        $expirationMs = is_int($expirationMs) || is_string($expirationMs)
            ? filter_var($expirationMs, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $expiresAt = $expirationMs !== false ? Carbon::createFromTimestampMs($expirationMs) : null;

        if ($expiresAt === null && in_array($type, self::EXPIRY_REQUIRED, true)) {
            Log::error('RevenueCat webhook: event has no valid expiry; entitlement unchanged.', [
                'event_id' => $eventId,
                'event_type' => $type,
                'user_id' => $user->id,
            ]);

            SubscriptionWebhookEvent::create([
                'event_id' => $eventId,
                'event_type' => $type,
                'app_user_id' => $appUserId,
                'payload' => $payload,
                'processed_at' => Carbon::now(),
            ]);

            return response()->json([
                'message' => 'Event recorded without an expiry; nothing granted',
                'event_id' => $eventId,
                'status' => 'missing_expiry',
            ], 200);
        }

        // 5. Handle event types
        switch ($type) {
            case 'INITIAL_PURCHASE':
            case 'RENEWAL':
            case 'PRODUCT_CHANGE':
            case 'UNCANCELLATION':
            case 'NON_RENEWING_PURCHASE':
                $user->grantSubscription(
                    expiresAt: $expiresAt,
                    planId: $productId,
                    provider: 'revenuecat',
                    externalId: $externalId,
                );

                SubscriptionAuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'webhook_granted',
                    'reason' => "RevenueCat event [{$type}] for product [{$productId}]",
                    'previous_premium_until' => $previousExpiry,
                    'new_premium_until' => $user->premium_until,
                    'metadata' => [
                        'event_id' => $eventId,
                        'event_type' => $type,
                        'product_id' => $productId,
                    ],
                ]);
                break;

            case 'EXPIRATION':
                $user->revokeStoreSubscription();

                SubscriptionAuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'webhook_expired',
                    'reason' => "RevenueCat subscription expired for product [{$productId}]",
                    'previous_premium_until' => $previousExpiry,
                    'new_premium_until' => $user->premium_until,
                    'metadata' => [
                        'event_id' => $eventId,
                        'event_type' => $type,
                    ],
                ]);
                break;

            case 'CANCELLATION':
                // Cancellation turns off auto-renew; access remains until expiration_at_ms
                if ($expiresAt->isPast()) {
                    $user->revokeStoreSubscription();
                } else {
                    // Retain current entitlement until the end of the paid period
                    $user->grantSubscription(
                        expiresAt: $expiresAt,
                        planId: $productId,
                        provider: 'revenuecat',
                        externalId: $externalId,
                    );
                }

                SubscriptionAuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'webhook_cancelled',
                    'reason' => "RevenueCat auto-renew cancelled for product [{$productId}]",
                    'previous_premium_until' => $previousExpiry,
                    'new_premium_until' => $user->premium_until,
                    'metadata' => [
                        'event_id' => $eventId,
                        'event_type' => $type,
                        'expires_at' => $expiresAt->toIso8601String(),
                    ],
                ]);
                break;

            case 'REVOCATION':
                // Immediate store refund / chargeback
                $user->revokeStoreSubscription();

                SubscriptionAuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'webhook_revoked',
                    'reason' => "RevenueCat store revocation / refund for product [{$productId}]",
                    'previous_premium_until' => $previousExpiry,
                    'new_premium_until' => $user->premium_until,
                    'metadata' => [
                        'event_id' => $eventId,
                        'event_type' => $type,
                    ],
                ]);
                break;

            default:
                Log::info("RevenueCat webhook: Unhandled event type [{$type}] for user [{$user->id}]");
                break;
        }

        // Record processed webhook event
        SubscriptionWebhookEvent::create([
            'event_id' => $eventId,
            'event_type' => $type,
            'app_user_id' => $appUserId,
            'payload' => $payload,
            'processed_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Webhook processed successfully',
            'event_id' => $eventId,
            'is_premium' => $user->fresh()->isPremium(),
        ], 200);
    }

    /**
     * Restore: re-reads the signed-in account from RevenueCat so access is
     * repaired even when the purchase webhook was lost, then answers it.
     */
    public function restore(Request $request): JsonResponse
    {
        $user = $request->user();
        try {
            $this->subscriberSync->sync($user, 'restore');
        } catch (RevenueCatUnavailableException $e) {
            Log::warning('RevenueCat restore sync failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'RevenueCat unavailable; try again shortly'], 503);
        }
        $user->refresh();

        return response()->json([
            'is_premium' => $user->isPremium(),
            'premium_until' => $user->premium_until?->toIso8601String(),
            'subscription_plan_id' => $user->subscription_plan_id,
            'subscription_provider' => $user->subscription_provider,
        ]);
    }
}
