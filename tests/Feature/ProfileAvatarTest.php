<?php

namespace Tests\Feature;

use App\Enums\AiStatus;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\CvTemplateRenderer;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Salem review, issue 5: a profile photo for the profile and the CV. */
class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_upload_replace_and_remove_keep_one_file_and_the_payload_in_step(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $paths = [];
        foreach (['png', 'jpg', 'webp'] as $extension) {
            $url = $this->post('/api/auth/avatar', [
                'avatar' => UploadedFile::fake()->image("me.$extension", 512, 512),
            ], ['Accept' => 'application/json'])
                ->assertOk()
                ->json('data.avatar_url');

            $path = $user->fresh()->avatar_path;
            $this->assertNotNull($path);
            $this->assertMatchesRegularExpression(
                '/^avatars\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(png|jpe?g|webp)$/',
                $path,
                'random file names never reveal the account',
            );
            Storage::disk('public')->assertExists($path);
            $this->assertStringEndsWith('storage/'.$path, $url);
            $paths[] = $path;

            // Only the latest photo is kept.
            $this->assertSame([$path], Storage::disk('public')->files('avatars'));
        }
        $this->assertSame($paths, array_unique($paths));

        $this->getJson('/api/auth/me')->assertJsonPath('data.avatar_url', fn ($url) => str_ends_with($url, end($paths)));

        $this->deleteJson('/api/auth/avatar')->assertOk()->assertJsonPath('data.avatar_url', null);
        $this->assertNull($user->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->files('avatars'));
        // Removing twice is harmless.
        $this->deleteJson('/api/auth/avatar')->assertOk();
    }

    public function test_only_reasonable_images_are_accepted(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach ([
            'not an image' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
            'svg' => UploadedFile::fake()->create('me.svg', 5, 'image/svg+xml'),
            'too small' => UploadedFile::fake()->image('me.png', 40, 40),
            'too large' => UploadedFile::fake()->image('me.jpg', 600, 600)->size(6000),
        ] as $label => $file) {
            $this->post('/api/auth/avatar', ['avatar' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('avatar');
            $this->assertNull($user->fresh()->avatar_path, $label);
        }
        $this->post('/api/auth/avatar', [], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_deleting_the_account_deletes_the_photo(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        Sanctum::actingAs($user);
        $this->post('/api/auth/avatar', [
            'avatar' => UploadedFile::fake()->image('me.png', 300, 300),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotEmpty(Storage::disk('public')->files('avatars'));

        $this->deleteJson('/api/auth/account', ['password' => 'password123'])->assertOk();
        $this->assertSame([], Storage::disk('public')->files('avatars'));
    }

    public function test_the_photo_is_on_the_cv_and_its_pdf_only_when_set(): void
    {
        $this->seed(CvTemplateSeeder::class);
        $user = User::factory()->create();
        $cv = GeneratedCv::create([
            'user_id' => $user->id,
            'full_name' => 'Sara Ahmed',
            'email' => 'sara@example.com',
            'phone' => '+966500000000',
            'target_job_title' => 'Engineer',
            'language' => 'en',
            'skills_input' => 'PHP',
            'experience_input' => "Engineer | Acme | 01/2020 - 02/2022\n- Built APIs.",
            'education_input' => 'BSc | KSU | 09/2015 - 06/2019',
            'generated_markdown' => '',
            'form_payload' => [],
            'ai_status' => AiStatus::Completed,
            'score_total' => 80,
            'grade' => 'B',
        ]);
        $renderer = app(CvTemplateRenderer::class);

        $without = $renderer->renderHtml($cv->fresh(), 'ats-classic-professional', forExport: true);
        $this->assertStringNotContainsString('class="photo"', $without);
        $template = $renderer->resolve('ats-classic-professional', 'en');
        $keyWithout = $renderer->pdfCacheKey($cv->fresh(), $template, 'en');

        Sanctum::actingAs($user);
        $this->post('/api/auth/avatar', [
            'avatar' => UploadedFile::fake()->image('me.png', 256, 256),
        ], ['Accept' => 'application/json'])->assertOk();

        $with = $renderer->renderHtml($cv->fresh(), 'ats-classic-professional', forExport: true);
        $this->assertMatchesRegularExpression('/<img class="photo" src="data:image\/png;base64,[A-Za-z0-9+\/=]+"/', $with);

        $this->assertNotSame($keyWithout, $renderer->pdfCacheKey($cv->fresh(), $template, 'en'),
            'a cached PDF without the photo is never served after adding one');
        $pdf = $renderer->renderPdfBlob($cv->fresh(), $template, 'en');
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf, 'the photo is embedded in the PDF');
    }
}
