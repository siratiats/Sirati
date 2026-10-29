<?php

namespace Tests\Feature;

use App\Models\JobNews;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\JobNewsSeeder;
use Database\Seeders\JobTitleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalizedJobNewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JobTitleSeeder::class);
        $this->seed(JobNewsSeeder::class);
    }

    public function test_public_job_news_returns_verified_metadata(): void
    {
        $response = $this->getJson('/api/mobile/job-news');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertNotEmpty($items);

        $first = $items[0];
        $this->assertArrayHasKey('is_verified', $first);
        $this->assertArrayHasKey('source_type', $first);
        $this->assertArrayHasKey('source_badge', $first);
        $this->assertArrayHasKey('salary_range', $first);
        $this->assertArrayHasKey('skills_required', $first);
        $this->assertTrue($first['is_verified']);
    }

    public function test_filter_by_preset_gov_returns_only_government_jobs(): void
    {
        $response = $this->getJson('/api/mobile/job-news?preset=gov');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertSame('gov', $item['source_type']);
        }
    }

    public function test_filter_by_preset_tamheer_returns_only_tamheer_jobs(): void
    {
        $response = $this->getJson('/api/mobile/job-news?preset=tamheer');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertTrue(
                $item['source_type'] === 'tamheer' || $item['employment_type'] === 'tamheer'
            );
        }
    }

    public function test_matched_preset_never_exposes_a_score_and_only_returns_the_users_field(): void
    {
        $devTitle = JobTitle::where('slug', 'software-engineer')->firstOrFail();
        $user = User::factory()->create([
            'job_title_id' => $devTitle->id,
            'location' => 'الرياض',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/mobile/job-news?preset=matched');
        $response->assertOk();

        $context = $response->json('data.profile_context');
        $this->assertTrue($context['has_profile']);
        $this->assertSame($devTitle->id, $context['job_title_id']);

        foreach ($response->json('data.items') as $item) {
            foreach (['match_score', 'match_reasons', 'matched_skills', 'missing_skills'] as $removed) {
                $this->assertArrayNotHasKey($removed, $item);
            }
            $stored = JobNews::findOrFail($item['id']);
            $this->assertTrue(
                $stored->job_title_id === $devTitle->id || $stored->category === $devTitle->category,
                'matched preset returned a job outside the user field',
            );
        }
    }

    public function test_matched_preset_is_empty_without_a_role_on_the_profile(): void
    {
        Sanctum::actingAs(User::factory()->create(['job_title_id' => null, 'location' => 'الرياض']));

        $response = $this->getJson('/api/mobile/job-news?preset=matched');
        $response->assertOk();
        $this->assertSame([], $response->json('data.items'));
        $this->assertFalse($response->json('data.profile_context.has_profile'));
    }

    public function test_matched_preset_works_from_a_cv_target_title_alone(): void
    {
        $user = User::factory()->create(['job_title_id' => null, 'location' => null]);
        \App\Models\CvAnalysis::create([
            'user_id' => $user->id,
            'target_job_title' => 'مطور',
            'input_method' => 'paste',
            'resume_text' => 'Laravel developer',
            'score_total' => 50,
            'grade' => 'C',
            'job_match' => 50,
            'criteria' => [],
            'strengths' => [],
            'weaknesses' => [],
            'keywords_found' => [],
            'keywords_missing' => [],
            'quick_wins' => [],
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/mobile/job-news?preset=matched')->assertOk();

        $this->assertTrue($response->json('data.profile_context.has_profile'));
        $this->assertSame('مطور', $response->json('data.profile_context.job_title_name'));
        foreach ($response->json('data.items') as $item) {
            $stored = JobNews::findOrFail($item['id']);
            $this->assertTrue(str_contains($stored->title.' '.$stored->body, 'مطور'), $stored->title);
        }
    }

    public function test_guest_matched_preset_is_empty(): void
    {
        $this->getJson('/api/mobile/job-news?preset=matched')
            ->assertOk()
            ->assertJsonPath('data.items', []);
    }
}
