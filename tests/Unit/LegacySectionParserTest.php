<?php

namespace Tests\Unit;

use App\Cv\CvDocument;
use App\Cv\LegacySectionParser;
use PHPUnit\Framework\TestCase;

class LegacySectionParserTest extends TestCase
{
    public function test_date_delimited_jobs_become_separate_titled_entries(): void
    {
        $blob = <<<'TEXT'
Senior Backend Engineer, Acme Cloud, Riyadh, 2021 - 2024
- Cut API p95 latency by 35%.
- Led a team of 6 engineers.

ICU Staff Nurse, King Faisal Specialist Hospital, 2018 - Present
- Managed a 12-bed ICU rotation.
TEXT;

        $entries = LegacySectionParser::experience($blob, 'en');

        $this->assertCount(2, $entries);
        $this->assertSame('Senior Backend Engineer', $entries[0]->title->en);
        $this->assertSame('Acme Cloud', $entries[0]->company->en);
        $this->assertSame('2021', $entries[0]->startDate);
        $this->assertSame('2024', $entries[0]->endDate);
        $this->assertCount(2, $entries[0]->bullets);
        $this->assertSame('ICU Staff Nurse', $entries[1]->title->en);
        $this->assertTrue($entries[1]->isCurrent);
    }

    public function test_arabic_jobs_with_dates_split_the_same_way(): void
    {
        $blob = <<<'TEXT'
مهندس برمجيات أول، شركة التقنية، الرياض، 2020 - 2023
- بناء منصات سحابية.

ممرض عناية مركزة، مستشفى الملك فيصل، 2017 - حتى الآن
- إدارة مناوبات العناية المركزة.
TEXT;

        $entries = LegacySectionParser::experience($blob, 'ar');

        $this->assertCount(2, $entries);
        $this->assertNotSame('', $entries[0]->title->ar);
        $this->assertSame('2020', $entries[0]->startDate);
        $this->assertTrue($entries[1]->isCurrent);
        $this->assertNotEmpty($entries[0]->bullets);
    }

    public function test_editorial_annotation_lines_are_stripped(): void
    {
        $blob = "Backend Engineer, Acme, 2020 - 2021\n- Shipped APIs.\nتحسينات مطلوبة: أضف رابط LinkedIn\nRequired improvements: add metrics";

        $entries = LegacySectionParser::experience($blob, 'en');

        $this->assertCount(1, $entries);
        $resolved = $entries[0]->resolveNarrative('en');
        $this->assertStringNotContainsString('تحسينات مطلوبة', $resolved);
        $this->assertStringNotContainsString('Required improvements', $resolved);
        $this->assertStringContainsString('Shipped APIs', $resolved);
    }

    public function test_from_legacy_feeds_structured_entries_not_one_narrative_blob(): void
    {
        $document = CvDocument::fromLegacy([
            'language' => 'en',
            'full_name' => 'Sara Ahmed',
            'email' => 'sara@example.com',
            'phone' => '+966500000000',
            'target_job_title' => 'software engineer',
            'summary_input' => 'Backend developer',
            'skills_input' => 'Laravel, PHP',
            'experience_input' => "Engineer, Acme, 2020 - 2022\n- Built APIs.\n\nEngineer, Beta, 2022 - 2024\n- Led migrations.",
            'education_input' => 'BSc Computer Science, King Saud University, 2016 - 2020',
        ]);

        $this->assertTrue($document->hasStructuredExperience());
        $this->assertCount(2, $document->experience ?? []);
        $this->assertCount(1, $document->education ?? []);

        $sections = $document->resolve('en')->toStructuredSections();
        $experience = null;
        foreach ($sections as $section) {
            if (($section['key'] ?? null) === 'experience') {
                $experience = $section;
                break;
            }
        }
        $this->assertIsArray($experience);
        $this->assertSame('entries', $experience['type']);
        $this->assertCount(2, $experience['entries']);
        $this->assertSame('Engineer', $experience['entries'][0]['title']);
        $this->assertNotSame('', $experience['entries'][0]['date_range']);
    }

    public function test_markdown_overlay_replaces_unstructured_generator_blob(): void
    {
        $document = CvDocument::fromLegacy([
            'language' => 'en',
            'full_name' => 'Sara Ahmed',
            'email' => 'sara@example.com',
            'phone' => '+966500000000',
            'target_job_title' => 'Engineer',
            'experience_input' => 'I worked at a company and did marketing and campaign work across several teams with mixed responsibilities.',
            'education_input' => 'Bachelor of Computer Science',
            'skills_input' => 'PHP',
        ]);

        $this->assertFalse($document->hasStructuredExperience());

        $overlaid = $document->overlayGeneratedMarkdown(<<<'MD'
## Professional Summary
Backend engineer.

## Experience
Staff Engineer, Northwind, 2021 - 2024
- Reduced p95 latency 40%.

## Education
BSc Computer Science, King Saud University, 2016 - 2020
MD, 'en', 'Staff Engineer');

        $this->assertTrue($overlaid->hasStructuredExperience());
        $this->assertSame('Staff Engineer', $overlaid->personal->headline->en);
        $this->assertCount(1, $overlaid->experience ?? []);
        $this->assertSame('Staff Engineer', $overlaid->experience[0]->title->en);
    }

    public function test_sample_format_markdown_fills_every_section(): void
    {
        $document = CvDocument::fromLegacy([
            'language' => 'en',
            'full_name' => 'Sara Ahmed',
            'email' => 'sara@example.com',
            'target_job_title' => 'Data Analyst',
            'experience_input' => '',
            'education_input' => '',
            'skills_input' => '',
        ]);

        $overlaid = $document->overlayGeneratedMarkdown(<<<'MD'
# Sara Ahmed
+966500000000 | sara@example.com

## Professional Summary
Data analyst with five years of experience.

## Core Competencies
Analytics | SQL, Power BI, Excel
Soft Skills | Communication, Leadership

## Professional Experience
Senior Data Analyst | Acme Corp | Riyadh, KSA | Jan 2021 – Present
Owned reporting for the retail division.
- Built 12 executive dashboards.
- Cut report preparation time by 40%.

Data Analyst | Beta Ltd | Jeddah | Mar 2018 – Dec 2020
- Automated weekly KPIs.

## Education
BSc Information Systems | King Saud University | 2014 – 2018

## Certifications
- Google Data Analytics | Google | 2022
- PMP | PMI | 2023

## Professional Development
- Advanced SQL Workshop, 2023

## Languages
Arabic: Native | English: Fluent
MD, 'en');

        $this->assertCount(2, $overlaid->experience ?? []);
        $first = $overlaid->experience[0];
        $this->assertSame('Senior Data Analyst', $first->title->en);
        $this->assertSame('Acme Corp', $first->company->en);
        $this->assertSame('Riyadh, KSA', $first->location->en);
        $this->assertSame('Jan 2021', $first->startDate);
        $this->assertTrue($first->isCurrent);
        $this->assertSame('Owned reporting for the retail division.', $first->narrative->en);
        $this->assertCount(2, $first->bullets);
        $this->assertSame('Dec 2020', $overlaid->experience[1]->endDate);

        $this->assertSame('King Saud University', $overlaid->education[0]->institution->en);
        $this->assertSame('2014', $overlaid->education[0]->startDate);

        $this->assertCount(2, $overlaid->certifications ?? []);
        $this->assertSame('Google', $overlaid->certifications[0]->issuer->en);
        $this->assertSame('2022', $overlaid->certifications[0]->date);

        $this->assertCount(5, $overlaid->skills ?? []);

        $this->assertCount(2, $overlaid->languages ?? []);
        $this->assertSame('English', $overlaid->languages[1]->name->en);
        $this->assertSame('Fluent', $overlaid->languages[1]->level->en);

        $this->assertCount(1, $overlaid->customSections ?? []);
        $this->assertSame('Professional Development', $overlaid->customSections[0]->title->en);

        $keys = array_column($overlaid->resolve('en')->structuredSections, 'key');
        $this->assertContains('languages', $keys);
        $this->assertContains('custom', $keys);
    }

    public function test_arabic_markdown_languages_and_training_sections_are_kept(): void
    {
        $sections = LegacySectionParser::markdownSections(<<<'MD'
# سارة أحمد

## الخبرة المهنية
محللة بيانات | شركة أكمي | الرياض | يناير 2021 – حتى الآن
- بناء لوحات تقارير.

## المهارات اللغوية
العربية: اللغة الأم | الإنجليزية: متقدم

## التطوير المهني
- دورة SQL المتقدمة

## معلومات التواصل
sara@example.com
MD);

        $this->assertArrayHasKey('experience', $sections);
        $this->assertArrayHasKey('languages', $sections);
        $this->assertArrayNotHasKey('skills', $sections);
        $this->assertSame([['title' => 'التطوير المهني', 'body' => '- دورة SQL المتقدمة']], $sections['custom']);

        $experience = LegacySectionParser::experience($sections['experience'], 'ar');
        $this->assertSame('شركة أكمي', $experience[0]->company->ar);
        $this->assertSame('يناير 2021', $experience[0]->startDate);
        $this->assertTrue($experience[0]->isCurrent);

        $languages = LegacySectionParser::languages($sections['languages'], 'ar');
        $this->assertCount(2, $languages);
        $this->assertSame('اللغة الأم', $languages[0]->level->ar);
    }

    public function test_title_words_that_start_like_months_are_not_taken_as_dates(): void
    {
        $entries = LegacySectionParser::experience('Marketing Manager | Acme | 2019 – 2021', 'en');

        $this->assertSame('Marketing Manager', $entries[0]->title->en);
        $this->assertSame('2019', $entries[0]->startDate);
    }

    /** Live Qwen run: the raw form parse looked "structured" and hid the AI's rewrite. */
    public function test_overlay_with_form_payload_replaces_entries_parsed_from_form_text(): void
    {
        $form = [
            'language' => 'ar',
            'full_name' => 'سارة العتيبي',
            'target_job_title' => 'أخصائية موارد بشرية',
            'experience_input' => 'أخصائية موارد بشرية في شركة المراعي، جدة، من 2021 حتى الآن. إدارة دورة التوظيف الكاملة، تطبيق نظام تقييم الأداء.',
            'education_input' => 'بكالوريوس إدارة أعمال، جامعة الملك عبدالعزيز، 2015 - 2019',
            'certifications_input' => 'شهادة SHRM-CP، جمعية إدارة الموارد البشرية، 2022. دورة قانون العمل السعودي 2023.',
            'skills_input' => 'التوظيف',
        ];
        // Stored the way GeneratedCvController saves it, then reloaded.
        $document = CvDocument::fromArray(CvDocument::fromLegacy($form)->toArray());
        $this->assertTrue($document->hasStructuredExperience());

        $overlaid = $document->overlayGeneratedMarkdown(<<<'MD'
## الكفاءات الأساسية
- التوظيف | إدارة الأداء | قانون العمل السعودي

## الخبرة المهنية
أخصائية موارد بشرية | شركة المراعي | جدة | 2021 – حتى الآن
- إدارة دورة التوظيف الكاملة.
- تطبيق نظام تقييم الأداء.

## التعليم
بكالوريوس إدارة أعمال | جامعة الملك عبدالعزيز | 2015 – 2019

## الشهادات
- شهادة SHRM-CP | جمعية إدارة الموارد البشرية | 2022
- دورة قانون العمل السعودي | 2023
MD, 'ar', null, $form);

        $this->assertSame('شركة المراعي', $overlaid->experience[0]->company->ar);
        $this->assertTrue($overlaid->experience[0]->isCurrent);
        $this->assertCount(2, $overlaid->experience[0]->bullets);
        $this->assertSame('جامعة الملك عبدالعزيز', $overlaid->education[0]->institution->ar);
        $this->assertCount(2, $overlaid->certifications ?? []);
        $this->assertSame('2022', $overlaid->certifications[0]->date);
        $this->assertSame('دورة قانون العمل السعودي', $overlaid->certifications[1]->name->ar);
        $this->assertSame('2023', $overlaid->certifications[1]->date);
        $this->assertCount(3, $overlaid->skills ?? []);
        $this->assertSame('قانون العمل السعودي', $overlaid->skills[2]->name->ar);
    }

    public function test_overlay_keeps_builder_entries_that_differ_from_the_form_text(): void
    {
        $form = ['language' => 'en', 'full_name' => 'Sara', 'target_job_title' => 'Engineer', 'experience_input' => 'Engineer at Acme, 2019 - 2021'];
        $builder = CvDocument::fromLegacy(['language' => 'en', 'full_name' => 'Sara', 'target_job_title' => 'Engineer', 'experience_input' => 'Lead Engineer | Hand Edited Co | 2019 - 2024']);

        $overlaid = $builder->overlayGeneratedMarkdown("## Experience\nEngineer | Acme | 2019 – 2021", 'en', null, $form);

        $this->assertSame('Hand Edited Co', $overlaid->experience[0]->company->en);
    }

    public function test_single_item_skill_group_keeps_its_category(): void
    {
        $document = CvDocument::fromLegacy(['language' => 'en', 'full_name' => 'Sara', 'target_job_title' => 'Analyst']);

        $overlaid = $document->overlayGeneratedMarkdown("## Core Competencies\nData Analysis | SQL, Excel\nProgramming | Python", 'en');

        $this->assertCount(3, $overlaid->skills ?? []);
        $this->assertSame('Python', $overlaid->skills[2]->name->en);
        $this->assertSame('Programming', $overlaid->skills[2]->category->en);
    }

    public function test_generic_language_label_lines_are_read(): void
    {
        $languages = LegacySectionParser::languages('Language: Arabic native | Language: English fluent', 'en');

        $this->assertSame('Arabic', $languages[0]->name->en);
        $this->assertSame('native', $languages[0]->level->en);
        $this->assertSame('English', $languages[1]->name->en);
    }
}
