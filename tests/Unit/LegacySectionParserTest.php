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
            'education_input' => "BSc Computer Science, King Saud University, 2016 - 2020",
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
}
