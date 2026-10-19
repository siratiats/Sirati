<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Cv\CvDocument;
use App\Enums\AiStatus;
use App\Jobs\GenerateJobMatchAdviceJob;
use App\Models\GeneratedCv;
use App\Services\AtsScoringService;
use App\Support\ArabicPdfText;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

require_once __DIR__.'/JobMatchControllerTest.php';

class JobMatchFactConfirmationTest extends TestCase
{
    use JobMatchFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareJobMatch();
    }

    private function skill(string $key, string $after, bool $needsFact = true): array
    {
        return [
            'key' => $key, 'kind' => 'append_skill',
            'target' => ['section' => 'skills', 'field' => 'name', 'entry_id' => null, 'bullet_index' => null],
            'before' => null, 'after' => $after, 'needs_user_fact' => $needsFact,
            'rationale' => 'Confirm this skill before adding it.', 'jd_terms' => [$after],
        ];
    }

    public function test_generated_mixed_selections_require_every_new_fact_and_audit_only_successful_applications(): void
    {
        $this->freezeTime();
        $random = new Randomizer(new Mt19937(20261019));
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        foreach (['en', 'ar'] as $language) {
            for ($sample = 0; $sample < 6; $sample++) {
                $doc = CvDocument::fromArray([
                    'export_language' => $language,
                    'personal' => ['full_name' => [$language => 'Candidate']],
                    'summary' => [$language => $language === 'ar' ? 'خبرة في الأنظمة' : 'Experienced in systems'],
                ]);
                $cv = $this->savedCv($user, $doc);
                $suggestions = [];
                for ($i = 0, $n = $random->getInt(4, 12); $i < $n; $i++) {
                    $token = bin2hex($random->getBytes(5));
                    $suggestions[] = $this->skill('key-'.$token, ($language === 'ar' ? 'مهارة ' : 'Skill ').$token, $i % 2 === 0);
                }
                $suggestions = $random->shuffleArray($suggestions);
                $match = $this->createMatch($user, $cv, $suggestions);
                $url = '/api/job-matches/'.$match->id.'/apply-suggestions';
                $selected = array_slice($suggestions, 0, count($suggestions) - 1);
                $keys = array_column($selected, 'key');
                $confirmed = array_column(array_filter($selected, fn (array $s): bool => $s['needs_user_fact']), 'key');
                $beforeMatch = $match->fresh()->getRawOriginal();
                $beforeCv = $cv->fresh()->getRawOriginal();
                $count = GeneratedCv::count();
                $this->postJson($url, ['suggestion_keys' => $keys, 'confirmed_fact_keys' => array_slice($confirmed, 1)])
                    ->assertUnprocessable();
                $this->assertSame($count, GeneratedCv::count());
                $this->assertSame($beforeMatch, $match->fresh()->getRawOriginal());
                $this->assertSame($beforeCv, $cv->fresh()->getRawOriginal());

                $response = $this->postJson($url, ['suggestion_keys' => $keys, 'confirmed_fact_keys' => $confirmed])->assertOk();
                $copyId = $response->json('data.generated_cv.id');
                $copy = GeneratedCv::findOrFail($copyId);
                $this->assertSame($beforeCv, $cv->fresh()->getRawOriginal());
                $this->assertEqualsCanonicalizing(array_column($selected, 'after'), array_map(fn (array $s): string => $s['name'][$language], $copy->document['skills']));
                $this->assertSame($copy->cvDocument()->legacySnapshot()['skills_input'], $copy->skills_input);
                $this->assertSame($copy->document, $copy->form_payload['document']);
                $this->assertSame($copy->cvDocument()->resolve()->toMarkdown(), $copy->generated_markdown);
                $event = [
                    'applied_suggestion_ids' => $keys, 'confirmed_fact_keys' => $confirmed,
                    'applied_generated_cv_id' => $copyId, 'applied_at' => now()->toISOString(),
                ];
                $this->getJson('/api/job-matches/'.$match->id)->assertOk()
                    ->assertJsonPath('data.confirmed_fact_keys', $confirmed)
                    ->assertJsonPath('data.suggestion_application_audit', [$event]);
                $saved = $match->fresh()->getRawOriginal();
                $this->travel(1)->minutes();
                config(['monetization.job_match.daily_limit' => 0]);
                $this->postJson($url, ['suggestion_keys' => array_reverse($keys), 'confirmed_fact_keys' => array_reverse($confirmed)])
                    ->assertOk()->assertJsonPath('data.generated_cv.id', $copyId);
                // An attestation is persisted; retries do not need to repeat it.
                $this->postJson($url, ['suggestion_keys' => $keys])->assertOk()->assertJsonPath('data.generated_cv.id', $copyId);
                $this->assertSame($count + 1, GeneratedCv::count());
                $this->assertSame($saved, $match->fresh()->getRawOriginal());

                $next = $suggestions[array_key_last($suggestions)];
                $this->postJson($url, ['suggestion_keys' => [$next['key']], 'confirmed_fact_keys' => $next['needs_user_fact'] ? [$next['key']] : []])->assertOk();
                $match->refresh();
                $this->assertCount(2, $match->suggestion_application_audit);
                $this->assertSame($event, $match->suggestion_application_audit[0]);
                $this->assertSame([...$keys, $next['key']], $match->applied_suggestion_ids);
                $this->assertSame($next['needs_user_fact'] ? [...$confirmed, $next['key']] : $confirmed, $match->confirmed_fact_keys);
                $this->assertCount(count($suggestions), $match->appliedGeneratedCv->document['skills']);
            }
        }
    }

    public function test_confirmations_cannot_cross_matches_selections_or_ownership_or_accept_malformed_lists(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        $cv = $this->savedCv($user);
        $key = fake()->uuid();
        $safeKey = fake()->uuid();
        $foreignKey = fake()->uuid();
        $match = $this->createMatch($user, $cv, [$this->skill($key, 'Kubernetes'), $this->skill($safeKey, 'Documentation', false)]);
        $other = $this->createMatch($user, $cv, [$this->skill($foreignKey, 'Terraform')]);
        $url = '/api/job-matches/'.$match->id.'/apply-suggestions';
        $before = $match->fresh()->getRawOriginal();
        foreach ([
            ['suggestion_keys' => [$key], 'confirmed_fact_keys' => [$foreignKey]],
            ['suggestion_keys' => [$key, $foreignKey], 'confirmed_fact_keys' => [$key, $foreignKey]],
            ['suggestion_keys' => [$safeKey], 'confirmed_fact_keys' => [$key]],
            ['suggestion_keys' => [$safeKey], 'confirmed_fact_keys' => [$safeKey]],
            ['suggestion_keys' => [$key], 'confirmed_fact_keys' => [$key, $key]],
            ['suggestion_keys' => [$key, $key], 'confirmed_fact_keys' => [$key]],
            ['suggestion_keys' => [$key], 'confirmed_fact_keys' => null],
            ['suggestion_keys' => [$key], 'confirmed_fact_keys' => $key],
            ['suggestion_keys' => [$key], 'confirmed_fact_keys' => ['named' => $key]],
            ['suggestion_keys' => [$key], 'confirmed_fact_keys' => [[$key]]],
            ['suggestion_keys' => [$key], 'confirmed_fact_keys' => array_fill(0, 51, $key)],
        ] as $payload) {
            $this->postJson($url, $payload)->assertUnprocessable();
            $this->assertSame($before, $match->fresh()->getRawOriginal());
            $this->assertDatabaseCount('generated_cvs', 1);
            $this->assertNull($other->fresh()->confirmed_fact_keys);
        }
        Sanctum::actingAs($this->premiumUser());
        $this->postJson($url, ['suggestion_keys' => [$key], 'confirmed_fact_keys' => [$key]])->assertNotFound();
    }

    public function test_confirmation_never_bypasses_shape_before_target_readiness_or_staleness_checks(): void
    {
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        foreach (['kind', 'target', 'before', 'replace_before', 'replace_target', 'length', 'multiline', 'missing_flag', 'snapshot', 'deleted', 'queued'] as $case) {
            $cv = $this->savedCv($user);
            $key = fake()->uuid();
            $s = $this->skill($key, 'Kubernetes');
            $status = 422;
            if ($case === 'kind') {
                $s['kind'] = 'replace';
            } elseif ($case === 'target') {
                $s['target']['section'] = 'personal';
            } elseif ($case === 'before') {
                $s['before'] = 'Existing fact';
                $status = 409;
            } elseif ($case === 'replace_before' || $case === 'replace_target') {
                $s['kind'] = 'replace';
                $s['target'] = ['section' => 'summary', 'field' => 'text', 'entry_id' => null, 'bullet_index' => null];
                $s['before'] = $cv->summary_input;
                if ($case === 'replace_before') {
                    $s['before'] .= ' changed';
                    $status = 409;
                } else {
                    $s['target']['entry_id'] = fake()->uuid();
                }
            } elseif ($case === 'length') {
                $s['after'] = str_repeat('م', 61);
            } elseif ($case === 'multiline') {
                $s['after'] .= "\nTerraform";
            } elseif ($case === 'missing_flag') {
                unset($s['needs_user_fact']);
            }
            $match = $this->createMatch($user, $cv, [$s]);
            if ($case === 'snapshot') {
                $doc = $cv->document;
                $doc['summary']['en'] .= ' changed';
                $cv->update(['document' => $doc]);
                $status = 409;
            } elseif ($case === 'deleted') {
                $cv->delete();
                $status = 409;
            } elseif ($case === 'queued') {
                $match->update(['ai_status' => AiStatus::Queued]);
                $status = 409;
            }
            $before = $match->fresh()->getRawOriginal();
            $count = GeneratedCv::count();
            $this->postJson('/api/job-matches/'.$match->id.'/apply-suggestions', ['suggestion_keys' => [$key], 'confirmed_fact_keys' => [$key]])->assertStatus($status);
            $this->assertSame($before, $match->fresh()->getRawOriginal());
            $this->assertSame($count, GeneratedCv::count());
        }
    }

    public function test_guarded_confirmed_facts_reach_downloaded_arabic_and_english_copies(): void
    {
        $this->seed(CvTemplateSeeder::class);
        $user = $this->premiumUser();
        Sanctum::actingAs($user);
        $random = new Randomizer(new Mt19937(20261021));
        foreach (['en', 'ar'] as $language) {
            $local = fn (string $s): array => [$language => $s];
            $token = bin2hex($random->getBytes(4));
            $summary = $language === 'ar' ? 'خبرة في توثيق الأنظمة' : 'Experienced in documenting systems';
            $bullet = $language === 'ar' ? 'توثيق العمليات' : 'Documented operations';
            $skill = ($language === 'ar' ? 'تحليل ' : 'Analysis ').'Tool'.$token;
            $newSummary = $summary.($language === 'ar' ? ' باستخدام ' : ' using ').$skill;
            $newBullet = $bullet.($language === 'ar' ? ' باستخدام ' : ' using ').$skill;
            $entryId = fake()->uuid();
            $document = CvDocument::fromArray([
                'export_language' => $language,
                'personal' => ['full_name' => $local($language === 'ar' ? 'مرشح الاختبار' : 'Test Candidate'), 'email' => $token.'@example.com', 'phone' => '+966551234567'],
                'summary' => $local($summary),
                'experience' => [['id' => $entryId, 'title' => $local($language === 'ar' ? 'مسؤول الأنظمة' : 'Systems Specialist'), 'company' => $local('Employer '.$token), 'bullets' => [$local($bullet)]]],
            ]);
            $cv = $this->savedCv($user, $document);
            $suggestions = [$this->skill(fake()->uuid(), $skill, false)];
            foreach ([['summary', $summary, $newSummary], ['experience', $bullet, $newBullet]] as [$section, $before, $after]) {
                $suggestions[] = [
                    ...$this->skill(fake()->uuid(), $after, false), 'kind' => 'replace', 'before' => $before,
                    'target' => ['section' => $section, 'field' => $section === 'summary' ? 'text' : 'bullets',
                        'entry_id' => $section === 'summary' ? null : $entryId, 'bullet_index' => $section === 'summary' ? null : 0],
                    'jd_terms' => [$skill],
                ];
            }
            $match = $this->createMatch($user, $cv);
            $match->update(['ai_status' => AiStatus::Queued, 'ai_feedback' => null, 'job_description_input' => $skill]);
            $provider = Mockery::mock(CvAiProvider::class);
            $provider->shouldReceive('isConfigured')->once()->andReturn(true);
            $provider->shouldReceive('jobMatchAdvice')->once()->andReturn(['suggestions' => $suggestions]);
            (new GenerateJobMatchAdviceJob($match->id))->handle($provider, app(AtsScoringService::class));
            $feedback = $this->getJson('/api/job-matches/'.$match->id)->assertOk()->json('data.ai_feedback.suggestions');
            $this->assertCount(3, $feedback);
            foreach ($feedback as $s) {
                $this->assertTrue($s['needs_user_fact']);
            }
            $keys = array_column($feedback, 'key');
            $url = '/api/job-matches/'.$match->id.'/apply-suggestions';
            $this->postJson($url, ['suggestion_keys' => $keys])->assertUnprocessable();
            $result = $this->postJson($url, ['suggestion_keys' => $keys, 'confirmed_fact_keys' => $keys])->assertOk();
            $copyId = $result->json('data.generated_cv.id');
            $this->assertNotSame($cv->id, $copyId);
            $this->assertSame($document->toArray(), $cv->fresh()->document);
            $download = $this->get('/api/generated-cvs/'.$copyId.'/download?template=ats-classic-professional&language='.$language)
                ->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $raw = (new Parser)->parseContent($download->getContent())->getText();
            // Extraction is the single logical-text normalization boundary.
            $text = preg_replace('/\s+/u', ' ', ArabicPdfText::normalizeExtracted($raw));
            foreach ([$newSummary, $newBullet, $skill, $token.'@example.com'] as $fact) {
                $this->assertStringContainsString($fact, $text);
            }
            $score = app(AtsScoringService::class)->score($text, 'Engineer', 'software', $skill);
            $this->assertSame(100, $score['job_match']);
        }
    }
}
