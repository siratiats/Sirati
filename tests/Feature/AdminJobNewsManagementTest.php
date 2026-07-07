<?php

namespace Tests\Feature;

use App\Models\JobNews;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminJobNewsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_job_news_dashboard(): void
    {
        $admin = User::factory()->create();

        JobNews::create([
            'language' => 'ar',
            'title' => 'Ù…Ø·ÙˆØ± Flutter',
            'company' => 'Ø´Ø±ÙƒØ© Ø§Ù„Ø£Ù…Ù„',
            'body' => 'ØªÙØ§ØµÙŠÙ„ Ø§Ù„ÙˆØ¸ÙŠÙØ©',
            'is_published' => true,
            'source' => 'google_sheet',
            'source_row_key' => 'JOB-1',
        ]);
        JobNews::create([
            'language' => 'en',
            'title' => 'Backend Engineer',
            'company' => 'Example Co',
            'body' => 'Build APIs',
            'is_published' => true,
            'source' => 'manual',
            'source_row_key' => 'JOB-2',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.jobs.index', [
            'job_q' => 'Flutter',
            'job_source' => 'google_sheet',
        ]));

        $response->assertOk();
        $response->assertSee('Ù…Ø·ÙˆØ± Flutter');
        $response->assertDontSee('Backend Engineer');
        $response->assertSee('Job management');
    }

    public function test_admin_can_update_job_news_from_edit_drawer(): void
    {
        $admin = User::factory()->create();
        $job = JobNews::create([
            'language' => 'ar',
            'title' => 'Ù‚Ø¯ÙŠÙ…',
            'body' => 'ØªÙØ§ØµÙŠÙ„ Ù‚Ø¯ÙŠÙ…Ø©',
            'is_published' => false,
            'source' => 'manual',
            'source_row_key' => 'JOB-3',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.job-news.update', $job), [
            'language' => 'ar',
            'title' => 'Ø¹Ù†ÙˆØ§Ù† Ù…Ø­Ø¯Ø«',
            'company' => 'Ø´Ø±ÙƒØ© Ø¬Ø¯ÙŠØ¯Ø©',
            'location' => 'Ø§Ù„Ø±ÙŠØ§Ø¶',
            'body' => 'ØªÙØ§ØµÙŠÙ„ Ù…Ø­Ø¯Ø«Ø©',
            'url' => 'https://example.com/job',
            'apply_url' => 'https://example.com/apply',
            'valid_from' => '2026-06-29',
            'valid_until' => '2026-07-29',
            'sort_order' => 5,
            'is_published' => '1',
        ]);

        $response->assertRedirect();
        $job->refresh();
        $this->assertSame('Ø¹Ù†ÙˆØ§Ù† Ù…Ø­Ø¯Ø«', $job->title);
        $this->assertSame('Ø´Ø±ÙƒØ© Ø¬Ø¯ÙŠØ¯Ø©', $job->company);
        $this->assertTrue($job->is_published);
        $this->assertSame('2026-07-29', $job->valid_until->toDateString());
    }

    public function test_admin_can_bulk_unpublish_job_news(): void
    {
        $admin = User::factory()->create();
        $first = JobNews::create([
            'language' => 'ar',
            'title' => 'Ø§Ù„Ø£ÙˆÙ„Ù‰',
            'body' => 'ØªÙØ§ØµÙŠÙ„',
            'is_published' => true,
            'source' => 'manual',
            'source_row_key' => 'JOB-4',
        ]);
        $second = JobNews::create([
            'language' => 'ar',
            'title' => 'Ø§Ù„Ø«Ø§Ù†ÙŠØ©',
            'body' => 'ØªÙØ§ØµÙŠÙ„',
            'is_published' => true,
            'source' => 'manual',
            'source_row_key' => 'JOB-5',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.job-news.bulk'), [
            'ids' => [$first->id, $second->id],
            'action' => 'unpublish',
        ]);

        $response->assertRedirect();
        $this->assertFalse($first->fresh()->is_published);
        $this->assertFalse($second->fresh()->is_published);
    }
    public function test_admin_can_create_job_news_with_json_response(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.job-news.store'), [
            'language' => 'en',
            'title' => 'Frontend Engineer',
            'company' => 'Sirati Labs',
            'body' => 'Build responsive dashboard workflows for the admin team.',
            'is_published' => '1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Job saved.')
            ->assertJsonStructure(['item', 'row_html', 'dialog_html', 'stats']);

        $this->assertDatabaseHas('job_news', ['title' => 'Frontend Engineer']);
    }

    public function test_admin_job_news_json_validation_errors_are_returned(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->postJson(route('admin.job-news.store'), [
            'language' => 'en',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'body']);
    }
}

