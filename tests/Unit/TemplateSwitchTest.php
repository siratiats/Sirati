<?php

namespace Tests\Unit;

use App\Cv\CvDocument;
use App\Cv\LocalizedText;
use App\Cv\PersonalDetails;
use App\Cv\Project;
use App\Cv\Skill;
use App\Cv\TemplateSwitch;
use App\Models\CvTemplate;
use PHPUnit\Framework\TestCase;

class TemplateSwitchTest extends TestCase
{
    public function test_switch_preserves_document_and_warns_about_hidden_sections(): void
    {
        $document = new CvDocument(
            exportLanguage: 'en',
            personal: new PersonalDetails(fullName: LocalizedText::make(en: 'Sara')),
            summary: LocalizedText::make(en: 'Backend developer'),
            skills: [new Skill(name: LocalizedText::make(en: 'Laravel'))],
            projects: [new Project(name: LocalizedText::make(en: 'Sirati API'))],
        );

        $classic = new CvTemplate([
            'slug' => 'ats-classic-professional',
            'supported_sections' => ['summary', 'skills', 'experience', 'education', 'certifications'],
        ]);

        $result = (new TemplateSwitch)->evaluate($document, $classic);

        $this->assertSame($document->toArray(), $result->document->toArray());
        $this->assertSame(['projects'], $result->omittedSections);
        $this->assertTrue($result->hasWarning());
        $this->assertStringContainsString('projects', (string) $result->warningMessage('en'));
    }

    public function test_switch_is_reversible_with_no_data_loss(): void
    {
        $document = new CvDocument(
            exportLanguage: 'en',
            summary: LocalizedText::make(en: 'Summary'),
            projects: [new Project(name: LocalizedText::make(en: 'Open source'))],
        );

        $narrow = new CvTemplate([
            'slug' => 'classic',
            'supported_sections' => ['summary', 'experience'],
        ]);
        $wide = new CvTemplate([
            'slug' => 'bilingual-global-professional',
            'supported_sections' => ['summary', 'skills', 'experience', 'education', 'certifications', 'languages', 'projects'],
        ]);

        $switch = new TemplateSwitch;
        $hidden = $switch->evaluate($document, $narrow);
        $restored = $switch->evaluate($hidden->document, $wide);

        $this->assertSame($document->toArray(), $restored->document->toArray());
        $this->assertSame(['projects'], $hidden->omittedSections);
        $this->assertSame([], $restored->omittedSections);
    }

    public function test_empty_supported_sections_hides_nothing(): void
    {
        $document = new CvDocument(
            summary: LocalizedText::make(en: 'Summary'),
            projects: [new Project(name: LocalizedText::make(en: 'App'))],
        );

        $result = (new TemplateSwitch)->evaluate($document, new CvTemplate([
            'slug' => 'unknown',
            'supported_sections' => [],
        ]));

        $this->assertFalse($result->hasWarning());
    }
}
