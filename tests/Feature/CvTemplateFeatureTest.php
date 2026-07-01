<?php

namespace Tests\Feature;

use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CvTemplateFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_template_with_preview_and_set_default(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.cv-templates.store'), [
            'name_ar' => 'حديث',
            'name_en' => 'Modern',
            'slug' => 'modern-rtl',
            'renderer_key' => 'modern_rtl',
            'preview_image' => UploadedFile::fake()->image('preview.png', 420, 594),
            'language_direction' => 'rtl',
            'supported_languages' => ['ar', 'en'],
            'supported_sections' => ['summary', 'skills'],
            'primary_color' => '#1f2937',
            'accent_color' => '#2563eb',
            'is_active' => '1',
            'is_default' => '1',
            'sort_order' => 2,
        ]);

        $response->assertRedirect(route('admin.cv-templates.index'));
        $template = CvTemplate::firstOrFail();
        $this->assertSame('modern-rtl', $template->slug);
        $this->assertTrue($template->is_default);
        $this->assertTrue($template->is_active);
        $this->assertSame(['ar', 'en'], $template->supported_languages);
        $this->assertTrue(Storage::disk('public')->exists((string) $template->preview_image_path));
    }

    public function test_invalid_preview_upload_is_rejected(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->from('/admin')->post(route('admin.cv-templates.store'), [
            'name_ar' => 'قالب',
            'name_en' => 'Template',
            'slug' => 'template',
            'renderer_key' => 'classic_rtl',
            'preview_image' => UploadedFile::fake()->create('template.pdf', 20, 'application/pdf'),
            'language_direction' => 'rtl',
            'supported_languages' => ['ar'],
        ]);

        $response->assertRedirect('/admin');
        $response->assertSessionHasErrors('preview_image');
        $this->assertSame(0, CvTemplate::count());
    }

    public function test_mobile_template_list_only_returns_active_templates(): void
    {
        CvTemplate::create([
            'name_ar' => 'نشط',
            'name_en' => 'Active',
            'slug' => 'active',
            'renderer_key' => 'classic_rtl',
            'language_direction' => 'rtl',
            'supported_languages' => ['ar'],
            'is_active' => true,
            'is_default' => true,
        ]);
        CvTemplate::create([
            'name_ar' => 'مخفي',
            'name_en' => 'Hidden',
            'slug' => 'hidden',
            'renderer_key' => 'classic_rtl',
            'language_direction' => 'rtl',
            'supported_languages' => ['ar'],
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/mobile/cv-templates?lang=ar');

        $response->assertOk();
        $this->assertSame(['active'], collect($response->json('data.items'))->pluck('slug')->all());
    }

    public function test_pdf_export_falls_back_when_requested_template_is_inactive(): void
    {
        $user = User::factory()->create();
        $cv = GeneratedCv::create([
            'user_id' => $user->id,
            'full_name' => 'أحمد علي',
            'email' => 'ahmed@example.com',
            'phone' => '0500000000',
            'target_job_title' => 'محلل بيانات',
            'language' => 'ar',
            'skills_input' => 'SQL, Excel',
            'experience_input' => 'خبرة في تحليل البيانات وإعداد التقارير.',
            'education_input' => 'بكالوريوس نظم معلومات',
            'generated_markdown' => "# أحمد علي\n\n## الملخص\nمحلل بيانات.",
            'form_payload' => ['language' => 'ar'],
            'ai_status' => 'not_configured',
            'score_total' => 82,
            'grade' => 'B',
        ]);
        CvTemplate::create([
            'name_ar' => 'افتراضي',
            'name_en' => 'Default',
            'slug' => 'default',
            'renderer_key' => 'classic_rtl',
            'language_direction' => 'rtl',
            'supported_languages' => ['ar'],
            'is_active' => true,
            'is_default' => true,
        ]);
        CvTemplate::create([
            'name_ar' => 'غير نشط',
            'name_en' => 'Inactive',
            'slug' => 'inactive',
            'renderer_key' => 'modern_rtl',
            'language_direction' => 'rtl',
            'supported_languages' => ['ar'],
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->get('/api/generated-cvs/'.$cv->id.'/download?template=inactive');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_admin_cannot_archive_only_active_default_template(): void
    {
        $admin = User::factory()->create();
        $template = CvTemplate::create([
            'name_ar' => 'افتراضي',
            'name_en' => 'Default',
            'slug' => 'default',
            'renderer_key' => 'classic_rtl',
            'language_direction' => 'rtl',
            'supported_languages' => ['ar'],
            'is_active' => true,
            'is_default' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.cv-templates.destroy', $template));

        $response->assertRedirect(route('admin.cv-templates.index'));
        $this->assertFalse($template->fresh()->trashed());
    }
}
