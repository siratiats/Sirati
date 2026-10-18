<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Cv\CvDocument;
use App\Cv\LocalizedText;
use App\Enums\AiStatus;
use App\Jobs\GenerateJobMatchAdviceJob;
use App\Models\GeneratedCv;
use App\Models\JobMatch;
use App\Services\AtsScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

require_once __DIR__.'/JobMatchControllerTest.php';

class JobMatchApplyTest extends TestCase
{
    use JobMatchFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareJobMatch();
    }

    private function suggestion(string $key, string $section, string $field, string $before, string $after, ?string $entryId = null, ?int $bulletIndex = null): array
    {
        return [
            'key' => $key,
            'target' => ['section' => $section, 'field' => $field, 'entry_id' => $entryId, 'bullet_index' => $bulletIndex],
            'before' => $before,
            'after' => $after,
            'rationale' => 'Use the relevant terminology already supplied by the user.',
            'jd_terms' => ['Kubernetes'],
            'needs_user_fact' => false,
        ];
    }

    private function richDocument(): CvDocument
    {
        $token = bin2hex(random_bytes(5));
        $text = fn (string $value): array => ['ar' => 'نص '.$value, 'en' => $value];

        return CvDocument::fromArray([
            'export_language' => 'en',
            'personal' => ['full_name' => $text('Candidate '.$token), 'headline' => $text('Specialist '.$token), 'email' => 'candidate@example.com', 'phone' => '+966555123456', 'location' => $text('Riyadh')],
            'summary' => $text('Systems specialist '.$token),
            'experience' => [
                ['id' => 'job-'.$token, 'title' => $text('Role '.$token), 'company' => $text('Employer '.$token), 'start_date' => '2020-03', 'end_date' => '2024-01', 'bullets' => [$text('Built systems'), $text('Wrote documentation')], 'narrative' => $text('Original narrative')],
                ['id' => 'other-'.$token, 'title' => $text('Another role'), 'company' => $text('Another employer'), 'is_current' => true, 'start_date' => '2024-02', 'bullets' => [$text('Managed systems')]],
            ],
            'skills' => [['id' => 'skill-'.$token, 'name' => $text('Documentation'), 'level' => ['en' => 'expert'], 'category' => ['en' => 'technical']]],
            'education' => [['id' => 'education-'.$token, 'degree' => $text('Degree'), 'institution' => $text('University'), 'start_date' => '2015', 'end_date' => '2019']],
            'certifications' => [['id' => 'cert-'.$token, 'name' => $text('Certificate'), 'issuer' => $text('Issuer')]],
            'languages' => [['id' => 'lang-'.$token, 'name' => $text('English'), 'level' => ['en' => 'fluent']]],
            'projects' => [['id' => 'project-'.$token, 'name' => $text('Project'), 'description' => $text('Project details')]],
            'custom_sections' => [['id' => 'custom-'.$token, 'key' => 'volunteering', 'title' => $text('Volunteering'), 'body' => $text('Community service')]],
        ]);
    }

    public function test_selected_edits_preserve_untouched_document_data_and_sync_all_persisted_formats(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        foreach (range(1, 3) as $iteration) {
            $document = $this->richDocument();
            $cv = $this->savedCv($user, $document);
            $original = $document->toArray();
            $entryId = $original['experience'][0]['id'];
            $summary = $this->suggestion('summary', 'summary', 'text', $document->resolve()->summary, 'Systems specialist using Kubernetes');
            $skill = $this->suggestion('skill', 'skills', 'name', '', 'Kubernetes');
            $bullet = $this->suggestion('bullet', 'experience', 'bullets', 'Built systems', 'Built Kubernetes systems', $entryId, 0);
            $match = $this->createMatch($user, $cv, [$summary, $skill, $bullet]);
            $result = $this->postJson('/api/job-matches/'.$match->id.'/apply-suggestions', ['suggestion_keys' => ['summary', 'skill', 'bullet']]);
            $result->assertOk();
            $copy = GeneratedCv::findOrFail($result->json('data.generated_cv.id'));
            $this->assertNotSame($cv->id, $copy->id);
            $this->assertSame($original, $cv->fresh()->cvDocument()->toArray());
            $updated = $copy->cvDocument()->toArray();
            $expected = $original;
            $expected['summary']['en'] = $summary['after'];
            $expected['experience'][0]['bullets'][0]['en'] = $bullet['after'];
            // Existing skills retain their IDs, levels, categories, and both translations.
            $appended = array_pop($updated['skills']);
            $this->assertSame('Kubernetes', $appended['name']['en']);
            $this->assertNotEmpty($appended['id']);
            $this->assertSame($expected, $updated);
            $this->assertSame($copy->document, CvDocument::fromArray($copy->document)->toArray());
            foreach ($copy->cvDocument()->legacySnapshot() as $field => $value) {
                $this->assertSame($value, $copy->$field);
                $this->assertSame($value, $copy->form_payload[$field]);
            }
            $this->assertSame($copy->document, $copy->form_payload['document']);
            $this->assertSame($copy->cvDocument()->resolve()->toMarkdown(), $copy->generated_markdown);
            $score = app(AtsScoringService::class)->scoreDocument($copy->cvDocument(), null, $match->job_description_input);
            $result->assertJsonPath('data.job_match.job_match', $score['job_match']);
            $this->assertSame($score['total'], $copy->score_total);
            $this->assertNull($copy->idempotency_key);
        }
        Queue::assertNothingPushed();
    }

    public function test_entries_without_ids_receive_stable_advice_targets_and_apply_through_the_api(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        $document = $this->richDocument()->toArray();
        foreach ($document['experience'] as &$entry) {
            unset($entry['id']);
        }
        unset($entry);
        $cv = $this->savedCv($user, CvDocument::fromArray($document));
        $payload = ['generated_cv_id' => $cv->id, 'job_description_input' => 'Kubernetes systems'];
        $matchId = $this->postJson('/api/job-matches', $payload)->assertCreated()->json('data.id');
        $match = JobMatch::findOrFail($matchId);
        $targetId = $match->cv_snapshot['experience'][0]['id'];
        $this->assertNotEmpty($targetId);
        $this->assertNotSame($targetId, $match->cv_snapshot['experience'][1]['id']);
        $secondId = $this->postJson('/api/job-matches', $payload)->assertCreated()->json('data.id');
        $this->assertSame($targetId, JobMatch::find($secondId)->cv_snapshot['experience'][0]['id']);
        $match->update([
            'ai_status' => AiStatus::Completed,
            'ai_feedback' => ['suggestions' => [$this->suggestion('bullet', 'experience', 'bullets', 'Built systems', 'Built Kubernetes systems', $targetId, 0)]],
        ]);
        $copyId = $this->postJson('/api/job-matches/'.$match->id.'/apply-suggestions', ['suggestion_keys' => ['bullet']])->assertOk()->json('data.generated_cv.id');
        $this->assertSame($document, $cv->fresh()->document);
        $copy = GeneratedCv::findOrFail($copyId);
        $this->assertSame('Built Kubernetes systems', $copy->document['experience'][0]['bullets'][0]['en']);
        $this->assertSame($targetId, $copy->document['experience'][0]['id']);
        $this->getJson('/api/generated-cvs/'.$copyId.'/preview?language=en')->assertOk()->assertSee('Kubernetes');
    }

    public function test_accepted_term_reaches_preview_html_and_downloaded_pdf(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        $cv = $this->savedCv($user, $this->richDocument());
        $accepted = 'Kubernetes';
        $match = $this->createMatch($user, $cv, [$this->suggestion('skill', 'skills', 'append', '', $accepted)]);
        $copyId = $this->postJson('/api/job-matches/'.$match->id.'/apply-suggestions', ['suggestion_keys' => ['skill']])->assertOk()->json('data.generated_cv.id');
        $this->getJson('/api/generated-cvs/'.$copyId.'/preview?language=en')->assertOk()->assertSee($accepted);
        $download = $this->get('/api/generated-cvs/'.$copyId.'/download?language=en');
        $download->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdfText = (new Parser)->parseContent($download->getContent())->getText();
        $this->assertStringContainsString($accepted, $pdfText);
        Queue::assertNotPushed(GenerateJobMatchAdviceJob::class);
    }

    public function test_reapplication_is_idempotent_and_additional_selections_build_on_prior_ones(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        $cv = $this->savedCv($user);
        $match = $this->createMatch($user, $cv, [
            $this->suggestion('summary', 'summary', 'text', $cv->summary_input, 'Systems documentation specialist'),
            $this->suggestion('skill', 'skills', 'append', '', 'Kubernetes'),
        ]);
        $url = '/api/job-matches/'.$match->id.'/apply-suggestions';
        $first = $this->postJson($url, ['suggestion_keys' => ['summary', 'skill']])->assertOk()->json('data.generated_cv.id');
        $this->postJson($url, ['suggestion_keys' => ['skill', 'summary']])->assertOk()->assertJsonPath('data.generated_cv.id', $first);
        $this->postJson($url, ['suggestion_keys' => ['skill']])->assertOk()->assertJsonPath('data.generated_cv.id', $first);
        $this->assertDatabaseCount('generated_cvs', 2);
        $this->assertCount(1, GeneratedCv::find($first)->cvDocument()->skills);
        $this->assertSame(['summary', 'skill'], $match->fresh()->applied_suggestion_ids);

        $other = $this->createMatch($user, $cv, $match->ai_feedback['suggestions']);
        $url = '/api/job-matches/'.$other->id.'/apply-suggestions';
        $partial = $this->postJson($url, ['suggestion_keys' => ['summary']])->assertOk()->json('data.generated_cv.id');
        $next = $this->postJson($url, ['suggestion_keys' => ['skill']])->assertOk()->json('data.generated_cv.id');
        $this->assertNotSame($partial, $next);
        $this->assertSame('Systems documentation specialist', GeneratedCv::find($next)->summary_input);
        $this->assertSame('Kubernetes', GeneratedCv::find($next)->skills_input);
    }

    public function test_stale_source_before_text_entry_id_and_bullet_index_fail_without_writing(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        foreach (['snapshot', 'before', 'entry', 'index', 'deleted'] as $case) {
            $cv = $this->savedCv($user, $this->richDocument());
            $suggestion = $this->suggestion('change', 'experience', 'bullets', 'Built systems', 'Built Kubernetes systems', $cv->document['experience'][0]['id'], 0);
            if ($case === 'before') {
                $suggestion['before'] .= ' changed';
            } elseif ($case === 'entry') {
                $suggestion['target']['entry_id'] = 'unknown';
            } elseif ($case === 'index') {
                $suggestion['target']['bullet_index'] = 99;
            }
            $match = $this->createMatch($user, $cv, [$suggestion]);
            if ($case === 'snapshot') {
                $doc = $cv->document;
                $doc['summary']['ar'] .= ' changed';
                $cv->update(['document' => $doc]);
            } elseif ($case === 'deleted') {
                $cv->delete();
            }
            $count = GeneratedCv::count();
            $this->postJson('/api/job-matches/'.$match->id.'/apply-suggestions', ['suggestion_keys' => ['change']])->assertStatus(409);
            $this->assertSame($count, GeneratedCv::count());
            $this->assertNull($match->fresh()->applied_suggestion_ids);
        }
    }

    public function test_unknown_conflicting_unverified_or_unsupported_edits_fail_atomically(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        foreach (['unknown', 'fact', 'missing_fact_flag', 'unsupported', 'conflict', 'duplicate', 'empty'] as $case) {
            $cv = $this->savedCv($user);
            $safe = $this->suggestion('safe', 'summary', 'text', $cv->summary_input, 'Systems documentation specialist');
            $unsafe = $this->suggestion('unsafe', 'skills', 'append', '', 'Kubernetes');
            $keys = ['safe', 'unsafe'];
            if ($case === 'unknown') {
                $keys[] = 'unknown';
            } elseif ($case === 'fact') {
                $unsafe['needs_user_fact'] = true;
            } elseif ($case === 'missing_fact_flag') {
                unset($unsafe['needs_user_fact']);
            } elseif ($case === 'unsupported') {
                $unsafe['target']['section'] = 'personal';
            } elseif ($case === 'conflict') {
                $unsafe = [...$safe, 'key' => 'unsafe', 'after' => 'Different summary'];
            } elseif ($case === 'duplicate') {
                $keys = ['safe', 'safe'];
            } elseif ($case === 'empty') {
                $keys = [];
            }
            $match = $this->createMatch($user, $cv, [$safe, $unsafe]);
            $count = GeneratedCv::count();
            $this->postJson('/api/job-matches/'.$match->id.'/apply-suggestions', ['suggestion_keys' => $keys])->assertUnprocessable();
            $this->assertSame($count, GeneratedCv::count());
            $this->assertNull($match->fresh()->applied_generated_cv_id);
        }
    }

    public function test_apply_requires_premium_and_ready_advice(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        $cv = $this->savedCv($user);
        $match = $this->createMatch($user, $cv, [$this->suggestion('skill', 'skills', 'append', '', 'Kubernetes')]);
        $url = '/api/job-matches/'.$match->id.'/apply-suggestions';
        foreach ([AiStatus::Queued, AiStatus::Processing, AiStatus::Failed] as $state) {
            $match->update(['ai_status' => $state]);
            $this->postJson($url, ['suggestion_keys' => ['skill']])->assertStatus(409);
        }
        $match->update(['ai_status' => AiStatus::Completed]);
        $user->revokeSubscription();
        $this->postJson($url, ['suggestion_keys' => ['skill']])->assertStatus(402);
        $this->getJson('/api/job-matches/'.$match->id)->assertStatus(402);
        $this->assertDatabaseCount('generated_cvs', 1);
    }

    public function test_real_guard_marks_invented_facts_and_the_apply_endpoint_rejects_them_without_writing(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        foreach ([1, 17, random_int(100, 9999)] as $unsupportedNumber) {
            $document = new CvDocument(
                exportLanguage: 'en',
                summary: LocalizedText::make(en: 'Documented systems for customers.'),
            );
            $cv = $this->savedCv($user, $document);
            $id = $this->postJson('/api/job-matches', [
                'generated_cv_id' => $cv->id,
                'job_description_input' => 'Document systems for customers.',
            ])->assertCreated()->json('data.id');
            $suggestion = $this->suggestion(
                fake()->uuid(), 'summary', 'text', $document->resolve()->summary,
                'Documented systems for '.$unsupportedNumber.' customers.',
            );
            $suggestion['jd_terms'] = ['systems'];
            $this->assertFalse($suggestion['needs_user_fact']);
            $provider = Mockery::mock(CvAiProvider::class);
            $provider->shouldReceive('isConfigured')->once()->andReturn(true);
            $provider->shouldReceive('jobMatchAdvice')->once()->andReturn(['suggestions' => [$suggestion]]);

            // Exercise the production job and real guard, then the client-visible
            // advice and apply endpoints rather than pre-setting the safety flag.
            (new GenerateJobMatchAdviceJob($id))->handle($provider, app(AtsScoringService::class));
            $this->getJson('/api/job-matches/'.$id)->assertOk()
                ->assertJsonPath('data.ai_status', 'completed')
                ->assertJsonPath('data.ai_feedback.suggestions.0.needs_user_fact', true);
            $match = JobMatch::findOrFail($id);
            $beforeMatch = $match->getRawOriginal();
            $beforeCv = $cv->fresh()->getRawOriginal();
            $count = GeneratedCv::count();
            $this->postJson('/api/job-matches/'.$id.'/apply-suggestions', [
                'suggestion_keys' => [$suggestion['key']],
            ])->assertUnprocessable()->assertJsonValidationErrors('suggestion_keys');
            $this->assertSame($count, GeneratedCv::count());
            $this->assertSame($beforeCv, $cv->fresh()->getRawOriginal());
            $this->assertSame($beforeMatch, $match->fresh()->getRawOriginal());
        }
    }

    public function test_apply_uses_requested_export_language_and_preserves_the_other_translation(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        $cv = $this->savedCv($user, $this->richDocument());
        $before = $cv->cvDocument()->summary->resolve('ar');
        $after = 'خبرة في Kubernetes';
        $match = $this->createMatch($user, $cv, [$this->suggestion('summary', 'summary', 'text', $before, $after)]);
        $match->update(['language' => 'ar']);
        $result = $this->postJson('/api/job-matches/'.$match->id.'/apply-suggestions', ['suggestion_keys' => ['summary']])->assertOk();
        $copy = GeneratedCv::find($result->json('data.generated_cv.id'));
        $this->assertSame('ar', $copy->language);
        $this->assertSame($after, $copy->summary_input);
        $this->assertSame($cv->cvDocument()->summary->en, $copy->cvDocument()->summary->en);
        $this->getJson('/api/generated-cvs/'.$copy->id.'/preview?language=ar')->assertOk()->assertSee('Kubernetes');
    }
}
