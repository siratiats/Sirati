<?php

namespace App\Services;

use Sentry\Event;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use Throwable;

class ErrorReporter
{
    public function __construct(private readonly HubInterface $sentry) {}

    public function captureAiFailure(
        Throwable $exception,
        string $operation,
        string $model,
        int $durationMs,
        ?int $userId,
    ): void {
        $this->capture(
            operation: $operation,
            errorClass: $exception::class,
            userId: $userId,
            tags: [
                'ai_status' => 'failed',
                'model' => $model,
            ],
            context: [
                'ai_status' => 'failed',
                'model' => $model,
                'duration_ms' => $durationMs,
            ],
        );
    }

    public function captureRateLimit(string $window, ?int $userId): void
    {
        $this->capture(
            operation: 'ai_heavy_rate_limit',
            errorClass: 'TooManyRequests',
            userId: $userId,
            tags: ['window' => $window],
        );
    }

    public function captureQueueFailure(
        Throwable $exception,
        string $job,
        string $connection,
        ?string $queue,
    ): void {
        $this->capture(
            operation: 'queue_job_failed',
            errorClass: $exception::class,
            tags: [
                'job' => $job,
                'connection' => $connection,
                'queue' => $queue ?? 'default',
            ],
        );
    }

    public function captureMailFailure(Throwable $exception, string $operation, int $userId): void
    {
        $this->capture(
            operation: $operation,
            errorClass: $exception::class,
            userId: $userId,
        );
    }

    public function captureNotificationFailure(
        Throwable $exception,
        string $operation,
        ?int $userId = null,
    ): void {
        $this->capture(
            operation: $operation,
            errorClass: $exception::class,
            userId: $userId,
        );
    }

    /**
     * Capture a deliberately sparse handled event. Exception messages,
     * request/response payloads, breadcrumbs, and the active request scope are
     * intentionally excluded because they can contain candidate CV data.
     *
     * @param  array<string, string>  $tags
     * @param  array<string, mixed>  $context
     */
    private function capture(
        string $operation,
        string $errorClass,
        ?int $userId = null,
        array $tags = [],
        array $context = [],
    ): void {
        $client = $this->sentry->getClient();

        if ($client === null) {
            return;
        }

        $event = Event::createEvent()
            ->setLevel(Severity::error())
            ->setMessage("Handled failure: {$operation} ({$errorClass})")
            ->setTags([
                'handled' => 'true',
                'operation' => $operation,
                'error_class' => $errorClass,
                ...$tags,
            ])
            ->setFingerprint([$operation, $errorClass]);

        if ($userId !== null) {
            $event->setUser(UserDataBag::createFromUserIdentifier($userId));
        }

        if ($context !== []) {
            $event->setContext('failure', [
                'operation' => $operation,
                ...$context,
            ]);
        }

        // A fresh scope prevents request bodies and unrelated breadcrumbs from
        // being inherited by these handled failure events.
        try {
            $client->captureEvent($event, scope: new Scope);
        } catch (Throwable) {
            // Error reporting must never change the application outcome.
        }
    }
}
