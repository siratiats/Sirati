<?php

namespace Tests\Feature;

use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Models\SubscriptionWebhookEvent;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AGENTS.md rule 7: every security and billing gate fails closed, loudly, when
 * its required configuration is unset or empty outside local/testing.
 */
class FailClosedGatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';
        parent::tearDown();
    }

    /** Any environment that is not local development or the test suite. */
    private static function deployedEnvironments(): array
    {
        return ['production', 'staging', 'preview', 'env-'.Str::lower(Str::random(6))];
    }

    // ------------------------------------------------------- monetization

    public static function missingMonetizationCases(): iterable
    {
        // 'absent' removes the key entirely, as when the config file never sets it.
        $values = ['absent' => '__absent__', 'null' => null, 'empty' => '', 'blank' => "  \t", 'word' => 'maybe',
            'two' => 2, 'string_two' => '2', 'float' => 1.0, 'array' => [], 'enabled' => 'enabled'];

        foreach (self::deployedEnvironments() as $environment) {
            foreach ($values as $label => $value) {
                yield $environment.'_'.$label => [$environment, $value];
            }
        }
    }

    #[DataProvider('missingMonetizationCases')]
    public function test_unset_monetization_switch_fails_closed_on_every_gated_endpoint(string $environment, mixed $value): void
    {
        [$cv, $analysis] = $this->fixtures($user = User::factory()->create());
        $this->app['env'] = $environment;
        $this->setMonetization($value);
        Log::spy();
        Sanctum::actingAs($user);

        foreach ($this->gatedEndpoints($cv, $analysis) as $label => [$method, $uri, $body]) {
            $response = $this->json($method, $uri, $body);
            $this->assertSame(503, $response->status(), "{$label} must fail closed, got {$response->status()}");
        }

        $this->assertNull($user->fresh()->premium_until);
        Log::shouldHaveReceived('critical')->with(
            'Monetization unavailable: required configuration is missing or invalid.',
            ['configuration' => 'monetization.enabled', 'environment' => $environment],
        )->times(count($this->gatedEndpoints($cv, $analysis)));
    }

    #[DataProvider('missingMonetizationCases')]
    public function test_unset_monetization_switch_keeps_account_snapshots_available_and_closed(string $environment, mixed $value): void
    {
        $user = User::factory()->create();
        $this->app['env'] = $environment;
        $this->setMonetization($value);
        Sanctum::actingAs($user);

        foreach (['/api/auth/me' => 'data.subscription', '/api/mobile/subscription-plans' => 'data.access'] as $endpoint => $path) {
            $this->getJson($endpoint)->assertOk()
                ->assertJsonPath($path.'.enforced', true)
                ->assertJsonPath($path.'.can_download', false)
                ->assertJsonPath($path.'.can_enhance', false)
                ->assertJsonPath($path.'.can_view_full_analysis', false);
        }
        $this->getJson('/api/mobile/subscription-plans')->assertJsonPath('data.enforced', true);
    }

    public static function explicitSwitchCases(): iterable
    {
        $environments = [...self::deployedEnvironments(), 'local', 'testing'];
        $off = [false, 'false', 'FALSE', '0', 0, 'off', 'no'];
        $on = [true, 'true', 'TRUE', '1', 1, 'on', 'yes'];

        foreach ($environments as $environment) {
            foreach ($off as $index => $value) {
                yield $environment.'_off_'.$index => [$environment, $value, false];
            }
            foreach ($on as $index => $value) {
                yield $environment.'_on_'.$index => [$environment, $value, true];
            }
        }
    }

    #[DataProvider('explicitSwitchCases')]
    public function test_only_an_explicit_switch_decides_enforcement(string $environment, mixed $value, bool $enforced): void
    {
        [$cv, $analysis] = $this->fixtures($user = User::factory()->create());
        $this->app['env'] = $environment;
        config(['monetization.enabled' => $value]);
        Log::spy();
        Sanctum::actingAs($user);

        $this->postJson('/api/generated-cvs/enhance-job-description', $this->enhanceBody())
            ->assertStatus($enforced ? 402 : 200);
        $this->getJson('/api/generated-cvs/'.$cv->id.'/download')
            ->assertStatus($enforced ? 402 : 200);
        $this->getJson('/api/auth/me')->assertJsonPath('data.subscription.enforced', $enforced);

        // Job Match has its own stricter rule; only the monetization gate is checked here.
        Log::shouldNotHaveReceived('critical', [
            \Mockery::on(fn (string $message): bool => str_starts_with($message, 'Monetization')),
            \Mockery::any(),
        ]);
    }

    public function test_local_and_testing_treat_an_unset_switch_as_off(): void
    {
        foreach (['local', 'testing'] as $environment) {
            foreach (['__absent__', null, ''] as $value) {
                $this->app['env'] = $environment;
                $this->setMonetization($value);
                Log::spy();
                Sanctum::actingAs(User::factory()->create());

                $this->postJson('/api/generated-cvs/enhance-job-description', $this->enhanceBody())->assertOk();
                Log::shouldNotHaveReceived('critical');
            }
        }
    }

    // --------------------------------------------------------- admin gate

    public static function missingAdminEmailCases(): iterable
    {
        foreach (self::deployedEnvironments() as $environment) {
            foreach (['absent' => '__absent__', 'empty' => [], 'null' => null, 'blank_entries' => ['', '  '], 'not_a_list' => 'admin@sirati.test'] as $label => $value) {
                yield $environment.'_'.$label => [$environment, $value];
            }
        }
    }

    #[DataProvider('missingAdminEmailCases')]
    public function test_admin_login_fails_closed_without_an_allow_list(string $environment, mixed $value): void
    {
        $admin = User::factory()->create(['email' => 'admin@sirati.test']);
        $this->app['env'] = $environment;
        $this->setAdminEmails($value);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Log::spy();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertStatus(503);
        $this->assertGuest();

        $this->actingAs($admin)->get(route('admin.index'))->assertStatus(503);

        Log::shouldHaveReceived('critical')->with(
            'Admin panel unavailable: required configuration is missing or invalid.',
            ['configuration' => 'services.admin.emails', 'environment' => $environment],
        )->twice();
    }

    public static function adminEmailCaseVariants(): iterable
    {
        $emails = ['admin@sirati.test', 'Ops.Lead+admin@Example.COM', 'x@y.sa', strtolower(Str::random(12)).'@Sirati.Test'];

        foreach (['production', 'testing'] as $environment) {
            foreach ($emails as $index => $email) {
                yield $environment.'_'.$index => [$environment, $email];
            }
        }
    }

    #[DataProvider('adminEmailCaseVariants')]
    public function test_admin_emails_match_case_insensitively_in_login_and_panel(string $environment, string $email): void
    {
        $this->app['env'] = $environment;
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $stored = $this->scrambleCase($email);
        $listed = ' '.$this->scrambleCase($email).' ';
        $admin = User::factory()->create(['email' => $stored]);
        $outsider = User::factory()->create(['email' => 'not-'.strtolower($email)]);
        config(['services.admin.emails' => [$listed, 'other@sirati.test']]);

        $this->post(route('admin.login.store'), ['email' => $stored, 'password' => 'password'])
            ->assertRedirect(route('admin.index'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.index'))->assertOk();
        auth()->logout();

        $this->post(route('admin.login.store'), ['email' => $outsider->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($outsider)->get(route('admin.index'))->assertForbidden();
    }

    // ------------------------------------------------------- webhook gate

    public static function missingWebhookSecretCases(): iterable
    {
        foreach (self::deployedEnvironments() as $environment) {
            foreach (['absent' => '__absent__', 'null' => null, 'empty' => '', 'blank' => " \n "] as $label => $value) {
                yield $environment.'_'.$label => [$environment, $value];
            }
        }
    }

    #[DataProvider('missingWebhookSecretCases')]
    public function test_revenuecat_webhook_fails_closed_without_a_secret(string $environment, mixed $value): void
    {
        $user = User::factory()->create();
        $this->app['env'] = $environment;
        $this->setConfig('services.revenuecat.webhook_secret', $value);
        Log::spy();

        $this->postJson('/api/webhooks/revenuecat', ['event' => [
            'id' => 'evt_'.Str::random(8),
            'type' => 'INITIAL_PURCHASE',
            'app_user_id' => (string) $user->id,
            'expiration_at_ms' => now()->addYear()->getTimestampMs(),
        ]], ['Authorization' => 'Bearer '.(is_string($value) ? $value : '')])->assertStatus(503);

        $this->assertFalse($user->fresh()->isPremium());
        $this->assertDatabaseCount('subscription_webhook_events', 0);
        Log::shouldHaveReceived('critical')->with(
            'RevenueCat webhook unavailable: required configuration is missing or invalid.',
            ['configuration' => 'services.revenuecat.webhook_secret', 'environment' => $environment],
        )->once();
    }

    public static function missingExpiryCases(): iterable
    {
        $types = ['INITIAL_PURCHASE', 'RENEWAL', 'PRODUCT_CHANGE', 'UNCANCELLATION', 'NON_RENEWING_PURCHASE', 'CANCELLATION'];
        $expiries = ['absent' => '__absent__', 'null' => null, 'empty' => '', 'zero' => 0, 'negative' => -1000,
            'word' => 'soon', 'array' => [], 'string_zero' => '0', 'fraction' => '12.5'];

        foreach ($types as $type) {
            foreach ($expiries as $label => $expiry) {
                yield $type.'_'.$label => [$type, $expiry];
            }
        }
    }

    #[DataProvider('missingExpiryCases')]
    public function test_subscription_event_without_expiry_grants_nothing(string $type, mixed $expiry): void
    {
        config(['services.revenuecat.webhook_secret' => 'whsec_test']);
        $free = User::factory()->create();
        $paidUntil = now()->addDays(9)->startOfSecond();
        $paid = User::factory()->create();
        $paid->grantSubscription($paidUntil);
        Log::spy();

        foreach ([$free, $paid] as $user) {
            $before = $this->entitlement($user);
            $event = ['id' => 'evt_'.Str::random(10), 'type' => $type, 'app_user_id' => (string) $user->id, 'product_id' => 'pro_monthly'];
            if ($expiry !== '__absent__') {
                $event['expiration_at_ms'] = $expiry;
            }

            $this->postJson('/api/webhooks/revenuecat', ['event' => $event], ['Authorization' => 'Bearer whsec_test'])
                ->assertOk()
                ->assertJsonPath('status', 'missing_expiry');

            $this->assertSame($before, $this->entitlement($user));
            $this->assertTrue(SubscriptionWebhookEvent::where('event_id', $event['id'])->exists());
        }

        $this->assertFalse($free->fresh()->isPremium());
        $this->assertDatabaseCount('subscription_audit_logs', 0);
        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message, array $context): bool => str_contains($message, 'no valid expiry') && $context['event_type'] === $type,
        )->twice();
    }

    public function test_subscription_event_with_an_expiry_still_grants_until_that_expiry(): void
    {
        config(['services.revenuecat.webhook_secret' => 'whsec_test']);

        foreach ([1, 45, 400] as $days) {
            foreach (['expiration_at_ms', 'expires_date_ms'] as $field) {
                foreach ([fn ($ms) => $ms, fn ($ms) => (string) $ms] as $shape) {
                    $user = User::factory()->create();
                    $expiresAt = Carbon::now()->addDays($days)->startOfSecond();

                    $this->postJson('/api/webhooks/revenuecat', ['event' => [
                        'id' => 'evt_'.Str::random(10),
                        'type' => 'INITIAL_PURCHASE',
                        'app_user_id' => (string) $user->id,
                        $field => $shape($expiresAt->getTimestampMs()),
                    ]], ['Authorization' => 'Bearer whsec_test'])->assertOk();

                    $this->assertTrue($user->fresh()->isPremium());
                    $this->assertTrue($expiresAt->equalTo($user->fresh()->premium_until));
                }
            }
        }
    }

    // ----------------------------------------------------------- schedule

    public function test_daily_notification_planner_is_scheduled_exactly_once(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'notifications:plan-daily'));

        $this->assertCount(1, $events);
    }

    // ------------------------------------------------------------ helpers

    /** @return array<string, array{0: string, 1: string, 2: array<string, mixed>}> */
    private function gatedEndpoints(GeneratedCv $cv, CvAnalysis $analysis): array
    {
        return [
            'enhance_job_description' => ['POST', '/api/generated-cvs/enhance-job-description', $this->enhanceBody()],
            'enhance_field' => ['POST', '/api/generated-cvs/enhance-field', []],
            'generate_cv' => ['POST', '/api/generated-cvs', []],
            'analyze' => ['POST', '/api/cv-analyses', []],
            'list_cvs' => ['GET', '/api/generated-cvs', []],
            'show_cv' => ['GET', '/api/generated-cvs/'.$cv->id, []],
            'download_cv' => ['GET', '/api/generated-cvs/'.$cv->id.'/download', []],
            'show_analysis' => ['GET', '/api/cv-analyses/'.$analysis->id, []],
        ];
    }

    /** @return array{0: GeneratedCv, 1: CvAnalysis} */
    private function fixtures(User $user): array
    {
        $cv = GeneratedCv::create([
            'user_id' => $user->id,
            'full_name' => 'Noura Alotaibi',
            'email' => 'noura@example.com',
            'phone' => '+966500000000',
            'location' => 'Riyadh',
            'target_job_title' => 'HR Manager',
            'language' => 'en',
            'summary_input' => 'Broad talent management experience.',
            'skills_input' => 'Recruitment, onboarding',
            'experience_input' => '8 years.',
            'education_input' => 'MBA.',
            'generated_markdown' => '',
            'form_payload' => ['language' => 'en'],
            'ai_status' => 'completed',
            'score_total' => 90,
            'grade' => 'A',
        ]);
        $analysis = CvAnalysis::create([
            'user_id' => $user->id,
            'target_job_title' => 'HR Manager',
            'input_method' => 'paste',
            'resume_text' => 'HR Manager with 8 years of recruitment experience.',
            'score_total' => 70,
            'grade' => 'B',
            'job_match' => 60,
            'criteria' => [],
            'strengths' => ['Recruitment'],
            'weaknesses' => ['Metrics'],
            'keywords_found' => ['HR'],
            'keywords_missing' => ['HRIS'],
            'quick_wins' => ['Add numbers'],
        ]);
        Queue::fake();

        return [$cv, $analysis];
    }

    private function entitlement(User $user): array
    {
        $fresh = $user->fresh();

        return [(bool) $fresh->is_premium, $fresh->premium_until?->toIso8601String(), $fresh->subscription_plan_id];
    }

    private function enhanceBody(): array
    {
        return ['target_job_title' => 'HR Manager', 'job_description' => 'Lead recruitment.', 'language' => 'en'];
    }

    private function setMonetization(mixed $value): void
    {
        $this->setConfig('monetization.enabled', $value);
    }

    private function setAdminEmails(mixed $value): void
    {
        $this->setConfig('services.admin.emails', $value);
    }

    private function setConfig(string $key, mixed $value): void
    {
        if ($value !== '__absent__') {
            config([$key => $value]);

            return;
        }

        [$group, $path] = explode('.', $key, 2);
        $groupConfig = config($group);
        Arr::forget($groupConfig, $path);
        config([$group => $groupConfig]);
        $this->assertFalse(config()->has($key));
    }

    private function scrambleCase(string $value): string
    {
        $scrambled = '';
        foreach (str_split($value) as $index => $char) {
            $scrambled .= ($index + random_int(0, 1)) % 2 === 0 ? strtoupper($char) : strtolower($char);
        }

        return $scrambled;
    }
}
