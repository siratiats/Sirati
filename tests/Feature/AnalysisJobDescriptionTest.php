<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Jobs\GenerateCvAdviceJob;
use App\Models\CvAnalysis;
use App\Models\User;
use App\Services\AtsScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Issue #16: the sync and async analysis paths score and advise with the
 * same job description, and job match exists only when it was measured
 * against one.
 */
class AnalysisJobDescriptionTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{score: array<string, mixed>, resume: string, title: string, jd: ?string}> */
    private array $adviceCalls = [];

    public function test_sync_and_async_paths_advise_with_the_submitted_job_description(): void
    {
        mt_srand(16);

        for ($i = 0; $i < 12; $i++) {
            [$title, $cv, $jd] = $this->randomCase();
            $expected = app(AtsScoringService::class)->score($cv, $title, null, $jd);

            $sync = $this->submit($title, $cv, $jd, async: false);
            $async = $this->submit($title, $cv, $jd, async: true);

            $this->assertSame($jd, $sync['call']['jd'], "case {$i}: sync advice lost the JD");
            $this->assertSame($jd, $async['call']['jd'], "case {$i}: async advice lost the JD");
            $this->assertEquals($expected, $sync['call']['score'], "case {$i}: sync advice score differs from the JD score");
            $this->assertEquals($expected, $async['call']['score'], "case {$i}: async advice score differs from the JD score");

            foreach ([$sync['json'], $async['json']] as $json) {
                $this->assertSame($expected['total'], $json['score_total']);
                $this->assertSame($expected['grade'], $json['grade']);
                $this->assertSame($expected['keyword_source'], $json['keyword_source']);
                $this->assertSame($expected['keyword_source'] === 'job_description' ? $expected['job_match'] : null, $json['job_match']);
            }
        }
    }

    public function test_without_a_job_description_there_is_no_job_match_on_either_path(): void
    {
        mt_srand(1616);

        for ($i = 0; $i < 8; $i++) {
            [$title, $cv] = $this->randomCase();
            $blank = [null, '', "  \n\t "][$i % 3];

            foreach ([false, true] as $async) {
                $result = $this->submit($title, $cv, $blank, $async);

                $this->assertNull($result['json']['job_match'], "case {$i}: job match without a JD");
                $this->assertSame('category', $result['json']['keyword_source']);
                $this->assertNull($result['call']['jd']);
                $this->assertNull($result['call']['score']['job_match']);
                $this->assertSame('category', $result['call']['score']['keyword_source']);
            }
        }
    }

    public function test_a_job_description_with_no_recognisable_terms_reports_no_job_match(): void
    {
        mt_srand(160);

        for ($i = 0; $i < 6; $i++) {
            [$title, $cv] = $this->randomCase();
            $jd = $this->noiseText();
            $this->assertSame([], app(AtsScoringService::class)->extractJobTerms($jd), 'noise produced job terms');

            foreach ([false, true] as $async) {
                $json = $this->submit($title, $cv, $jd, $async)['json'];

                $this->assertNull($json['job_match']);
                $this->assertSame('category', $json['keyword_source']);
            }
        }
    }

    public function test_job_match_is_null_whenever_keywords_did_not_come_from_a_job_description(): void
    {
        mt_srand(61);
        $scorer = app(AtsScoringService::class);

        for ($i = 0; $i < 40; $i++) {
            [$title, $cv, $jd] = $this->randomCase();
            $jd = $i % 2 === 0 ? $jd : null;
            $score = $scorer->score($cv, $title, null, $jd);

            $this->assertSame(
                $score['keyword_source'] === 'job_description',
                $score['job_match'] !== null,
                "case {$i}: job_match must exist exactly when it was measured against a JD",
            );
            if ($score['job_match'] !== null) {
                $this->assertGreaterThanOrEqual(0, $score['job_match']);
                $this->assertLessThanOrEqual(100, $score['job_match']);
            }
        }
    }

    public function test_grade_comes_from_the_single_threshold_table_for_every_total(): void
    {
        $thresholds = AtsScoringService::GRADE_THRESHOLDS;
        $floors = array_values($thresholds);
        $sorted = $floors;
        rsort($sorted);
        $this->assertSame($sorted, $floors, 'thresholds must be listed from the highest floor down');
        $this->assertSame(0, end($floors), 'the lowest grade must cover a total of 0');

        for ($total = 0; $total <= 100; $total++) {
            $expected = null;
            foreach ($thresholds as $grade => $floor) {
                if ($total >= $floor) {
                    $expected = $grade;
                    break;
                }
            }
            $this->assertSame($expected, AtsScoringService::gradeFor($total), "total {$total}");
        }
    }

    /**
     * @return array{json: array<string, mixed>, call: array{score: array<string, mixed>, resume: string, title: string, jd: ?string}}
     */
    private function submit(string $title, string $cv, ?string $jd, bool $async): array
    {
        Queue::fake();
        $this->adviceCalls = [];
        $this->captureProvider();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = ['target_job_title' => $title, 'resume_text' => $cv];
        if ($jd !== null) {
            $payload['job_description'] = $jd;
        }

        $request = $async ? $this->withHeader('X-Sirati-Async', '1') : $this;
        $response = $request->postJson('/api/cv-analyses', $payload)->assertCreated();
        $this->defaultHeaders = [];

        if ($async) {
            $this->assertSame([], $this->adviceCalls, 'async path called the AI inline');
            $jobs = Queue::pushed(GenerateCvAdviceJob::class);
            $this->assertCount(1, $jobs);
            app()->call([$jobs->first(), 'handle']);
            // The job forgets auth guards when it finishes.
            Sanctum::actingAs($user);
            $id = $response->json('data.id');
            $json = $this->getJson("/api/cv-analyses/{$id}")->assertOk()->json('data');
        } else {
            $json = $response->json('data');
        }

        $this->assertCount(1, $this->adviceCalls, 'advice must be requested exactly once');

        return ['json' => $json, 'call' => $this->adviceCalls[0]];
    }

    private function captureProvider(): void
    {
        $provider = Mockery::mock(CvAiProvider::class);
        $provider->shouldReceive('isConfigured')->andReturnTrue();
        $provider->shouldReceive('classifyDocument')->andReturn(['is_resume' => true]);
        $provider->shouldReceive('analysisAdvice')->andReturnUsing(
            function (array $score, string $resume, string $title, ?string $jd = null): array {
                $this->adviceCalls[] = compact('score', 'resume', 'title', 'jd');

                return ['executive_summary' => 'ok'];
            },
        );
        $this->app->instance(CvAiProvider::class, $provider);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function randomCase(): array
    {
        $pool = array_values(array_unique(array_merge(...array_values(AtsScoringService::jobKeywords()))));
        $pick = function (int $n) use ($pool): array {
            $keys = (array) array_rand($pool, $n);
            shuffle($keys);

            return array_map(fn (int $k): string => $pool[$k], $keys);
        };

        $titles = ['Data Analyst', 'محاسب', 'Registered Nurse', 'مهندس مدني', 'Sales Manager', 'Laravel Developer', 'أخصائي موارد بشرية'];
        $title = $titles[mt_rand(0, count($titles) - 1)];
        $skills = $pick(mt_rand(3, 9));
        $wins = $pick(2);
        $year = mt_rand(2010, 2020);

        $cv = implode("\n", [
            'Candidate '.mt_rand(100, 999),
            'person'.mt_rand(1, 999).'@example.com | +9665'.mt_rand(10000000, 99999999).' | linkedin.com/in/candidate',
            '',
            'Summary',
            "{$title} with ".mt_rand(2, 15).' years of experience in '.implode(', ', array_slice($skills, 0, 2)).'.',
            '',
            'Skills',
            implode(', ', $skills),
            '',
            'Experience',
            "{$title}, Company ".mt_rand(1, 50).", {$year} - 2026",
            '- Improved '.$wins[0].' results by '.mt_rand(5, 60).'%.',
            '- Led '.$wins[1].' work for '.mt_rand(5, 200).' clients.',
            '',
            'Education',
            'Bachelor degree, '.($year - 1),
        ]);

        $wanted = $pick(mt_rand(2, 10));
        $jd = mt_rand(0, 1) === 1
            ? "We are hiring a {$title}. Requirements: ".implode(', ', $wanted).'. Experience with '.$wanted[0].' is a plus.'
            : "نبحث عن {$title}. المتطلبات: ".implode('، ', $wanted).'.';

        return [$title, $cv, $jd];
    }

    private function noiseText(): string
    {
        $parts = [];
        for ($i = 0, $n = mt_rand(4, 12); $i < $n; $i++) {
            $parts[] = (string) mt_rand(10, 99999);
        }

        return implode(' - ', $parts);
    }
}
