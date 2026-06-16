<?php

namespace Tests\Unit;

use App\Services\AtsScoringService;
use PHPUnit\Framework\TestCase;

class AtsScoringServiceTest extends TestCase
{
    public function test_it_scores_a_keyword_rich_resume(): void
    {
        $resume = <<<'CV'
Salem Sayer
Laravel Backend Developer
salem@example.com | +966591890300 | linkedin.com/in/salem

Summary
Backend developer with 5+ years building Laravel API platforms, SQL dashboards, and agile integrations.

Skills
PHP, Laravel, API, SQL, Git, Agile, Scrum, Backend, JavaScript

Experience
Backend Developer, Sirati, 2021 - 2025
- Developed Laravel APIs used by 25 internal users.
- Improved reporting speed by 35%.
- Built SQL dashboards and reduced support tickets by 20%.

Education
Bachelor of Computer Science, 2020

Certifications
AWS Certified Cloud Practitioner
CV;

        $score = (new AtsScoringService)->score($resume, 'Laravel Backend Developer');

        $this->assertGreaterThanOrEqual(70, $score['total']);
        $this->assertSame('software', $score['category']);
        $this->assertContains('laravel', $score['keywords_found']);
    }
}
