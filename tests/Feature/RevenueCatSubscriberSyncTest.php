<?php

namespace Tests\Feature;

use App\Models\SubscriptionWebhookEvent;
use App\Models\User;
use App\Services\Subscriptions\RevenueCatSubscriberSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Restore and TRANSFER re-read RevenueCat (the store's source of truth) so a
 * lost webhook or an unexpected transfer is repaired on the server.
 */
class RevenueCatSubscriberSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array<string, mixed>|int> RevenueCat answer per app user id. */
    private array $subscribers = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.revenuecat.api_key' => 'sk_test_key',
            'services.revenuecat.webhook_secret' => null,
            'services.revenuecat.entitlement_id' => null,
        ]);
        Http::fake(function (HttpRequest $request) {
            $id = rawurldecode(substr($request->url(), strlen(RevenueCatSubscriberSync::ENDPOINT)));
            $answer = $this->subscribers[$id] ?? ['subscriber' => ['entitlements' => []]];

            return is_int($answer) ? Http::response([], $answer) : Http::response($answer);
        });
    }

    /** @param array<string, array{0: ?Carbon, 1?: string}> $entitlements id => [expiry, product] */
    private function storeHas(User $user, array $entitlements): void
    {
        $this->subscribers[(string) $user->id] = ['subscriber' => ['entitlements' => array_map(
            fn (array $e) => [
                'expires_date' => $e[0]?->toIso8601ZuluString(),
                'product_identifier' => $e[1] ?? 'sirati_pro_monthly',
            ],
            $entitlements,
        )]];
    }

    private function restore(User $user)
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/subscriptions/restore');
    }

    public function test_restore_repairs_an_active_store_subscription_the_server_never_heard_of(): void
    {
        $user = User::factory()->create();
        $expires = Carbon::now()->addDays(20)->startOfSecond();
        $this->storeHas($user, ['pro' => [$expires, 'sirati_pro_semiannual']]);

        $this->restore($user)->assertOk()->assertJson([
            'is_premium' => true,
            'subscription_plan_id' => 'sirati_pro_semiannual',
            'subscription_provider' => 'revenuecat',
        ]);
        $this->assertTrue($user->fresh()->premium_until->equalTo($expires));
        $this->assertDatabaseHas('subscription_audit_logs', ['user_id' => $user->id, 'action' => 'sync_granted']);

        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer sk_test_key')
            && str_ends_with($r->url(), '/subscribers/'.$user->id));

        // Restoring again changes nothing and logs nothing new.
        $this->restore($user)->assertOk()->assertJson(['is_premium' => true]);
        $this->assertDatabaseCount('subscription_audit_logs', 1);
    }

    public function test_only_an_unexpired_entitlement_with_an_expiry_grants_and_the_latest_wins(): void
    {
        $cases = [
            'expired' => [['pro' => [Carbon::now()->subDay()]], false],
            'no expiry' => [['pro' => [null]], false],
            'none' => [[], false],
            'latest of two' => [[
                'pro' => [Carbon::now()->addDays(3), 'sirati_pro_monthly'],
                'pro_plus' => [Carbon::now()->addMonths(6), 'sirati_pro_annual'],
            ], 'sirati_pro_annual'],
        ];
        foreach ($cases as $case => [$entitlements, $plan]) {
            $user = User::factory()->create();
            $this->storeHas($user, $entitlements);
            $this->restore($user)->assertOk()->assertJson(['is_premium' => $plan !== false], $case);
            if ($plan !== false) {
                $this->assertSame($plan, $user->fresh()->subscription_plan_id, $case);
            }
        }
    }

    public function test_a_configured_entitlement_id_ignores_other_entitlements(): void
    {
        config(['services.revenuecat.entitlement_id' => 'pro']);
        $user = User::factory()->create();
        $this->storeHas($user, ['legacy' => [Carbon::now()->addYear()]]);

        $this->restore($user)->assertOk()->assertJson(['is_premium' => false]);
    }

    public function test_restore_revokes_store_access_revenuecat_no_longer_has_but_keeps_admin_grants(): void
    {
        $storeUser = User::factory()->create();
        $storeUser->grantSubscription(Carbon::now()->addMonth(), 'sirati_pro_monthly', 'revenuecat', 'tx_1');
        $this->storeHas($storeUser, []);
        $this->restore($storeUser)->assertOk()->assertJson(['is_premium' => false]);
        $this->assertDatabaseHas('subscription_audit_logs', ['user_id' => $storeUser->id, 'action' => 'sync_revoked']);

        $adminUser = User::factory()->create();
        $adminUntil = Carbon::now()->addYear()->startOfSecond();
        $adminUser->grantSubscription($adminUntil, 'pro', 'admin');
        $this->storeHas($adminUser, ['pro' => [Carbon::now()->addMonth()]]);
        $this->restore($adminUser)->assertOk()->assertJson(['is_premium' => true, 'subscription_provider' => 'admin']);
        $this->assertTrue($adminUser->fresh()->premium_until->equalTo($adminUntil), 'A longer admin grant is not shortened');
    }

    public function test_restore_never_guesses_when_revenuecat_cannot_be_read(): void
    {
        foreach ([500, 401, 404] as $status) {
            $user = User::factory()->create();
            $user->grantSubscription(Carbon::now()->addMonth(), 'sirati_pro_monthly', 'revenuecat', 'tx');
            $this->subscribers[(string) $user->id] = $status;

            $this->restore($user)->assertStatus(503);
            $this->assertTrue($user->fresh()->isPremium(), "HTTP {$status} must not revoke");
        }
    }

    public function test_restore_fails_closed_without_an_api_key_outside_local_development(): void
    {
        config(['services.revenuecat.api_key' => '']);
        Log::spy();
        $user = User::factory()->create();
        foreach (['production', 'staging'] as $environment) {
            $this->app['env'] = $environment;
            $this->restore($user)->assertStatus(503);
        }
        $this->app['env'] = 'testing';
        Http::assertNothingSent();
        Log::shouldHaveReceived('critical')
            ->withArgs(fn ($message, $context = []) => ($context['configuration'] ?? null) === 'services.revenuecat.api_key')
            ->twice();
    }

    private function transfer(string $eventId, User $from, User $to)
    {
        return $this->postJson('/api/webhooks/revenuecat', ['event' => [
            'id' => $eventId,
            'type' => 'TRANSFER',
            'transferred_from' => [(string) $from->id],
            'transferred_to' => [(string) $to->id],
        ]]);
    }

    public function test_a_transfer_makes_both_accounts_match_revenuecat_and_alerts(): void
    {
        Log::spy();
        $from = User::factory()->create();
        $from->grantSubscription(Carbon::now()->addMonth(), 'sirati_pro_monthly', 'revenuecat', 'tx');
        $to = User::factory()->create();
        $expires = Carbon::now()->addMonth()->startOfSecond();
        $this->storeHas($from, []);
        $this->storeHas($to, ['pro' => [$expires]]);

        $this->transfer('evt_transfer_1', $from, $to)->assertOk()->assertJson(['status' => 'transfer_reconciled']);
        $this->assertFalse($from->fresh()->isPremium());
        $this->assertTrue($to->fresh()->isPremium());
        $this->assertTrue($to->fresh()->premium_until->equalTo($expires));
        Log::shouldHaveReceived('critical')->once();

        // A duplicate delivery is acknowledged without touching RevenueCat again.
        $sent = count(Http::recorded());
        $this->transfer('evt_transfer_1', $from, $to)->assertOk()->assertJson(['status' => 'duplicate']);
        $this->assertCount($sent, Http::recorded());
    }

    public function test_a_transfer_only_reconciles_well_formed_siratie_user_ids(): void
    {
        $to = User::factory()->create();
        $this->storeHas($to, ['pro' => [Carbon::now()->addMonth()]]);
        // Aliases that a loose integer comparison could coerce into a real id.
        $aliases = ['$RCAnonymousID:'.$to->id, $to->id.'abc', '0'.$to->id, ' '.$to->id, '-'.$to->id, '0', ''];
        $bystander = User::factory()->create();
        $bystander->grantSubscription(Carbon::now()->addMonth(), 'sirati_pro_monthly', 'revenuecat', 'tx');
        $this->storeHas($bystander, []);

        $this->postJson('/api/webhooks/revenuecat', ['event' => [
            'id' => 'evt_transfer_aliases',
            'type' => 'TRANSFER',
            'app_user_id' => (string) $to->id,
            'transferred_from' => [...$aliases, $bystander->id.'x'],
            'transferred_to' => [(string) $to->id],
        ]])->assertOk()->assertJson(['status' => 'transfer_reconciled']);

        $this->assertTrue($to->fresh()->isPremium());
        $this->assertTrue($bystander->fresh()->isPremium(), 'an alias must never sync another account');
        Http::assertSentCount(1);
    }

    public function test_only_canonical_positive_integer_ids_count_as_siratie_users(): void
    {
        foreach ([1, 7, 42, 999999999] as $n) {
            $this->assertSame([(string) $n], RevenueCatSubscriberSync::siratieUserIds([$n]));
            $this->assertSame([(string) $n], RevenueCatSubscriberSync::siratieUserIds([(string) $n, $n]), 'deduplicated');
            $coercible = ['$RCAnonymousID:'.$n, $n.'abc', '0'.$n, ' '.$n, $n.' ', $n."\n", '-'.$n, '+'.$n, $n.'.0', $n.'e0', '0x'.$n];
            $this->assertSame([], RevenueCatSubscriberSync::siratieUserIds($coercible), "aliases of {$n}");
        }
        $this->assertSame([], RevenueCatSubscriberSync::siratieUserIds([0, -3, '0', '', null, true, 4.0, ['4']]));
    }

    public function test_a_transfer_revenuecat_cannot_confirm_is_retried_not_recorded(): void
    {
        $from = User::factory()->create();
        $from->grantSubscription(Carbon::now()->addMonth(), 'sirati_pro_monthly', 'revenuecat', 'tx');
        $to = User::factory()->create();
        $this->subscribers[(string) $from->id] = 500;
        $this->storeHas($to, ['pro' => [Carbon::now()->addMonth()]]);

        $this->transfer('evt_transfer_2', $from, $to)->assertStatus(503);
        $this->assertFalse(SubscriptionWebhookEvent::where('event_id', 'evt_transfer_2')->exists());

        // RevenueCat's retry succeeds once it answers.
        $this->storeHas($from, []);
        $this->transfer('evt_transfer_2', $from, $to)->assertOk()->assertJson(['status' => 'transfer_reconciled']);
        $this->assertFalse($from->fresh()->isPremium());
        $this->assertTrue($to->fresh()->isPremium());
        $this->assertTrue(SubscriptionWebhookEvent::where('event_id', 'evt_transfer_2')->exists());
    }
}
