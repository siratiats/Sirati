<?php

use App\Services\ErrorReporter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            $identity = static function (Request $request): string {
                $userId = $request->user()?->id;

                return $userId !== null
                    ? 'user:'.$userId
                    : 'ip:'.$request->ip();
            };

            $shortWindowResponse = static fn (Request $request, array $headers) => response()->json([
                'message' => 'لقد وصلت إلى الحد المؤقت للطلبات. يرجى المحاولة مرة أخرى بعد قليل.',
                'code' => 'ai_rate_limit_short',
            ], 429, $headers);

            $dailyResponse = static fn (Request $request, array $headers) => response()->json([
                'message' => 'لقد وصلت إلى الحد المسموح به اليوم. يرجى المحاولة مرة أخرى غداً.',
                'code' => 'ai_rate_limit_daily',
            ], 429, $headers);

            $heavyShortWindowResponse = static function (Request $request, array $headers) use ($shortWindowResponse) {
                app(ErrorReporter::class)->captureRateLimit('hour', $request->user()?->id);

                return $shortWindowResponse($request, $headers);
            };

            $heavyDailyResponse = static function (Request $request, array $headers) use ($dailyResponse) {
                app(ErrorReporter::class)->captureRateLimit('day', $request->user()?->id);

                return $dailyResponse($request, $headers);
            };

            RateLimiter::for('ai-heavy', static function (Request $request) use ($identity, $heavyShortWindowResponse, $heavyDailyResponse): array {
                $key = $identity($request);

                return [
                    Limit::perHour(10)
                        ->by('hour:'.$key)
                        ->response($heavyShortWindowResponse),
                    Limit::perDay(40)
                        ->by('day:'.$key)
                        ->response($heavyDailyResponse),
                ];
            });

            RateLimiter::for('ai-light', static function (Request $request) use ($identity, $shortWindowResponse, $dailyResponse): array {
                $key = $identity($request);

                return [
                    Limit::perMinute(20)
                        ->by('minute:'.$key)
                        ->response($shortWindowResponse),
                    Limit::perDay(200)
                        ->by('day:'.$key)
                        ->response($dailyResponse),
                ];
            });
        },
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('jobs:sync-sheet')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('notifications:plan-daily')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
