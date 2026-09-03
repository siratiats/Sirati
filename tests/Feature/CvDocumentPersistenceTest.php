<?php

namespace Tests\Feature;

use App\Cv\CvDocument;
use App\Cv\LocalizedText;
use App\Cv\PersonalDetails;
use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\CvTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CvDocumentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_cv_persists_a_typed_bilingual_document(): void
    {
        config(['services.openai.api_key' => null]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/generated-cvs', $this->payload());

        $response->assertCreated();
        $cv = GeneratedCv::first();
        $this->assertNotNull($cv?->document);
        $this->assertSame(CvDocument::SCHEMA_VERSION, $cv->document['schema_version']);
        $this->assertSame('Sara Ahmed', $cv->cvDocument()->personal->fullName->en);
        $this->assertSame('', $cv->cvDocument()->personal->fullName->ar);
        $this->assertContains('personal.full_name.ar', $response->json('data.missing_translations') ?? $response->json('missing_translations'));
    }

    public function test_document_payload_stores_both_language_variants(): void
    {
        config(['services.openai.api_key' => null]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $document = (new CvDocument(
            exportLanguage: 'en',
            personal: new PersonalDetails(
                fullName: LocalizedText::make(ar: 'سارة أحمد', en: 'Sara Ahmed'),
                headline: LocalizedText::make(ar: 'مطورة خلفية', en: 'Backend Developer'),
                email: 'sara@example.com',
                location: LocalizedText::make(ar: 'الرياض', en: 'Riyadh'),
            ),
            summary: LocalizedText::make(ar: 'مطورة خلفية', en: 'Backend developer'),
        ))->toArray();

        $response = $this->postJson('/api/generated-cvs', [
            ...$this->payload(),
            'document' => $document,
        ]);

        $response->assertCreated();
        $stored = GeneratedCv::first()->cvDocument();
        $this->assertSame('سارة أحمد', $stored->personal->fullName->ar);
        $this->assertSame('Sara Ahmed', $stored->personal->fullName->en);
        $this->assertSame([], $stored->missingTranslations());
        $this->assertSame('سارة أحمد', $stored->resolve('ar')->fullName);
        $this->assertSame('Sara Ahmed', $stored->resolve('en')->fullName);
    }

    public function test_duplicating_a_cv_preserves_both_language_variants(): void
    {
        config(['services.openai.api_key' => null]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $original = $this->postJson('/api/generated-cvs', [
            ...$this->payload(),
            'document' => (new CvDocument(
                exportLanguage: 'en',
                personal: new PersonalDetails(
                    fullName: LocalizedText::make(ar: 'سارة أحمد', en: 'Sara Ahmed'),
                    headline: LocalizedText::make(en: 'Backend Developer'),
                    email: 'sara@example.com',
                ),
                summary: LocalizedText::make(ar: 'ملخص', en: 'Summary'),
            ))->toArray(),
        ])->json('data.id') ?? GeneratedCv::first()->id;

        $response = $this->postJson("/api/generated-cvs/{$original}/duplicate");

        $response->assertCreated();
        $copy = GeneratedCv::query()->latest('id')->first();
        $this->assertNotSame($original, $copy->id);
        $this->assertSame('سارة أحمد', $copy->cvDocument()->personal->fullName->ar);
        $this->assertSame('Sara Ahmed', $copy->cvDocument()->personal->fullName->en);
        $this->assertSame('ملخص', $copy->cvDocument()->summary->ar);
        $this->assertSame('Summary', $copy->cvDocument()->summary->en);
    }

    public function test_template_view_model_uses_resolved_document_fields(): void
    {
        $cv = GeneratedCv::create([
            ...$this->payload(),
            'generated_markdown' => "## Experience\nBuilt APIs",
            'form_payload' => $this->payload(),
            'document' => (new CvDocument(
                exportLanguage: 'en',
                personal: new PersonalDetails(
                    fullName: LocalizedText::make(ar: 'سارة أحمد', en: 'Sara Ahmed'),
                    headline: LocalizedText::make(en: 'Backend Developer'),
                    location: LocalizedText::make(ar: 'الرياض', en: 'Riyadh'),
                ),
                summary: LocalizedText::make(en: 'Backend developer'),
            ))->toArray(),
        ]);

        $viewModel = app(CvTemplateRenderer::class)->viewModel(
            $cv,
            new CvTemplate([
                'name_en' => 'Classic',
                'name_ar' => 'كلاسيكي',
                'slug' => 'classic',
                'color_tokens' => [],
                'config_json' => [],
            ]),
        );

        $this->assertSame('Sara Ahmed', $viewModel['candidate']['full_name']);
        $this->assertSame('Riyadh', $viewModel['candidate']['location']);
        $this->assertSame('Backend developer', $viewModel['summary']);
    }

    /**
     * @return array<string, string|null>
     */
    private function payload(): array
    {
        return [
            'full_name' => 'Sara Ahmed',
            'email' => 'sara@example.com',
            'phone' => '+966500000000',
            'linkedin' => 'linkedin.com/in/sara',
            'location' => 'Riyadh',
            'target_job_title' => 'Backend Developer',
            'job_description_input' => null,
            'language' => 'en',
            'summary_input' => 'Backend developer focused on Laravel APIs and dashboards.',
            'skills_input' => 'Laravel, API, SQL, Git',
            'experience_input' => 'Backend Developer at Sirati from 2021 to 2025. Developed Laravel APIs for 25 users, improved reporting speed by 35%, and reduced support tickets by 20%.',
            'education_input' => 'Bachelor of Computer Science, 2020',
            'certifications_input' => null,
        ];
    }
}
