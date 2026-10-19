<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Cv\CvDocument;
use App\Cv\LocalizedText;
use App\Cv\PersonalDetails;
use App\Enums\AiStatus;
use App\Jobs\GenerateJobMatchAdviceJob;
use App\Models\AiCallLog;
use App\Models\GeneratedCv;
use App\Models\JobMatch;
use App\Models\User;
use App\Services\Ai\CachedCvAiProvider;
use App\Services\Ai\JobMatchResultGuard;
use App\Services\AtsScoringService;
use App\Services\Subscriptions\SubscriptionGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

trait JobMatchFixtures
{
    protected function prepareJobMatch(): void
    {
        Queue::fake();
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['monetization.job_match.daily_limit' => 20]);
    }

    protected function premiumUser(): User
    {
        return User::factory()->create(['premium_until' => now()->addMonth()]);
    }

    protected function savedCv(User $user, ?CvDocument $document = null): GeneratedCv
    {
        $document ??= new CvDocument(
            exportLanguage: 'en',
            personal: new PersonalDetails(fullName: LocalizedText::make(en: 'Candidate'), headline: LocalizedText::make(en: 'Specialist'), email: 'candidate@example.com'),
            summary: LocalizedText::make(en: 'Experienced in systems and documentation.'),
        );

        return GeneratedCv::create([
            'user_id' => $user->id,
            ...$document->legacySnapshot(),
            'document' => $document->toArray(),
            'generated_markdown' => $document->resolve()->toMarkdown(),
            'form_payload' => $document->legacySnapshot(),
            'ai_status' => AiStatus::Completed,
        ]);
    }

    protected function createMatch(User $user, GeneratedCv $cv, array $suggestions = []): JobMatch
    {
        $doc = $cv->cvDocument();

        return JobMatch::create([
            'user_id' => $user->id,
            'generated_cv_id' => $cv->id,
            'request_fingerprint' => hash('sha256', 'fixture'),
            'cv_snapshot_hash' => JobMatch::snapshotHash($doc),
            'cv_snapshot' => JobMatch::adviceSnapshot($doc),
            'target_job_title' => $cv->target_job_title,
            'job_description_input' => 'systems documentation Kubernetes automation',
            'language' => $cv->language,
            'ai_status' => AiStatus::Completed,
            'ai_feedback' => ['suggestions' => $suggestions],
            ...JobMatch::scoreAttributes(app(AtsScoringService::class)->scoreDocument($doc, null, 'systems documentation Kubernetes automation')),
        ]);
    }
}

class JobMatchControllerTest extends TestCase
{
    use JobMatchFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareJobMatch();
    }

    public function test_authentication_is_required(): void
    {
        $this->postJson('/api/job-matches', [])->assertUnauthorized();
        $this->getJson('/api/job-matches/1')->assertUnauthorized();
        $this->postJson('/api/job-matches/1/retry-advice')->assertUnauthorized();
        $this->postJson('/api/job-matches/1/apply-suggestions', [])->assertUnauthorized();
    }

    public function test_non_premium_is_rejected_even_when_monetization_is_disabled_and_before_replay(): void
    {
        $user = $this->premiumUser();
        $cv = $this->savedCv($user);
        Sanctum::actingAs($user);
        $payload = ['generated_cv_id' => $cv->id, 'job_description_input' => 'systems automation'];
        $this->postJson('/api/job-matches', $payload, ['Idempotency-Key' => 'replay'])->assertCreated();
        $user->revokeSubscription();
        $this->postJson('/api/job-matches', $payload, ['Idempotency-Key' => 'replay'])->assertStatus(402);
        $this->assertDatabaseCount('job_matches', 1);
        Queue::assertPushed(GenerateJobMatchAdviceJob::class, 1);
    }

    public function test_foreign_records_return_404_at_every_endpoint(): void
    {
        $owner = $this->premiumUser();
        $cv = $this->savedCv($owner);
        $match = $this->createMatch($owner, $cv);
        Sanctum::actingAs($this->premiumUser());
        $this->postJson('/api/job-matches', ['generated_cv_id' => $cv->id, 'job_description_input' => 'automation'])->assertNotFound();
        $this->getJson('/api/job-matches/'.$match->id)->assertNotFound();
        $this->postJson('/api/job-matches/'.$match->id.'/retry-advice')->assertNotFound();
        $this->postJson('/api/job-matches/'.$match->id.'/apply-suggestions', ['suggestion_keys' => ['x']])->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function test_scores_follow_deterministic_jd_coverage_across_arbitrary_inputs(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        $scorer = app(AtsScoringService::class);
        foreach (['en', 'ar'] as $language) {
            foreach ([0, 1, 3] as $covered) {
                $terms = array_map(fn () => 'term'.bin2hex(random_bytes(5)), range(1, 3));
                $jd = implode(' ', $terms);
                $document = new CvDocument(
                    exportLanguage: $language,
                    personal: new PersonalDetails(fullName: LocalizedText::forLanguage($language, 'Candidate'), headline: LocalizedText::forLanguage($language, 'Role'.bin2hex(random_bytes(4)))),
                    summary: LocalizedText::forLanguage($language, implode(' ', array_slice($terms, 0, $covered))),
                );
                $cv = $this->savedCv($user, $document);
                $expected = $scorer->scoreDocument($document, null, $jd);
                $response = $this->postJson('/api/job-matches', ['generated_cv_id' => $cv->id, 'job_description_input' => $jd]);
                $response->assertCreated()->assertJsonPath('data.job_match', (int) round($covered / 3 * 100));
                $response->assertJsonPath('data.score_total', $expected['total'])->assertJsonPath('data.keywords_found', $expected['keywords_found']);
                $this->getJson('/api/job-matches/'.$response->json('data.id'))->assertOk()->assertJsonPath('data.job_match', $expected['job_match']);
            }
        }
    }

    public function test_idempotency_binds_all_inputs_and_the_source_snapshot_without_spending_quota_twice(): void
    {
        config(['monetization.job_match.daily_limit' => 1]);
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        $cv = $this->savedCv($user);
        $input = ['generated_cv_id' => $cv->id, 'job_description_input' => 'automation systems', 'language' => 'en', 'target_job_title' => 'Systems specialist'];
        $headers = ['Idempotency-Key' => 'one-request'];
        $id = $this->postJson('/api/job-matches', $input, $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/job-matches', $input, $headers)->assertOk()->assertJsonPath('data.id', $id);
        foreach (['job_description_input' => 'different requirements', 'language' => 'ar', 'target_job_title' => 'Different title', 'generated_cv_id' => $this->savedCv($user)->id] as $field => $value) {
            $this->postJson('/api/job-matches', [...$input, $field => $value], $headers)->assertStatus(409);
        }
        $this->postJson('/api/job-matches', $input, ['Idempotency-Key' => 'other-request'])->assertStatus(429);
        $doc = $cv->document;
        $doc['summary']['en'] = 'Changed content';
        $cv->update(['document' => $doc]);
        $this->postJson('/api/job-matches', $input, $headers)->assertStatus(409);
        $this->assertDatabaseCount('job_matches', 1);
        Queue::assertPushed(GenerateJobMatchAdviceJob::class, 1);
    }

    public function test_identical_matches_share_one_claude_call_while_quotas_and_records_remain_per_user(): void
    {
        config([
            'monetization.job_match.daily_limit' => 1,
            'services.cv_ai.provider' => 'claude',
            'services.cv_ai.response_cache_enabled' => true,
            'services.anthropic.api_key' => 'test-key',
            'services.anthropic.model' => 'claude-haiku-5-5',
            'services.anthropic.base_url' => 'https://api.anthropic.com/v1',
            'cache.default' => 'array',
        ]);
        Cache::flush();
        $this->app->forgetInstance(CvAiProvider::class);
        $provider = app(CvAiProvider::class);
        $this->assertInstanceOf(CachedCvAiProvider::class, $provider);
        $feedback = [
            'executive_summary' => 'Experienced in systems and documentation.',
            'strengths' => ['Experienced in systems'],
            'gaps' => [],
            'suggestions' => [[
                'key' => fake()->uuid(), 'kind' => 'replace',
                'target' => ['section' => 'summary', 'entry_id' => null, 'field' => 'text', 'bullet_index' => null],
                'before' => 'Experienced in systems and documentation.',
                'after' => 'Experienced in documentation and systems.',
                'rationale' => 'Highlight documentation', 'jd_terms' => ['documentation'], 'needs_user_fact' => false,
            ]],
        ];
        Http::preventStrayRequests();
        Http::fake(['https://api.anthropic.com/v1/messages' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => json_encode($feedback)]],
            'usage' => ['input_tokens' => 300, 'output_tokens' => 200],
        ])]);
        $users = [$this->premiumUser(), $this->premiumUser()];
        $cvs = array_map(fn ($user) => $this->savedCv($user), $users);
        $matches = [];
        $jd = 'systems documentation '.fake()->word();
        foreach ($users as $index => $user) {
            Sanctum::actingAs($user);
            $payload = ['generated_cv_id' => $cvs[$index]->id, 'job_description_input' => $jd, 'language' => 'en'];
            $headers = ['Idempotency-Key' => fake()->uuid()];
            $id = $this->postJson('/api/job-matches', $payload, $headers)->assertCreated()->json('data.id');
            $matches[] = $id;
            Queue::assertPushed(GenerateJobMatchAdviceJob::class, fn ($job) => $job->jobMatchId === $id);
            (new GenerateJobMatchAdviceJob($id))->handle($provider, app(AtsScoringService::class));
            $this->getJson('/api/job-matches/'.$id)->assertOk()
                ->assertJsonPath('data.ai_status', 'completed')
                ->assertJsonPath('data.ai_feedback', $feedback);
            $this->postJson('/api/job-matches', $payload, $headers)->assertOk()->assertJsonPath('data.id', $id);
            $this->postJson('/api/job-matches', $payload, ['Idempotency-Key' => fake()->uuid()])->assertStatus(429);
            $this->assertSame(1, app(SubscriptionGate::class)->jobMatchesUsedToday($user));
            $this->assertSame($user->id, JobMatch::findOrFail($id)->user_id);
        }
        $this->assertSame(JobMatch::findOrFail($matches[0])->cv_snapshot, JobMatch::findOrFail($matches[1])->cv_snapshot);
        foreach ($users as $index => $user) {
            Sanctum::actingAs($user);
            $foreignId = $matches[1 - $index];
            $this->getJson('/api/job-matches/'.$foreignId)->assertNotFound();
            $this->postJson('/api/job-matches/'.$foreignId.'/retry-advice')->assertNotFound();
            $this->postJson('/api/job-matches/'.$foreignId.'/apply-suggestions', ['suggestion_keys' => [$feedback['suggestions'][0]['key']]])->assertNotFound();
            $this->postJson('/api/job-matches', ['generated_cv_id' => $cvs[1 - $index]->id, 'job_description_input' => $jd])->assertNotFound();
        }
        Http::assertSentCount(1);
        Queue::assertPushed(GenerateJobMatchAdviceJob::class, 2);
        $this->assertDatabaseCount('job_matches', 2);
        $logs = AiCallLog::query()->where('operation', 'job_match_advice')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame($users[0]->id, $logs[0]->user_id);
        $this->assertFalse($logs[0]->was_response_cache_hit);
        $this->assertSame($users[1]->id, $logs[1]->user_id);
        $this->assertTrue($logs[1]->was_response_cache_hit);
        $this->assertSame(0, $logs[1]->input_tokens);
        $this->assertSame(0, $logs[1]->output_tokens);
    }

    public function test_daily_quota_is_per_user_and_resets_at_day_boundary(): void
    {
        config(['monetization.job_match.daily_limit' => 1]);
        $user = $this->premiumUser();
        $cv = $this->savedCv($user);
        $match = $this->createMatch($user, $cv);
        Sanctum::actingAs($user);
        $input = ['generated_cv_id' => $cv->id, 'job_description_input' => 'automation'];
        $this->postJson('/api/job-matches', $input)->assertStatus(429);
        $match->update(['created_at' => now()->startOfDay()->subSecond()]);
        $this->postJson('/api/job-matches', $input)->assertCreated();
        $another = $this->premiumUser();
        Sanctum::actingAs($another);
        $this->postJson('/api/job-matches', ['generated_cv_id' => $this->savedCv($another)->id, 'job_description_input' => 'automation'])->assertCreated();
    }

    public function test_replays_at_full_quota_preserve_attempts_and_dispatch_only_once_for_every_ai_state(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $gate = app(SubscriptionGate::class);
        foreach ([1, 3, 7] as $limit) {
            config(['monetization.job_match.daily_limit' => $limit]);
            $user = $this->premiumUser();
            Sanctum::actingAs($user);
            $cv = $this->savedCv($user);
            $input = [
                'generated_cv_id' => $cv->id,
                'job_description_input' => fake()->paragraph(),
                'target_job_title' => fake()->jobTitle(),
            ];
            foreach (range(1, $limit) as $attempt) {
                $headers = ['Idempotency-Key' => fake()->uuid()];
                $id = $this->postJson('/api/job-matches', $input, $headers)->assertCreated()->json('data.id');
            }

            foreach ([AiStatus::Queued, AiStatus::Processing, AiStatus::Completed, AiStatus::Failed] as $state) {
                JobMatch::findOrFail($id)->update(['ai_status' => $state]);
                foreach (range(1, 3) as $replay) {
                    $this->postJson('/api/job-matches', $input, $headers)->assertOk()
                        ->assertJsonPath('data.id', $id)->assertJsonPath('data.ai_status', $state->value);
                    $this->assertSame($limit, $gate->jobMatchesUsedToday($user));
                }
            }
            $this->postJson('/api/job-matches', $input, ['Idempotency-Key' => fake()->uuid()])
                ->assertStatus(429)->assertHeader('Retry-After');
            $this->postJson('/api/job-matches', $input)->assertStatus(429);
            Queue::assertPushed(GenerateJobMatchAdviceJob::class, fn ($job) => $job->jobMatchId === $id);
        }
        Queue::assertPushed(GenerateJobMatchAdviceJob::class, 1 + 3 + 7);
        $this->assertDatabaseCount('job_matches', 1 + 3 + 7);
    }

    public function test_exhausted_quota_only_blocks_new_attempts_at_every_existing_match_boundary(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        foreach ([1, random_int(2, 5)] as $limit) {
            config(['monetization.job_match.daily_limit' => $limit]);
            $user = $this->premiumUser();
            Sanctum::actingAs($user);
            $cv = $this->savedCv($user);
            $input = ['generated_cv_id' => $cv->id, 'job_description_input' => fake()->paragraph()];
            foreach (range(1, $limit) as $attempt) {
                $headers = ['Idempotency-Key' => fake()->uuid()];
                $id = $this->postJson('/api/job-matches', $input, $headers)->assertCreated()->json('data.id');
            }
            $match = JobMatch::findOrFail($id);
            $key = fake()->uuid();
            $match->update(['ai_feedback' => ['suggestions' => [[
                'key' => $key,
                'kind' => 'replace',
                'target' => ['section' => 'summary', 'field' => 'text'],
                'before' => $cv->summary_input,
                'after' => $cv->summary_input.' '.fake()->sentence(),
                'needs_user_fact' => false,
            ]]]]);
            $url = '/api/job-matches/'.$id;
            foreach (AiStatus::cases() as $state) {
                $match->update(['ai_status' => $state]);
                $stored = $this->getJson($url)->assertOk()->assertJsonPath('data.ai_status', $state->value)->json('data');
                $this->postJson('/api/job-matches', $input, $headers)->assertOk()->assertExactJson(['data' => $stored]);
                if ($state !== AiStatus::Completed) {
                    $this->postJson($url.'/apply-suggestions', ['suggestion_keys' => [$key]])->assertStatus(409);
                }
            }
            $match->update(['ai_status' => AiStatus::Completed]);
            $copyId = $this->postJson($url.'/apply-suggestions', ['suggestion_keys' => [$key]])
                ->assertOk()->json('data.generated_cv.id');
            $this->assertNotSame($cv->id, $copyId);
            $this->postJson($url.'/apply-suggestions', ['suggestion_keys' => [$key]])
                ->assertOk()->assertJsonPath('data.generated_cv.id', $copyId);
            $stored = $this->getJson($url)->assertOk()->json('data');
            $this->postJson('/api/job-matches', $input, $headers)->assertOk()->assertExactJson(['data' => $stored]);
            $this->postJson('/api/job-matches', $input)->assertStatus(429)->assertHeader('Retry-After');
            $this->postJson('/api/job-matches', $input, ['Idempotency-Key' => fake()->uuid()])->assertStatus(429);
            $this->assertSame($limit, app(SubscriptionGate::class)->jobMatchesUsedToday($user));
            $user->revokeSubscription();
            $this->getJson($url)->assertStatus(402);
            $this->postJson($url.'/apply-suggestions', ['suggestion_keys' => [$key]])->assertStatus(402);
            $this->postJson('/api/job-matches', $input, $headers)->assertStatus(402);
        }
    }

    public function test_character_limits_include_unicode_boundaries_and_invalid_keys(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        $cv = $this->savedCv($user);
        foreach (['', str_repeat('م', 4001)] as $jd) {
            $this->postJson('/api/job-matches', ['generated_cv_id' => $cv->id, 'job_description_input' => $jd])->assertUnprocessable();
        }
        foreach ([' ', str_repeat('k', 129)] as $key) {
            $this->postJson('/api/job-matches', ['generated_cv_id' => $cv->id, 'job_description_input' => 'systems'], ['Idempotency-Key' => $key])->assertUnprocessable();
        }
        $this->postJson('/api/job-matches', ['generated_cv_id' => $cv->id, 'job_description_input' => str_repeat('م', 4000)])->assertCreated();
        foreach ([7000, 7001] as $length) {
            $large = new CvDocument(exportLanguage: 'en', summary: LocalizedText::make(en: str_repeat('a', $length)));
            $cv = $this->savedCv($user, $large);
            $response = $this->postJson('/api/job-matches', ['generated_cv_id' => $cv->id, 'job_description_input' => 'systems']);
            $response->assertStatus($length === 7000 ? 201 : 422);
        }
    }

    public function test_snapshot_limit_cannot_be_bypassed_using_secondary_translations_or_custom_sections(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        foreach (['ar', 'en'] as $exportLanguage) {
            foreach (['translation', 'custom'] as $section) {
                $document = ['export_language' => $exportLanguage, 'summary' => [$exportLanguage => 'Short summary']];
                if ($section === 'translation') {
                    $document['summary'][$exportLanguage === 'ar' ? 'en' : 'ar'] = str_repeat('x', 7001);
                } else {
                    $document['custom_sections'] = [['key' => 'additional', 'title' => [$exportLanguage => 'Additional'], 'body' => [$exportLanguage => str_repeat('x', 7001)]]];
                }
                $cv = $this->savedCv($user, CvDocument::fromArray($document));
                $this->postJson('/api/job-matches', ['generated_cv_id' => $cv->id, 'job_description_input' => 'systems'])->assertUnprocessable();
            }
        }
        $this->assertDatabaseCount('job_matches', 0);
        Queue::assertNothingPushed();
    }

    public function test_retry_advice_is_premium_and_owner_only_and_does_not_spend_quota(): void
    {
        config(['monetization.job_match.daily_limit' => 1]);
        $owner = $this->premiumUser();
        $cv = $this->savedCv($owner);
        $match = $this->createMatch($owner, $cv);
        $url = '/api/job-matches/'.$match->id.'/retry-advice';
        $gate = app(SubscriptionGate::class);

        Sanctum::actingAs($owner);
        $this->postJson($url)->assertStatus(409);
        $match->update(['ai_status' => AiStatus::Processing]);
        $this->postJson($url)->assertOk()->assertJsonPath('data.ai_status', 'processing');
        $match->update(['ai_status' => AiStatus::Queued, 'ai_error' => 'stale']);
        $this->postJson($url)->assertOk()->assertJsonPath('data.ai_status', 'queued')->assertJsonPath('data.ai_error', 'stale');
        Queue::assertNothingPushed();

        $match->update([
            'ai_status' => AiStatus::Failed,
            'ai_error' => 'Job Match advice is unavailable.',
            'ai_feedback' => ['suggestions' => [['key' => 'old']]],
        ]);
        $this->postJson($url)->assertOk()
            ->assertJsonPath('data.ai_status', 'queued')
            ->assertJsonPath('data.ai_error', null)
            ->assertJsonPath('data.ai_feedback', null);
        $this->postJson($url)->assertOk()->assertJsonPath('data.ai_status', 'queued');
        Queue::assertPushed(GenerateJobMatchAdviceJob::class, 1);
        $this->assertSame(1, $gate->jobMatchesUsedToday($owner));
        $this->postJson('/api/job-matches', [
            'generated_cv_id' => $cv->id,
            'job_description_input' => 'a different role',
        ])->assertStatus(429);
        $this->assertSame(1, $gate->jobMatchesUsedToday($owner));

        $owner->revokeSubscription();
        $match->update(['ai_status' => AiStatus::Failed]);
        $this->postJson($url)->assertStatus(402);
        Queue::assertPushed(GenerateJobMatchAdviceJob::class, 1);
    }

    public function test_all_job_match_routes_keep_authentication_and_heavy_throttling(): void
    {
        foreach ([['POST', '/api/job-matches'], ['GET', '/api/job-matches/1'], ['POST', '/api/job-matches/1/retry-advice'], ['POST', '/api/job-matches/1/apply-suggestions']] as [$method, $uri]) {
            $route = app('router')->getRoutes()->match(Request::create($uri, $method));
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            $this->assertContains('throttle:ai-heavy', $route->gatherMiddleware());
        }
    }

    public function test_provider_exceptions_fail_without_leaking_error_content_or_losing_the_score(): void
    {
        $user = $this->premiumUser();
        $cv = $this->savedCv($user);
        $match = $this->createMatch($user, $cv);
        $match->update(['ai_status' => AiStatus::Queued]);
        $provider = Mockery::mock(CvAiProvider::class);
        $provider->shouldReceive('isConfigured')->once()->andReturn(true);
        $provider->shouldReceive('jobMatchAdvice')->once()->andThrow(new \RuntimeException('Sensitive provider diagnostic'));
        (new GenerateJobMatchAdviceJob($match->id))->handle($provider, app(AtsScoringService::class));
        Sanctum::actingAs($user);
        $this->getJson('/api/job-matches/'.$match->id)->assertOk()
            ->assertJsonPath('data.ai_status', 'failed')
            ->assertJsonPath('data.job_match', $match->job_match)
            ->assertDontSee('Sensitive provider diagnostic');
    }

    public function test_ai_failure_retains_all_deterministic_scores(): void
    {
        $user = $this->premiumUser();
        $cv = $this->savedCv($user);
        $match = $this->createMatch($user, $cv);
        $match->update(['ai_status' => AiStatus::Queued]);
        $before = JobMatch::scoreAttributes(app(AtsScoringService::class)->scoreDocument($cv->cvDocument(), null, $match->job_description_input));
        $provider = Mockery::mock(CvAiProvider::class);
        $provider->shouldReceive('isConfigured')->once()->andReturn(false);
        (new GenerateJobMatchAdviceJob($match->id))->handle($provider, app(AtsScoringService::class));
        $match->refresh();
        $this->assertSame(AiStatus::Failed, $match->ai_status);
        foreach ($before as $field => $value) {
            $this->assertSame($value, $match->$field);
        }
    }

    public function test_job_uses_frozen_snapshot_and_guard_then_ignores_duplicate_delivery(): void
    {
        $user = $this->premiumUser();
        $cv = $this->savedCv($user);
        $match = $this->createMatch($user, $cv);
        $match->update(['ai_status' => AiStatus::Queued]);
        $snapshot = $match->cv_snapshot;
        $cv->update(['document' => (new CvDocument)->toArray()]);
        $raw = ['executive_summary' => 'Advice', 'suggestions' => []];
        $provider = Mockery::mock(CvAiProvider::class);
        $provider->shouldReceive('isConfigured')->once()->andReturn(true);
        $provider->shouldReceive('jobMatchAdvice')->once()->with($snapshot, $match->job_description_input, $match->language)->andReturn($raw);
        $scorer = app(AtsScoringService::class);
        $guard = Mockery::mock();
        $guard->shouldReceive('sanitize')->once()->with($raw, $snapshot, [...$scorer->extractJobTerms($match->job_description_input), $match->job_description_input])->andReturn($raw);
        $this->app->instance(JobMatchResultGuard::class, $guard);
        $job = new GenerateJobMatchAdviceJob($match->id);
        $job->handle($provider, $scorer);
        $job->handle($provider, $scorer);
        $this->assertSame(AiStatus::Completed, $match->fresh()->ai_status);
        $this->assertSame($raw, $match->fresh()->ai_feedback);
    }
}
