<?php

namespace Tests\Feature;

use App\Exceptions\SubscriptionRequiredException;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionGate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class JobMatchGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->startOfDay()->addHours(13));
        config([
            'monetization.enabled' => true,
            'monetization.job_match.daily_limit' => 20,
            'services.revenuecat.webhook_secret' => 'job-match-test-webhook-secret',
        ]);

        // Task C owns the production migration. Keep this gate suite runnable
        // in isolation, and use the real table once that migration is present.
        if (! Schema::hasTable('job_matches')) {
            Schema::create('job_matches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('generated_cv_id')->nullable();
                $table->string('idempotency_key');
                $table->string('target_job_title');
                $table->text('job_description_input');
                $table->string('language');
                $table->unsignedInteger('score_total');
                $table->string('grade');
                $table->unsignedInteger('job_match');
                $table->string('category');
                foreach (['criteria', 'keywords_found', 'keywords_missing', 'strengths', 'weaknesses', 'ai_feedback', 'applied_suggestion_ids'] as $column) {
                    $table->json($column)->nullable();
                }
                $table->string('ai_status');
                $table->text('ai_error')->nullable();
                $table->string('cv_snapshot_hash');
                $table->timestamps();
                $table->unique(['user_id', 'idempotency_key']);
            });
        }
    }

    public static function entitlementCases(): iterable
    {
        $statuses = [
            'free' => [false, null, false],
            'unsubscribed_trial_offer' => [false, null, false],
            'active_trial' => [false, 3600, true],
            'active_paid' => [true, 86400, true],
            'cancelled_but_unexpired' => [true, 1, true],
            'expired_paid' => [true, -1, false],
            'expired_trial' => [false, -3600, false],
            'exact_expiry' => [true, 0, false],
            'legacy_premium' => [true, null, true],
        ];

        foreach (['local', 'testing', 'production', 'staging'] as $environment) {
            foreach ($statuses as $status => [$premium, $expiryOffset, $allowed]) {
                foreach (in_array($environment, ['local', 'testing'], true) ? [true, false] : [true] as $enforced) {
                    yield $environment.'_'.$status.'_'.(int) $enforced => [$environment, $enforced, $premium, $expiryOffset, $allowed];
                }
            }
        }
    }

    #[DataProvider('entitlementCases')]
    public function test_job_match_requires_a_current_premium_entitlement(
        string $environment,
        bool $enforced,
        bool $premium,
        ?int $expiryOffset,
        bool $allowed,
    ): void {
        $this->app['env'] = $environment;
        config(['monetization.enabled' => $enforced]);
        $user = $this->user($premium, $expiryOffset);
        $gate = app(SubscriptionGate::class);

        $this->assertSame($allowed, $gate->stateFor($user)['can_job_match']);

        if ($allowed) {
            $gate->assertCanJobMatch($user);

            return;
        }

        try {
            $gate->assertCanJobMatch($user);
            $this->fail('An account without current premium must hit the paywall.');
        } catch (SubscriptionRequiredException $exception) {
            $this->assertSame(402, $exception->getStatusCode());
            $request = Request::create('/api/job-matches', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
            $response = $exception->render($request);
            $this->assertSame(402, $response->getStatusCode());
            $this->assertSame('job_match', $response->getData(true)['feature']);
            $this->assertSame('subscription_required', $response->getData(true)['code']);
        }
    }

    public static function invalidConfigurationCases(): iterable
    {
        $invalid = [
            'monetization.enabled' => [null, false, '', '   ', 0, 'false', '0', 'invalid', 2, [], 1.0],
            'services.revenuecat.webhook_secret' => [null, '', '   ', "\t\n", false, 123, []],
            'monetization.job_match.daily_limit' => [null, '', ' ', -1, -100, 0, '0', '-9', 'unlimited', '20requests', '2.5', 2.5, true, false, [], '9999999999999999999999999'],
        ];

        foreach (['production', 'staging', 'preview'] as $environment) {
            foreach ($invalid as $key => $values) {
                foreach ($values as $index => $value) {
                    yield $environment.'_'.$key.'_'.$index => [$environment, $key, $value];
                }
            }
        }
    }

    #[DataProvider('invalidConfigurationCases')]
    public function test_configuration_errors_fail_closed_for_every_entry_point(string $environment, string $key, mixed $value): void
    {
        $this->app['env'] = $environment;
        config([$key => $value]);
        $gate = app(SubscriptionGate::class);
        Log::spy();

        // Misconfiguration takes precedence over entitlement, and cannot be
        // hidden by the user-visible capability snapshot.
        foreach ([$this->user(false), $this->user(true, 3600)] as $user) {
            $state = $gate->stateFor($user);
            $this->assertFalse($state['can_job_match']);
            $this->assertSame($key === 'monetization.job_match.daily_limit' ? 0 : 20, $state['quota']['job_match']['limit']);

            try {
                $gate->assertCanJobMatch($user);
                $this->fail('Invalid configuration must make Job Match unavailable.');
            } catch (HttpException $exception) {
                $this->assertSame(503, $exception->getStatusCode());
                $this->assertStringNotContainsString('job-match-test-webhook-secret', $exception->getMessage());
            }
        }

        if ($key === 'monetization.job_match.daily_limit') {
            try {
                $gate->jobMatchDailyLimit();
                $this->fail('The public limit accessor must fail closed too.');
            } catch (HttpException $exception) {
                $this->assertSame(503, $exception->getStatusCode());
            }
        }

        Log::shouldHaveReceived('critical')->with(
            'Job Match unavailable: required configuration is missing or invalid.',
            ['configuration' => $key, 'environment' => $environment],
        )->times($key === 'monetization.job_match.daily_limit' ? 5 : 4);
    }

    public function test_missing_configuration_keys_fail_closed(): void
    {
        $this->app['env'] = 'production';
        $user = $this->user(true, 3600);
        Log::spy();

        foreach (['monetization.enabled', 'monetization.job_match.daily_limit', 'services.revenuecat.webhook_secret'] as $key) {
            $original = config($key);
            [$group, $path] = explode('.', $key, 2);
            $groupConfig = config($group);
            Arr::forget($groupConfig, $path);
            config([$group => $groupConfig]);
            $this->assertFalse(config()->has($key));

            try {
                app(SubscriptionGate::class)->assertCanJobMatch($user);
                $this->fail('An absent key must not disable the gate.');
            } catch (HttpException $exception) {
                $this->assertSame(503, $exception->getStatusCode());
            } finally {
                config([$key => $original]);
            }
        }

        Log::shouldHaveReceived('critical')->times(3);
    }

    public function test_local_and_testing_allow_unconfigured_infrastructure_but_never_free_access(): void
    {
        Log::spy();

        foreach (['local', 'testing'] as $environment) {
            $this->app['env'] = $environment;
            config(['monetization.enabled' => false, 'services.revenuecat.webhook_secret' => null]);

            foreach ([null, '', -7, 0, 'invalid', []] as $limit) {
                config(['monetization.job_match.daily_limit' => $limit]);
                $gate = app(SubscriptionGate::class);
                $this->assertSame(20, $gate->jobMatchDailyLimit());
                $this->assertFalse($gate->stateFor(null)['can_job_match']);
                $gate->assertCanJobMatch($this->user(true, 3600));

                try {
                    $gate->assertCanJobMatch($this->user(false));
                    $this->fail('Local development must not bypass premium entitlement.');
                } catch (SubscriptionRequiredException $exception) {
                    $this->assertSame(402, $exception->getStatusCode());
                }
            }
        }

        Log::shouldNotHaveReceived('critical');
    }

    public function test_positive_integer_limits_are_preserved_without_truncation(): void
    {
        $this->app['env'] = 'production';

        foreach ([1, 2, 19, 20, 137, 65536, PHP_INT_MAX] as $limit) {
            foreach ([$limit, (string) $limit] as $configured) {
                config(['monetization.job_match.daily_limit' => $configured]);
                $this->assertSame($limit, app(SubscriptionGate::class)->jobMatchDailyLimit());
            }
        }
    }

    public function test_daily_quota_is_per_user_counts_all_attempts_and_resets_at_midnight(): void
    {
        $this->app['env'] = 'production';
        $gate = app(SubscriptionGate::class);
        $otherUser = $this->user(true, 86400 * 3);

        foreach ([1, 3, 7] as $limit) {
            config(['monetization.job_match.daily_limit' => $limit]);
            $user = $this->user(true, 86400 * 3);
            $this->match($user, now()->startOfDay()->subSecond());
            $this->match($otherUser, now());
            $this->match($user, now()->addDay()->startOfDay());

            foreach (range(0, $limit - 1) as $used) {
                $this->assertSame($used, $gate->jobMatchesUsedToday($user));
                $this->assertTrue($gate->stateFor($user)['can_job_match']);
                $gate->assertCanJobMatch($user);
                $this->match($user, $used === 0 ? now()->startOfDay() : now(), ['queued', 'completed', 'failed'][$used % 3]);
            }

            // Editing or detaching a CV does not refund a match attempt.
            DB::table('job_matches')->where('user_id', $user->id)->update(['generated_cv_id' => null, 'updated_at' => now()->addDay()]);
            $state = $gate->stateFor($user);
            $this->assertFalse($state['can_job_match']);
            $this->assertSame([
                'limit' => $limit,
                'used' => $limit,
                'resets_at' => now()->addDay()->startOfDay()->toISOString(),
            ], $state['quota']['job_match']);

            try {
                $gate->assertCanJobMatch($user);
                $this->fail('The exact limit must reject another match.');
            } catch (HttpException $exception) {
                $this->assertSame(429, $exception->getStatusCode());
                $this->assertSame((string) (11 * 3600), $exception->getHeaders()['Retry-After']);
            }

            $this->match($user, now());
            $this->assertSame($limit + 1, $gate->stateFor($user)['quota']['job_match']['used']);
            $this->assertFalse($gate->stateFor($user)['can_job_match']);
            try {
                $gate->assertCanJobMatch($user);
                $this->fail('Usage above the limit must remain blocked.');
            } catch (HttpException $exception) {
                $this->assertSame(429, $exception->getStatusCode());
            }
        }

        $this->travelTo(now()->addDay()->startOfDay());
        $this->assertSame(1, $gate->jobMatchesUsedToday($user));
        $gate->assertCanJobMatch($user);
        $this->assertTrue($gate->stateFor($user)['can_job_match']);
    }

    public function test_auth_and_plan_responses_expose_job_match_entitlement_and_usage(): void
    {
        $this->app['env'] = 'production';
        $user = $this->user(true, 3600);
        $this->match($user, now());
        Sanctum::actingAs($user);

        foreach (['/api/auth/me' => 'data.subscription', '/api/mobile/subscription-plans' => 'data.access'] as $endpoint => $path) {
            $this->getJson($endpoint)
                ->assertOk()
                ->assertJsonPath($path.'.can_job_match', true)
                ->assertJsonPath($path.'.quota.job_match', [
                    'limit' => 20,
                    'used' => 1,
                    'resets_at' => now()->addDay()->startOfDay()->toISOString(),
                ]);
        }

        $user->forceFill(['premium_until' => now()])->save();
        $this->getJson('/api/auth/me')->assertOk()
            ->assertJsonPath('data.subscription.can_job_match', false)
            ->assertJsonPath('data.subscription.quota.job_match.used', 1);
    }

    public function test_anonymous_plan_response_exposes_a_finite_unused_quota(): void
    {
        $this->getJson('/api/mobile/subscription-plans')->assertOk()
            ->assertJsonPath('data.access.can_job_match', false)
            ->assertJsonPath('data.access.quota.job_match', [
                'limit' => 20,
                'used' => 0,
                'resets_at' => now()->addDay()->startOfDay()->toISOString(),
            ]);
    }

    public function test_production_api_access_snapshot_disables_job_match_without_blocking_account_access(): void
    {
        $this->app['env'] = 'production';
        config(['monetization.enabled' => false]);
        Log::spy();
        Sanctum::actingAs($this->user(true, 3600));

        foreach (['/api/auth/me' => 'data.subscription', '/api/mobile/subscription-plans' => 'data.access'] as $endpoint => $path) {
            $this->getJson($endpoint)->assertOk()
                ->assertJsonPath($path.'.can_job_match', false)
                ->assertJsonPath($path.'.quota.job_match.limit', 20);
        }

        Log::shouldHaveReceived('critical')->twice();
    }

    private function user(bool $premium, ?int $expiryOffset = null): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'is_premium' => $premium,
            'premium_until' => $expiryOffset === null ? null : now()->addSeconds($expiryOffset),
        ])->save();

        return $user;
    }

    private function match(User $user, \DateTimeInterface $createdAt, string $status = 'completed'): void
    {
        DB::table('job_matches')->insert([
            'user_id' => $user->id,
            'generated_cv_id' => null,
            'idempotency_key' => fake()->uuid(),
            'target_job_title' => fake()->jobTitle(),
            'job_description_input' => fake()->paragraph(),
            'language' => 'en',
            'score_total' => 60,
            'grade' => 'C',
            'job_match' => 60,
            'category' => 'general',
            'criteria' => '{}',
            'keywords_found' => '[]',
            'keywords_missing' => '[]',
            'strengths' => '[]',
            'weaknesses' => '[]',
            'ai_status' => $status,
            'ai_feedback' => null,
            'ai_error' => null,
            'applied_suggestion_ids' => '[]',
            'cv_snapshot_hash' => hash('sha256', fake()->uuid()),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
