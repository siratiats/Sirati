<?php

namespace Tests\Feature;

use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Models\LandingLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CvMvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_cv_analysis_form_can_score_pasted_resume(): void
    {
        config(['services.openai.api_key' => null]);

        $response = $this->post('/analyze', [
            'target_job_title' => 'Laravel Backend Developer',
            'resume_text' => $this->sampleResume(),
        ]);

        $analysis = CvAnalysis::first();

        $response->assertRedirect(route('analyses.show', $analysis));
        $this->assertNotNull($analysis);
        $this->assertGreaterThan(50, $analysis->score_total);
        $this->assertSame('not_configured', $analysis->ai_status);
    }

    public function test_cv_generation_form_creates_local_template_without_openai(): void
    {
        config(['services.openai.api_key' => null]);

        $response = $this->post('/generate-cv', [
            'full_name' => 'Salem Sayer',
            'email' => 'salem@example.com',
            'phone' => '+966591890300',
            'linkedin' => 'https://linkedin.com/in/salem',
            'location' => 'Riyadh, Saudi Arabia',
            'target_job_title' => 'Laravel Backend Developer',
            'language' => 'en',
            'summary_input' => 'Backend developer focused on Laravel APIs and dashboards.',
            'skills_input' => 'Laravel, API, SQL, Git, Agile, Backend',
            'experience_input' => 'Backend Developer at Sirati from 2021 to 2025. Developed Laravel APIs for 25 users, improved reporting speed by 35%, and reduced support tickets by 20%.',
            'education_input' => 'Bachelor of Computer Science, 2020',
            'certifications_input' => 'AWS Certified Cloud Practitioner',
        ]);

        $generatedCv = GeneratedCv::first();

        $response->assertRedirect(route('generated-cvs.show', $generatedCv));
        $this->assertNotNull($generatedCv);
        $this->assertSame('not_configured', $generatedCv->ai_status);
        $this->assertStringContainsString('Salem Sayer', $generatedCv->generated_markdown);
        $this->assertGreaterThan(50, $generatedCv->score_total);
    }

    public function test_demo_cv_prefills_analysis_form(): void
    {
        $this->get('/analyze?demo=1')
            ->assertOk()
            ->assertSee('Salem Sayer')
            ->assertSee('Laravel Backend Developer');
    }

    public function test_generated_cv_can_be_downloaded_as_pdf(): void
    {
        $generatedCv = GeneratedCv::create([
            'full_name' => 'Salem Sayer',
            'email' => 'salem@example.com',
            'phone' => '+966591890300',
            'linkedin' => 'https://linkedin.com/in/salem',
            'location' => 'Riyadh',
            'target_job_title' => 'Laravel Backend Developer',
            'language' => 'en',
            'summary_input' => 'Backend developer.',
            'skills_input' => 'Laravel, API, SQL',
            'experience_input' => 'Developed Laravel APIs from 2021 to 2025 and improved reporting by 35%.',
            'education_input' => 'Bachelor of Computer Science, 2020',
            'certifications_input' => null,
            'generated_markdown' => "# Salem Sayer\nLaravel Backend Developer\n\n## Skills\nLaravel, API, SQL",
            'form_payload' => [],
            'ai_status' => 'not_configured',
            'score_total' => 70,
            'grade' => 'B',
            'criteria' => [],
        ]);

        $response = $this->get(route('generated-cvs.pdf', $generatedCv));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_analysis_recommendations_are_formatted_for_users(): void
    {
        $analysis = CvAnalysis::create([
            'target_job_title' => 'Laravel Backend Developer',
            'input_method' => 'paste',
            'resume_text' => $this->sampleResume(),
            'score_total' => 82,
            'grade' => 'A',
            'job_match' => 78,
            'criteria' => [],
            'strengths' => [],
            'weaknesses' => [],
            'keywords_found' => [],
            'keywords_missing' => [],
            'quick_wins' => [],
            'ai_status' => 'completed',
            'ai_feedback' => [
                'executive_summary' => 'السيرة قوية ومناسبة للدور المستهدف.',
                'top_priorities' => ['تعزيز قسم الخبرات بتفاصيل أكثر.'],
                'rewritten_summary' => 'مطور خلفية متخصص في Laravel.',
                'bullet_improvements' => [
                    [
                        'before' => '- Built APIs.',
                        'after' => '- صممت واجهات برمجة تطبيقات فعالة.',
                        'reason' => 'صياغة أوضح وأكثر تأثيراً.',
                    ],
                ],
                'keyword_recommendations' => ['Node.js'],
            ],
        ]);

        $this->get(route('analyses.show', $analysis))
            ->assertOk()
            ->assertSee('ملخص عام')
            ->assertSee('أهم الأولويات')
            ->assertSee('صياغة مقترحة للملخص المهني')
            ->assertSee('تحسينات مقترحة لنقاط الخبرة')
            ->assertSee('كلمات مفتاحية مقترحة')
            ->assertDontSee('executive_summary')
            ->assertDontSee('bullet_improvements');
    }

    public function test_admin_requires_token_when_configured(): void
    {
        config(['services.admin.access_token' => 'secret-token']);

        $this->get('/admin')->assertForbidden();
        $this->get('/admin?token=secret-token')
            ->assertOk()
            ->assertSee('لوحة متابعة النسخة الأولية');
    }

    public function test_admin_lists_mvp_activity(): void
    {
        LandingLead::create([
            'full_name' => 'Demo Lead',
            'email' => 'lead@example.com',
            'role_interest' => 'both',
            'source' => 'landing_page',
        ]);

        $this->post('/analyze', [
            'target_job_title' => 'Laravel Backend Developer',
            'resume_text' => $this->sampleResume(),
        ]);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Demo Lead')
            ->assertSee('Laravel Backend Developer');
    }

    private function sampleResume(): string
    {
        return <<<'CV'
Salem Sayer
Laravel Backend Developer
salem@example.com | +966591890300 | linkedin.com/in/salem

Summary
Backend developer with 5+ years of experience building Laravel API platforms and SQL dashboards.

Skills
PHP, Laravel, API, SQL, Git, Agile, Backend

Experience
Backend Developer, Sirati, 2021 - 2025
- Developed Laravel APIs for 25 users.
- Improved reporting speed by 35%.
- Reduced support tickets by 20%.

Education
Bachelor of Computer Science, 2020
CV;
    }
}
