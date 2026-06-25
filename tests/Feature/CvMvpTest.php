<?php

namespace Tests\Feature;

use App\Models\CvAnalysis;
use App\Models\EducationContent;
use App\Models\GeneratedCv;
use App\Models\JobNews;
use App\Models\LandingLead;
use App\Models\MobileNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    public function test_mobile_dashboard_endpoint_returns_summary(): void
    {
        $user = User::factory()->create(['name' => 'Aisha User']);
        Sanctum::actingAs($user);

        GeneratedCv::create([
            'user_id' => $user->id,
            'full_name' => 'Salem Sayer',
            'target_job_title' => 'Marketing Manager',
            'language' => 'en',
            'skills_input' => 'Marketing, SEO',
            'experience_input' => 'Managed campaigns with measurable outcomes for more than 80 characters across channels.',
            'education_input' => 'Bachelor of Marketing',
            'generated_markdown' => '# Salem Sayer',
            'form_payload' => [],
            'ai_status' => 'not_configured',
            'score_total' => 85,
            'grade' => 'B',
            'criteria' => [],
        ]);

        $this->getJson('/api/mobile/dashboard?lang=en')
            ->assertOk()
            ->assertJsonPath('data.profile.name', 'Aisha User')
            ->assertJsonPath('data.stats.generated_cvs', 1)
            ->assertJsonPath('data.primary_action.title', 'Create ATS-Optimized CV');
    }

    public function test_mobile_my_cvs_endpoint_returns_cv_cards(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        GeneratedCv::create([
            'user_id' => $user->id,
            'full_name' => 'Salem Sayer',
            'target_job_title' => 'Data Analyst',
            'language' => 'en',
            'skills_input' => 'SQL, Tableau',
            'experience_input' => 'Analyzed datasets and built dashboards with measurable outcomes for more than 80 characters.',
            'education_input' => 'Bachelor of Data Science',
            'generated_markdown' => '# Salem Sayer',
            'form_payload' => [],
            'ai_status' => 'not_configured',
            'score_total' => 92,
            'grade' => 'A',
            'criteria' => [],
        ]);

        $this->getJson('/api/mobile/my-cvs?lang=ar')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Data Analyst')
            ->assertJsonPath('data.items.0.badge', 'ATS 92%');
    }

    public function test_mobile_cv_data_is_scoped_to_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($owner);

        GeneratedCv::create([
            'user_id' => $owner->id,
            'full_name' => 'Owned CV',
            'target_job_title' => 'Owned Role',
            'language' => 'en',
            'skills_input' => 'SQL, Tableau',
            'experience_input' => 'Owned experience text with enough characters to pass validation and score well.',
            'education_input' => 'Bachelor of Data Science',
            'generated_markdown' => '# Owned CV',
            'form_payload' => [],
            'ai_status' => 'not_configured',
            'score_total' => 88,
            'grade' => 'A',
            'criteria' => [],
        ]);

        GeneratedCv::create([
            'user_id' => $otherUser->id,
            'full_name' => 'Other CV',
            'target_job_title' => 'Other Role',
            'language' => 'en',
            'skills_input' => 'Marketing',
            'experience_input' => 'Other experience text with enough characters to pass validation and score well.',
            'education_input' => 'Bachelor of Marketing',
            'generated_markdown' => '# Other CV',
            'form_payload' => [],
            'ai_status' => 'not_configured',
            'score_total' => 75,
            'grade' => 'B',
            'criteria' => [],
        ]);

        $this->getJson('/api/mobile/my-cvs?lang=en')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Owned Role');
    }

    public function test_generated_cv_can_be_updated_and_deleted_via_api(): void
    {
        config(['services.openai.api_key' => null]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $generatedCv = GeneratedCv::create([
            'user_id' => $user->id,
            'full_name' => 'Old Name',
            'email' => 'old@example.com',
            'phone' => '+966500000000',
            'linkedin' => null,
            'location' => 'Riyadh',
            'target_job_title' => 'Old Role',
            'language' => 'en',
            'summary_input' => 'Old summary.',
            'skills_input' => 'Old Skill',
            'experience_input' => 'Old experience text with enough characters to pass validation and produce a score.',
            'education_input' => 'Old education',
            'certifications_input' => null,
            'generated_markdown' => '# Old Name',
            'form_payload' => [],
            'ai_status' => 'not_configured',
            'score_total' => 50,
            'grade' => 'C',
            'criteria' => [],
        ]);

        $this->putJson("/api/generated-cvs/{$generatedCv->id}", [
            'full_name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '+966511111111',
            'linkedin' => 'https://linkedin.com/in/updated',
            'location' => 'Jeddah',
            'target_job_title' => 'Data Analyst',
            'language' => 'en',
            'summary_input' => 'Data analyst focused on business insights.',
            'skills_input' => 'SQL, Tableau, Python, Analytics',
            'experience_input' => 'Built dashboards and analyzed customer behavior across multiple teams with measurable results exceeding the required length.',
            'education_input' => 'Bachelor of Data Science',
            'certifications_input' => 'Google Data Analytics',
        ])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Updated Name')
            ->assertJsonPath('data.target_job_title', 'Data Analyst');

        $this->assertDatabaseHas('generated_cvs', [
            'id' => $generatedCv->id,
            'full_name' => 'Updated Name',
        ]);

        $this->deleteJson("/api/generated-cvs/{$generatedCv->id}")
            ->assertOk();

        $this->assertDatabaseMissing('generated_cvs', [
            'id' => $generatedCv->id,
        ]);
    }

    public function test_job_description_can_be_enhanced_before_generating_cv(): void
    {
        config(['services.openai.api_key' => null]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/generated-cvs/enhance-job-description', [
            'target_job_title' => 'Data Analyst',
            'job_description' => 'Analyze sales data and build dashboards.',
            'language' => 'en',
        ])
            ->assertOk()
            ->assertJsonPath('data.ai_status', 'not_configured')
            ->assertJsonPath('data.suggested_keywords.0', 'Data Analyst')
            ->assertJsonStructure(['data' => ['enhanced_description', 'suggested_keywords']]);
    }

    public function test_generated_cv_stores_job_description_input(): void
    {
        config(['services.openai.api_key' => null]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/generated-cvs', [
            'full_name' => 'Job Tailored User',
            'email' => 'tailored@example.com',
            'phone' => '+966500000000',
            'target_job_title' => 'Business Analyst',
            'job_description_input' => 'Own requirements gathering, dashboards, and stakeholder reporting.',
            'language' => 'en',
            'summary_input' => 'Business analyst focused on clear requirements and reporting.',
            'skills_input' => 'SQL, dashboards, requirements, reporting',
            'experience_input' => 'Worked with stakeholders to define reporting requirements, build dashboards, and improve decisions with measurable outcomes across teams.',
            'education_input' => 'Bachelor of Information Systems',
            'certifications_input' => null,
        ])
            ->assertCreated()
            ->assertJsonPath('data.job_description_input', 'Own requirements gathering, dashboards, and stakeholder reporting.');

        $this->assertDatabaseHas('generated_cvs', [
            'user_id' => $user->id,
            'job_description_input' => 'Own requirements gathering, dashboards, and stakeholder reporting.',
        ]);
    }

    public function test_mobile_education_endpoint_is_bilingual(): void
    {
        $this->getJson('/api/mobile/education?lang=en')
            ->assertOk()
            ->assertJsonPath('data.title', 'Learning & Development')
            ->assertJsonPath('data.featured_course.badge', 'Recommended');

        $this->getJson('/api/mobile/education?lang=ar')
            ->assertOk()
            ->assertJsonPath('data.title', 'التعلم والتطوير')
            ->assertJsonPath('data.featured_course.badge', 'موصى به لك');
    }

    public function test_mobile_education_detail_endpoint_returns_content(): void
    {
        $content = EducationContent::create([
            'language' => 'en',
            'type' => 'study',
            'title' => 'Portfolio Writing',
            'body' => 'Learn how to write a focused portfolio case study for recruiters.',
            'duration_label' => 'Reading time: 6 min',
            'target_role' => 'Product Designer',
            'badge' => 'Recommended',
            'is_published' => true,
        ]);

        $this->getJson("/api/mobile/education/{$content->id}?lang=en")
            ->assertOk()
            ->assertJsonPath('data.title', 'Portfolio Writing')
            ->assertJsonPath('data.target_role', 'Product Designer');
    }

    public function test_admin_can_manage_mobile_education_content(): void
    {
        $admin = User::factory()->create(['email' => 'admin@sirati.test']);
        config(['services.admin.emails' => ['admin@sirati.test']]);

        $this->actingAs($admin)
            ->post(route('admin.education-contents.store'), [
                'language' => 'ar',
                'type' => 'study',
                'title' => 'تحليل البيانات من لوحة الإدارة',
                'body' => 'محتوى تعليمي مضاف من لوحة الإدارة ويظهر في تطبيق الجوال.',
                'duration_label' => 'مدة القراءة: ٧ دقائق',
                'target_role' => 'محلل بيانات',
                'sort_order' => 1,
                'is_published' => '1',
            ])
            ->assertRedirect(route('admin.index'));

        $this->assertDatabaseHas('education_contents', [
            'title' => 'تحليل البيانات من لوحة الإدارة',
            'is_published' => true,
        ]);

        $this->getJson('/api/mobile/education?lang=ar')
            ->assertOk()
            ->assertJsonPath('data.study_cards.0.title', 'تحليل البيانات من لوحة الإدارة');

        $content = EducationContent::firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.education-contents.destroy', $content))
            ->assertRedirect(route('admin.index'));

        $this->assertDatabaseMissing('education_contents', [
            'title' => 'تحليل البيانات من لوحة الإدارة',
        ]);
    }

    public function test_job_news_can_be_managed_and_read_by_mobile_api(): void
    {
        $admin = User::factory()->create(['email' => 'admin@sirati.test']);
        config(['services.admin.emails' => ['admin@sirati.test']]);

        $this->actingAs($admin)
            ->post(route('admin.job-news.store'), [
                'language' => 'en',
                'title' => 'Data Analyst hiring wave',
                'company' => 'Sirati Labs',
                'location' => 'Riyadh',
                'body' => 'Several companies are hiring data analysts with SQL and dashboard skills.',
                'url' => 'https://example.com/jobs/data-analyst',
                'published_at' => '2026-06-24 10:00:00',
                'sort_order' => 1,
                'is_published' => '1',
            ])
            ->assertRedirect(route('admin.index'));

        $this->getJson('/api/mobile/job-news?lang=en')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Data Analyst hiring wave')
            ->assertJsonPath('data.items.0.company', 'Sirati Labs');

        $item = JobNews::firstOrFail();

        $this->getJson("/api/mobile/job-news/{$item->id}?lang=en")
            ->assertOk()
            ->assertJsonPath('data.title', 'Data Analyst hiring wave');

        $this->actingAs($admin)
            ->delete(route('admin.job-news.destroy', $item))
            ->assertRedirect(route('admin.index'));

        $this->assertDatabaseMissing('job_news', [
            'title' => 'Data Analyst hiring wave',
        ]);
    }

    public function test_mobile_notifications_can_be_listed_and_marked_read(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $notification = MobileNotification::create([
            'user_id' => $user->id,
            'type' => 'cv_ready',
            'title' => 'Your CV is ready',
            'body' => 'Open My CVs to download your latest file.',
        ]);

        $this->getJson('/api/mobile/dashboard?lang=en')
            ->assertOk()
            ->assertJsonPath('data.stats.unread_notifications', 1);

        $this->getJson('/api/mobile/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.items.0.title', 'Your CV is ready')
            ->assertJsonPath('data.items.0.is_read', false);

        $this->postJson("/api/mobile/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_mobile_notifications_are_user_scoped(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $otherNotification = MobileNotification::create([
            'user_id' => $otherUser->id,
            'title' => 'Other user notification',
            'body' => 'This should not be visible.',
        ]);

        $this->getJson('/api/mobile/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->postJson("/api/mobile/notifications/{$otherNotification->id}/read")
            ->assertNotFound();
    }

    public function test_privacy_policy_page_is_available(): void
    {
        $this->get(route('privacy-policy'))
            ->assertOk()
            ->assertSee('سياسة الخصوصية');
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

    public function test_admin_redirects_guests_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_admin_can_login_with_allowed_account(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@sirati.test',
        ]);

        config(['services.admin.emails' => ['admin@sirati.test']]);

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.index'));

        $this->assertAuthenticatedAs($user);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('لوحة متابعة النسخة الأولية');
    }

    public function test_admin_rejects_unlisted_account_when_admin_emails_are_configured(): void
    {
        $user = User::factory()->create([
            'email' => 'user@sirati.test',
        ]);

        config(['services.admin.emails' => ['admin@sirati.test']]);

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_can_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@sirati.test',
        ]);

        config(['services.admin.emails' => ['admin@sirati.test']]);

        $this->actingAs($user)
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
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

        $this->actingAs(User::factory()->create())
            ->get('/admin')
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
