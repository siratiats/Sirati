<?php

namespace App\Providers;

use App\Contracts\CvAiProvider;
use App\Services\Ai\CachedCvAiProvider;
use App\Services\ClaudeCvService;
use App\Services\ErrorReporter;
use App\Services\HealthMonitor;
use App\Services\OpenAiCvService;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
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

        $this->app->singleton(CvAiProvider::class, function ($app): CvAiProvider {
            $driver = strtolower((string) config('services.cv_ai.provider', 'openai'));

            $concrete = match ($driver) {
                'openai' => $app->make(OpenAiCvService::class),
                'claude', 'anthropic' => $app->make(ClaudeCvService::class),
                default => throw new InvalidArgumentException(
                    "Unsupported CV_AI_PROVIDER [{$driver}]. Use openai or claude."
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
    }
}
