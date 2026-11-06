<?php

namespace App\Services\Subscriptions;

use App\Models\SubscriptionAuditLog;
use App\Models\User;
use App\Support\FailClosed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles one account's access with RevenueCat, the store's source of
 * truth, for when a webhook was lost or failed (restore) or a subscription
 * moved between accounts (TRANSFER).
 *
 * RevenueCat's app user id is the Siratie user id. Only access that came
 * from RevenueCat is ever revoked here; admin grants are left alone.
 */
class RevenueCatSubscriberSync
{
    public const ENDPOINT = 'https://api.revenuecat.com/v1/subscribers/';

    /** RevenueCat dates, e.g. 2026-12-05T15:03:38Z (no relative words like "tomorrow"). */
    private const ISO_8601 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/D';

    /**
     * The canonical Siratie user ids among RevenueCat app user ids. Aliases
     * ($RCAnonymousID:…, "4abc", "04", " 4") are dropped: MySQL would coerce
     * some of them into a real account's id in an integer comparison.
     *
     * @param  array<mixed>  $appUserIds
     * @return list<string>
     */
    public static function siratieUserIds(array $appUserIds): array
    {
        $ids = [];
        foreach ($appUserIds as $id) {
            if ((is_string($id) || is_int($id)) && preg_match('/^[1-9]\d*$/D', (string) $id) === 1) {
                $ids[(string) $id] = (string) $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @return bool false when sync is skipped because local development has
     *              no RevenueCat API key; true once the account matches RevenueCat
     *
     * @throws RevenueCatUnavailableException when RevenueCat cannot be read
     */
    public function sync(User $user, string $reason): bool
    {
        $key = config('services.revenuecat.api_key');
        if (! is_string($key) || trim($key) === '') {
            if (FailClosed::allowsMissingConfiguration()) {
                return false;
            }
            FailClosed::unavailable('RevenueCat subscriber sync', 'services.revenuecat.api_key');
        }
        // Only the Siratie Premium entitlement grants access; another
        // entitlement in the RevenueCat project must never unlock it.
        $wanted = config('services.revenuecat.entitlement_id');
        $wanted = is_string($wanted) ? trim($wanted) : '';
        if ($wanted === '' && ! FailClosed::allowsMissingConfiguration()) {
            FailClosed::unavailable('RevenueCat subscriber sync', 'services.revenuecat.entitlement_id');
        }

        try {
            $response = Http::withToken(trim($key))
                ->acceptJson()
                ->timeout(10)
                ->get(self::ENDPOINT.rawurlencode((string) $user->id));
        } catch (ConnectionException $e) {
            throw new RevenueCatUnavailableException('RevenueCat unreachable', previous: $e);
        }
        if (! $response->successful()) {
            throw new RevenueCatUnavailableException("RevenueCat answered HTTP {$response->status()}");
        }

        $active = $this->latestActiveEntitlement($response->json('subscriber'), $wanted);
        $previousExpiry = $user->premium_until;

        if ($active !== null) {
            [$expiresAt, $productId] = $active;
            $keepsLongerGrant = $user->hasNonStoreAccessUntil($expiresAt);
            $unchanged = $user->subscription_provider === 'revenuecat'
                && $user->subscription_plan_id === $productId
                && $user->premium_until?->equalTo($expiresAt);
            if ($keepsLongerGrant || $unchanged) {
                return true;
            }

            $user->grantSubscription(
                expiresAt: $expiresAt,
                planId: $productId,
                provider: 'revenuecat',
                externalId: $user->subscription_provider === 'revenuecat' ? $user->subscription_external_id : null,
            );
            $this->audit($user, 'sync_granted', $reason, $previousExpiry, $productId);

            return true;
        }

        if ($user->subscription_provider === 'revenuecat' && $user->isPremium()) {
            $user->revokeStoreSubscription();
            $this->audit($user, 'sync_revoked', $reason, $previousExpiry, null);
        }

        return true;
    }

    /**
     * The furthest-expiring [$wanted] entitlement that is active now (any
     * entitlement when $wanted is empty, which only local development allows).
     * An entitlement whose expiry is null is never granted: nothing sold here
     * is lifetime.
     *
     * An answer of any other shape is unreadable, never "no access": a
     * changed or broken response must not revoke what a customer paid for.
     *
     * @return array{0: Carbon, 1: string}|null
     *
     * @throws RevenueCatUnavailableException when the answer cannot be read
     */
    private function latestActiveEntitlement(mixed $subscriber, string $wanted): ?array
    {
        $entitlements = is_array($subscriber) && $subscriber !== [] && ! array_is_list($subscriber)
            ? ($subscriber['entitlements'] ?? null)
            : null;
        // JSON {} decodes to []; a non-empty list is not an entitlement map.
        if (! is_array($entitlements) || ($entitlements !== [] && array_is_list($entitlements))) {
            throw new RevenueCatUnavailableException('RevenueCat answered without a readable entitlement map');
        }

        $best = null;
        foreach ($entitlements as $id => $entitlement) {
            if ($wanted !== '' && (string) $id !== $wanted) {
                continue;
            }
            if (! is_array($entitlement) || ! array_key_exists('expires_date', $entitlement)) {
                throw new RevenueCatUnavailableException("RevenueCat entitlement [{$id}] is unreadable");
            }
            $expires = $entitlement['expires_date'];
            if ($expires === null) {
                continue;
            }
            if (! is_string($expires) || preg_match(self::ISO_8601, $expires) !== 1) {
                throw new RevenueCatUnavailableException("RevenueCat entitlement [{$id}] has an unreadable expiry");
            }
            $expiresAt = Carbon::parse($expires);
            if (! $expiresAt->isFuture()) {
                continue;
            }
            if ($best === null || $expiresAt->greaterThan($best[0])) {
                $best = [$expiresAt, (string) ($entitlement['product_identifier'] ?? 'pro')];
            }
        }

        return $best;
    }

    private function audit(User $user, string $action, string $reason, mixed $previousExpiry, ?string $productId): void
    {
        SubscriptionAuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'reason' => "RevenueCat sync ({$reason})",
            'previous_premium_until' => $previousExpiry,
            'new_premium_until' => $user->premium_until,
            'metadata' => array_filter(['product_id' => $productId, 'reason' => $reason]),
        ]);
        Log::info("RevenueCat sync: {$action} for user [{$user->id}]", ['reason' => $reason]);
    }
}
