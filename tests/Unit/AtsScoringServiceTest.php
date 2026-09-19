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

        $scorer = new AtsScoringService;
        $score = $scorer->score($resume, 'Laravel Backend Developer');

        $this->assertGreaterThanOrEqual(70, $score['total']);
        $this->assertSame('software', $score['category']);
        $this->assertContains('laravel', $score['keywords_found']);

        // Optional category hint must not be required — scores stay stable when omitted.
        $again = $scorer->score($resume, 'Laravel Backend Developer');
        $this->assertSame($score['total'], $again['total']);
        $this->assertSame($score['criteria'], $again['criteria']);
        $this->assertSame($score['category'], $again['category']);

        // When provided, a valid hint is authoritative over free-text inference.
        $hinted = $scorer->score($resume, 'Laravel Backend Developer', 'finance');
        $this->assertSame('finance', $hinted['category']);
    }

    public function test_extract_job_terms_returns_ranked_terms_and_drops_boilerplate(): void
    {
        $scorer = new AtsScoringService;

        $jd = <<<'JD'
We are looking for a Senior Backend Engineer to work with our platform team.
Responsibilities:
- Design and ship REST APIs in Laravel and PHP
- Own PostgreSQL schema evolution and Redis caching strategy
- Mentor mid-level engineers on distributed systems and Kubernetes

Requirements:
- Strong experience with PHP, Laravel, and PostgreSQL
- Solid knowledge of distributed systems, Docker, and Kubernetes
- Ability to work with product managers and designers

Nice to have: AWS, Terraform
JD;

        $terms = $scorer->extractJobTerms($jd);

        // Substantive tech terms survive
        $this->assertContains('laravel', $terms, 'laravel should be present');
        $this->assertContains('php', $terms, 'php should be present');
        $this->assertContains('postgresql', $terms, 'postgresql should be present');
        $this->assertContains('kubernetes', $terms, 'kubernetes should be present');

        // Boilerplate stopwords are dropped
        $this->assertNotContains('the', $terms);
        $this->assertNotContains('with', $terms);
        $this->assertNotContains('for', $terms);
        $this->assertNotContains('and', $terms);
        $this->assertNotContains('work', $terms);

        // Bigrams that appear repeatedly win over their component unigrams
        $this->assertContains('distributed systems', $terms);
        // (No orphan "distributed" unigram alongside the bigram)
        $this->assertNotContains('distributed', $terms);

        // Cap enforced
        $this->assertLessThanOrEqual(25, count($terms));

        // Empty / whitespace JD returns []
        $this->assertSame([], $scorer->extractJobTerms(''));
        $this->assertSame([], $scorer->extractJobTerms("   \n  "));
    }

    public function test_job_description_wins_over_category_keyword_list(): void
    {
        $scorer = new AtsScoringService;

        // A CV that matches a niche JD term ("terraform") but few generic 'software' category terms.
        $resume = <<<'CV'
Sara Ali
Platform Engineer
sara@example.com | +966501234567 | linkedin.com/in/saraali

Summary
Platform engineer with 4 years running Terraform and Kubernetes for a SaaS product.

Skills
Terraform, Kubernetes, Docker, PostgreSQL, Prometheus, Grafana

Experience
Platform Engineer, DataCo, 2022 - 2025
- Migrated infrastructure to Terraform, reducing drift by 90%.
- Ran Kubernetes clusters serving 4.2M requests per day at 99.98% uptime.
- Implemented Prometheus + Grafana observability across 12 services.

Education
Bachelor of Computer Engineering, 2021

Certifications
HashiCorp Terraform Associate
CV;

        $jd = 'Senior Platform Engineer — Terraform, Kubernetes, PostgreSQL, Docker required. '
            .'Prometheus and Grafana experience a plus. AWS or GCP background welcome.';

        $withJd = $scorer->score($resume, 'Platform Engineer', null, $jd);
        $withoutJd = $scorer->score($resume, 'Platform Engineer', null, null);

        $this->assertSame('job_description', $withJd['keyword_source']);
        $this->assertSame('category', $withoutJd['keyword_source']);

        // With the JD in, actual role-specific terms show up as "found".
        $this->assertContains('terraform', $withJd['keywords_found']);
        $this->assertContains('kubernetes', $withJd['keywords_found']);

        // job_match is pure coverage (no title-match / early-mention bonuses baked in),
        // so it never exceeds 100 even when the CV also matches the title strongly.
        $this->assertLessThanOrEqual(100, $withJd['job_match']);
        $this->assertGreaterThanOrEqual(0, $withJd['job_match']);
    }

    public function test_missing_keywords_come_from_the_job_description_when_provided(): void
    {
        $scorer = new AtsScoringService;

        // Backend CV mentioning Node + Postgres only.
        $resume = <<<'CV'
Ahmed Nasser
Backend Developer
ahmed@example.com | +966555000111 | linkedin.com/in/ahmedn

Summary
Backend developer building Node.js services on PostgreSQL for 3 years.

Skills
Node.js, PostgreSQL, Docker, Git

Experience
Backend Developer, Ventures Inc, 2022 - 2025
- Built REST APIs in Node handling 500 requests per second.
- Migrated PostgreSQL 12 to 15 with zero downtime.
- Reduced deploy time from 40 minutes to 6 minutes.

Education
Bachelor of Computer Science, 2021
CV;

        $jd = 'Backend Developer needed. Must know Ruby on Rails, Sidekiq, and Kafka. '
            .'PostgreSQL a plus. Familiarity with Kubernetes helpful.';

        $result = $scorer->score($resume, 'Backend Developer', null, $jd);

        // The scorer must complain about the JD-specific terms the CV lacks (rails/sidekiq/kafka),
        // NOT about hardcoded category terms like flutter/react/scrum which are irrelevant here.
        $missing = array_map('strtolower', $result['keywords_missing']);
        $this->assertContains('rails', $missing, 'rails is in the JD but not the CV — must be flagged');
        $this->assertNotContains('flutter', $missing, 'flutter is not in the JD; must not be suggested');
        $this->assertNotContains('react', $missing, 'react is not in the JD; must not be suggested');
        $this->assertNotContains('scrum', $missing, 'scrum is not in the JD; must not be suggested');
    }
}
