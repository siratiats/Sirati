<?php

namespace Tests\Feature;

use App\Enums\AiStatus;
use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Models\User;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The paywall ships switched off. These tests pin both states: the current
 * production behaviour (nothing gated) and the behaviour after
 * MONETIZATION_ENABLED is flipped on once App Store products go live.
 */
class SubscriptionPaywallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CvTemplateSeeder::class);
    }

    // ------------------------------------------------- enforcement disabled

    public function test_nothing_is_gated_while_monetization_is_disabled(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $cv = $this->cv($user);

        $this->get("/api/generated-cvs/{$cv->id}/download")->assertOk();
    }

    public function test_free_user_sees_the_whole_analysis_while_disabled(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $analysis = $this->analysis($user);

        $this->getJson("/api/cv-analyses/{$analysis->id}")
            ->assertOk()
            ->assertJsonCount(4, 'data.quick_wins')
            ->assertJsonPath('data.access.is_locked', false)
            ->assertJsonPath('data.ai_feedback', 'Full AI advice.');
    }

    // -------------------------------------------------- enforcement enabled

    public function test_free_user_cannot_download_but_gets_the_plans(): void
    {
        $this->enforceMonetization();

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $cv = $this->cv($user);

        $response = $this->getJson("/api/generated-cvs/{$cv->id}/download");

        $response->assertStatus(402)
            ->assertJsonPath('error', 'subscription_required')
            ->assertJsonPath('feature', 'cv_download')
            ->assertJsonPath('paywall.trial_days', 3)
            ->assertJsonCount(3, 'paywall.plans')
            ->assertJsonPath('paywall.plans.0.product_id', 'com.sirati.pro.monthly');
    }

    public function test_subscriber_can_download(): void
    {
        $this->enforceMonetization();

        $user = User::factory()->create();
        $user->grantSubscription(Carbon::now()->addMonth(), 'monthly');
        Sanctum::actingAs($user);
        $cv = $this->cv($user);

        $this->get("/api/generated-cvs/{$cv->id}/download")->assertOk();
    }

    public function test_expired_subscription_is_treated_as_free(): void
    {
        $this->enforceMonetization();

        $user = User::factory()->create();
        $user->grantSubscription(Carbon::now()->subDay(), 'monthly');
        Sanctum::actingAs($user);
        $cv = $this->cv($user);

        $this->getJson("/api/generated-cvs/{$cv->id}/download")->assertStatus(402);
    }

    public function test_unfinished_cv_still_fails_quality_check_before_the_paywall(): void
    {
        $this->enforceMonetization();

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $cv = $this->cv($user, ['ai_status' => AiStatus::Queued]);

        // 409, not 402: never sell a subscription for a CV that is not ready.
        $this->getJson("/api/generated-cvs/{$cv->id}/download")->assertStatus(409);
    }

    public function test_preview_stays_free_and_is_watermarked_for_free_users(): void
    {
        $this->enforceMonetization();

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $cv = $this->cv($user);

        $this->getJson("/api/generated-cvs/{$cv->id}/preview")
            ->assertOk()
            ->assertJsonPath('data.is_watermarked', true)
            ->assertJsonPath('data.can_download', false);
    }

    public function test_preview_is_clean_for_subscribers(): void
    {
        $this->enforceMonetization();

        $user = User::factory()->create();
        $user->grantSubscription(Carbon::now()->addMonth(), 'monthly');
        Sanctum::actingAs($user);
        $cv = $this->cv($user);

        $this->getJson("/api/generated-cvs/{$cv->id}/preview")
            ->assertOk()
            ->assertJsonPath('data.is_watermarked', false)
            ->assertJsonPath('data.can_download', true);
    }

    public function test_analysis_is_truncated_and_reports_what_is_hidden(): void
    {
        $this->enforceMonetization();

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $analysis = $this->analysis($user);

        $response = $this->getJson("/api/cv-analyses/{$analysis->id}");

        $response->assertOk()
            // Score stays free - it is the hook.
            ->assertJsonPath('data.score_total', 62)
            ->assertJsonPath('data.grade', 'C')
            ->assertJsonCount(2, 'data.criteria')
            // Advice is the product.
            ->assertJsonCount(1, 'data.quick_wins')
            ->assertJsonCount(2, 'data.strengths')
            ->assertJsonCount(0, 'data.keywords_missing')
            ->assertJsonPath('data.ai_feedback', null)
            ->assertJsonPath('data.access.is_locked', true)
            ->assertJsonPath('data.access.hidden.quick_wins', 3)
            ->assertJsonPath('data.access.hidden.keywords_missing', 4);

        // 1 strength + 3 quick wins + 4 keywords + 1 feedback.
        $this->assertSame(9, $response->json('data.access.hidden_total'));
    }

    public function test_subscriber_sees_the_full_analysis(): void
    {
        $this->enforceMonetization();

        $user = User::factory()->create();
        $user->grantSubscription(Carbon::now()->addMonth(), 'monthly');
        Sanctum::actingAs($user);
        $analysis = $this->analysis($user);

        $this->getJson("/api/cv-analyses/{$analysis->id}")
            ->assertOk()
            ->assertJsonCount(4, 'data.quick_wins')
            ->assertJsonCount(4, 'data.keywords_missing')
            ->assertJsonPath('data.ai_feedback', 'Full AI advice.')
            ->assertJsonPath('data.access.is_locked', false);
    }

    public function test_enhancement_endpoints_require_a_subscription(): void
    {
        $this->enforceMonetization();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/generated-cvs/enhance-field', [
            'field' => 'summary',
            'draft' => 'I am a backend developer with five years of experience.',
            'job_title' => 'Backend Developer',
            'language' => 'en',
        ])->assertStatus(402)->assertJsonPath('feature', 'ai_enhancement');

        $this->postJson('/api/generated-cvs/enhance-job-description', [
            'target_job_title' => 'Backend Developer',
            'language' => 'en',
        ])->assertStatus(402)->assertJsonPath('feature', 'ai_enhancement');
    }

    public function test_free_daily_cv_quota_is_enforced(): void
    {
        $this->enforceMonetization();
        config(['monetization.free.daily_cv_generations' => 1]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->cv($user);

        $this->postJson('/api/generated-cvs', $this->cvPayload())
            ->assertStatus(402)
            ->assertJsonPath('feature', 'daily_cv_quota')
            ->assertJsonPath('paywall.limit', 1);
    }

    public function test_subscriber_has_no_daily_cv_quota(): void
    {
        $this->enforceMonetization();
        config(['monetization.free.daily_cv_generations' => 1]);

        $user = User::factory()->create();
        $user->grantSubscription(Carbon::now()->addMonth(), 'monthly');
        Sanctum::actingAs($user);
        $this->cv($user);

        $this->postJson('/api/generated-cvs', $this->cvPayload())->assertStatus(201);
    }

    public function test_duplicate_also_consumes_the_quota(): void
    {
        $this->enforceMonetization();
        config(['monetization.free.daily_cv_generations' => 1]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $cv = $this->cv($user);

        $this->postJson("/api/generated-cvs/{$cv->id}/duplicate")
            ->assertStatus(402)
            ->assertJsonPath('feature', 'daily_cv_quota');
    }

    // ------------------------------------------------------------- catalogue

    public function test_plans_endpoint_is_public(): void
    {
        $this->getJson('/api/mobile/subscription-plans')
            ->assertOk()
            ->assertJsonCount(3, 'data.plans')
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.plans.1.key', 'semiannual')
            ->assertJsonPath('data.plans.1.price_per_month', 16.5)
            ->assertJsonPath('data.plans.2.price_per_month', 14.92);
    }

    public function test_me_reports_the_entitlement_state(): void
    {
        $this->enforceMonetization();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.subscription.is_subscribed', false)
            ->assertJsonPath('data.subscription.can_download', false)
            ->assertJsonPath('data.subscription.jobs_free', true)
            ->assertJsonPath('data.subscription.quota.cv.limit', 1);
    }

    // --------------------------------------------------------------- helpers

    /**
     * @return array<string, mixed>
     */
    private function cvPayload(): array
    {
        return [
            'full_name' => 'Sara Ahmed',
            'email' => 'sara@example.com',
            'phone' => '+966500000000',
            'target_job_title' => 'Engineer',
            'language' => 'en',
            'summary_input' => 'Backend developer',
            'skills_input' => 'Laravel, PHP',
            'experience_input' => "Engineer, Acme, 2020 - 2022\n- Built and shipped internal REST APIs used by three product teams.\n- Cut p95 latency by 40 percent.",
            'education_input' => 'BSc Computer Science',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function cv(User $user, array $overrides = []): GeneratedCv
    {
        return GeneratedCv::create(array_merge([
            'user_id' => $user->id,
            'full_name' => 'Sara Ahmed',
            'email' => 'sara@example.com',
            'phone' => '+966500000000',
            'linkedin' => null,
            'location' => 'Riyadh',
            'target_job_title' => 'Engineer',
            'language' => 'en',
            'summary_input' => 'Backend developer',
            'skills_input' => 'Laravel, PHP',
            'experience_input' => "Engineer, Acme, 2020 - 2022\n- Built and shipped internal REST APIs used by three product teams.\n- Cut p95 latency by 40 percent.",
            'education_input' => 'BSc Computer Science',
            'generated_markdown' => "## Experience\nEngineer, Acme, 2020 - 2022\n- Built APIs.",
            'form_payload' => [],
            'ai_status' => AiStatus::Completed,
            'score_total' => 80,
            'grade' => 'B',
        ], $overrides));
    }

    private function analysis(User $user): CvAnalysis
    {
        return CvAnalysis::create([
            'user_id' => $user->id,
            'target_job_title' => 'Engineer',
            'input_method' => 'text',
            'resume_text' => 'Backend developer with five years of experience building APIs.',
            'raw_text' => 'Backend developer with five years of experience.',
            'score_total' => 62,
            'grade' => 'C',
            'job_match' => 55,
            'criteria' => [
                'format' => ['score' => 3, 'max' => 15, 'label' => 'Format'],
                'keywords' => ['score' => 13, 'max' => 30, 'label' => 'Keywords'],
            ],
            'strengths' => ['S1', 'S2', 'S3'],
            'weaknesses' => ['W1', 'W2'],
            'keywords_found' => ['php', 'laravel'],
            'keywords_missing' => ['docker', 'aws', 'redis', 'kafka'],
            'quick_wins' => ['Q1', 'Q2', 'Q3', 'Q4'],
            'ai_status' => AiStatus::Completed,
            'ai_feedback' => 'Full AI advice.',
        ]);
    }
}
