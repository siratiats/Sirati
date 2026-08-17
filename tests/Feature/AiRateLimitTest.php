<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_eleventh_ai_heavy_request_within_an_hour_returns_429(): void
    {
        $user = User::factory()->create();

        for ($request = 1; $request <= 10; $request++) {
            $this->actingAs($user)
                ->postJson('/api/cv-analyses')
                ->assertUnprocessable();
        }

        $response = $this->actingAs($user)->postJson('/api/cv-analyses');

        $response
            ->assertTooManyRequests()
            ->assertJsonPath(
                'message',
                'لقد وصلت إلى الحد المؤقت للطلبات. يرجى المحاولة مرة أخرى بعد قليل.'
            )
            ->assertJsonPath('code', 'ai_rate_limit_short')
            ->assertHeader('Retry-After');
    }

    public function test_forty_first_ai_heavy_request_is_blocked_by_the_daily_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-29 00:00:00', 'UTC'));
        $user = User::factory()->create();

        for ($window = 1; $window <= 4; $window++) {
            for ($request = 1; $request <= 10; $request++) {
                $this->actingAs($user)
                    ->postJson('/api/cv-analyses')
                    ->assertUnprocessable();
            }

            $this->travel(61)->minutes();
        }

        $response = $this->actingAs($user)->postJson('/api/cv-analyses');

        $response
            ->assertTooManyRequests()
            ->assertJsonPath(
                'message',
                'لقد وصلت إلى الحد المسموح به اليوم. يرجى المحاولة مرة أخرى غداً.'
            )
            ->assertJsonPath('code', 'ai_rate_limit_daily')
            ->assertHeader('Retry-After');
    }

    public function test_ai_limits_are_per_user_instead_of_global(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        for ($request = 1; $request <= 10; $request++) {
            $this->actingAs($userA)
                ->postJson('/api/cv-analyses')
                ->assertUnprocessable();
        }

        $this->actingAs($userA)
            ->postJson('/api/cv-analyses')
            ->assertTooManyRequests();

        $this->actingAs($userB)
            ->postJson('/api/cv-analyses')
            ->assertUnprocessable();
    }

    public function test_requests_below_the_ai_heavy_limit_are_unaffected(): void
    {
        $user = User::factory()->create();

        for ($request = 1; $request <= 9; $request++) {
            $this->actingAs($user)
                ->postJson('/api/cv-analyses')
                ->assertUnprocessable();
        }
    }

    public function test_every_ai_route_uses_its_named_limiter(): void
    {
        $expected = [
            'api/cv-analyses' => 'throttle:ai-heavy',
            'api/generated-cvs' => 'throttle:ai-heavy',
            'api/cv-analyses/1/generated-cv' => 'throttle:ai-heavy',
            'api/generated-cvs/enhance-job-description' => 'throttle:ai-light',
            'api/generated-cvs/enhance-field' => 'throttle:ai-light',
        ];

        foreach ($expected as $uri => $middleware) {
            $route = Route::getRoutes()->match(Request::create('/'.$uri, 'POST'));

            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }
}
