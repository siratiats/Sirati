<?php

namespace Tests\Feature;

use App\Models\JobMatch;
use App\Models\User;
use App\Services\Admin\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin@sirati.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.admin.emails' => [self::ADMIN_EMAIL]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------- access

    public function test_every_monitoring_route_is_admin_only(): void
    {
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);
        $member = User::factory()->create(['email' => 'member@sirati.test']);

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => Str::startsWith((string) $route->getName(), ['admin.health.', 'admin.users.']))
            ->values();

        $this->assertNotEmpty($routes);
        $this->assertTrue(Route::has('admin.health.index'));
        $this->assertTrue(Route::has('admin.users.index'));
        $this->assertTrue(Route::has('admin.users.show'));

        foreach ($routes as $route) {
            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
            $name = $route->getName();
            $url = fn () => route($name, $this->routeParameters($route->parameterNames(), $member));

            $this->app['auth']->forgetGuards();
            $this->call($method, $url())->assertRedirect(route('login'));

            $this->actingAs($member)->call($method, $url())->assertForbidden();

            $response = $this->actingAs($admin)->call($method, $url());
            if ($method === 'GET') {
                $response->assertOk();
            } else {
                $this->assertTrue($response->isRedirect(), "{$name} should redirect for admins, got {$response->getStatusCode()}");
            }
        }
    }

    public function test_overview_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'member@sirati.test']))
            ->get(route('admin.index'))
            ->assertForbidden();
    }

    // -------------------------------------------------------------- overview

    public function test_overview_renders_with_an_empty_database_for_every_range(): void
    {
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);

        foreach (DashboardMetrics::RANGES as $range) {
            $this->actingAs($admin)
                ->get(route('admin.index', ['range' => $range]))
                ->assertOk()
                ->assertSee('New users')
                ->assertSee('Premium users')
                ->assertSee('Activity trends')
                ->assertSee('No failed queue jobs or AI requests recorded.');
        }

        // Unknown ranges fall back to 7d instead of failing.
        $this->actingAs($admin)->get(route('admin.index', ['range' => 'forever']))->assertOk();
    }

    public function test_overview_renders_seeded_metrics_failures_and_shell(): void
    {
        Carbon::setTestNow('2026-10-20 15:30:00');
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);
        $member = User::factory()->create();

        $this->insertAnalysis($member->id, now()->subHour(), 'failed', 'OpenAI timeout after 30s');
        $this->insertAnalysis($member->id, now()->subHours(2));
        $this->insertGeneratedCv($member->id, now()->subHours(3));
        $this->insertAiCall('openai', 'gpt-4o-mini', now()->subMinutes(5), 900);
        $this->insertFailedJob('App\\Jobs\\RenderCvPdfJob', 'RuntimeException: font missing', now()->subMinutes(10));

        $response = $this->actingAs($admin)->get(route('admin.index', ['range' => 'today']));

        $response->assertOk()
            ->assertSee('Operations dashboard')
            ->assertSee('OpenAI timeout after 30s')
            ->assertSee('RenderCvPdfJob')
            ->assertSee('RuntimeException: font missing')
            ->assertSee('Openai (gpt-4o-mini)')
            ->assertSee('Retry all (1)')
            ->assertSee('Testing') // environment badge
            ->assertSee('System health')
            ->assertSee('Users');

        // Localization nav items render only when their routes exist.
        if (! Route::has('admin.languages.index')) {
            $response->assertDontSee('>Languages<', false);
        }
    }

    public function test_overview_metrics_are_cached_for_sixty_seconds_and_refresh_clears_them(): void
    {
        Carbon::setTestNow('2026-10-20 12:00:00');
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);
        $metrics = app(DashboardMetrics::class);

        $first = $metrics->overview('7d')['kpis']['new_users']['value'];
        User::factory()->count(3)->create();

        $this->assertSame($first, $metrics->overview('7d')['kpis']['new_users']['value']);

        $this->actingAs($admin)->get(route('admin.index', ['range' => '7d', 'refresh' => 1]))->assertOk();
        $this->assertSame($first + 3, $metrics->overview('7d')['kpis']['new_users']['value']);
    }

    // ------------------------------------------------------ metrics invariants

    public function test_previous_window_has_the_same_length_and_ends_where_the_current_starts(): void
    {
        $metrics = app(DashboardMetrics::class);

        foreach (['2026-10-20 00:00:00', '2026-10-20 00:00:01', '2026-03-01 23:59:59', '2026-12-31 13:14:15'] as $now) {
            foreach (DashboardMetrics::RANGES as $range) {
                $window = $metrics->window($range, Carbon::parse($now));

                $this->assertEquals(
                    $window['start']->diffInSeconds($window['end'], true),
                    $window['previous_start']->diffInSeconds($window['start'], true),
                    "{$range} @ {$now}",
                );
                $this->assertTrue($window['start']->lte($window['end']));
                $this->assertSame($window['start']->copy()->startOfDay()->getTimestamp(), $window['start']->getTimestamp());
                $this->assertCount(['today' => $window['end']->hour + 1, '7d' => 7, '30d' => 30][$range], $window['buckets']);
            }
        }
    }

    public function test_kpi_counts_and_deltas_match_the_database_for_arbitrary_dates_at_window_boundaries(): void
    {
        $metrics = app(DashboardMetrics::class);

        foreach ([11, 42, 2026] as $seed) {
            mt_srand($seed);
            $now = Carbon::parse('2026-10-20 00:00:00')->addSeconds(mt_rand(0, 86399));
            Carbon::setTestNow($now);
            DB::table('cv_analyses')->delete();
            DB::table('generated_cvs')->delete();
            DB::table('job_matches')->delete();

            $users = User::factory()->count(4)->create(['created_at' => $now->copy()->subDays(90)])->pluck('id')->all();
            $timestamps = [];

            foreach (DashboardMetrics::RANGES as $range) {
                $window = $metrics->window($range, $now);
                // Exact edges of both windows plus one second either side.
                foreach ([$window['start'], $window['previous_start'], $window['end']] as $edge) {
                    $timestamps[] = $edge->copy();
                    $timestamps[] = $edge->copy()->subSecond();
                    $timestamps[] = $edge->copy()->addSecond();
                }
            }
            for ($i = 0; $i < 25; $i++) {
                $timestamps[] = $now->copy()->subSeconds(mt_rand(0, 65 * 86400));
            }

            $rows = [];
            foreach ($timestamps as $i => $at) {
                $table = ['cv_analyses', 'generated_cvs', 'job_matches'][mt_rand(0, 2)];
                // Some analyses are guest rows (no user) and must not count as active users.
                $userId = $table === 'cv_analyses' && mt_rand(0, 4) === 0 ? null : $users[mt_rand(0, 3)];
                match ($table) {
                    'cv_analyses' => $this->insertAnalysis($userId, $at),
                    'generated_cvs' => $this->insertGeneratedCv($userId, $at),
                    'job_matches' => JobMatch::factory()->create(['user_id' => $userId, 'created_at' => $at, 'updated_at' => $at]),
                };
                $rows[] = ['table' => $table, 'user_id' => $userId, 'at' => $at->getTimestamp()];
            }

            foreach (DashboardMetrics::RANGES as $range) {
                $window = $metrics->window($range, $now);
                $kpis = $metrics->kpis($window);
                $start = $window['start']->getTimestamp();
                $end = $window['end']->getTimestamp();
                $previousStart = $window['previous_start']->getTimestamp();
                $context = "seed {$seed}, range {$range}, now {$now}";

                foreach (['cv_analyses', 'generated_cvs', 'job_matches'] as $table) {
                    $current = count(array_filter($rows, fn ($r) => $r['table'] === $table && $r['at'] >= $start && $r['at'] <= $end));
                    $previous = count(array_filter($rows, fn ($r) => $r['table'] === $table && $r['at'] >= $previousStart && $r['at'] < $start));

                    $this->assertSame($current, $kpis[$table]['value'], "{$table} current, {$context}");
                    $this->assertSame($previous, $kpis[$table]['previous'], "{$table} previous, {$context}");
                    $this->assertSame($current, array_sum($kpis[$table]['series']), "{$table} buckets sum, {$context}");
                    $this->assertSame(
                        $previous === 0 ? null : round(($current - $previous) / $previous * 100, 1),
                        $kpis[$table]['delta'],
                        "{$table} delta, {$context}",
                    );
                }

                $active = array_unique(array_column(array_filter($rows, fn ($r) => $r['user_id'] !== null && $r['at'] >= $start && $r['at'] <= $end), 'user_id'));
                $activeBefore = array_unique(array_column(array_filter($rows, fn ($r) => $r['user_id'] !== null && $r['at'] >= $previousStart && $r['at'] < $start), 'user_id'));
                $this->assertSame(count($active), $kpis['active_users']['value'], "active users, {$context}");
                $this->assertSame(count($activeBefore), $kpis['active_users']['previous'], "active users previous, {$context}");
            }
        }
    }

    public function test_delta_is_undefined_without_a_previous_base(): void
    {
        $this->assertNull(DashboardMetrics::delta(5, 0));
        $this->assertNull(DashboardMetrics::delta(null, 3));
        $this->assertSame(0.0, DashboardMetrics::delta(7, 7));
        $this->assertSame(-100.0, DashboardMetrics::delta(0, 7));
        $this->assertSame(50.0, DashboardMetrics::delta(3, 2));
    }

    public function test_premium_users_follow_the_entitlement_rule(): void
    {
        Carbon::setTestNow('2026-10-20 12:00:00');
        User::factory()->create(['is_premium' => true, 'premium_until' => null]);
        User::factory()->create(['is_premium' => true, 'premium_until' => now()->subDay()]); // expired override wins
        User::factory()->create(['is_premium' => false, 'premium_until' => now()->addDay()]);
        User::factory()->create(['is_premium' => false, 'premium_until' => null]);

        $expected = User::all()->filter->isPremium()->count();

        $this->assertSame(2, $expected);
        $this->assertSame($expected, app(DashboardMetrics::class)->premiumUsers(now()));
    }

    public function test_ai_usage_percentile_and_provider_totals_match_the_rows(): void
    {
        Carbon::setTestNow('2026-10-20 12:00:00');
        mt_srand(7);
        $durations = ['openai' => [], 'anthropic' => []];

        foreach ([37, 3] as $i => $count) {
            $provider = $i === 0 ? 'openai' : 'anthropic';
            for ($n = 0; $n < $count; $n++) {
                $ms = mt_rand(50, 9000);
                $durations[$provider][] = $ms;
                $this->insertAiCall($provider, $provider.'-model', now()->subMinutes(mt_rand(0, 600)), $ms);
            }
        }
        $this->insertAiCall('openai', 'old', now()->subDays(3), 99999); // outside the window

        $usage = app(DashboardMetrics::class)->aiUsage(now()->subDay(), now());
        $nearestRank = function (array $values): int {
            sort($values);

            return $values[(int) ceil(0.95 * count($values)) - 1];
        };

        $this->assertSame(40, $usage['calls']);
        $this->assertSame($nearestRank(array_merge(...array_values($durations))), $usage['p95_ms']);
        $this->assertNull($usage['cost'], 'ai_call_logs has no cost column, so no cost may be shown');
        $this->assertNull($usage['error_rate'], 'ai_call_logs has no status column');

        $byProvider = collect($usage['providers'])->keyBy('provider');
        $this->assertSame(37, $byProvider['openai']['calls']);
        $this->assertSame($nearestRank($durations['openai']), $byProvider['openai']['p95_ms']);
        $this->assertSame($nearestRank($durations['anthropic']), $byProvider['anthropic']['p95_ms']);
        $this->assertEqualsWithDelta(100.0, $byProvider->sum('share'), 0.2);
    }

    public function test_pipeline_failure_rate_counts_failed_over_finished_requests(): void
    {
        Carbon::setTestNow('2026-10-20 12:00:00');
        $user = User::factory()->create();
        $this->insertAnalysis($user->id, now()->subHour(), 'failed', 'boom');
        $this->insertAnalysis($user->id, now()->subHour());
        $this->insertGeneratedCv($user->id, now()->subHour());
        $this->insertGeneratedCv($user->id, now()->subHour(), 'processing'); // unfinished: ignored

        $summary = app(DashboardMetrics::class)->pipelineSummary(now()->subDay(), now());

        $this->assertSame(['completed' => 2, 'failed' => 1, 'failure_rate' => 33.33, 'available' => true], $summary);
    }

    // ---------------------------------------------------------------- health

    public function test_health_page_shows_queue_failed_jobs_and_providers(): void
    {
        config(['health.queue.connection' => 'database']);
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null,
            'available_at' => now()->subMinutes(5)->getTimestamp(), 'created_at' => now()->subMinutes(5)->getTimestamp(),
        ]);
        $this->insertFailedJob('App\\Jobs\\GenerateCvContentJob', 'Illuminate\\Http\\Client\\ConnectionException: timed out', now()->subMinute());
        $this->insertAiCall('deepinfra', 'llama', now()->subHour(), 320);

        $this->actingAs($admin)->get(route('admin.health.index'))
            ->assertOk()
            ->assertSee('Queue workers')
            ->assertSee('GenerateCvContentJob')
            ->assertSee('ConnectionException: timed out')
            ->assertSee('deepinfra')
            ->assertSee('5m') // oldest pending job age
            ->assertSee('Not logged');
    }

    public function test_health_page_degrades_when_failed_jobs_table_is_missing(): void
    {
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);
        Schema::drop('failed_jobs');

        $this->actingAs($admin)->get(route('admin.health.index'))
            ->assertOk()
            ->assertSee('The failed jobs table is unavailable.');

        $this->actingAs($admin)->get(route('admin.index'))->assertOk();
    }

    public function test_retry_pushes_the_failed_job_back_and_removes_it(): void
    {
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);
        $uuid = $this->insertFailedJob('App\\Jobs\\RenderCvPdfJob', 'RuntimeException: x', now(), 'pdfs');
        $other = $this->insertFailedJob('App\\Jobs\\RenderCvPdfJob', 'RuntimeException: y', now());

        $this->actingAs($admin)->post(route('admin.health.failed.retry', $uuid))
            ->assertRedirect(route('admin.health.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $other]);
        $this->assertSame(1, DB::table('jobs')->where('queue', 'pdfs')->count());
    }

    public function test_retry_of_an_unknown_job_reports_an_error(): void
    {
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);

        $this->actingAs($admin)->post(route('admin.health.failed.retry', 'missing-uuid'))
            ->assertRedirect(route('admin.health.index'))
            ->assertSessionHas('error');
    }

    public function test_forget_deletes_only_that_failed_job(): void
    {
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);
        $uuid = $this->insertFailedJob('App\\Jobs\\RenderCvPdfJob', 'RuntimeException: x', now());
        $other = $this->insertFailedJob('App\\Jobs\\RenderCvPdfJob', 'RuntimeException: y', now());

        $this->actingAs($admin)->post(route('admin.health.failed.forget', $uuid))
            ->assertRedirect(route('admin.health.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $other]);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_retry_all_requeues_every_failed_job(): void
    {
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);
        foreach (range(1, 3) as $i) {
            $this->insertFailedJob('App\\Jobs\\RenderCvPdfJob', "RuntimeException: {$i}", now());
        }

        $this->actingAs($admin)->post(route('admin.health.failed.retry-all'))->assertRedirect(route('admin.health.index'));

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(3, DB::table('jobs')->count());
    }

    public function test_non_admins_cannot_retry_or_forget(): void
    {
        $uuid = $this->insertFailedJob('App\\Jobs\\RenderCvPdfJob', 'RuntimeException: x', now());
        $member = User::factory()->create(['email' => 'member@sirati.test']);

        $this->actingAs($member)->post(route('admin.health.failed.retry', $uuid))->assertForbidden();
        $this->actingAs($member)->post(route('admin.health.failed.forget', $uuid))->assertForbidden();
        $this->actingAs($member)->post(route('admin.health.failed.retry-all'))->assertForbidden();

        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    // ----------------------------------------------------------------- users

    public function test_users_index_searches_and_filters(): void
    {
        Carbon::setTestNow('2026-10-20 12:00:00');
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL, 'name' => 'Root Admin']);
        $premium = User::factory()->create(['name' => 'Sara Premium', 'email' => 'sara@example.com', 'premium_until' => now()->addMonth()]);
        $expired = User::factory()->create(['name' => 'Omar Expired', 'email' => 'omar@example.com', 'is_premium' => true, 'premium_until' => now()->subDay()]);
        $unverified = User::factory()->unverified()->create(['name' => 'Lina Free', 'email' => 'lina@example.com']);

        $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'sara']))
            ->assertOk()->assertSee('Sara Premium')->assertDontSee('Omar Expired');

        $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'LINA@EXAMPLE']))
            ->assertOk()->assertSee('Lina Free')->assertDontSee('Sara Premium');

        $this->actingAs($admin)->get(route('admin.users.index', ['premium' => 'yes']))
            ->assertOk()->assertSee('Sara Premium')->assertDontSee('Omar Expired')->assertDontSee('Lina Free');

        $this->actingAs($admin)->get(route('admin.users.index', ['premium' => 'no']))
            ->assertOk()->assertDontSee('Sara Premium')->assertSee('Omar Expired')->assertSee('Lina Free')->assertSee('Root Admin');

        $this->actingAs($admin)->get(route('admin.users.index', ['verified' => 'no']))
            ->assertOk()->assertSee('Lina Free')->assertDontSee('Sara Premium');

        $this->actingAs($admin)->get(route('admin.users.index', ['verified' => 'yes', 'premium' => 'yes']))
            ->assertOk()->assertSee('Sara Premium')->assertDontSee('Lina Free');

        // Filter results agree with User::isPremium() for every account.
        $premiumIds = User::all()->filter->isPremium()->pluck('id')->sort()->values()->all();
        $this->assertSame([$premium->id], $premiumIds);
        $this->assertNotContains($expired->id, $premiumIds);
        $this->assertNotContains($unverified->id, $premiumIds);
    }

    public function test_user_detail_shows_activity_counts_and_premium_actions(): void
    {
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);
        $user = User::factory()->create(['name' => 'Huda <b>Detail</b> & Co']);
        $this->insertAnalysis($user->id, now());
        $this->insertAnalysis($user->id, now());
        $this->insertGeneratedCv($user->id, now());
        JobMatch::factory()->count(3)->create(['user_id' => $user->id]);
        JobMatch::factory()->create(); // someone else's

        $response = $this->actingAs($admin)->get(route('admin.users.show', $user));

        $response->assertOk()
            ->assertSee('Huda <b>Detail</b> & Co')
            ->assertDontSee('<b>Detail</b>', false)
            ->assertDontSee('&amp;amp;', false)
            ->assertSee(route('admin.users.grant-premium', $user))
            ->assertDontSee(route('admin.users.revoke-premium', $user));

        $user = $response->viewData('user');
        $this->assertSame(2, (int) $user->cv_analyses_count);
        $this->assertSame(1, (int) $user->generated_cvs_count);
        $this->assertSame(3, (int) $user->job_matches_count);

        $this->actingAs($admin)->post(route('admin.users.grant-premium', $user), ['days' => 10])->assertRedirect();
        $this->actingAs($admin)->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee(route('admin.users.revoke-premium', $user));
    }

    // --------------------------------------------------------------- helpers

    private function routeParameters(array $names, User $user): array
    {
        $parameters = [];
        foreach ($names as $name) {
            $parameters[$name] = match ($name) {
                'user' => $user->id,
                'id' => $this->insertFailedJob('App\\Jobs\\RenderCvPdfJob', 'RuntimeException: test', now()),
                default => throw new \LogicException("No test value for route parameter {$name}"),
            };
        }

        return $parameters;
    }

    private function insertAnalysis(?int $userId, Carbon $at, string $status = 'completed', ?string $error = null): void
    {
        DB::table('cv_analyses')->insert([
            'user_id' => $userId,
            'target_job_title' => 'Backend Developer',
            'resume_text' => 'Resume',
            'score_total' => 70,
            'grade' => 'B',
            'job_match' => 60,
            'criteria' => '[]', 'strengths' => '[]', 'weaknesses' => '[]',
            'keywords_found' => '[]', 'keywords_missing' => '[]', 'quick_wins' => '[]',
            'ai_status' => $status,
            'ai_error' => $error,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function insertGeneratedCv(?int $userId, Carbon $at, string $status = 'completed'): void
    {
        DB::table('generated_cvs')->insert([
            'user_id' => $userId,
            'full_name' => 'Test User',
            'target_job_title' => 'Backend Developer',
            'skills_input' => 'PHP',
            'experience_input' => 'Experience',
            'education_input' => 'BSc',
            'generated_markdown' => '# CV',
            'form_payload' => '{}',
            'ai_status' => $status,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function insertAiCall(string $provider, string $model, Carbon $at, int $durationMs): void
    {
        DB::table('ai_call_logs')->insert([
            'provider' => $provider,
            'model' => $model,
            'operation' => 'analysis_advice',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'duration_ms' => $durationMs,
            'created_at' => $at,
        ]);
    }

    private function insertFailedJob(string $class, string $exception, Carbon $at, string $queue = 'default'): string
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => $queue,
            'payload' => json_encode(['uuid' => $uuid, 'displayName' => $class, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'attempts' => 3, 'data' => ['commandName' => $class]]),
            'exception' => $exception."\n#0 /app/stack.php(1): trace",
            'failed_at' => $at,
        ]);

        return $uuid;
    }
}
