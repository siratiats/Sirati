<?php

namespace Tests\Feature;

use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\CvTemplateRenderer;
use App\Services\EntitlementService;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PremiumTemplateGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CvTemplateSeeder::class);
    }

    public function test_free_user_can_export_free_template(): void
    {
        $freeUser = User::factory()->create(['is_premium' => false]);
        $cv = $this->createCvForUser($freeUser);

        $response = $this->actingAs($freeUser)
            ->getJson("/api/generated-cvs/{$cv->id}/download?template=ats-classic-professional");

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_free_user_preview_of_premium_template_includes_watermark(): void
    {
        $freeUser = User::factory()->create(['is_premium' => false]);
        $cv = $this->createCvForUser($freeUser);

        $response = $this->actingAs($freeUser)
            ->getJson("/api/generated-cvs/{$cv->id}/preview?template=executive-leadership-brief");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'is_premium' => true,
                'is_watermarked' => true,
            ],
        ]);

        $html = $response->json('data.html');
        $this->assertStringContainsString('معاينة', $html);
    }

    public function test_free_user_is_blocked_with_403_when_exporting_premium_template(): void
    {
        $freeUser = User::factory()->create(['is_premium' => false]);
        $cv = $this->createCvForUser($freeUser);

        $response = $this->actingAs($freeUser)
            ->getJson("/api/generated-cvs/{$cv->id}/download?template=executive-leadership-brief");

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'premium_template_locked',
        ]);
    }

    public function test_premium_user_can_export_premium_template_without_restriction(): void
    {
        $premiumUser = User::factory()->create(['is_premium' => true]);
        $cv = $this->createCvForUser($premiumUser);

        $response = $this->actingAs($premiumUser)
            ->getJson("/api/generated-cvs/{$cv->id}/download?template=executive-leadership-brief");

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_unsigned_requests_to_pdf_download_routes_are_blocked_with_403(): void
    {
        $owner = User::factory()->create(['is_premium' => false]);
        $cv = $this->createCvForUser($owner);
        $stranger = User::factory()->create(['is_premium' => false]);

        // Unauthenticated access to API pdf route without signature -> 403
        $this->get("/api/generated-cvs/{$cv->id}/pdf")->assertStatus(403);

        // Unauthenticated access to Web pdf route without signature -> 403
        $this->get("/generated-cvs/{$cv->id}/pdf")->assertStatus(403);

        // Authenticated stranger access to Web pdf route without signature -> 403
        $this->actingAs($stranger)->get("/generated-cvs/{$cv->id}/pdf")->assertStatus(403);
    }

    public function test_paying_user_can_download_premium_template_via_signed_url_without_auth_session(): void
    {
        $premiumUser = User::factory()->create(['is_premium' => true]);
        $cv = $this->createCvForUser($premiumUser);

        $signedUrl = URL::temporarySignedRoute(
            'api.generated-cvs.pdf',
            now()->addMinutes(30),
            ['generatedCv' => $cv->id]
        );

        // Mobile app flow: unauthenticated GET to signed URL with template parameter
        $response = $this->get($signedUrl.'&template=executive-leadership-brief');

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_free_user_download_of_premium_template_via_signed_url_is_blocked_with_403(): void
    {
        $freeUser = User::factory()->create(['is_premium' => false]);
        $cv = $this->createCvForUser($freeUser);

        $signedUrl = URL::temporarySignedRoute(
            'api.generated-cvs.pdf',
            now()->addMinutes(30),
            ['generatedCv' => $cv->id]
        );

        // Unauthenticated GET to signed URL requesting a premium template when user is free tier
        $response = $this->get($signedUrl.'&template=executive-leadership-brief');

        $response->assertStatus(403);
    }

    public function test_paying_user_can_download_premium_template_on_web_route_with_valid_signature(): void
    {
        $premiumUser = User::factory()->create(['is_premium' => true]);
        $cv = $this->createCvForUser($premiumUser);

        $signedUrl = URL::temporarySignedRoute(
            'generated-cvs.pdf',
            now()->addMinutes(30),
            ['generatedCv' => $cv->id]
        );

        $response = $this->get($signedUrl.'&template=executive-leadership-brief');

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_inactive_template_cannot_be_previewed(): void
    {
        $template = CvTemplate::where('slug', 'executive-leadership-brief')->firstOrFail();
        $template->update(['is_active' => false]);

        $entitlements = app(EntitlementService::class);
        $user = User::factory()->create();
        $cv = $this->createCvForUser($user);

        $this->assertFalse($entitlements->canPreviewTemplate($user, $template));

        $response = $this->actingAs($user)
            ->getJson("/api/generated-cvs/{$cv->id}/preview?template=executive-leadership-brief");

        $response->assertStatus(404);
    }

    private function createCvForUser(User $user): GeneratedCv
    {
        return GeneratedCv::create([
            'user_id' => $user->id,
            'full_name' => 'طارق الزهراني',
            'email' => 'tariq@example.com',
            'phone' => '+966512345678',
            'location' => 'الدمام',
            'target_job_title' => 'مستشار مالي',
            'language' => 'ar',
            'summary_input' => 'خبير مالي واستثماري.',
            'skills_input' => 'التحليل المالي، إدارة المحافظ',
            'experience_input' => 'عشر سنوات في القطاع المصرفي.',
            'education_input' => 'بكالوريوس علوم مالية ومصرفية.',
            'generated_markdown' => '',
            'form_payload' => ['language' => 'ar'],
            'ai_status' => 'completed',
            'score_total' => 90,
            'grade' => 'A',
        ]);
    }
}
