<?php

namespace App\Providers;

use App\Contracts\CvAiProvider;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Observers\GeneratedCvObserver;
use App\Services\Ai\CachedCvAiProvider;
use App\Services\ClaudeCvService;
use App\Services\DeepInfraCvService;
use App\Services\ErrorReporter;
use App\Services\HealthMonitor;
use App\Services\OpenAiCvService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OpenAiCvService::class);
        $this->app->singleton(ClaudeCvService::class);
        $this->app->singleton(DeepInfraCvService::class);

        $this->app->singleton(CvAiProvider::class, function ($app): CvAiProvider {
            $driver = strtolower((string) config('services.cv_ai.provider', 'openai'));

            $concrete = match ($driver) {
                'openai' => $app->make(OpenAiCvService::class),
                'deepinfra', 'qwen', 'kimi' => $app->make(DeepInfraCvService::class),
                'claude', 'anthropic' => $app->make(ClaudeCvService::class),
                default => throw new InvalidArgumentException(
                    "Unsupported CV_AI_PROVIDER [{$driver}]. Use openai, deepinfra, or claude."
                ),
            };

            if (! config('services.cv_ai.response_cache_enabled', true)) {
                return $concrete;
            }

            // Pass the ACTIVE provider/model so cache keys are namespaced per
            // vendor. Omitting these would let a Claude run read OpenAI's
            // cached responses and invalidate any quality comparison.
            $provider = CachedCvAiProvider::activeProvider();

            return new CachedCvAiProvider(
                $concrete,
                $provider,
                CachedCvAiProvider::modelForProvider($provider),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        GeneratedCv::observe(GeneratedCvObserver::class);
        $pdfObserver = app(GeneratedCvObserver::class);
        User::updated($pdfObserver->userUpdated(...));

        Event::listen(DiagnosingHealth::class, function (): void {
            app(HealthMonitor::class)->assertHealthy();
        });

        Queue::looping(function (Looping $event): void {
            app(HealthMonitor::class)->recordQueueHeartbeat($event);
        });

        // Covers current notification jobs and future queued AI operations
        // without reading or attaching the serialized job payload.
        Queue::failing(function (JobFailed $event): void {
            app(ErrorReporter::class)->captureQueueFailure(
                exception: $event->exception,
                job: $event->job->resolveName(),
                connection: $event->connectionName,
                queue: $event->job->getQueue(),
            );
        });

        $this->configureRateLimiting();
    }

    /**
     * Configure named rate limiters for AI and heavy routes.
     * Defined in boot so they stay active when routes are cached.
     */
    protected function configureRateLimiting(): void
    {
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
    }
}
