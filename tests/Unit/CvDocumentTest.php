<?php

namespace Tests\Unit;

use App\Cv\Certification;
use App\Cv\CustomSection;
use App\Cv\CvDocument;
use App\Cv\EducationEntry;
use App\Cv\ExperienceEntry;
use App\Cv\LanguageSkill;
use App\Cv\LocalizedText;
use App\Cv\PersonalDetails;
use App\Cv\ProficiencyStorageKeys;
use App\Cv\Project;
use App\Cv\Skill;
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

    public function test_proficiency_english_maps_contain_no_arabic(): void
    {
        $maps = [
            ...ProficiencyStorageKeys::SKILL,
            ...ProficiencyStorageKeys::LANGUAGE_LEVEL,
        ];
        $this->assertNotEmpty($maps);
        foreach ($maps as $ar => $en) {
            $this->assertNotSame('', $en);
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $en);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $ar);
        }
    }

    public function test_english_resolve_of_bilingual_proficiency_has_no_arabic(): void
    {
        $languageLevels = [
            'اللغة الأم (Native)' => 'Native',
            'طليق (C2 / Fluent)' => 'Fluent',
            'مهني متقدم (C1)' => 'Advanced professional (C1)',
            'متوسط (B2)' => 'Intermediate (B2)',
            'أساسي (A2)' => 'Elementary (A2)',
        ];
        $languages = [];
        $index = 0;
        foreach ($languageLevels as $ar => $en) {
            $languages[] = new LanguageSkill(
                name: LocalizedText::make(en: 'Language-'.$index),
                level: LocalizedText::make(ar: $ar, en: $en),
            );
            $index++;
        }

        $document = new CvDocument(
            exportLanguage: 'en',
            personal: new PersonalDetails(
                fullName: LocalizedText::make(ar: 'سارة القحطاني', en: 'Sara Al-Qahtani'),
            ),
            languages: $languages,
        );

        $resolved = $document->resolve('en');

        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $resolved->languages);
        $this->assertSame('Sara Al-Qahtani', $resolved->fullName);

        foreach ($languageLevels as $ar => $en) {
            $this->assertStringContainsString($en, $resolved->languages);
            $this->assertStringNotContainsString($ar, $resolved->languages);
        }
    }

    public function test_known_language_levels_backfill_english_when_en_is_empty(): void
    {
        $languageLevels = ProficiencyStorageKeys::LANGUAGE_LEVEL;
        $languages = [];
        $index = 0;
        foreach ($languageLevels as $ar => $en) {
            $languages[] = LanguageSkill::fromArray([
                'name' => ['ar' => '', 'en' => 'Language-'.$index],
                'level' => ['ar' => $ar, 'en' => ''],
            ]);
            $index++;
        }
        $languages[] = LanguageSkill::fromArray([
            'name' => ['ar' => '', 'en' => 'Other'],
            'level' => ['ar' => 'مستوى غير معروف', 'en' => ''],
        ]);
        $languages[] = LanguageSkill::fromArray([
            'name' => ['ar' => '', 'en' => 'Kept'],
            'level' => ['ar' => 'مهني متقدم (C1)', 'en' => 'Already English'],
        ]);

        $document = new CvDocument(
            exportLanguage: 'en',
            languages: $languages,
        );
        $resolved = $document->resolve('en');

        foreach ($languageLevels as $ar => $en) {
            $this->assertStringContainsString($en, $resolved->languages);
            $this->assertStringNotContainsString($ar, $resolved->languages);
        }
        $this->assertStringContainsString('مستوى غير معروف', $resolved->languages);
        $this->assertStringContainsString('Already English', $resolved->languages);
        $this->assertStringContainsString('Kept (Already English)', $resolved->languages);
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
        $this->assertSame(['personal.full_name.ar', 'personal.headline.ar', 'personal.location.ar', 'summary.ar', 'experience.0.title.ar', 'education.0.degree.ar', 'skills.0.name.ar', 'skills.1.name.ar'], $document->missingTranslations());
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

    public function test_fully_populated_document_round_trip_identity_is_lossless(): void
    {
        $document = new CvDocument(
            schemaVersion: 1,
            exportLanguage: 'en',
            personal: new PersonalDetails(
                fullName: LocalizedText::make('سارة أحمد', 'Sara Ahmed'),
                headline: LocalizedText::make('مهندسة برمجيات', 'Software Engineer'),
                email: 'sara.dev@example.com',
                phone: '+966501234567',
                linkedin: 'linkedin.com/in/saradev',
                location: LocalizedText::make('الرياض، السعودية', 'Riyadh, Saudi Arabia'),
            ),
            summary: LocalizedText::make('مهندسة متمرسة في بناء المنصات السحابية', 'Seasoned engineer building scalable cloud systems'),
            experience: [
                new ExperienceEntry(
                    company: LocalizedText::make('شركة التقنية المتقدمة', 'Advanced Tech Co'),
                    title: LocalizedText::make('مهندسة نظم أولى', 'Senior Systems Engineer'),
                    location: LocalizedText::make('الرياض', 'Riyadh'),
                    startDate: '2021-01-01',
                    endDate: '2024-08-31',
                    isCurrent: false,
                    bullets: [
                        LocalizedText::make('تصميم واجهات برمجية عالية الأداء', 'Architected high-throughput API gateway'),
                        LocalizedText::make('تحسين أداء قواعد البيانات بنسبة 40%', 'Optimized database queries reducing latency by 40%'),
                    ],
                    narrative: LocalizedText::make('قيادة الفريق التقني لمشاريع البنية التحتية', 'Led infrastructure backend initiatives'),
                    id: 'exp-uuid-101',
                ),
            ],
            education: [
                new EducationEntry(
                    institution: LocalizedText::make('جامعة الملك فهد للبترول والمعادن', 'KFUPM'),
                    degree: LocalizedText::make('بكالوريوس', 'Bachelor of Science'),
                    field: LocalizedText::make('هندسة البرمجيات', 'Software Engineering'),
                    startDate: '2016-09-01',
                    endDate: '2020-05-30',
                    narrative: LocalizedText::make('مرتبة الشرف الأولى', 'First Class Honors'),
                    id: 'edu-uuid-202',
                ),
            ],
            skills: [
                new Skill(
                    name: LocalizedText::make('لارافيل', 'Laravel'),
                    level: LocalizedText::make('خبير', 'Expert'),
                    category: LocalizedText::make('مهارات تقنية', 'Technical skills'),
                    id: 'skill-uuid-301',
                ),
                new Skill(
                    name: LocalizedText::make('إدارة الفرق', 'Team Leadership'),
                    level: LocalizedText::make('متقدم', 'Advanced'),
                    category: LocalizedText::make('مهارات قيادية وشخصية', 'Interpersonal skills'),
                    id: 'skill-uuid-302',
                ),
            ],
            languages: [
                new LanguageSkill(
                    name: LocalizedText::make('العربية', 'Arabic'),
                    level: LocalizedText::make('اللغة الأم (Native)', 'Native'),
                    id: 'lang-uuid-401',
                ),
                new LanguageSkill(
                    name: LocalizedText::make('الإنجليزية', 'English'),
                    level: LocalizedText::make('طليق (C2 / Fluent)', 'Fluent'),
                    id: 'lang-uuid-402',
                ),
            ],
            certifications: [
                new Certification(
                    name: LocalizedText::make('شهادة مهندس حلول معتمد', 'AWS Certified Solutions Architect'),
                    issuer: LocalizedText::make('أمازون لخدمات الويب', 'Amazon Web Services'),
                    date: '2023-04-15',
                    expiryDate: '2026-04-15',
                    narrative: LocalizedText::make('تخصص في الحوسبة السحابية المؤسسية', 'Enterprise cloud computing specialty'),
                    id: 'cert-uuid-501',
                ),
            ],
            projects: [
                new Project(
                    name: LocalizedText::make('منصة التوظيف الذكي', 'Smart Hiring Platform'),
                    role: LocalizedText::make('المطور الرئيسي', 'Lead Architect'),
                    url: 'https://github.com/example/hiring',
                    description: LocalizedText::make('نظام متكامل لمعالجة السير الذاتية بالذكاء الاصطناعي', 'End-to-end AI resume processing system'),
                    bullets: [
                        LocalizedText::make('معالجة أكثر من 100 ألف طلب توظيف', 'Processed 100k+ candidate profiles'),
                    ],
                    id: 'proj-uuid-601',
                ),
            ],
            customSections: [
                new CustomSection(
                    key: 'volunteer',
                    title: LocalizedText::make('العمل التطوعي', 'Volunteering'),
                    body: LocalizedText::make('مرشد تقني في معسكرات البرمجة الوطنية', 'Technical mentor at national coding bootcamps'),
                    items: [
                        LocalizedText::make('تدريب 50 مطور ناشئ', 'Mentored 50 junior engineers'),
                    ],
                    id: 'custom-uuid-701',
                ),
            ],
        );

        $array = $document->toArray();
        $rehydrated = CvDocument::fromArray($array);

        $this->assertEquals($document, $rehydrated);
        $this->assertSame($array, $rehydrated->toArray());

        // Invariant: double round-trip is strictly involutive
        $secondRoundTrip = CvDocument::fromArray($rehydrated->toArray());
        $this->assertEquals($document, $secondRoundTrip);
    }

    public function test_known_skill_levels_and_categories_backfill_english_when_en_is_empty(): void
    {
        $skill = Skill::fromArray([
            'name' => ['ar' => 'بي إتش بي', 'en' => 'PHP'],
            'level' => ['ar' => 'خبير', 'en' => ''],
            'category' => ['ar' => 'مهارات تقنية', 'en' => ''],
            'id' => 'skill-auto-fill',
        ]);

        $this->assertSame('Expert', $skill->level->en);
        $this->assertSame('Technical skills', $skill->category->en);
        $this->assertSame('skill-auto-fill', $skill->id);

        $beginnerSkill = Skill::fromArray([
            'name' => 'Docker',
            'level' => ['ar' => 'مبتدئ', 'en' => ''],
            'category' => ['ar' => 'أدوات وبرمجيات', 'en' => ''],
        ]);

        $this->assertSame('Beginner', $beginnerSkill->level->en);
        $this->assertSame('Tools and software', $beginnerSkill->category->en);
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
