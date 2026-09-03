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
}
