<?php

namespace Tests\Feature;

use App\Enums\AiStatus;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\CvTemplateRenderer;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CvExportSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CvTemplateSeeder::class);
    }

    public function test_queued_generation_cannot_be_exported(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $cv = $this->cv($user, ['ai_status' => AiStatus::Queued]);

        $this->get("/api/generated-cvs/{$cv->id}/download")->assertStatus(409);
    }

    public function test_failed_unstructured_generation_cannot_be_exported(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $cv = $this->cv($user, [
            'ai_status' => AiStatus::Failed,
            'experience_input' => 'I worked at a company and did marketing and campaign work without job titles or dates.',
            'document' => null,
        ]);

        $this->get("/api/generated-cvs/{$cv->id}/download")->assertStatus(422);
    }

    public function test_completed_cv_html_renders_multiple_experience_entries(): void
    {
        $user = User::factory()->create();
        $cv = $this->cv($user, [
            'ai_status' => AiStatus::Completed,
            'experience_input' => "Engineer, Acme, 2020 - 2022\n- Built APIs.\n\nLead Engineer, Beta, 2022 - 2024\n- Led migrations.",
            'document' => null,
        ]);

        $html = app(CvTemplateRenderer::class)->renderHtml($cv, 'ats-classic-professional', forExport: true);

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'class="entry-item"'));
        $this->assertStringContainsString('Engineer', $html);
        $this->assertStringContainsString('Lead Engineer', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);
        $this->assertStringNotContainsString('ATS score', $html);
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
            'experience_input' => "Engineer, Acme, 2020 - 2022\n- Built APIs.",
            'education_input' => 'BSc Computer Science',
            'generated_markdown' => "## Experience\nEngineer, Acme, 2020 - 2022\n- Built APIs.",
            'form_payload' => [],
            'ai_status' => AiStatus::Completed,
            'score_total' => 80,
            'grade' => 'B',
        ], $overrides));
    }
}
