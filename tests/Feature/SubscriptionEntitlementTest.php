<?php

namespace Tests\Feature;

use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use App\Models\SubscriptionAuditLog;
use App\Models\SubscriptionWebhookEvent;
use App\Models\User;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CvTemplateSeeder::class);
    }

    public function test_revenuecat_webhook_requires_valid_secret_if_configured(): void
    {
        config(['services.revenuecat.webhook_secret' => 'test-secret-token']);

        $user = User::factory()->create();

        // 1. Missing secret
        $response = $this->postJson('/api/webhooks/revenuecat', [
            'event' => [
                'id' => 'evt_1',
                'type' => 'INITIAL_PURCHASE',
                'app_user_id' => (string) $user->id,
            ],
        ]);
        $response->assertStatus(401);

        // 2. Invalid secret
        $response = $this->withHeaders(['Authorization' => 'Bearer wrong'])
            ->postJson('/api/webhooks/revenuecat', [
                'event' => [
                    'id' => 'evt_1',
                    'type' => 'INITIAL_PURCHASE',
                    'app_user_id' => (string) $user->id,
                ],
            ]);
        $response->assertStatus(401);

        // 2b. Partial prefix mismatch rejected
        $response = $this->withHeaders(['Authorization' => 'Bearer test-secret-token-extra'])
            ->postJson('/api/webhooks/revenuecat', [
                'event' => [
                    'id' => 'evt_1',
                    'type' => 'INITIAL_PURCHASE',
                    'app_user_id' => (string) $user->id,
                ],
            ]);
        $response->assertStatus(401);

        // 3. Valid secret in X-RevenueCat-Secret header
        $response = $this->withHeaders(['X-RevenueCat-Secret' => 'test-secret-token'])
            ->postJson('/api/webhooks/revenuecat', [
                'event' => [
                    'id' => 'evt_custom_hdr',
                    'type' => 'INITIAL_PURCHASE',
                    'app_user_id' => (string) $user->id,
                    'product_id' => 'pro_monthly',
                    'expiration_at_ms' => Carbon::now()->addMonth()->getTimestampMs(),
                ],
            ]);
        $response->assertStatus(200);

        // 4. Valid secret in Authorization Bearer
        $response = $this->withHeaders(['Authorization' => 'Bearer test-secret-token'])
            ->postJson('/api/webhooks/revenuecat', [
                'event' => [
                    'id' => 'evt_bearer_hdr',
                    'type' => 'INITIAL_PURCHASE',
                    'app_user_id' => (string) $user->id,
                    'product_id' => 'pro_monthly',
                    'expiration_at_ms' => Carbon::now()->addMonth()->getTimestampMs(),
                ],
            ]);
        $response->assertStatus(200);
        $this->assertTrue($user->fresh()->isPremium());
    }

    public function test_revenuecat_webhook_fails_closed_outside_local_when_secret_is_unconfigured(): void
    {
        config(['services.revenuecat.webhook_secret' => null]);
        $originalEnv = $this->app['env'];

        try {
            // In production without secret: fails closed 503
            $this->app['env'] = 'production';
            $user = User::factory()->create();

            $response = $this->postJson('/api/webhooks/revenuecat', [
                'event' => [
                    'id' => 'evt_prod_unconfigured',
                    'type' => 'INITIAL_PURCHASE',
                    'app_user_id' => (string) $user->id,
                ],
            ]);

            $response->assertStatus(503);
            $response->assertJson(['message' => 'Webhook secret unconfigured']);
            $this->assertFalse($user->fresh()->isPremium());

            // In staging without secret: also fails closed 503
            $this->app['env'] = 'staging';
            $response = $this->postJson('/api/webhooks/revenuecat', [
                'event' => [
                    'id' => 'evt_staging_unconfigured',
                    'type' => 'INITIAL_PURCHASE',
                    'app_user_id' => (string) $user->id,
                ],
            ]);

            $response->assertStatus(503);
            $response->assertJson(['message' => 'Webhook secret unconfigured']);
            $this->assertFalse($user->fresh()->isPremium());
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    public function test_initial_purchase_webhook_grants_premium(): void
    {
        config(['services.revenuecat.webhook_secret' => null]);

        $user = User::factory()->create();
        $this->assertFalse($user->isPremium());

        $expiresMs = Carbon::now()->addYear()->getTimestampMs();

        $response = $this->postJson('/api/webhooks/revenuecat', [
            'event' => [
                'id' => 'rc_evt_purchase_100',
                'type' => 'INITIAL_PURCHASE',
                'app_user_id' => (string) $user->id,
                'product_id' => 'sirati_pro_annual',
                'original_transaction_id' => 'orig_tx_999',
                'expiration_at_ms' => $expiresMs,
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Webhook processed successfully',
            'is_premium' => true,
        ]);

        $freshUser = $user->fresh();
        $this->assertTrue($freshUser->isPremium());
        $this->assertSame('sirati_pro_annual', $freshUser->subscription_plan_id);
        $this->assertSame('revenuecat', $freshUser->subscription_provider);
        $this->assertSame('orig_tx_999', $freshUser->subscription_external_id);

        $this->assertDatabaseHas('subscription_audit_logs', [
            'user_id' => $user->id,
            'action' => 'webhook_granted',
        ]);
        $this->assertDatabaseHas('subscription_webhook_events', [
            'event_id' => 'rc_evt_purchase_100',
            'event_type' => 'INITIAL_PURCHASE',
        ]);
    }

    public function test_renewal_webhook_extends_premium(): void
    {
        config(['services.revenuecat.webhook_secret' => null]);

        $user = User::factory()->create();
        $initialExpiry = Carbon::now()->addDays(15);
        $user->grantSubscription($initialExpiry, 'pro_monthly', 'revenuecat', 'tx_initial');

        $newExpiry = Carbon::now()->addDays(45);

        $response = $this->postJson('/api/webhooks/revenuecat', [
            'event' => [
                'id' => 'rc_evt_renewal_200',
                'type' => 'RENEWAL',
                'app_user_id' => (string) $user->id,
                'product_id' => 'pro_monthly',
                'original_transaction_id' => 'tx_initial',
                'expiration_at_ms' => $newExpiry->getTimestampMs(),
            ],
        ]);

        $response->assertStatus(200);
        $freshUser = $user->fresh();
        $this->assertTrue($freshUser->isPremium());
        $this->assertSame($newExpiry->timestamp, $freshUser->premium_until->timestamp);
    }

    public function test_webhook_is_idempotent(): void
    {
        config(['services.revenuecat.webhook_secret' => null]);

        $user = User::factory()->create();
        $payload = [
            'event' => [
                'id' => 'rc_evt_idempotent_test',
                'type' => 'INITIAL_PURCHASE',
                'app_user_id' => (string) $user->id,
                'product_id' => 'pro_monthly',
                'expiration_at_ms' => Carbon::now()->addMonth()->getTimestampMs(),
            ],
        ];

        $first = $this->postJson('/api/webhooks/revenuecat', $payload);
        $first->assertStatus(200);

        $this->assertSame(1, SubscriptionWebhookEvent::where('event_id', 'rc_evt_idempotent_test')->count());
        $this->assertSame(1, SubscriptionAuditLog::where('user_id', $user->id)->count());

        // Replay exact same payload
        $second = $this->postJson('/api/webhooks/revenuecat', $payload);
        $second->assertStatus(200);
        $second->assertJson([
            'status' => 'duplicate',
            'event_id' => 'rc_evt_idempotent_test',
        ]);

        // Verify no duplicate audit logs created
        $this->assertSame(1, SubscriptionWebhookEvent::where('event_id', 'rc_evt_idempotent_test')->count());
        $this->assertSame(1, SubscriptionAuditLog::where('user_id', $user->id)->count());
    }

    public function test_expiration_webhook_revokes_premium(): void
    {
        config(['services.revenuecat.webhook_secret' => null]);

        $user = User::factory()->create();
        $user->grantSubscription(Carbon::now()->addDays(5), 'pro_monthly');
        $this->assertTrue($user->isPremium());

        $response = $this->postJson('/api/webhooks/revenuecat', [
            'event' => [
                'id' => 'rc_evt_expiration_300',
                'type' => 'EXPIRATION',
                'app_user_id' => (string) $user->id,
                'product_id' => 'pro_monthly',
            ],
        ]);

        $response->assertStatus(200);
        $freshUser = $user->fresh();
        $this->assertFalse($freshUser->isPremium());

        $this->assertDatabaseHas('subscription_audit_logs', [
            'user_id' => $user->id,
            'action' => 'webhook_expired',
        ]);
    }

    public function test_revocation_webhook_immediately_revokes_premium(): void
    {
        config(['services.revenuecat.webhook_secret' => null]);

        $user = User::factory()->create();
        $user->grantSubscription(Carbon::now()->addDays(20), 'pro_monthly');
        $this->assertTrue($user->isPremium());

        $response = $this->postJson('/api/webhooks/revenuecat', [
            'event' => [
                'id' => 'rc_evt_revocation_400',
                'type' => 'REVOCATION',
                'app_user_id' => (string) $user->id,
                'product_id' => 'pro_monthly',
            ],
        ]);

        $response->assertStatus(200);
        $freshUser = $user->fresh();
        $this->assertFalse($freshUser->isPremium());

        $this->assertDatabaseHas('subscription_audit_logs', [
            'user_id' => $user->id,
            'action' => 'webhook_revoked',
        ]);
    }

    public function test_restore_endpoint_returns_user_entitlement(): void
    {
        $freeUser = User::factory()->create();
        $response = $this->actingAs($freeUser, 'sanctum')
            ->postJson('/api/subscriptions/restore');
        $response->assertStatus(200);
        $response->assertJson([
            'is_premium' => false,
            'premium_until' => null,
        ]);

        $premiumUser = User::factory()->create();
        $expires = Carbon::now()->addMonths(3);
        $premiumUser->grantSubscription($expires, 'pro_quarterly', 'revenuecat', 'tx_restore');

        $response = $this->actingAs($premiumUser, 'sanctum')
            ->postJson('/api/subscriptions/restore');
        $response->assertStatus(200);
        $response->assertJson([
            'is_premium' => true,
            'subscription_plan_id' => 'pro_quarterly',
            'subscription_provider' => 'revenuecat',
        ]);
    }

    public function test_admin_can_grant_and_revoke_premium_with_audit_logging(): void
    {
        $admin = User::factory()->create(['email' => 'admin@sirati.local']);
        config(['services.admin.emails' => ['admin@sirati.local']]);

        $targetUser = User::factory()->create();
        $this->assertFalse($targetUser->isPremium());

        // Admin grants 60 days
        $grantResponse = $this->actingAs($admin)
            ->postJson("/admin/users/{$targetUser->id}/grant-premium", [
                'days' => 60,
                'plan_id' => 'vip_gift',
                'reason' => 'Granted for conference attendees',
            ]);

        $grantResponse->assertStatus(200);
        $grantResponse->assertJson([
            'success' => true,
            'user' => [
                'is_premium' => true,
            ],
        ]);

        $freshTarget = $targetUser->fresh();
        $this->assertTrue($freshTarget->isPremium());
        $this->assertSame('vip_gift', $freshTarget->subscription_plan_id);
        $this->assertSame('admin', $freshTarget->subscription_provider);

        $this->assertDatabaseHas('subscription_audit_logs', [
            'user_id' => $targetUser->id,
            'admin_id' => $admin->id,
            'action' => 'grant',
            'reason' => 'Granted for conference attendees',
        ]);

        // Admin revokes
        $revokeResponse = $this->actingAs($admin)
            ->postJson("/admin/users/{$targetUser->id}/revoke-premium", [
                'reason' => 'User requested account downgrade',
            ]);

        $revokeResponse->assertStatus(200);
        $revokeResponse->assertJson([
            'success' => true,
            'user' => [
                'is_premium' => false,
            ],
        ]);

        $this->assertFalse($targetUser->fresh()->isPremium());
        $this->assertDatabaseHas('subscription_audit_logs', [
            'user_id' => $targetUser->id,
            'admin_id' => $admin->id,
            'action' => 'revoke',
            'reason' => 'User requested account downgrade',
        ]);
    }

    public function test_user_with_active_subscription_can_access_premium_templates(): void
    {
        $user = User::factory()->create();
        $user->grantSubscription(Carbon::now()->addMonth());

        $cv = GeneratedCv::create([
            'user_id' => $user->id,
            'full_name' => 'نورة العتيبي',
            'email' => 'noura@example.com',
            'phone' => '+966500000000',
            'location' => 'الرياض',
            'target_job_title' => 'مديرة موارد بشرية',
            'language' => 'ar',
            'summary_input' => 'خبرة واسعة في إدارة المواهب.',
            'skills_input' => 'إدارة الموارد البشرية، التوظيف',
            'experience_input' => '8 سنوات خبرة.',
            'education_input' => 'ماجستير إدارة أعمال.',
            'generated_markdown' => '',
            'form_payload' => ['language' => 'ar'],
            'ai_status' => 'completed',
            'score_total' => 95,
            'grade' => 'A+',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/generated-cvs/{$cv->id}/download?template=executive-leadership-brief");

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
