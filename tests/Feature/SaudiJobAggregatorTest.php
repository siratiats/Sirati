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

    public function test_aggregator_parses_feed_and_links_taxonomy(): void
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
            '*tanqeeb.com*' => Http::response('<rss><channel></channel></rss>', 200),
            '*' => Http::response('<rss><channel></channel></rss>', 200),
        ]);

        $aggregator = app(SaudiJobAggregatorService::class);
        $result = $aggregator->aggregateAll();
        $this->assertEmpty($result['errors'], json_encode($result['errors']));
        $this->assertGreaterThanOrEqual(1, $result['created']);

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
