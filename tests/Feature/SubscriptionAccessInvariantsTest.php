<?php

namespace Tests\Feature;

use App\Models\SubscriptionAuditLog;
use App\Models\User;
use App\Services\Subscriptions\RevenueCatSubscriberSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Invariants that hold for every path that changes premium access (store
 * webhooks, restore sync, admin grants):
 *  - a RevenueCat id only ever resolves the account it canonically names;
 *  - access from one source (App Store, admin) is never shortened or removed
 *    by the other source. Admin access includes access set by hand in the
 *    database, under any provider name other than the store's or with no
 *    end date.
 *
 * Run on MySQL too (phpunit.mysql.xml): MySQL coerces "4abc" to 4 in an
 * integer comparison where SQLite does not.
 */
class SubscriptionAccessInvariantsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Provider names access can carry when someone set it by hand instead of
     * through the admin panel (production had 'manual'), including near
     * misses of the store's own name.
     */
    private const HAND_SET_PROVIDERS = ['manual', 'admin', 'admin_override', 'gift', 'RevenueCat', '', null];

    /** @var array<string, array<string, mixed>> RevenueCat answer per app user id. */
    private array $subscribers = [];

    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Hundreds of webhooks per test from one address in one frozen minute;
        // rate limits are not what these tests are about.
        $this->withoutMiddleware(ThrottleRequests::class);
        Carbon::setTestNow(Carbon::now()->startOfSecond());
        config([
            'services.revenuecat.api_key' => 'sk_test_key',
            'services.revenuecat.webhook_secret' => null,
            'services.revenuecat.entitlement_id' => 'pro',
            'services.admin.emails' => ['admin@sirati.local'],
        ]);
        Http::fake(function (HttpRequest $request) {
            $id = rawurldecode(substr($request->url(), strlen(RevenueCatSubscriberSync::ENDPOINT)));

            return Http::response($this->subscribers[$id] ?? ['subscriber' => ['entitlements' => []]]);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function webhook(array $event): TestResponse
    {
        static $sequence = 0;
        $event['id'] ??= 'evt_'.(++$sequence).'_'.bin2hex(random_bytes(4));

        return $this->postJson('/api/webhooks/revenuecat', ['event' => $event]);
    }

    private function storeGrant(User $user, int $days): Carbon
    {
        $expires = Carbon::now()->addDays($days);
        $this->webhook([
            'type' => 'INITIAL_PURCHASE',
            'app_user_id' => (string) $user->id,
            'product_id' => 'sirati_pro_monthly',
            'expiration_at_ms' => $expires->getTimestampMs(),
        ])->assertOk();

        return $expires;
    }

    private function adminGrant(User $user, int $days, string $plan = 'vip_gift'): Carbon
    {
        $this->admin ??= User::factory()->create(['email' => 'admin@sirati.local']);
        $this->actingAs($this->admin)
            ->postJson("/admin/users/{$user->id}/grant-premium", ['days' => $days, 'plan_id' => $plan])
            ->assertOk();

        return Carbon::now()->addDays($days);
    }

    /** Premium written straight into the database; $days null = no end date. */
    private function handSetAccess(?string $provider, ?int $days): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'is_premium' => true,
            'premium_until' => $days === null ? null : Carbon::now()->addDays($days),
            'subscription_provider' => $provider,
            'subscription_plan_id' => 'hand_set',
        ])->save();

        return $user->fresh();
    }

    private function assertAccessUntil(?Carbon $until, User $user, string $case): void
    {
        $fresh = $user->fresh();
        $this->assertTrue($fresh->isPremium(), $case);
        if ($until === null) {
            $this->assertNull($fresh->premium_until, $case);
        } else {
            $this->assertTrue($fresh->premium_until?->equalTo($until) === true, $case);
        }
    }

    /** @return array<string, callable(User): void> every way the store side of access ends */
    private function storeEndings(): array
    {
        $event = fn (string $type, Carbon $expiry) => fn (User $user) => $this->webhook([
            'type' => $type,
            'app_user_id' => (string) $user->id,
            'product_id' => 'sirati_pro_monthly',
            'expiration_at_ms' => $expiry->getTimestampMs(),
        ])->assertOk();

        return [
            'EXPIRATION webhook' => $event('EXPIRATION', Carbon::now()->subMinute()),
            'REVOCATION webhook' => $event('REVOCATION', Carbon::now()->addMonth()),
            'CANCELLATION webhook after expiry' => $event('CANCELLATION', Carbon::now()->subMinute()),
            'restore sync' => function (User $user) {
                $this->subscribers[(string) $user->id] = ['subscriber' => ['entitlements' => []]];
                $this->actingAs($user->fresh(), 'sanctum')->postJson('/api/subscriptions/restore')->assertOk();
            },
        ];
    }

    public function test_a_webhook_never_resolves_an_account_from_a_malformed_id(): void
    {
        $victim = User::factory()->create();
        $victim->grantSubscription(Carbon::now()->addMonth(), 'sirati_pro_monthly', 'revenuecat', 'tx');
        $bystander = User::factory()->create();
        $malformed = fn (int $id) => [
            '$RCAnonymousID:'.$id, $id.'abc', '0'.$id, '00'.$id, '+'.$id, '-'.$id,
            $id.'.0', $id.'.9', $id.'e0', '0x'.$id, $id.'-1',
        ];

        foreach (['EXPIRATION', 'REVOCATION'] as $type) {
            foreach ($malformed($victim->id) as $raw) {
                $this->webhook(['type' => $type, 'app_user_id' => $raw])
                    ->assertOk()->assertJson(['message' => 'Event recorded but user not found']);
                $this->assertTrue($victim->fresh()->isPremium(), "{$type} for app_user_id ".json_encode($raw));
            }
            $this->webhook([
                'type' => $type,
                'app_user_id' => '$RCAnonymousID:none',
                'aliases' => $malformed($victim->id),
            ])->assertOk()->assertJson(['message' => 'Event recorded but user not found']);
            $this->assertTrue($victim->fresh()->isPremium(), "{$type} through aliases");
        }

        foreach ($malformed($bystander->id) as $raw) {
            $this->webhook([
                'type' => 'INITIAL_PURCHASE',
                'app_user_id' => $raw,
                'expiration_at_ms' => Carbon::now()->addYear()->getTimestampMs(),
            ])->assertOk();
            $this->assertFalse($bystander->fresh()->isPremium(), 'INITIAL_PURCHASE for '.json_encode($raw));
        }

        // Aliases that are not even a list are ignored, never an error.
        $this->webhook(['type' => 'REVOCATION', 'app_user_id' => 'x', 'aliases' => (string) $victim->id.'abc'])
            ->assertOk();
        $this->assertTrue($victim->fresh()->isPremium());
    }

    public function test_a_webhook_finds_the_account_through_its_canonical_id_or_alias(): void
    {
        $direct = User::factory()->create();
        $viaAlias = User::factory()->create();
        $expires = Carbon::now()->addMonth();

        $this->webhook([
            'type' => 'INITIAL_PURCHASE',
            'app_user_id' => (string) $direct->id,
            'expiration_at_ms' => $expires->getTimestampMs(),
        ])->assertOk()->assertJson(['is_premium' => true]);
        $this->webhook([
            'type' => 'INITIAL_PURCHASE',
            'app_user_id' => '$RCAnonymousID:abc',
            'aliases' => ['$RCAnonymousID:abc', $viaAlias->id.'abc', (string) $viaAlias->id],
            'expiration_at_ms' => $expires->getTimestampMs(),
        ])->assertOk()->assertJson(['is_premium' => true]);

        $this->assertTrue($direct->fresh()->premium_until->equalTo($expires));
        $this->assertTrue($viaAlias->fresh()->premium_until->equalTo($expires));
    }

    public function test_the_store_side_ending_keeps_the_remaining_admin_access(): void
    {
        foreach ($this->storeEndings() as $ending => $end) {
            // Admin access shorter than, equal to and longer than the store month.
            foreach ([10, 30, 45, 400] as $adminDays) {
                foreach (['admin first', 'store first'] as $order) {
                    $user = User::factory()->create();
                    if ($order === 'admin first') {
                        $adminUntil = $this->adminGrant($user, $adminDays);
                        $this->storeGrant($user, 30);
                    } else {
                        $this->storeGrant($user, 30);
                        $adminUntil = $this->adminGrant($user, $adminDays);
                    }

                    $end($user);

                    $case = "{$ending}, admin {$adminDays} days, {$order}";
                    $fresh = $user->fresh();
                    $this->assertTrue($fresh->isPremium(), $case);
                    $this->assertTrue($fresh->premium_until->equalTo($adminUntil), $case);
                    $this->assertSame('admin', $fresh->subscription_provider, $case);
                    $this->assertSame('vip_gift', $fresh->subscription_plan_id, $case);
                }
            }
        }
    }

    public function test_no_grant_shortens_access_from_the_other_source(): void
    {
        foreach ([10, 30, 45, 400] as $adminDays) {
            foreach ([1, 30, 180] as $storeDays) {
                foreach (['admin first', 'store first'] as $order) {
                    $user = User::factory()->create();
                    if ($order === 'admin first') {
                        $adminUntil = $this->adminGrant($user, $adminDays);
                        $storeUntil = $this->storeGrant($user, $storeDays);
                    } else {
                        $storeUntil = $this->storeGrant($user, $storeDays);
                        $adminUntil = $this->adminGrant($user, $adminDays);
                    }

                    $case = "admin {$adminDays} days, store {$storeDays} days, {$order}";
                    $this->assertTrue(
                        $user->fresh()->premium_until->equalTo($adminUntil->max($storeUntil)),
                        $case,
                    );
                }
            }
        }

        // The restore sync obeys the same rule.
        $user = User::factory()->create();
        $adminUntil = $this->adminGrant($user, 400);
        $this->subscribers[(string) $user->id] = ['subscriber' => ['entitlements' => [
            'pro' => ['expires_date' => Carbon::now()->addMonth()->toIso8601ZuluString(), 'product_identifier' => 'sirati_pro_monthly'],
        ]]];
        $this->actingAs($user->fresh(), 'sanctum')->postJson('/api/subscriptions/restore')->assertOk();
        $this->assertTrue($user->fresh()->premium_until->equalTo($adminUntil));
    }

    public function test_an_admin_grant_that_has_run_out_is_never_brought_back(): void
    {
        foreach ($this->storeEndings() as $ending => $end) {
            $user = User::factory()->create();
            $this->adminGrant($user, 10);
            $this->storeGrant($user, 30);

            Carbon::setTestNow(Carbon::now()->addDays(20));
            $end($user);
            $this->assertFalse($user->fresh()->isPremium(), $ending);
            Carbon::setTestNow(Carbon::now()->subDays(20));
        }
    }

    public function test_access_set_by_hand_is_never_shortened_or_ended_by_the_store(): void
    {
        foreach ($this->storeEndings() as $ending => $end) {
            foreach (self::HAND_SET_PROVIDERS as $provider) {
                // Shorter than, equal to and longer than the store month; null = no end date.
                foreach ([10, 30, 45, 400, null] as $handDays) {
                    $user = $this->handSetAccess($provider, $handDays);
                    $handUntil = $user->premium_until;
                    $storeUntil = $this->storeGrant($user, 30);

                    $case = "{$ending}, provider ".json_encode($provider).', hand-set '.($handDays ?? 'no end date');
                    $this->assertAccessUntil($handUntil?->max($storeUntil), $user, "{$case}: store grant");

                    $end($user);
                    $this->assertAccessUntil($handUntil, $user, "{$case}: store end");
                }
            }
        }
    }

    public function test_a_restore_never_shortens_access_set_by_hand(): void
    {
        foreach (self::HAND_SET_PROVIDERS as $provider) {
            foreach ([10, 45, null] as $handDays) {
                $user = $this->handSetAccess($provider, $handDays);
                $handUntil = $user->premium_until;
                $storeUntil = Carbon::now()->addDays(30);
                $case = 'provider '.json_encode($provider).', hand-set '.($handDays ?? 'no end date');

                $this->subscribers[(string) $user->id] = ['subscriber' => ['entitlements' => [
                    'pro' => ['expires_date' => $storeUntil->toIso8601ZuluString(), 'product_identifier' => 'sirati_pro_monthly'],
                ]]];
                $this->actingAs($user->fresh(), 'sanctum')->postJson('/api/subscriptions/restore')->assertOk();
                $this->assertAccessUntil($handUntil?->max($storeUntil), $user, "{$case}: restore with a subscription");
                // The audit log records a grant only when access changed.
                $this->assertSame(
                    $handUntil === null || $handUntil->greaterThanOrEqualTo($storeUntil) ? 0 : 1,
                    SubscriptionAuditLog::where('user_id', $user->id)->where('action', 'sync_granted')->count(),
                    "{$case}: audit",
                );

                $this->subscribers[(string) $user->id] = ['subscriber' => ['entitlements' => []]];
                $this->actingAs($user->fresh(), 'sanctum')->postJson('/api/subscriptions/restore')->assertOk();
                $this->assertAccessUntil($handUntil, $user, "{$case}: restore after the subscription ended");
            }
        }
    }

    public function test_access_set_by_hand_that_has_run_out_is_never_brought_back(): void
    {
        foreach ($this->storeEndings() as $ending => $end) {
            foreach (self::HAND_SET_PROVIDERS as $provider) {
                $user = $this->handSetAccess($provider, 10);
                $this->storeGrant($user, 30);

                Carbon::setTestNow(Carbon::now()->addDays(20));
                $end($user);
                $this->assertFalse($user->fresh()->isPremium(), "{$ending}, provider ".json_encode($provider));
                Carbon::setTestNow(Carbon::now()->subDays(20));
            }
        }
    }

    public function test_an_admin_revoke_removes_all_access_and_nothing_brings_the_grant_back(): void
    {
        foreach ($this->storeEndings() as $ending => $end) {
            foreach ([10, 400] as $adminDays) {
                $user = User::factory()->create();
                $this->adminGrant($user, $adminDays);
                $this->storeGrant($user, 30);

                $this->actingAs($this->admin)
                    ->postJson("/admin/users/{$user->id}/revoke-premium", ['reason' => 'abuse'])
                    ->assertOk();
                $this->assertFalse($user->fresh()->isPremium(), "{$ending}, admin {$adminDays}: revoke");

                $end($user);
                $this->assertFalse($user->fresh()->isPremium(), "{$ending}, admin {$adminDays}: after store end");
            }
        }
    }
}
