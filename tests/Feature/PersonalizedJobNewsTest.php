<?php

namespace Tests\Feature;

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

    public function test_authenticated_user_receives_match_score_and_reasons(): void
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

        $items = $response->json('data.items');
        $this->assertNotEmpty($items);

        $first = $items[0];
        $this->assertNotNull($first['match_score']);
        $this->assertGreaterThanOrEqual(45, $first['match_score']);
        $this->assertNotEmpty($first['match_reasons']);
    }
}
