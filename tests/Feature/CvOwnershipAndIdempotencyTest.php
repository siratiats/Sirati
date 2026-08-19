<?php

namespace Tests\Feature;

use App\Enums\AiStatus;
use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CvOwnershipAndIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.openai.api_key' => null,
            'services.claude.api_key' => null,
        ]);
    }

    public function test_unsigned_analysis_page_is_forbidden(): void
    {
        $analysis = $this->analysis();

        $this->get(route('analyses.show', $analysis))->assertForbidden();
    }

    public function test_signed_analysis_page_is_visible(): void
    {
        $analysis = $this->analysis();

        $this->get(URL::temporarySignedRoute(
            'analyses.show',
            now()->addMinutes(5),
            ['analysis' => $analysis],
        ))->assertOk();
    }

    public function test_owner_can_view_unsigned_analysis_page(): void
    {
        $user = User::factory()->create();
        $analysis = $this->analysis($user);

        $this->actingAs($user)
            ->get(route('analyses.show', $analysis))
            ->assertOk();
    }

    public function test_stranger_cannot_view_unsigned_analysis_page(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $analysis = $this->analysis($owner);

        $this->actingAs($stranger)
            ->get(route('analyses.show', $analysis))
            ->assertForbidden();
    }

    public function test_analysis_idempotency_key_replays_the_same_record(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $payload = [
            'target_job_title' => 'Laravel Developer',
            'resume_text' => $this->resumeText(),
        ];

        $first = $this->withHeader('Idempotency-Key', 'analysis-key-1')
            ->postJson('/api/cv-analyses', $payload)
            ->assertCreated();

        $second = $this->withHeader('Idempotency-Key', 'analysis-key-1')
            ->postJson('/api/cv-analyses', $payload)
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, CvAnalysis::count());
    }

    public function test_generated_cv_idempotency_key_replays_the_same_record(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $payload = $this->generationPayload();

        $first = $this->withHeader('Idempotency-Key', 'cv-key-1')
            ->postJson('/api/generated-cvs', $payload)
            ->assertCreated();

        $second = $this->withHeader('Idempotency-Key', 'cv-key-1')
            ->postJson('/api/generated-cvs', $payload)
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, GeneratedCv::count());
    }

    public function test_history_endpoints_are_paginated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        for ($i = 0; $i < 21; $i++) {
            $this->analysis($user);
        }

        $this->getJson('/api/cv-analyses?per_page=20')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 21);
    }

    private function analysis(?User $user = null): CvAnalysis
    {
        return CvAnalysis::create([
            'user_id' => $user?->id,
            'target_job_title' => 'Laravel Developer',
            'input_method' => 'paste',
            'resume_text' => $this->resumeText(),
            'score_total' => 70,
            'grade' => 'B',
            'job_match' => 70,
            'criteria' => [],
            'strengths' => [],
            'weaknesses' => [],
            'keywords_found' => [],
            'keywords_missing' => [],
            'quick_wins' => [],
            'ai_status' => AiStatus::NotConfigured,
        ]);
    }

    private function generationPayload(): array
    {
        return [
            'full_name' => 'Salem Sayer',
            'target_job_title' => 'Laravel Developer',
            'language' => 'en',
            'skills_input' => 'Laravel, PHP, API, SQL, Git',
            'experience_input' => 'Backend Developer at Sirati from 2021 to 2025. Developed Laravel APIs for 25 users and improved reporting speed by 35%.',
            'education_input' => 'Bachelor of Computer Science, 2020',
        ];
    }

    private function resumeText(): string
    {
        return <<<'CV'
Salem Sayer
Laravel Backend Developer
salem@example.com

Skills
PHP, Laravel, API, SQL, Git

Experience
Backend Developer, Sirati Labs, 2021 - 2025
- Developed Laravel APIs used by 25 internal users.
- Improved reporting speed by 35%.
CV;
    }
}
