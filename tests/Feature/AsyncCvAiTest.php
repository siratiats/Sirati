<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Enums\AiStatus;
use App\Exceptions\AiRefusalException;
use App\Jobs\GenerateCvAdviceJob;
use App\Jobs\GenerateCvContentJob;
use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\AtsScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AsyncCvAiTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_async_header_returns_queued_and_dispatches_without_calling_ai(): void
    {
        Queue::fake();
        $provider = $this->configuredProvider();
        $provider->shouldNotReceive('analysisAdvice');

        Sanctum::actingAs(User::factory()->create());

        $response = $this->withHeader('X-Sirati-Async', '1')
            ->postJson('/api/cv-analyses', [
                'target_job_title' => 'Laravel Developer',
                'resume_text' => $this->resumeText(),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.ai_status', AiStatus::Queued->value);

        $analysis = CvAnalysis::firstOrFail();
        $this->assertSame(AiStatus::Queued, $analysis->ai_status);
        $this->assertGreaterThan(0, $analysis->score_total);
        Queue::assertPushed(
            GenerateCvAdviceJob::class,
            fn (GenerateCvAdviceJob $job): bool => $job->analysisId === $analysis->id
                && $job->queue === 'ai',
        );
    }

    public function test_generated_cv_async_header_returns_queued_and_dispatches_without_calling_ai(): void
    {
        Queue::fake();
        $provider = $this->configuredProvider();
        $provider->shouldNotReceive('generateCv');

        Sanctum::actingAs(User::factory()->create());

        $response = $this->withHeader('X-Sirati-Async', '1')
            ->postJson('/api/generated-cvs', $this->generationPayload());

        $response->assertCreated()
            ->assertJsonPath('data.ai_status', AiStatus::Queued->value);

        $generatedCv = GeneratedCv::firstOrFail();
        $this->assertSame(AiStatus::Queued, $generatedCv->ai_status);
        $this->assertGreaterThan(0, $generatedCv->score_total);
        $this->assertNotSame('', trim($generatedCv->generated_markdown));
        Queue::assertPushed(
            GenerateCvContentJob::class,
            fn (GenerateCvContentJob $job): bool => $job->generatedCvId === $generatedCv->id
                && $job->queue === 'ai',
        );
    }

    public function test_analysis_to_cv_async_header_queues_generation(): void
    {
        Queue::fake();
        $provider = $this->configuredProvider();
        $provider->shouldNotReceive('generateCv');

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $analysis = $this->analysis($user);

        $this->withHeader('X-Sirati-Async', '1')
            ->postJson("/api/cv-analyses/{$analysis->id}/generated-cv")
            ->assertCreated()
            ->assertJsonPath('data.ai_status', AiStatus::Queued->value);

        Queue::assertPushedOn('ai', GenerateCvContentJob::class);
    }

    public function test_analysis_job_writes_completed_feedback(): void
    {
        $user = User::factory()->create();
        $analysis = $this->analysis($user);
        $feedback = ['executive_summary' => 'Strong fit'];
        $provider = $this->configuredProvider();
        $provider->shouldReceive('analysisAdvice')
            ->once()
            ->andReturn($feedback);

        (new GenerateCvAdviceJob($analysis->id))
            ->handle($provider, app(AtsScoringService::class));

        $analysis->refresh();
        $this->assertSame(AiStatus::Completed, $analysis->ai_status);
        $this->assertSame($feedback, $analysis->ai_feedback);
        $this->assertNull($analysis->ai_error);
    }

    public function test_generated_cv_job_writes_completed_content_and_rescores_it(): void
    {
        $user = User::factory()->create();
        $generatedCv = $this->generatedCv($user);
        $output = [
            'cv_markdown' => "# Queue User\n\n## Skills\nLaravel, PHP, API, SQL, Git",
            'headline' => 'Laravel Developer',
        ];
        $provider = $this->configuredProvider();
        $provider->shouldReceive('generateCv')
            ->once()
            ->with($generatedCv->form_payload)
            ->andReturn($output);

        (new GenerateCvContentJob($generatedCv->id))
            ->handle($provider, app(AtsScoringService::class));

        $generatedCv->refresh();
        $this->assertSame(AiStatus::Completed, $generatedCv->ai_status);
        $this->assertSame($output, $generatedCv->ai_output);
        $this->assertSame($output['cv_markdown'], $generatedCv->generated_markdown);
        $this->assertNull($generatedCv->ai_error);
    }

    public function test_connection_failure_is_recorded_and_rethrown_for_retry(): void
    {
        $analysis = $this->analysis(User::factory()->create());
        $provider = $this->configuredProvider();
        $provider->shouldReceive('analysisAdvice')
            ->once()
            ->andThrow(new ConnectionException('SMTP-style network outage'));

        try {
            (new GenerateCvAdviceJob($analysis->id))
                ->handle($provider, app(AtsScoringService::class));
            $this->fail('The transient exception should be rethrown for the queue retry.');
        } catch (ConnectionException) {
            $analysis->refresh();
            $this->assertSame(AiStatus::Failed, $analysis->ai_status);
            $this->assertSame('SMTP-style network outage', $analysis->ai_error);
        }
    }

    public function test_refusal_is_terminal_and_does_not_throw_for_retry(): void
    {
        $analysis = $this->analysis(User::factory()->create());
        $provider = $this->configuredProvider();
        $provider->shouldReceive('analysisAdvice')
            ->once()
            ->andThrow(new AiRefusalException('Model declined this CV.'));

        (new GenerateCvAdviceJob($analysis->id))
            ->handle($provider, app(AtsScoringService::class));

        $analysis->refresh();
        $this->assertSame(AiStatus::Failed, $analysis->ai_status);
        $this->assertSame('Model declined this CV.', $analysis->ai_error);
    }

    public function test_permanent_job_failure_callback_marks_record_failed(): void
    {
        $generatedCv = $this->generatedCv(User::factory()->create());

        (new GenerateCvContentJob($generatedCv->id))
            ->failed(new RuntimeException('Worker exhausted retries.'));

        $generatedCv->refresh();
        $this->assertSame(AiStatus::Failed, $generatedCv->ai_status);
        $this->assertSame('Worker exhausted retries.', $generatedCv->ai_error);
    }

    public function test_headerless_client_keeps_synchronous_behavior(): void
    {
        Queue::fake();
        $feedback = ['executive_summary' => 'Synchronous result'];
        $provider = $this->configuredProvider();
        $provider->shouldReceive('analysisAdvice')
            ->once()
            ->andReturn($feedback);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/cv-analyses', [
            'target_job_title' => 'Laravel Developer',
            'resume_text' => $this->resumeText(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.ai_status', AiStatus::Completed->value)
            ->assertJsonPath('data.ai_feedback.executive_summary', 'Synchronous result');

        Queue::assertNothingPushed();
    }

    public function test_headerless_generated_cv_client_keeps_synchronous_behavior(): void
    {
        Queue::fake();
        $output = [
            'cv_markdown' => "# Synchronous User\n\n## Skills\nLaravel, API, SQL",
        ];
        $provider = $this->configuredProvider();
        $provider->shouldReceive('generateCv')
            ->once()
            ->andReturn($output);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/generated-cvs', $this->generationPayload())
            ->assertCreated()
            ->assertJsonPath('data.ai_status', AiStatus::Completed->value)
            ->assertJsonPath('data.generated_markdown', $output['cv_markdown']);

        Queue::assertNothingPushed();
    }

    public function test_ai_jobs_have_explicit_queue_retry_and_timeout_settings(): void
    {
        $advice = new GenerateCvAdviceJob(10);
        $content = new GenerateCvContentJob(20);

        foreach ([$advice, $content] as $job) {
            $this->assertSame('ai', $job->queue);
            $this->assertSame(3, $job->tries);
            $this->assertSame([10, 30], $job->backoff);
            $this->assertSame(120, $job->timeout);
        }
    }

    private function configuredProvider(): Mockery\MockInterface
    {
        $provider = Mockery::mock(CvAiProvider::class);
        $provider->shouldReceive('isConfigured')->andReturnTrue();
        $this->app->instance(CvAiProvider::class, $provider);

        return $provider;
    }

    private function analysis(User $user): CvAnalysis
    {
        $score = app(AtsScoringService::class)->score(
            $this->resumeText(),
            'Laravel Developer',
        );

        return CvAnalysis::create([
            'user_id' => $user->id,
            'target_job_title' => 'Laravel Developer',
            'input_method' => 'paste',
            'resume_text' => $this->resumeText(),
            'score_total' => $score['total'],
            'grade' => $score['grade'],
            'job_match' => $score['job_match'],
            'criteria' => $score['criteria'],
            'strengths' => $score['strengths'],
            'weaknesses' => $score['weaknesses'],
            'keywords_found' => $score['keywords_found'],
            'keywords_missing' => $score['keywords_missing'],
            'quick_wins' => $score['quick_wins'],
            'ai_status' => AiStatus::Queued,
        ]);
    }

    private function generatedCv(User $user): GeneratedCv
    {
        $payload = $this->generationPayload();
        $markdown = "# Queue User\n\n## Experience\nBuilt Laravel APIs.";
        $score = app(AtsScoringService::class)->score(
            $markdown,
            $payload['target_job_title'],
        );

        return GeneratedCv::create([
            ...$payload,
            'user_id' => $user->id,
            'generated_markdown' => $markdown,
            'form_payload' => $payload,
            'ai_status' => AiStatus::Queued,
            'score_total' => $score['total'],
            'grade' => $score['grade'],
            'criteria' => $score['criteria'],
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function generationPayload(): array
    {
        return [
            'full_name' => 'Queue User',
            'email' => 'queue@example.com',
            'phone' => '+966500000000',
            'linkedin' => null,
            'location' => 'Riyadh',
            'target_job_title' => 'Laravel Developer',
            'job_description_input' => null,
            'language' => 'en',
            'summary_input' => 'Backend developer focused on reliable Laravel APIs.',
            'skills_input' => 'Laravel, PHP, API, SQL, Git',
            'experience_input' => 'Built and maintained Laravel APIs for multiple product teams, improving response times and reliability with measurable production outcomes.',
            'education_input' => 'Bachelor of Computer Science',
            'certifications_input' => null,
        ];
    }

    private function resumeText(): string
    {
        return <<<'CV'
Queue User
queue@example.com | +966500000000 | linkedin.com/in/queue

Summary
Laravel backend developer with 5 years of experience.

Skills
PHP, Laravel, API, SQL, Git, Agile, Backend

Experience
Backend Developer, 2021 - 2026
- Developed Laravel APIs for 40 users.
- Improved response times by 35%.

Education
Bachelor of Computer Science, 2020
CV;
    }
}
