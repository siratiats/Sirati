<?php

namespace Tests\Feature;

use App\Models\JobNews;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Jobs\JobTaxonomyMatcher;
use App\Services\Jobs\SaudiJobAggregatorService;
use Database\Seeders\JobTitleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaudiJobAggregatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JobTitleSeeder::class);
    }

    public function test_taxonomy_matcher_resolves_software_engineer_and_riyadh(): void
    {
        $matcher = app(JobTaxonomyMatcher::class);

        $result = $matcher->match(
            'مطلوب مهندس برمجيات وتطبيقات فلاتر',
            'خبرة في تطوير تطبيقات الهواتف الذكية وإدارة قواعد البيانات',
            'الرياض، السعودية',
        );

        $this->assertNotNull($result['job_title_id']);
        $this->assertSame('riyadh', $result['city']);
        $this->assertFalse($result['is_remote']);

        $matchedTitle = JobTitle::find($result['job_title_id']);
        $this->assertSame('software-engineer', $matchedTitle->slug);
    }

    public function test_taxonomy_matcher_detects_remote_jobs(): void
    {
        $matcher = app(JobTaxonomyMatcher::class);

        $result = $matcher->match(
            'محلل بيانات أول - عن بعد',
            'العمل عن بعد بدوام كامل',
            'عن بعد (Remote)',
        );

        $this->assertNotNull($result['job_title_id']);
        $this->assertTrue($result['is_remote']);

        $matchedTitle = JobTitle::find($result['job_title_id']);
        $this->assertSame('data-analyst', $matchedTitle->slug);
    }

    public function test_aggregator_parses_telegram_feed_and_links_taxonomy(): void
    {
        $fakeHtml = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<div class="tgme_widget_message_wrap js-widget_message_wrap">
  <div class="tgme_widget_message js-widget_message" data-post="jobmag/5501">
    <div class="tgme_widget_message_text js-message_text">
      شركة تقنية رائدة تعلن عن شاغر بمسمى مطور برمجيات ولارفيل للعمل في مدينة الرياض.<br/>
      المتطلبات: خبرة في تطوير واجهات البرمجة والأنظمة الخلفية.<br/>
      رابط التقديم: <a href="https://example.com/careers/apply">https://example.com/careers/apply</a>
    </div>
    <time datetime="2026-09-03T12:00:00+03:00"></time>
  </div>
</div>
</body>
</html>
HTML;

        Http::fake([
            'https://t.me/s/jobmag*' => Http::response($fakeHtml, 200),
            'https://t.me/s/*' => Http::response('', 200),
            'https://remotive.com/*' => Http::response(['jobs' => []], 200),
            '*' => Http::response('', 200),
        ]);

        $aggregator = app(SaudiJobAggregatorService::class);
        $result = $aggregator->aggregateFeed([
            'source' => 'telegram_jobmag',
            'name' => 'مجلة وظائف السعودية (@jobmag)',
            'channel' => 'jobmag',
            'type' => 'telegram',
            'language' => 'ar',
        ]);

        $this->assertSame(1, $result['fetched']);
        $this->assertSame(1, $result['created']);

        $job = JobNews::where('external_id', '5501')->first();
        $this->assertNotNull($job);
        $this->assertSame('riyadh', $job->city);
        $this->assertSame('https://example.com/careers/apply', $job->apply_url);
        $this->assertNotNull($job->job_title_id);
    }

    public function test_aggregator_parses_remotive_api_and_sets_remote(): void
    {
        $fakeJson = [
            'jobs' => [
                [
                    'id' => 9988,
                    'title' => 'Senior Full Stack Software Engineer',
                    'company_name' => 'Global Cloud Inc',
                    'url' => 'https://remotive.com/job/9988',
                    'description' => 'We are hiring a remote software engineer with Laravel and Flutter experience.',
                    'publication_date' => '2026-09-02T10:00:00',
                ],
            ],
        ];

        Http::fake([
            'https://remotive.com/*' => Http::response($fakeJson, 200),
            '*' => Http::response('', 200),
        ]);

        $aggregator = app(SaudiJobAggregatorService::class);
        $result = $aggregator->aggregateFeed([
            'source' => 'remotive_remote',
            'name' => 'Remotive Remote',
            'url' => 'https://remotive.com/api/remote-jobs?limit=25',
            'type' => 'remotive_api',
            'language' => 'en',
        ]);

        $this->assertSame(1, $result['fetched']);
        $this->assertSame(1, $result['created']);

        $job = JobNews::where('external_id', '9988')->first();
        $this->assertNotNull($job);
        $this->assertTrue($job->is_remote);
        $this->assertSame('Global Cloud Inc', $job->company);
    }

    public function test_aggregator_parses_rss_feed_and_links_taxonomy(): void
    {
        $fakeRss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Saudi Job Board</title>
        <link>https://wazaif.net</link>
        <item>
            <title>مطور ويب ولارفيل - شركة تقنية رائدة</title>
            <link>https://wazaif.net/job/101</link>
            <description>مطلوب مبرمج Laravel ومطور ويب للعمل في الرياض</description>
            <pubDate>Mon, 25 Aug 2026 09:00:00 +0300</pubDate>
            <guid>job-101-laravel</guid>
        </item>
    </channel>
</rss>
XML;

        Http::fake([
            '*wazaif.net*' => Http::response($fakeRss, 200),
            '*' => Http::response('<rss><channel></channel></rss>', 200),
        ]);

        $aggregator = app(SaudiJobAggregatorService::class);
        $result = $aggregator->aggregateRssFeed([
            'source' => 'wazaif_sa',
            'name' => 'Wazaif Saudi Arabia',
            'url' => 'https://wazaif.net/rss.xml',
            'type' => 'rss',
            'language' => 'ar',
        ]);

        $this->assertSame(1, $result['fetched']);
        $this->assertSame(1, $result['created']);

        $job = JobNews::where('external_id', 'job-101-laravel')->first();
        $this->assertNotNull($job);
        $this->assertSame('riyadh', $job->city);
        $this->assertNotNull($job->job_title_id);
    }

    public function test_mobile_job_news_api_filters_by_taxonomy_and_city(): void
    {
        $devTitle = JobTitle::where('slug', 'software-engineer')->firstOrFail();
        $accTitle = JobTitle::where('slug', 'accountant')->firstOrFail();

        JobNews::create([
            'language' => 'ar',
            'title' => 'مطور برمجيات في الرياض',
            'company' => 'شركة أ',
            'location' => 'الرياض',
            'city' => 'riyadh',
            'job_title_id' => $devTitle->id,
            'body' => 'تفاصيل الوظيفة',
            'is_published' => true,
            'published_at' => now(),
        ]);

        JobNews::create([
            'language' => 'ar',
            'title' => 'محاسب مالي في جدة',
            'company' => 'شركة ب',
            'location' => 'جدة',
            'city' => 'jeddah',
            'job_title_id' => $accTitle->id,
            'body' => 'تفاصيل المحاسب',
            'is_published' => true,
            'published_at' => now(),
        ]);

        // Filter by dev job title
        $response = $this->getJson("/api/mobile/job-news?job_title_id={$devTitle->id}");
        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame('مطور برمجيات في الرياض', $items[0]['title']);

        // Filter by city جدة
        $cityResponse = $this->getJson('/api/mobile/job-news?city=jeddah');
        $cityResponse->assertOk();
        $cityItems = $cityResponse->json('data.items');
        $this->assertCount(1, $cityItems);
        $this->assertSame('محاسب مالي في جدة', $cityItems[0]['title']);
    }

    public function test_matched_preset_returns_tiered_results_exact_first_then_category(): void
    {
        $devTitle = JobTitle::where('slug', 'software-engineer')->firstOrFail();
        $devTitle->update(['category' => 'tech']);

        $otherTechTitle = JobTitle::where('slug', 'devops-engineer')->first();
        if (! $otherTechTitle) {
            $otherTechTitle = JobTitle::create([
                'slug' => 'devops-engineer',
                'name_ar' => 'مهندس ديف أوبس',
                'name_en' => 'DevOps Engineer',
                'category' => 'tech',
                'is_active' => true,
            ]);
        } else {
            $otherTechTitle->update(['category' => 'tech']);
        }

        $accTitle = JobTitle::where('slug', 'accountant')->firstOrFail();
        $accTitle->update(['category' => 'finance']);

        $user = User::factory()->create([
            'job_title_id' => $devTitle->id,
            'location' => 'riyadh',
        ]);

        // Exact match
        $exactJob = JobNews::create([
            'language' => 'ar',
            'title' => 'مطور برمجيات متقدم',
            'company' => 'شركة تقنية كبرى',
            'location' => 'الرياض',
            'city' => 'riyadh',
            'job_title_id' => $devTitle->id,
            'category' => 'tech',
            'body' => 'تفاصيل الوظيفة',
            'is_published' => true,
            'published_at' => now(),
        ]);

        // Category match (tech)
        $categoryJob = JobNews::create([
            'language' => 'ar',
            'title' => 'مهندس ديف أوبس وسحاب',
            'company' => 'شركة حلول سحابية',
            'location' => 'الرياض',
            'city' => 'riyadh',
            'job_title_id' => $otherTechTitle->id,
            'category' => 'tech',
            'body' => 'إدارة البنية السحابية',
            'is_published' => true,
            'published_at' => now(),
        ]);

        // Remote match (tech)
        $remoteJob = JobNews::create([
            'language' => 'ar',
            'title' => 'وظيفة عن بعد للمطورين',
            'company' => 'شركة عالمية',
            'location' => 'عن بعد',
            'city' => null,
            'is_remote' => true,
            'category' => 'tech',
            'body' => 'عمل عن بعد',
            'is_published' => true,
            'published_at' => now(),
        ]);

        // Unrelated job (finance)
        $financeJob = JobNews::create([
            'language' => 'ar',
            'title' => 'محاسب عام وتدقيق',
            'company' => 'بنك تجاري',
            'location' => 'جدة',
            'city' => 'jeddah',
            'job_title_id' => $accTitle->id,
            'category' => 'finance',
            'body' => 'تفاصيل المحاسبة',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/mobile/job-news?preset=matched');
        $response->assertOk();

        $items = $response->json('data.items');

        // All 3 matching/fallback jobs returned
        $returnedIds = collect($items)->pluck('id')->all();
        $this->assertContains($exactJob->id, $returnedIds);
        $this->assertContains($categoryJob->id, $returnedIds);
        $this->assertContains($remoteJob->id, $returnedIds);
        $this->assertNotContains($financeJob->id, $returnedIds);

        // Exact match should be ranked first with highest match score
        $this->assertSame($exactJob->id, $items[0]['id']);
        $this->assertGreaterThanOrEqual($items[1]['match_score'], $items[0]['match_score']);
    }

    public function test_feed_health_tracking_records_success_and_warnings(): void
    {
        $aggregator = app(SaudiJobAggregatorService::class);

        $aggregator->recordFeedSuccess('telegram_jobmag', 12);
        $aggregator->recordFeedWarning('telegram_ewdifh', '0 posts parsed');

        $health = collect($aggregator->getFeedsHealth())->keyBy('source');

        $this->assertTrue($health['telegram_jobmag']['healthy']);
        $this->assertSame(12, $health['telegram_jobmag']['last_fetched_count']);
        $this->assertNotNull($health['telegram_jobmag']['last_success_at']);

        $this->assertSame('0 posts parsed', $health['telegram_ewdifh']['last_issue']);
        $this->assertSame(1, $health['telegram_ewdifh']['consecutive_zero_fetches']);
    }

    public function test_notification_respects_global_toggle_and_anti_spam(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $devTitle = JobTitle::where('slug', 'software-engineer')->firstOrFail();

        $user = User::factory()->create([
            'job_title_id' => $devTitle->id,
            'email_verified_at' => now(),
        ]);

        $user->fcmTokens()->create([
            'token' => 'mock_fcm_token_123',
            'device_type' => 'ios',
            'is_active' => true,
        ]);

        $job1 = JobNews::create([
            'language' => 'ar',
            'title' => 'مطور برمجيات 1',
            'company' => 'شركة أ',
            'body' => 'تفاصيل الوظيفة الأولى',
            'job_title_id' => $devTitle->id,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $job2 = JobNews::create([
            'language' => 'ar',
            'title' => 'مطور برمجيات 2',
            'company' => 'شركة ب',
            'body' => 'تفاصيل الوظيفة الثانية',
            'job_title_id' => $devTitle->id,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $aggregator = app(SaudiJobAggregatorService::class);

        // Case 1: When smart notifications is disabled, nothing should be dispatched
        config(['smart_notifications.enabled' => false]);
        $aggregator->notifyInterestedCandidates($job1);
        \Illuminate\Support\Facades\Queue::assertNothingPushed();

        // Case 2: When enabled, user receives first job alert
        config(['smart_notifications.enabled' => true]);

        // Ensure user preference allows notifications and timezone is not in quiet hours
        $pref = app(\App\Services\Notifications\NotificationPreferenceService::class)->forUser($user);
        $pref->update([
            'enabled' => true,
            'quiet_hours_start' => '01:00',
            'quiet_hours_end' => '02:00', // outside current test time
            'timezone_offset_minutes' => 0,
        ]);

        $aggregator->notifyInterestedCandidates($job1);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendPushNotificationJob::class, 1);

        // Case 3: Same aggregation batch - second job does NOT spam the user again!
        $aggregator->notifyInterestedCandidates($job2);
        // Count should still be 1!
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendPushNotificationJob::class, 1);
    }

    public function test_an_existing_decision_for_the_same_user_and_job_is_never_resent(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        config(['smart_notifications.enabled' => true]);

        $devTitle = JobTitle::where('slug', 'software-engineer')->firstOrFail();
        $user = User::factory()->create([
            'job_title_id' => $devTitle->id,
            'email_verified_at' => now(),
        ]);
        $user->fcmTokens()->create(['token' => 'tok-'.$user->id, 'is_active' => true]);
        app(\App\Services\Notifications\NotificationPreferenceService::class)->forUser($user)->update([
            'enabled' => true,
            'quiet_hours_start' => '01:00',
            'quiet_hours_end' => '02:00',
            'timezone_offset_minutes' => 0,
        ]);

        // Inactive statuses pass the gap/cap checks, so only idempotency can stop a resend.
        foreach (['failed', 'skipped'] as $status) {
            $job = JobNews::create([
                'language' => 'ar',
                'title' => 'مطور برمجيات '.$status,
                'company' => 'شركة',
                'body' => 'تفاصيل',
                'job_title_id' => $devTitle->id,
                'is_published' => true,
                'published_at' => now(),
            ]);
            \App\Models\NotificationDecision::query()->create([
                'user_id' => $user->id,
                'rule_key' => 'matching_job',
                'template_key' => 'matching_job',
                'context' => [],
                'idempotency_key' => "realtime_job:{$user->id}:{$job->id}",
                'scheduled_for' => now(),
                'status' => $status,
            ]);

            // A fresh service instance has no in-memory batch guard, like a second worker.
            app()->forgetInstance(SaudiJobAggregatorService::class);
            app(SaudiJobAggregatorService::class)->notifyInterestedCandidates($job);

            $this->assertSame(1, \App\Models\NotificationDecision::query()
                ->where('idempotency_key', "realtime_job:{$user->id}:{$job->id}")
                ->count());
        }

        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SendPushNotificationJob::class);

        // Control: without a prior decision the same user is notified.
        $fresh = JobNews::create([
            'language' => 'ar',
            'title' => 'مطور برمجيات جديد',
            'company' => 'شركة',
            'body' => 'تفاصيل',
            'job_title_id' => $devTitle->id,
            'is_published' => true,
            'published_at' => now(),
        ]);
        app()->forgetInstance(SaudiJobAggregatorService::class);
        app(SaudiJobAggregatorService::class)->notifyInterestedCandidates($fresh);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendPushNotificationJob::class, 1);
    }
}
