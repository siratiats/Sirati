<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use App\Notifications\VerifyEmailCode;
use App\Services\EmailVerificationService;
use App\Services\PasswordResetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class QueuedTransactionalNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactional_email_notifications_have_an_explicit_retry_policy(): void
    {
        $verify = new VerifyEmailCode('123456', 10);
        $reset = new PasswordResetCode('654321', 20);

        $this->assertInstanceOf(ShouldQueue::class, $verify);
        $this->assertInstanceOf(ShouldQueue::class, $reset);
        $this->assertSame(5, $verify->tries);
        $this->assertSame(5, $reset->tries);
        $this->assertSame([60, 300, 900, 1800], $verify->backoff);
        $this->assertSame([60, 300, 900, 1800], $reset->backoff);
    }

    public function test_services_dispatch_transactional_email_as_queued_notifications(): void
    {
        Queue::fake();

        $unverifiedUser = User::factory()->unverified()->create();
        app(EmailVerificationService::class)->send($unverifiedUser, force: true);

        $resetUser = User::factory()->create();
        app(PasswordResetService::class)->send($resetUser, force: true);

        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job): bool => $job->notification instanceof VerifyEmailCode
                && $job->notification->userId === $unverifiedUser->id
        );
        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job): bool => $job->notification instanceof PasswordResetCode
                && $job->notification->userId === $resetUser->id
        );
    }

    public function test_failed_handlers_log_the_user_and_transport_reason(): void
    {
        Log::spy();

        (new VerifyEmailCode('123456', 10))
            ->failed(new RuntimeException('SMTP verification failure'));
        (new PasswordResetCode('654321', 20))
            ->failed(new RuntimeException('SMTP reset failure'));

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Queued email verification notification failed.'
                && $context['user_id'] === 10
                && $context['reason'] === 'SMTP verification failure')
            ->once();
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Queued password reset notification failed.'
                && $context['user_id'] === 20
                && $context['reason'] === 'SMTP reset failure')
            ->once();
    }

    public function test_failed_job_provider_writes_to_the_failed_jobs_table(): void
    {
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        $this->assertSame('database-uuids', config('queue.failed.driver'));

        $uuid = (string) Str::uuid();
        app('queue.failer')->log(
            'database',
            'default',
            json_encode(['uuid' => $uuid], JSON_THROW_ON_ERROR),
            new RuntimeException('Expected test failure')
        );

        $this->assertDatabaseHas('failed_jobs', [
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
        ]);
    }
}
