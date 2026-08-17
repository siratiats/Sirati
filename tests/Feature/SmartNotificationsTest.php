<?php

namespace Tests\Feature;

use App\Jobs\SendPlannedNotificationJob;
use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Models\JobNews;
use App\Models\JobTitle;
use App\Models\NotificationDecision;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Services\AtsScoringService;
use App\Services\Notifications\DailyNotificationCandidateService;
use App\Services\Notifications\DailyNotificationPlanner;
use App\Services\Notifications\NotificationPolicyService;
use App\Services\Notifications\NotificationPreferenceService;
use App\Support\DailyNotificationCandidate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmartNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'smart_notifications.enabled' => true,
            'smart_notifications.recent_activity_hours' => 3,
            'smart_notifications.minimum_gap_hours' => 24,
            'smart_notifications.delivery_window_minutes' => 120,
            'smart_notifications.default_preferred_time' => '12:00',
            'smart_notifications.default_quiet_hours_start' => '21:00',
            'smart_notifications.default_quiet_hours_end' => '09:00',
            'smart_notifications.job_match_min_score' => 2,
        ]);
    }

    public function test_candidate_prefers_first_analysis_for_new_users(): void
    {
        $user = $this->user();
        $candidate = app(DailyNotificationCandidateService::class)->forUser($user);

        $this->assertNotNull($candidate);
        $this->assertSame('first_analysis', $candidate->ruleKey);
        $this->assertSame('cv-analysis', $candidate->actionUrl);
    }

    public function test_candidate_prefers_low_ats_score(): void
    {
        $user = $this->user();
        $this->analysis($user, score: 55, tip: 'Add measurable results');

        $candidate = app(DailyNotificationCandidateService::class)->forUser($user);

        $this->assertNotNull($candidate);
        $this->assertSame('low_ats_score', $candidate->ruleKey);
        $this->assertSame(55, $candidate->context['score']);
    }

    public function test_policy_blocks_opted_out_and_quiet_hours(): void
    {
        $user = $this->userWithToken();
        $prefs = app(NotificationPreferenceService::class)->forUser($user);
        $prefs->forceFill([
            'enabled' => false,
            'preferred_time' => '12:00',
            'timezone_offset_minutes' => 0,
            'quiet_hours_start' => '21:00',
            'quiet_hours_end' => '09:00',
            'last_active_at' => now()->subDays(2),
        ])->save();

        $candidate = new DailyNotificationCandidate(
            ruleKey: 'first_analysis',
            templateKey: 'first_analysis',
            type: 'tip',
            actionType: 'screen',
            actionUrl: 'cv-analysis',
        );

        $policy = app(NotificationPolicyService::class);
        $result = $policy->evaluate($user, $prefs->refresh(), $candidate, collect(), Carbon::parse('2026-07-17 12:00:00', 'UTC'));
        $this->assertFalse($result['allowed']);
        $this->assertSame('opted_out', $result['reason']);

        $prefs->forceFill(['enabled' => true])->save();
        $quiet = $policy->evaluate(
            $user,
            $prefs->refresh(),
            $candidate,
            collect(),
            Carbon::parse('2026-07-17 22:00:00', 'UTC'),
        );
        $this->assertFalse($quiet['allowed']);
        $this->assertSame('quiet_hours', $quiet['reason']);
    }

    public function test_planner_queues_one_decision_idempotently(): void
    {
        Bus::fake([SendPlannedNotificationJob::class]);

        $user = $this->userWithToken();

        // Freeze time before writing last_active_at so "recent activity" is relative
        // to the delivery window under test, not wall-clock now.
        Carbon::setTestNow(Carbon::parse('2026-07-17 12:05:00', 'UTC'));

        $prefs = app(NotificationPreferenceService::class)->forUser($user);
        $prefs->forceFill([
            'enabled' => true,
            'preferred_time' => '12:00',
            'timezone_offset_minutes' => 0,
            'quiet_hours_start' => '21:00',
            'quiet_hours_end' => '09:00',
            'last_active_at' => now()->subDays(2),
        ])->save();

        $planner = app(DailyNotificationPlanner::class);
        $this->assertSame('planned', $planner->planForUser($user));
        $this->assertSame('skipped', $planner->planForUser($user));

        $this->assertSame(1, NotificationDecision::query()->count());
        Bus::assertDispatched(SendPlannedNotificationJob::class, 1);

        Carbon::setTestNow();
    }

    public function test_preferences_api_and_activity_endpoints(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.enabled', true);

        $this->putJson('/api/mobile/notification-preferences', [
            'enabled' => false,
            'language' => 'en',
            'timezone_offset_minutes' => 180,
            'preferred_time' => '19:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.language', 'en')
            ->assertJsonPath('data.preferred_time', '19:00');

        $this->postJson('/api/mobile/activity', [
            'language' => 'ar',
            'timezone_offset_minutes' => 120,
            'event' => 'app_open',
        ])->assertOk();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'language' => 'ar',
            'timezone_offset_minutes' => 120,
        ]);
        $this->assertNotNull(
            NotificationPreference::query()->where('user_id', $user->id)->value('last_active_at')
        );
    }

    public function test_fcm_register_updates_preferences_metadata(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->postJson('/api/fcm-tokens', [
            'token' => 'token-abc',
            'device_id' => 'device-1',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'language' => 'en',
            'timezone_offset_minutes' => 60,
            'notifications_enabled' => true,
        ])->assertOk();

        $this->assertDatabaseHas('user_fcm_tokens', [
            'user_id' => $user->id,
            'token' => 'token-abc',
            'token_hash' => hash('sha256', 'token-abc'),
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'language' => 'en',
            'timezone_offset_minutes' => 60,
            'enabled' => true,
        ]);
    }

    public function test_opt_out_unregister_sets_preference_disabled(): void
    {
        $user = $this->userWithToken(token: 'token-xyz');
        Sanctum::actingAs($user);
        app(NotificationPreferenceService::class)->update($user, ['enabled' => true]);

        $this->deleteJson('/api/fcm-tokens', [
            'token' => 'token-xyz',
            'opt_out' => true,
        ])->assertOk();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'enabled' => false,
        ]);
        $this->assertDatabaseMissing('user_fcm_tokens', [
            'token' => 'token-xyz',
        ]);
    }

    public function test_user_with_job_title_but_zero_analyses_gets_matching_job(): void
    {
        $title = $this->softwareJobTitle();
        $job = $this->publishedJob(
            title: 'Senior Laravel Backend Developer',
            body: 'Build PHP APIs with Laravel, SQL, and agile delivery in Riyadh.',
        );

        $user = $this->user(['job_title_id' => $title->id]);

        $candidate = app(DailyNotificationCandidateService::class)->forUser($user);

        $this->assertNotNull($candidate);
        $this->assertSame('matching_job', $candidate->ruleKey);
        $this->assertSame('job-news/'.$job->id, $candidate->actionUrl);
        $this->assertGreaterThanOrEqual(2, $candidate->context['match_score']);
    }

    public function test_analysis_derived_titles_take_precedence_over_declared_job_title(): void
    {
        $marketingTitle = JobTitle::query()->create([
            'slug' => 'digital-marketer',
            'name_ar' => 'مسوق رقمي',
            'name_en' => 'Digital Marketer',
            'category' => 'marketing',
            'keywords' => ['marketing', 'seo', 'campaign', 'brand', 'social media'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $analysisJob = $this->publishedJob(
            title: 'Laravel Backend Engineer',
            body: 'PHP Laravel API backend SQL agile software developer role.',
        );
        $this->publishedJob(
            title: 'Performance Marketing Lead',
            body: 'SEO SEM campaign brand social media marketing specialist.',
        );

        $user = $this->user(['job_title_id' => $marketingTitle->id]);
        // High score + generated CV so we do not stop at low_ats / analysis_to_cv.
        $this->analysis($user, score: 85, targetJobTitle: 'Laravel Backend Developer');
        $this->generatedCv($user, targetJobTitle: 'Laravel Backend Developer');

        $candidate = app(DailyNotificationCandidateService::class)->forUser($user);

        $this->assertNotNull($candidate);
        $this->assertSame('matching_job', $candidate->ruleKey);
        $this->assertSame('job-news/'.$analysisJob->id, $candidate->actionUrl);
    }

    public function test_user_with_neither_job_title_nor_cv_titles_falls_through_to_daily_tip(): void
    {
        $user = $this->user();
        // Pass higher rules without producing interest keywords.
        $this->analysis($user, score: 85, targetJobTitle: '');
        $this->generatedCv($user, targetJobTitle: '');

        $candidate = app(DailyNotificationCandidateService::class)->forUser($user);

        $this->assertNotNull($candidate);
        $this->assertSame('daily_tip', $candidate->ruleKey);
    }

    public function test_below_threshold_job_match_returns_null_and_falls_through(): void
    {
        config(['smart_notifications.job_match_min_score' => 3]);

        $title = JobTitle::query()->create([
            'slug' => 'niche-role',
            'name_ar' => 'دور متخصص',
            'name_en' => 'Niche Role',
            'category' => 'software',
            'keywords' => ['obscurestack', 'raretool', 'uniqueframework'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Only one keyword appears — score 1 < threshold 3.
        $this->publishedJob(
            title: 'General opening',
            body: 'Looking for someone familiar with obscurestack tools only.',
        );

        $user = $this->user(['job_title_id' => $title->id]);
        $this->analysis($user, score: 85, targetJobTitle: '');
        $this->generatedCv($user, targetJobTitle: '');

        $candidate = app(DailyNotificationCandidateService::class)->forUser($user);

        $this->assertNotNull($candidate);
        $this->assertNotSame('matching_job', $candidate->ruleKey);
        $this->assertSame('daily_tip', $candidate->ruleKey);
    }

    public function test_admin_recipient_count_respects_job_title_filter(): void
    {
        config(['services.admin.emails' => ['admin@sirati.test']]);
        $admin = User::factory()->create(['email' => 'admin@sirati.test']);

        $software = $this->softwareJobTitle();
        $marketing = JobTitle::query()->create([
            'slug' => 'marketer',
            'name_ar' => 'مسوق',
            'name_en' => 'Marketer',
            'category' => 'marketing',
            'keywords' => ['marketing', 'seo'],
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $softwareUser = $this->userWithToken();
        $softwareUser->forceFill(['job_title_id' => $software->id])->save();

        $marketingUser = $this->userWithToken();
        $marketingUser->forceFill(['job_title_id' => $marketing->id])->save();

        $this->userWithToken(); // no job title

        $this->actingAs($admin)
            ->postJson(route('admin.notifications.count'), [
                'audience' => 'all',
                'job_title_ids' => [$software->id],
            ])
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->actingAs($admin)
            ->postJson(route('admin.notifications.count'), [
                'audience' => 'all',
                'job_title_category' => 'marketing',
            ])
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->actingAs($admin)
            ->postJson(route('admin.notifications.count'), [
                'audience' => 'all',
            ])
            ->assertOk()
            ->assertJsonPath('count', 3);
    }

    public function test_ats_scoring_category_hint_is_optional_and_authoritative(): void
    {
        $resume = <<<'CV'
Salem Sayer
Laravel Backend Developer
salem@example.com | +966591890300 | linkedin.com/in/salem

Summary
Backend developer with 5+ years building Laravel API platforms, SQL dashboards, and agile integrations.

Skills
PHP, Laravel, API, SQL, Git, Agile, Scrum, Backend, JavaScript

Experience
Backend Developer, Sirati, 2021 - 2025
- Developed Laravel APIs used by 25 internal users.
- Improved reporting speed by 35%.
- Built SQL dashboards and reduced support tickets by 20%.

Education
Bachelor of Computer Science, 2020

Certifications
AWS Certified Cloud Practitioner
CV;

        $scorer = new AtsScoringService;
        $inferred = $scorer->score($resume, 'Laravel Backend Developer');
        $this->assertSame('software', $inferred['category']);

        // Without hint, scores stay identical across calls (no behaviour change).
        $again = $scorer->score($resume, 'Laravel Backend Developer');
        $this->assertSame($inferred['total'], $again['total']);
        $this->assertSame($inferred['criteria'], $again['criteria']);

        // Explicit category hint wins over free-text inference.
        $hinted = $scorer->score($resume, 'Laravel Backend Developer', 'marketing');
        $this->assertSame('marketing', $hinted['category']);
    }

    public function test_lookup_sets_are_queried_once_per_run_not_once_per_user(): void
    {
        JobTitle::query()->create([
            'slug' => 'backend-developer',
            'name_ar' => 'مطور خلفية',
            'name_en' => 'Backend Developer',
            'category' => 'software',
            'keywords' => ['laravel', 'php', 'api', 'sql'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->publishedJob(
            title: 'Laravel Backend Engineer',
            body: 'PHP Laravel API backend SQL role.',
        );

        $users = collect(range(1, 5))->map(fn (): User => $this->user());

        $service = app(DailyNotificationCandidateService::class);
        $service->flushLookupCaches();

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'job_news')
                || str_contains($query->sql, 'education_contents')
                || str_contains($query->sql, 'job_titles')) {
                $queries++;
            }
        });

        foreach ($users as $user) {
            $service->forUser($user);
        }

        // Regression guard: these three lookup sets are identical for every user
        // on a given day. Before memoization this scaled as O(users) — five
        // users produced up to fifteen queries pulling full body text each time.
        $this->assertLessThanOrEqual(
            3,
            $queries,
            "Lookup sets must be memoized per run; got {$queries} queries for 5 users.",
        );
    }

    public function test_flush_lookup_caches_picks_up_newly_published_jobs(): void
    {
        JobTitle::query()->create([
            'slug' => 'backend-developer',
            'name_ar' => 'مطور خلفية',
            'name_en' => 'Backend Developer',
            'category' => 'software',
            'keywords' => ['laravel', 'php', 'api', 'sql'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $user = $this->user(['job_title_id' => JobTitle::query()->value('id')]);
        $service = app(DailyNotificationCandidateService::class);

        // Warms the memo while no job is published yet.
        $this->assertNotSame('matching_job', $service->forUser($user)?->ruleKey);

        $this->publishedJob(
            title: 'Laravel Backend Engineer',
            body: 'PHP Laravel API backend SQL role.',
        );

        // Memo still holds the pre-publish (empty) set.
        $this->assertNotSame('matching_job', $service->forUser($user)?->ruleKey);

        $service->flushLookupCaches();

        $candidate = $service->forUser($user);
        $this->assertNotNull($candidate);
        $this->assertSame('matching_job', $candidate->ruleKey);
    }

    private function user(array $overrides = []): User
    {
        $verifiedAt = $overrides['email_verified_at'] ?? now();
        unset($overrides['email_verified_at']);

        $user = User::create(array_merge([
            'name' => 'Test User',
            'email' => 'smart-'.uniqid().'@example.com',
            'password' => Hash::make('password123'),
        ], $overrides));

        $user->forceFill(['email_verified_at' => $verifiedAt])->save();

        return $user->fresh();
    }

    private function userWithToken(?string $token = null): User
    {
        $user = $this->user();
        UserFcmToken::query()->create([
            'user_id' => $user->id,
            'token' => $token ?? ('fcm-'.uniqid()),
            'platform' => 'android',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        return $user;
    }

    private function analysis(
        User $user,
        int $score = 55,
        ?string $tip = null,
        string $targetJobTitle = 'Backend Developer',
    ): CvAnalysis {
        return CvAnalysis::query()->create([
            'user_id' => $user->id,
            'target_job_title' => $targetJobTitle,
            'input_method' => 'paste',
            'resume_text' => 'Sample resume',
            'score_total' => $score,
            'grade' => 'C',
            'job_match' => 50,
            'criteria' => [],
            'strengths' => [],
            'weaknesses' => [],
            'keywords_found' => [],
            'keywords_missing' => [],
            'quick_wins' => $tip ? [$tip] : [],
        ]);
    }

    private function generatedCv(User $user, string $targetJobTitle = 'Backend Developer'): GeneratedCv
    {
        return GeneratedCv::query()->create([
            'user_id' => $user->id,
            'full_name' => $user->name,
            'target_job_title' => $targetJobTitle,
            'language' => 'en',
            'skills_input' => 'Laravel, PHP',
            'experience_input' => str_repeat('Built Laravel APIs with measurable outcomes. ', 5),
            'education_input' => 'BSc Computer Science',
            'generated_markdown' => '# CV',
            'form_payload' => [],
            'ai_status' => 'not_configured',
            'score_total' => 80,
            'grade' => 'B',
            'criteria' => [],
        ]);
    }

    private function softwareJobTitle(): JobTitle
    {
        return JobTitle::query()->create([
            'slug' => 'laravel-developer',
            'name_ar' => 'مطور Laravel',
            'name_en' => 'Laravel Developer',
            'category' => 'software',
            'keywords' => ['laravel', 'php', 'backend', 'api', 'sql', 'agile'],
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function publishedJob(string $title, string $body): JobNews
    {
        return JobNews::query()->create([
            'language' => 'en',
            'title' => $title,
            'company' => 'Sirati Labs',
            'body' => $body,
            'is_published' => true,
            'published_at' => now(),
            'source' => 'manual',
        ]);
    }
}
