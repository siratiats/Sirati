<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Enums\AiStatus;
use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class WebAiThrottleAndGuestRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_post_analyze_exceeding_hourly_limit_returns_429(): void
    {
        $payload = [
            'target_job_title' => 'Software Engineer',
            'resume_text' => 'Software developer with 5 years experience in PHP and Laravel.',
        ];

        // 10 requests allowed under ai-heavy per hour
        for ($i = 1; $i <= 10; $i++) {
            $response = $this->post('/analyze', $payload);
            $response->assertRedirect();
        }

        // 11th request from same IP must return 429
        $response = $this->post('/analyze', $payload);

        $response
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'ai_rate_limit_short')
            ->assertHeader('Retry-After');
    }

    public function test_unauthenticated_post_generate_cv_exceeding_hourly_limit_returns_429(): void
    {
        $payload = [
            'full_name' => 'Sara Al-Otaibi',
            'email' => 'sara@example.com',
            'phone' => '0501234567',
            'target_job_title' => 'Product Manager',
            'language' => 'ar',
            'experience_input' => 'خبرة خمس سنوات في إدارة المنتجات الرقمية وتطوير استراتيجيات المنتجات التقنية.',
            'education_input' => 'بكالوريوس نظم معلومات إدارية من جامعة الملك سعود',
        ];

        // 10 requests allowed under ai-heavy per hour
        for ($i = 1; $i <= 10; $i++) {
            $response = $this->post('/generate-cv', $payload);
            $response->assertRedirect();
        }

        // 11th request from same IP must return 429
        $response = $this->post('/generate-cv', $payload);

        $response
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'ai_rate_limit_short')
            ->assertHeader('Retry-After');
    }

    public function test_unauthenticated_guest_analyze_uses_deterministic_ats_and_makes_zero_ai_calls(): void
    {
        // Mock CvAiProvider to assert it is never called
        $aiMock = Mockery::mock(CvAiProvider::class);
        $aiMock->shouldReceive('isConfigured')->andReturn(true);
        $aiMock->shouldNotReceive('analysisAdvice');
        $this->app->instance(CvAiProvider::class, $aiMock);

        $response = $this->post('/analyze', [
            'target_job_title' => 'Laravel Backend Developer',
            'resume_text' => $this->sampleResume(),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $analysis = CvAnalysis::latest()->first();
        $this->assertNotNull($analysis);
        $this->assertNull($analysis->user_id);
        $this->assertGreaterThan(0, $analysis->score_total);
        $this->assertNotEquals(AiStatus::Completed, $analysis->ai_status);
        $this->assertNull($analysis->ai_feedback);
    }

    public function test_prune_guest_analyses_command_removes_expired_guest_records_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00'));

        $user = User::factory()->create();

        // 1. Guest analysis older than 24 hours -> must be pruned
        $oldGuestAnalysis = $this->createAnalysis(null, Carbon::now()->subHours(25));

        // 2. Guest analysis younger than 24 hours -> must be kept
        $recentGuestAnalysis = $this->createAnalysis(null, Carbon::now()->subHours(10));

        // 3. Authenticated analysis older than 24 hours -> must be kept
        $oldAuthAnalysis = $this->createAnalysis($user->id, Carbon::now()->subHours(48));

        // 4. Guest GeneratedCv older than 24 hours -> must be pruned
        $oldGuestCv = $this->createGeneratedCv(null, Carbon::now()->subHours(25));

        // 5. Guest GeneratedCv younger than 24 hours -> must be kept
        $recentGuestCv = $this->createGeneratedCv(null, Carbon::now()->subHours(12));

        // 6. Authenticated GeneratedCv older than 24 hours -> must be kept
        $oldAuthCv = $this->createGeneratedCv($user->id, Carbon::now()->subHours(72));

        $this->artisan('analyses:prune-guests --hours=24')
            ->expectsOutputToContain('Pruned 1 guest analyses and 1 guest CVs older than 24 hours.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('cv_analyses', ['id' => $oldGuestAnalysis->id]);
        $this->assertDatabaseHas('cv_analyses', ['id' => $recentGuestAnalysis->id]);
        $this->assertDatabaseHas('cv_analyses', ['id' => $oldAuthAnalysis->id]);

        $this->assertDatabaseMissing('generated_cvs', ['id' => $oldGuestCv->id]);
        $this->assertDatabaseHas('generated_cvs', ['id' => $recentGuestCv->id]);
        $this->assertDatabaseHas('generated_cvs', ['id' => $oldAuthCv->id]);
    }

    private function createAnalysis(?int $userId, Carbon $createdAt): CvAnalysis
    {
        $analysis = CvAnalysis::create([
            'user_id' => $userId,
            'target_job_title' => 'Software Engineer',
            'original_filename' => null,
            'input_method' => 'text',
            'resume_text' => 'Sample resume text with sufficient length for testing.',
            'score_total' => 85,
            'grade' => 'A',
            'job_match' => 90,
            'criteria' => [],
            'strengths' => ['PHP'],
            'weaknesses' => [],
            'keywords_found' => ['PHP', 'Laravel'],
            'keywords_missing' => [],
            'quick_wins' => [],
            'ai_status' => AiStatus::NotConfigured,
        ]);

        $analysis->created_at = $createdAt;
        $analysis->saveQuietly();

        return $analysis;
    }

    private function createGeneratedCv(?int $userId, Carbon $createdAt): GeneratedCv
    {
        $cv = GeneratedCv::create([
            'user_id' => $userId,
            'full_name' => 'Fahad Al-Harbi',
            'email' => 'fahad@example.com',
            'phone' => '0555555555',
            'target_job_title' => 'DevOps Engineer',
            'skills_input' => 'Docker, Kubernetes, Linux, CI/CD',
            'language' => 'en',
            'experience_input' => 'DevOps engineer with 4 years experience deploying Docker and Kubernetes.',
            'education_input' => 'B.S. in Software Engineering',
            'generated_markdown' => '# Fahad Al-Harbi',
            'form_payload' => [],
            'document' => [],
            'score_total' => 80,
            'grade' => 'B+',
            'criteria' => [],
            'ai_status' => AiStatus::NotConfigured,
        ]);

        $cv->created_at = $createdAt;
        $cv->saveQuietly();

        return $cv;
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
