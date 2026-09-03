<?php

namespace Tests\Unit;

use App\Cv\CvDocument;
use App\Cv\ExperienceEntry;
use App\Cv\LocalizedText;
use App\Cv\PersonalDetails;
use App\Services\AtsScoringService;
use PHPUnit\Framework\TestCase;

class CvDocumentTest extends TestCase
{
    public function test_json_round_trip_is_lossless(): void
    {
        $document = $this->sampleDocument();
        $encoded = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $decoded = CvDocument::fromArray(json_decode($encoded, true, 512, JSON_THROW_ON_ERROR));

        $this->assertSame($document->toArray(), $decoded->toArray());
        $this->assertSame($encoded, json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function test_omitted_sections_stay_omitted_after_round_trip(): void
    {
        $document = new CvDocument(
            exportLanguage: 'en',
            personal: new PersonalDetails(fullName: LocalizedText::make(en: 'Sara')),
            summary: LocalizedText::make(en: 'Summary'),
        );

        $this->assertArrayNotHasKey('projects', $document->toArray());
        $this->assertArrayNotHasKey('languages', $document->toArray());
        $this->assertNull(CvDocument::fromArray($document->toArray())->projects);
    }

    public function test_empty_section_is_distinct_from_omitted(): void
    {
        $document = new CvDocument(
            exportLanguage: 'en',
            experience: [],
        );

        $this->assertSame([], $document->toArray()['experience']);
        $this->assertSame([], CvDocument::fromArray($document->toArray())->experience);
    }

    public function test_export_language_falls_back_when_variant_is_empty(): void
    {
        $document = $this->sampleDocument()->withExportLanguage('en');
        $english = $document->resolve('en');
        $arabic = $document->resolve('ar');

        $this->assertSame('Sara Ahmed', $english->fullName);
        $this->assertSame('Riyadh', $english->location);
        $this->assertSame('Backend developer', $english->summary);
        $this->assertSame('سارة أحمد', $arabic->fullName);
        $this->assertSame('الرياض', $arabic->location);
        $this->assertSame('Backend developer', $arabic->summary);
    }

    public function test_missing_translations_lists_only_partial_fields(): void
    {
        $this->assertSame([
            'summary.ar',
            'experience.0.narrative.ar',
        ], $this->sampleDocument()->missingTranslations());
    }

    public function test_duplicate_preserves_both_language_variants(): void
    {
        $copy = $this->sampleDocument()->duplicate();

        $this->assertSame($this->sampleDocument()->toArray(), $copy->toArray());
        $this->assertSame('سارة أحمد', $copy->personal->fullName->ar);
        $this->assertSame('Sara Ahmed', $copy->personal->fullName->en);
    }

    public function test_copy_semantics_change_export_language_without_mutating_source(): void
    {
        $original = $this->sampleDocument();
        $copy = $original->withExportLanguage('en');

        $this->assertSame('ar', $original->exportLanguage);
        $this->assertSame('en', $copy->exportLanguage);
        $this->assertSame($original->personal->fullName->toArray(), $copy->personal->fullName->toArray());
    }

    public function test_from_legacy_puts_text_in_the_source_language_only(): void
    {
        $document = CvDocument::fromLegacy([
            'full_name' => 'Salem',
            'email' => 'salem@example.com',
            'phone' => '+966500000000',
            'linkedin' => null,
            'location' => 'Riyadh',
            'target_job_title' => 'Laravel Developer',
            'language' => 'en',
            'summary_input' => 'Backend developer',
            'skills_input' => 'Laravel, API',
            'experience_input' => 'Built Laravel APIs for production teams with measurable outcomes.',
            'education_input' => 'BSc Computer Science',
            'certifications_input' => null,
        ]);

        $this->assertSame('Salem', $document->personal->fullName->en);
        $this->assertSame('', $document->personal->fullName->ar);
        $this->assertSame(['Laravel', 'API'], array_map(
            fn ($skill) => $skill->name->en,
            $document->skills ?? [],
        ));
        $this->assertNull($document->projects);
        $this->assertSame(['personal.full_name.ar', 'personal.headline.ar', 'personal.location.ar', 'summary.ar', 'experience.0.narrative.ar', 'education.0.narrative.ar', 'skills.0.name.ar', 'skills.1.name.ar'], $document->missingTranslations());
    }

    public function test_json_schema_documents_bilingual_fields_for_ats_and_templates(): void
    {
        $schema = CvDocument::jsonSchema();

        $this->assertSame('sirati.cv_document.v1', $schema['$id']);
        $this->assertSame(['ar', 'en'], $schema['properties']['summary']['required']);
        $this->assertFalse($schema['additionalProperties']);
        $this->assertContains('personal', $schema['required']);
        $this->assertArrayHasKey('experience', $schema['properties']);
        $this->assertArrayHasKey('custom_sections', $schema['properties']);
    }

    public function test_ats_engine_scores_resolved_document_text(): void
    {
        $document = CvDocument::fromLegacy([
            'full_name' => 'Salem Sayer',
            'email' => 'salem@example.com',
            'phone' => '+966591890300',
            'linkedin' => 'linkedin.com/in/salem',
            'location' => 'Riyadh',
            'target_job_title' => 'Laravel Backend Developer',
            'language' => 'en',
            'summary_input' => 'Backend developer with 5+ years building Laravel API platforms, SQL dashboards, and agile integrations.',
            'skills_input' => 'PHP, Laravel, API, SQL, Git, Agile, Scrum, Backend, JavaScript',
            'experience_input' => "Backend Developer, Sirati, 2021 - 2025\n- Developed Laravel APIs used by 25 internal users.\n- Improved reporting speed by 35%.",
            'education_input' => 'Bachelor of Computer Science, 2020',
            'certifications_input' => 'AWS Certified Cloud Practitioner',
        ]);

        $score = (new AtsScoringService)->scoreDocument($document);

        $this->assertGreaterThanOrEqual(70, $score['total']);
        $this->assertSame('software', $score['category']);
        $this->assertContains('laravel', $score['keywords_found']);
    }

    private function sampleDocument(): CvDocument
    {
        return new CvDocument(
            exportLanguage: 'ar',
            personal: new PersonalDetails(
                fullName: LocalizedText::make(ar: 'سارة أحمد', en: 'Sara Ahmed'),
                headline: LocalizedText::make(ar: 'مطورة خلفية', en: 'Backend Developer'),
                email: 'sara@example.com',
                phone: '+966500000000',
                linkedin: 'linkedin.com/in/sara',
                location: LocalizedText::make(ar: 'الرياض', en: 'Riyadh'),
            ),
            summary: LocalizedText::make(en: 'Backend developer'),
            experience: [
                new ExperienceEntry(
                    narrative: LocalizedText::make(en: 'Built Laravel APIs'),
                ),
            ],
            education: [],
            skills: null,
            languages: null,
            certifications: null,
            projects: null,
            customSections: null,
        );
    }
}
