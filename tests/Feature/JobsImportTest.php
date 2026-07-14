<?php

namespace Tests\Feature;

use App\Models\JobNews;
use App\Services\JobsImportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class JobsImportTest extends TestCase
{
    use RefreshDatabase;

    private function csvFile(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'jobs_test_');
        file_put_contents($path, $contents);
        return new UploadedFile($path, 'jobs.csv', 'text/csv', null, true);
    }

    public function test_import_creates_rows_and_re_import_updates_them(): void
    {
        $csv = "job_id,language,title,company,location,body,url,apply_url,valid_from,valid_until,is_published\n"
            ."JOB-1,ar,مطور Flutter,شركة الأمل,الرياض,تطوير تطبيق Flutter,https://example.com/j1,https://example.com/apply/j1,2026-06-01,2026-07-30,1\n"
            ."JOB-2,en,Backend Engineer,Example Co.,Remote,Build Laravel services.,https://example.com/j2,https://example.com/apply/j2,2026-06-15,2026-08-15,1\n";

        $service = app(JobsImportService::class);
        $first = $service->importUploadedFile($this->csvFile($csv));

        $this->assertSame(2, $first['created']);
        $this->assertSame(0, $first['updated']);
        $this->assertSame(0, $first['skipped']);
        $this->assertSame([], $first['errors']);
        $this->assertSame(2, JobNews::count());

        $arabic = JobNews::where('source_row_key', 'JOB-1')->firstOrFail();
        $this->assertSame('مطور Flutter', $arabic->title);
        $this->assertSame('https://example.com/apply/j1', $arabic->apply_url);
        $this->assertSame('2026-07-30', $arabic->valid_until->toDateString());
        $this->assertSame('excel_upload', $arabic->source);

        $second = $service->importUploadedFile($this->csvFile($csv));
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['updated']);
        $this->assertSame(2, JobNews::count());
    }

    public function test_import_skips_rows_with_missing_required_fields(): void
    {
        $csv = "job_id,language,title,body\n"
            .",ar,,نص فقط\n"
            ."JOB-3,ar,Title only,\n"
            ."JOB-4,ar,Valid row,Valid body\n";

        $result = app(JobsImportService::class)->importUploadedFile($this->csvFile($csv));

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(2, $result['skipped']);
        $this->assertCount(2, $result['errors']);
        $this->assertSame(1, JobNews::count());
    }

    public function test_mobile_endpoint_hides_expired_and_future_jobs(): void
    {
        Carbon::setTestNow('2026-06-29 12:00:00');

        JobNews::create([
            'language' => 'ar',
            'title' => 'وظيفة نشطة',
            'body' => 'مفتوحة الآن',
            'valid_from' => '2026-06-01',
            'valid_until' => '2026-07-30',
            'is_published' => true,
            'source' => 'manual',
            'source_row_key' => 'A',
        ]);
        JobNews::create([
            'language' => 'ar',
            'title' => 'وظيفة منتهية',
            'body' => 'انتهى الموعد',
            'valid_from' => '2026-05-01',
            'valid_until' => '2026-06-15',
            'is_published' => true,
            'source' => 'manual',
            'source_row_key' => 'B',
        ]);
        JobNews::create([
            'language' => 'ar',
            'title' => 'وظيفة قادمة',
            'body' => 'لم تبدأ بعد',
            'valid_from' => '2026-07-15',
            'valid_until' => '2026-08-15',
            'is_published' => true,
            'source' => 'manual',
            'source_row_key' => 'C',
        ]);
        JobNews::create([
            'language' => 'ar',
            'title' => 'وظيفة بدون حدود',
            'body' => 'لا يوجد تاريخ',
            'is_published' => true,
            'source' => 'manual',
            'source_row_key' => 'D',
        ]);

        $response = $this->getJson('/api/mobile/job-news?lang=ar');
        $titles = collect($response->json('data.items'))->pluck('title')->all();

        $response->assertOk();
        $this->assertContains('وظيفة نشطة', $titles);
        $this->assertContains('وظيفة بدون حدود', $titles);
        $this->assertNotContains('وظيفة منتهية', $titles);
        $this->assertNotContains('وظيفة قادمة', $titles);
    }


    public function test_mobile_endpoint_filters_job_news_by_search_and_category(): void
    {
        Carbon::setTestNow('2026-06-29 12:00:00');

        JobNews::create([
            'language' => 'en',
            'title' => 'Senior Frontend Developer',
            'company' => 'Future Solutions Co.',
            'location' => 'Riyadh',
            'body' => 'Build Flutter and web software experiences.',
            'is_published' => true,
            'source' => 'manual',
            'source_row_key' => 'TECH-1',
        ]);
        JobNews::create([
            'language' => 'en',
            'title' => 'Finance Analyst',
            'company' => 'Capital House',
            'location' => 'Jeddah',
            'body' => 'Support accounting and investment reporting.',
            'is_published' => true,
            'source' => 'manual',
            'source_row_key' => 'FIN-1',
        ]);
        JobNews::create([
            'language' => 'en',
            'title' => 'Nurse Coordinator',
            'company' => 'Care Clinic',
            'location' => 'Dammam',
            'body' => 'Coordinate patient care and clinic operations.',
            'is_published' => true,
            'source' => 'manual',
            'source_row_key' => 'HEALTH-1',
        ]);

        $techPayload = $this->getJson('/api/mobile/job-news?lang=en&category=tech')->assertOk()->json('data');
        $this->assertSame('tech', $techPayload['selected_category']);
        $this->assertSame(['Senior Frontend Developer'], collect($techPayload['items'])->pluck('title')->all());
        $this->assertSame('tech', $techPayload['items'][0]['category']);

        $searchTitles = collect($this->getJson('/api/mobile/job-news?lang=en&q=Capital')->assertOk()->json('data.items'))
            ->pluck('title')
            ->all();
        $this->assertSame(['Finance Analyst'], $searchTitles);
    }

    public function test_mobile_endpoint_payload_includes_validity_and_apply_url(): void
    {
        Carbon::setTestNow('2026-06-29 12:00:00');

        $job = JobNews::create([
            'language' => 'ar',
            'title' => 'وظيفة بحقول كاملة',
            'body' => 'تفاصيل',
            'url' => 'https://example.com/details',
            'apply_url' => 'https://example.com/apply',
            'valid_from' => '2026-06-01',
            'valid_until' => '2026-07-30',
            'is_published' => true,
            'source' => 'manual',
            'source_row_key' => 'F',
        ]);

        $payload = $this->getJson('/api/mobile/job-news/'.$job->id.'?lang=ar')->json('data');

        $this->assertSame('https://example.com/apply', $payload['apply_url']);
        $this->assertSame('2026-06-01', $payload['valid_from']);
        $this->assertSame('2026-07-30', $payload['valid_until']);
        $this->assertNotEmpty($payload['valid_until_label']);
    }
}
