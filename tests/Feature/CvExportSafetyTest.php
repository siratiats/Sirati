<?php

namespace Tests\Feature;

use App\Cv\CvExportGuard;
use App\Enums\AiStatus;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\CvTemplateRenderer;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
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

    /**
     * Whatever makes a CV unexportable, the readiness every client sees (CV
     * detail, My CVs list) must match what the PDF endpoints actually do, and
     * the browser link must explain the gap instead of a bare error page.
     */
    public function test_reported_export_state_matches_the_pdf_endpoints(): void
    {
        $unstructured = 'I worked at a company and did marketing and campaign work without job titles or dates.';
        $cases = [
            'queued' => [['ai_status' => AiStatus::Queued], CvExportGuard::BLOCKED_GENERATING],
            'processing' => [['ai_status' => AiStatus::Processing], CvExportGuard::BLOCKED_GENERATING],
            'no phone' => [['phone' => ''], CvExportGuard::BLOCKED_MISSING_CONTACT],
            'blank phone' => [['phone' => '   '], CvExportGuard::BLOCKED_MISSING_CONTACT],
            'no email' => [['email' => null], CvExportGuard::BLOCKED_MISSING_CONTACT],
            'failed, unstructured' => [['ai_status' => AiStatus::Failed, 'experience_input' => $unstructured, 'document' => null], CvExportGuard::BLOCKED_UNSTRUCTURED_EXPERIENCE],
            'no AI, unstructured' => [['ai_status' => AiStatus::NotConfigured, 'experience_input' => $unstructured, 'document' => null], CvExportGuard::BLOCKED_UNSTRUCTURED_EXPERIENCE],
            'failed, structured' => [['ai_status' => AiStatus::Failed], null],
            'completed' => [[], null],
        ];

        foreach ($cases as $label => [$overrides, $expected]) {
            $user = User::factory()->create();
            Sanctum::actingAs($user);
            $cv = $this->cv($user, $overrides);

            $detail = $this->getJson("/api/generated-cvs/{$cv->id}")->assertOk()->json('data.export');
            $row = collect($this->getJson('/api/mobile/my-cvs?lang=en')->assertOk()->json('data.items'))
                ->firstWhere('id', $cv->id);

            foreach (['detail' => $detail, 'list' => $row['export']] as $where => $export) {
                $this->assertSame($expected === null, $export['ready'], "$label: $where ready");
                $this->assertSame($expected, $export['blocked_reason'], "$label: $where reason");
                $this->assertSame($expected === null, $export['message_en'] === null, "$label: $where message");
            }
            $this->assertSame($expected === CvExportGuard::BLOCKED_GENERATING, $row['is_draft'], "$label: is_draft");

            $api = $this->get("/api/generated-cvs/{$cv->id}/download")->status();
            $signed = $this->get(URL::temporarySignedRoute('api.generated-cvs.pdf', now()->addHour(), ['generatedCv' => $cv->id]));

            if ($expected === null) {
                $this->assertNotContains($api, [409, 422], "$label: ready CV must not be blocked by the guard");
                $this->assertNotContains($signed->status(), [409, 422], "$label: signed link");
            } else {
                $this->assertContains($api, [409, 422], "$label: blocked CV must be refused");
                $signed->assertStatus($api)
                    ->assertSee(__('generated_cvs.not_ready.heading', [], 'ar'))
                    ->assertSee(CvExportGuard::message($expected, 'ar'))
                    ->assertSee(CvExportGuard::message($expected, 'en'));
            }
        }
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

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'class="entry"'));
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
