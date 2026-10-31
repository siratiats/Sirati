<?php

namespace Tests\Feature;

use App\Enums\AiStatus;
use App\Jobs\RenderCvPdfJob;
use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\Cv\CvMarkdownRenderer;
use App\Services\CvTemplateRenderer;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class CvPdfArtifactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::set('public', Storage::fake('cv_pdf_photos_'.getmypid(), config('filesystems.disks.public')));
        Queue::fake();
        config(['monetization.enabled' => false]);
        $this->seed(CvTemplateSeeder::class);
    }

    public function test_repeated_downloads_return_the_same_valid_artifact_without_rendering_again(): void
    {
        $renderer = new class(app(CvMarkdownRenderer::class)) extends CvTemplateRenderer
        {
            public int $viewModels = 0;

            public function viewModel(GeneratedCv $generatedCv, CvTemplate $template, bool $watermark = false, ?string $languageOverride = null, bool $forExport = false): array
            {
                $this->viewModels++;

                return parent::viewModel($generatedCv, $template, $watermark, $languageOverride, $forExport);
            }
        };
        $this->app->instance(CvTemplateRenderer::class, $renderer);
        Log::spy();

        foreach (['en' => 'Maya Noor Patel', 'ar' => 'ليلى نور الحربي'] as $language => $name) {
            $cv = $this->cv(['language' => $language, 'full_name' => $name]);
            Sanctum::actingAs($cv->user);
            $before = $renderer->viewModels;
            $first = $this->getJson('/api/generated-cvs/'.$cv->id.'/download')->assertOk();
            $bytes = $first->getContent();
            $this->assertStringStartsWith('%PDF-', $bytes);
            $this->assertStringContainsStringIgnoringCase($name, (new Parser)->parseContent($bytes)->getText());
            $this->assertSame($before + 1, $renderer->viewModels, 'Build the view model only once per render.');

            $second = $this->getJson('/api/generated-cvs/'.$cv->id.'/download')->assertOk();
            $this->assertSame($bytes, $second->getContent());
            $this->assertSame($before + 1, $renderer->viewModels, 'A cache hit must never enter the renderer.');
            $path = $this->path($cv, $renderer->resolve(null, $language), $language);
            $this->assertSame($bytes, Storage::disk('cv_pdfs')->get($path));
            $this->assertSame('private', config('filesystems.disks.cv_pdfs.visibility'));
            $this->assertFalse(config('filesystems.disks.cv_pdfs.serve'));
            $this->assertFalse(str_starts_with(Storage::disk('cv_pdfs')->path($path), public_path()));
            Log::shouldHaveReceived('info')->with('CV PDF download', \Mockery::on(fn (array $data) => $data['cv_id'] === $cv->id && $data['cache'] === 'miss' && $data['render_ms'] >= 0 && $data['bytes'] === strlen($bytes)))->once();
            Log::shouldHaveReceived('info')->with('CV PDF download', \Mockery::on(fn (array $data) => $data['cv_id'] === $cv->id && $data['cache'] === 'hit' && ! isset($data['render_ms']) && $data['bytes'] === strlen($bytes)))->once();
        }
    }

    public function test_fingerprint_tracks_render_inputs_even_without_a_timestamp_change(): void
    {
        $cv = $this->cv();
        $renderer = app(CvTemplateRenderer::class);
        $template = $renderer->resolve(null, 'en');
        $original = $renderer->pdfFingerprint($cv, $template, 'en');
        $this->assertSame($original, $renderer->pdfFingerprint($cv->fresh(), $template->fresh(), 'en'));

        $mutations = [
            'markdown' => fn (GeneratedCv $copy) => $copy->generated_markdown .= "\nNew achievement ".bin2hex(random_bytes(8)),
            'identity' => fn (GeneratedCv $copy) => $copy->full_name = 'Candidate '.bin2hex(random_bytes(8)),
            'structured document' => fn (GeneratedCv $copy) => $copy->document = array_replace_recursive($copy->cvDocument()->toArray(), ['summary' => ['en' => 'Changed '.bin2hex(random_bytes(8))]]),
            'status' => fn (GeneratedCv $copy) => $copy->ai_status = AiStatus::Processing,
            'timestamp' => fn (GeneratedCv $copy) => $copy->updated_at = $copy->updated_at->addSecond(),
        ];
        foreach ($mutations as $label => $mutate) {
            $copy = clone $cv;
            $mutate($copy);
            $this->assertNotSame($original, $renderer->pdfFingerprint($copy, $template, 'en'), $label);
        }
        $otherTemplate = clone $template;
        $otherTemplate->slug = 'another-template';
        $this->assertNotSame($original, $renderer->pdfFingerprint($cv, $otherTemplate, 'en'));
        $this->assertNotSame($original, $renderer->pdfFingerprint($cv, $template, 'ar'));
        foreach (['config_json' => ['style' => ['name_size' => 24]], 'color_tokens' => ['primary' => '#223344'], 'updated_at' => now()->addDay()] as $field => $value) {
            $changed = clone $template;
            $changed->$field = $value;
            $this->assertNotSame($original, $renderer->pdfFingerprint($cv, $changed, 'en'), $field);
        }

        $cv->user->forceFill(['avatar_path' => 'avatars/same.jpg'])->save();
        $cv = $cv->fresh();
        $disk = Storage::disk('public');
        $disk->put('avatars/same.jpg', 'first image');
        $timestamp = $disk->lastModified('avatars/same.jpg');
        $withPhoto = $renderer->pdfFingerprint($cv, $template, 'en');
        $this->assertNotSame($original, $withPhoto);
        $disk->put('avatars/same.jpg', 'other image');
        touch($disk->path('avatars/same.jpg'), $timestamp);
        $this->assertNotSame($withPhoto, $renderer->pdfFingerprint($cv, $template, 'en'), 'Same path, length and mtime must still detect changed photo bytes.');
    }

    public function test_new_artifact_prunes_only_its_template_and_language(): void
    {
        $cv = $this->cv();
        Sanctum::actingAs($cv->user);
        $renderer = app(CvTemplateRenderer::class);
        $template = $renderer->resolve(null, 'en');
        $otherTemplate = $template->replicate();
        $otherTemplate->slug = 'alternate-layout';
        $otherTemplate->is_default = false;
        $otherTemplate->save();
        foreach ([[$template, 'en'], [$template, 'ar'], [$otherTemplate, 'en']] as [$layout, $language]) {
            $this->getJson('/api/generated-cvs/'.$cv->id.'/download?template='.$layout->slug.'&language='.$language)->assertOk();
        }
        $old = $this->path($cv, $template, 'en');
        $retained = [$this->path($cv, $template, 'ar'), $this->path($cv, $otherTemplate, 'en')];
        $cv->update(['full_name' => 'Changed Candidate '.bin2hex(random_bytes(4))]);
        $response = $this->getJson('/api/generated-cvs/'.$cv->id.'/download')->assertOk();
        $this->assertStringContainsStringIgnoringCase($cv->full_name, (new Parser)->parseContent($response->getContent())->getText());
        Storage::disk('cv_pdfs')->assertMissing($old);
        Storage::disk('cv_pdfs')->assertExists($retained);
        $this->assertCount(3, Storage::disk('cv_pdfs')->files('cv-pdfs/'.$cv->id));
    }

    public function test_stored_artifact_cannot_bypass_owner_premium_or_quality_checks(): void
    {
        $cv = $this->cv();
        $user = $cv->user;
        $user->forceFill(['is_premium' => true])->save();
        $template = app(CvTemplateRenderer::class)->resolve(null, 'en');
        $template->update(['is_premium' => true]);
        Sanctum::actingAs($user);
        $signed = URL::temporarySignedRoute('api.generated-cvs.pdf', now()->addHour(), ['generatedCv' => $cv->id]);
        $this->getJson('/api/generated-cvs/'.$cv->id.'/download')->assertOk();
        $path = $this->path($cv, $template, 'en');
        Storage::disk('cv_pdfs')->assertExists($path);
        $user->revokeSubscription();
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/generated-cvs/'.$cv->id.'/download')->assertStatus(402);
        $this->get($signed)->assertStatus(402);
        Storage::disk('cv_pdfs')->assertExists($path);

        $template->update(['is_premium' => false]);
        $cv->update(['phone' => '']);
        $this->getJson('/api/generated-cvs/'.$cv->id.'/download')->assertStatus(422);
        $this->get($signed)->assertStatus(422);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/generated-cvs/'.$cv->id.'/download')->assertNotFound();
        $this->get('/api/generated-cvs/'.$cv->id.'/pdf')->assertForbidden();
    }

    public function test_observer_dispatches_once_on_completion_and_prerender_is_downloaded(): void
    {
        config(['cv_pdf.queue' => 'pdfs']);
        $cv = $this->cv(['ai_status' => AiStatus::Processing]);
        Queue::assertNotPushed(RenderCvPdfJob::class);
        $cv->update(['ai_status' => AiStatus::Completed]);
        $cv->save();
        Queue::assertPushed(RenderCvPdfJob::class, 1);
        $job = Queue::pushed(RenderCvPdfJob::class)->first();
        $this->assertTrue($job->afterCommit);
        $this->assertSame('pdfs', $job->queue);
        $renderer = app(CvTemplateRenderer::class);
        $this->assertSame($renderer->pdfFingerprint($cv->fresh(), $renderer->resolve(null, 'en'), 'en'), $job->fingerprint);
        $job->handle($renderer);
        $path = $this->path($cv, $renderer->resolve(null, 'en'), 'en');
        Storage::disk('cv_pdfs')->assertExists($path);
        Sanctum::actingAs($cv->user);
        $response = $this->getJson('/api/generated-cvs/'.$cv->id.'/download')->assertOk();
        $this->assertSame(Storage::disk('cv_pdfs')->get($path), $response->getContent());
        $this->assertStringContainsStringIgnoringCase($cv->full_name, (new Parser)->parseContent($response->getContent())->getText());
    }

    public function test_unique_jobs_and_related_input_changes(): void
    {
        $cv = $this->cv();
        Queue::assertPushed(RenderCvPdfJob::class, 1);
        $first = Queue::pushed(RenderCvPdfJob::class)->first();
        RenderCvPdfJob::dispatch($cv->id, $first->fingerprint);
        Queue::assertPushed(RenderCvPdfJob::class, 1);
        $cv->update(['summary_input' => 'Changed summary']);
        Queue::assertPushed(RenderCvPdfJob::class, 2);
        Storage::disk('public')->put('avatars/new.jpg', 'first image');
        $cv->user->forceFill(['avatar_path' => 'avatars/new.jpg'])->save();
        Queue::assertPushed(RenderCvPdfJob::class, 3);
        $first->handle(app(CvTemplateRenderer::class));
        $this->assertSame([], Storage::disk('cv_pdfs')->files('cv-pdfs/'.$cv->id), 'Obsolete jobs must not publish stale artifacts.');
    }

    public function test_template_edits_and_deletion_do_not_fan_out_jobs_but_downloads_use_current_config(): void
    {
        $cv = $this->cv(['summary_input' => 'Summary '.bin2hex(random_bytes(8))]);
        Sanctum::actingAs($cv->user);
        $before = $this->getJson('/api/generated-cvs/'.$cv->id.'/download')->assertOk()->getContent();
        $this->assertStringContainsString($cv->summary_input, (new Parser)->parseContent($before)->getText());
        Queue::fake();
        $renderer = app(CvTemplateRenderer::class);
        $template = $renderer->resolve(null, 'en');
        $noFanOut = \Mockery::mock(CvTemplateRenderer::class);
        $noFanOut->shouldNotReceive('resolve');
        $noFanOut->shouldNotReceive('pdfFingerprint');
        $this->app->instance(CvTemplateRenderer::class, $noFanOut);
        $template->update(['config_json' => ['hidden_sections' => ['summary']]]);
        Queue::assertNotPushed(RenderCvPdfJob::class);
        $this->app->instance(CvTemplateRenderer::class, $renderer);
        $after = $this->getJson('/api/generated-cvs/'.$cv->id.'/download')->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF-', $after);
        $this->assertStringNotContainsString($cv->summary_input, (new Parser)->parseContent($after)->getText());
        $this->assertNotSame($before, $after);
        $this->app->instance(CvTemplateRenderer::class, $noFanOut);
        $template->delete();
        Queue::assertNotPushed(RenderCvPdfJob::class);
        $this->app->instance(CvTemplateRenderer::class, $renderer);
    }

    public function test_only_changed_avatar_paths_dispatch_for_the_owners_completed_cvs(): void
    {
        $owner = User::factory()->create();
        $cvs = [];
        foreach (['ar', 'en'] as $language) {
            $cvs[] = $this->cv(['user_id' => $owner->id, 'language' => $language]);
        }
        $this->cv(['user_id' => $owner->id, 'ai_status' => AiStatus::Processing]);
        $this->cv();
        Queue::fake();
        Sanctum::actingAs($owner);
        $paths = [];
        foreach (range(1, 2) as $upload) {
            $this->post('/api/auth/avatar', [
                'avatar' => UploadedFile::fake()->image('avatar.png', 128, 128),
            ], ['Accept' => 'application/json'])->assertOk();
            $owner->refresh();
            $paths[] = $owner->avatar_path;
            Queue::assertPushed(RenderCvPdfJob::class, $upload * count($cvs));
            foreach ($cvs as $cv) {
                Queue::assertPushed(RenderCvPdfJob::class, fn ($job) => $job->generatedCvId === $cv->id
                    && $job->fingerprint === app(CvTemplateRenderer::class)->pdfFingerprint($cv->fresh(), app(CvTemplateRenderer::class)->resolve(null, $cv->language), $cv->language));
            }
        }
        $this->assertCount(count($paths), array_unique($paths), 'Every upload must get a different path, even with the same filename and extension.');

        Queue::fake();
        // Pending unique jobs can hide attempted dispatches. Also prohibit the
        // expensive resolve/fingerprint work while an avatar remains unchanged.
        $renderer = app(CvTemplateRenderer::class);
        $noFanOut = \Mockery::mock(CvTemplateRenderer::class);
        $noFanOut->shouldNotReceive('resolve');
        $noFanOut->shouldNotReceive('pdfFingerprint');
        $this->app->instance(CvTemplateRenderer::class, $noFanOut);
        foreach (['name' => 'Changed name', 'location' => 'Changed city', 'is_premium' => true] as $field => $value) {
            $owner->forceFill([$field => $value])->save();
        }
        // A no-op save must not reuse the preceding avatar change event.
        $owner->save();
        Queue::assertNotPushed(RenderCvPdfJob::class);
        $this->app->instance(CvTemplateRenderer::class, $renderer);
        $this->deleteJson('/api/auth/avatar')->assertOk();
        Queue::assertPushed(RenderCvPdfJob::class, count($cvs));
    }

    public function test_deleting_a_cv_removes_all_its_artifacts_and_preserves_other_users_files(): void
    {
        $cv = $this->cv();
        $other = $this->cv();
        $disk = Storage::disk('cv_pdfs');
        foreach ([$cv, $other] as $candidate) {
            Sanctum::actingAs($candidate->user);
            foreach (['ar', 'en'] as $language) {
                $bytes = $this->getJson('/api/generated-cvs/'.$candidate->id.'/download?language='.$language)->assertOk()->getContent();
                $this->assertStringStartsWith('%PDF-', $bytes);
            }
        }
        $otherFiles = $disk->allFiles('cv-pdfs/'.$other->id);
        $otherBytes = array_map(fn ($path) => $disk->get($path), $otherFiles);
        $disk->put('cv-pdfs/'.$cv->id.'/previous.pdf', $bytes);
        $disk->put('cv-pdfs/'.$cv->id.'/interrupted.tmp', $bytes);
        Sanctum::actingAs($cv->user);
        $this->deleteJson('/api/generated-cvs/'.$other->id)->assertNotFound();
        $this->deleteJson('/api/generated-cvs/'.$cv->id)->assertOk();
        $this->assertFalse($disk->directoryExists('cv-pdfs/'.$cv->id));
        $this->assertSame([], $disk->allFiles('cv-pdfs/'.$cv->id));
        $this->assertSame($otherBytes, array_map(fn ($path) => $disk->get($path), $otherFiles));
        // A job queued before deletion must not recreate the private directory.
        Queue::pushed(RenderCvPdfJob::class)->first(fn ($job) => $job->generatedCvId === $cv->id)->handle(app(CvTemplateRenderer::class));
        $this->assertFalse($disk->directoryExists('cv-pdfs/'.$cv->id));
    }

    public function test_deleting_an_account_erases_all_cv_artifacts_in_both_languages_and_preserves_other_users(): void
    {
        $owner = User::factory()->create(['password' => 'password123']);
        $owned = [];
        foreach (['ar', 'en', 'ar', 'en'] as $language) {
            $owned[] = $this->cv(['user_id' => $owner->id, 'language' => $language]);
        }
        $other = $this->cv();
        foreach ([...$owned, $other] as $cv) {
            Sanctum::actingAs($cv->user);
            $bytes = $this->getJson('/api/generated-cvs/'.$cv->id.'/download')->assertOk()->getContent();
            $this->assertStringContainsStringIgnoringCase($cv->full_name, (new Parser)->parseContent($bytes)->getText());
        }
        $disk = Storage::disk('cv_pdfs');
        $retained = $disk->allFiles('cv-pdfs/'.$other->id);
        $retainedBytes = array_map(fn ($path) => $disk->get($path), $retained);
        Sanctum::actingAs($owner);
        $this->deleteJson('/api/auth/account', ['password' => 'wrong'])->assertUnprocessable();
        foreach ($owned as $cv) {
            $this->assertNotEmpty($disk->allFiles('cv-pdfs/'.$cv->id));
        }
        $this->deleteJson('/api/auth/account', ['password' => 'password123'])->assertOk();
        foreach ($owned as $cv) {
            $this->assertDatabaseMissing('generated_cvs', ['id' => $cv->id]);
            $this->assertFalse($disk->directoryExists('cv-pdfs/'.$cv->id));
            $this->assertSame([], $disk->allFiles('cv-pdfs/'.$cv->id));
        }
        $this->assertSame($retainedBytes, array_map(fn ($path) => $disk->get($path), $retained));
        $this->assertSame($retained, $disk->allFiles('cv-pdfs'));
    }

    public function test_guest_pruning_deletes_only_expired_guests_artifacts(): void
    {
        $this->freezeTime();
        $expired = [];
        $retained = [];
        foreach (['ar', 'en'] as $language) {
            foreach ([25, 24, 23] as $hours) {
                $cv = $this->cv(['user_id' => null, 'language' => $language]);
                $cv->forceFill(['created_at' => now()->subHours($hours)])->save();
                if ($hours > 24) {
                    $expired[] = $cv;
                } else {
                    $retained[] = $cv;
                }
            }
        }
        $owned = $this->cv();
        $owned->forceFill(['created_at' => now()->subDays(5)])->save();
        $retained[] = $owned;
        $disk = Storage::disk('cv_pdfs');
        foreach ([...$expired, ...$retained] as $cv) {
            $url = URL::temporarySignedRoute('api.generated-cvs.pdf', now()->addHour(), ['generatedCv' => $cv->id]);
            $bytes = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsStringIgnoringCase($cv->full_name, (new Parser)->parseContent($bytes)->getText());
        }
        $retainedFiles = [];
        foreach ($retained as $cv) {
            foreach ($disk->allFiles('cv-pdfs/'.$cv->id) as $path) {
                $retainedFiles[$path] = $disk->get($path);
            }
        }
        $this->artisan('analyses:prune-guests', ['--hours' => 24])->assertSuccessful();
        foreach ($expired as $cv) {
            $this->assertDatabaseMissing('generated_cvs', ['id' => $cv->id]);
            $this->assertFalse($disk->directoryExists('cv-pdfs/'.$cv->id));
        }
        foreach ($retainedFiles as $path => $bytes) {
            $this->assertSame($bytes, $disk->get($path));
        }
    }

    public function test_dispatch_waits_for_commit_and_rollback_does_not_lock_out_a_retry(): void
    {
        $cv = $this->cv(['ai_status' => AiStatus::Processing]);
        DB::beginTransaction();
        $cv->update(['ai_status' => AiStatus::Completed]);
        Queue::assertNotPushed(RenderCvPdfJob::class);
        DB::rollBack();
        Queue::assertNotPushed(RenderCvPdfJob::class);

        $cv->refresh();
        DB::beginTransaction();
        $cv->update(['ai_status' => AiStatus::Completed]);
        Queue::assertNotPushed(RenderCvPdfJob::class);
        DB::commit();
        Queue::assertPushed(RenderCvPdfJob::class, 1);
    }

    private function path(GeneratedCv $cv, CvTemplate $template, string $language): string
    {
        return 'cv-pdfs/'.$cv->id.'/'.app(CvTemplateRenderer::class)->pdfFingerprint($cv->fresh(), $template->fresh(), $language).'.pdf';
    }

    private function cv(array $overrides = []): GeneratedCv
    {
        return GeneratedCv::create(array_replace([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Maya Noor',
            'email' => 'maya@example.test',
            'phone' => '+966500000000',
            'target_job_title' => 'Engineer',
            'language' => 'en',
            'summary_input' => 'Designs reliable systems',
            'skills_input' => 'PHP, Laravel',
            'experience_input' => "Engineer, Example Co, 2020 - 2024\n- Built reliable systems.",
            'education_input' => 'BSc Computer Science',
            'form_payload' => [],
            'generated_markdown' => '## Skills'."\n\nPHP, Laravel",
            'ai_status' => AiStatus::Completed,
        ], $overrides))->fresh();
    }
}
