<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Services\FirebaseNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SendTestNotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_when_user_does_not_exist(): void
    {
        $this->artisan('notifications:send-test', ['email' => 'nonexistent@example.com'])
            ->expectsOutputToContain('User with email [nonexistent@example.com] not found')
            ->assertFailed();
    }

    public function test_warns_when_user_has_no_active_tokens(): void
    {
        $user = User::factory()->create(['email' => 'notokens@example.com']);

        $serviceMock = Mockery::mock(FirebaseNotificationService::class);
        $serviceMock->shouldReceive('createAndSendToUser')
            ->once()
            ->withArgs(fn ($u, $title, $body) => $u->id === $user->id)
            ->andReturn([
                'total' => 0,
                'successes' => 0,
                'failures' => 0,
                'invalid_tokens' => [],
            ]);
        $this->app->instance(FirebaseNotificationService::class, $serviceMock);

        $this->artisan('notifications:send-test', ['email' => $user->email])
            ->expectsOutputToContain('User found: [ID: ' . $user->id . ']')
            ->expectsOutputToContain('Warning: User has no active FCM tokens registered')
            ->expectsOutputToContain('Total: 0, Successes: 0, Failures: 0')
            ->assertSuccessful();
    }

    public function test_dispatches_queued_job_when_queue_flag_is_passed(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email' => 'queued@example.com']);

        $this->artisan('notifications:send-test', [
            'email' => $user->email,
            '--queue' => true,
            '--title' => 'Custom Title',
            '--body' => 'Custom Body',
        ])
            ->expectsOutputToContain('Notification job dispatched to the queue')
            ->assertSuccessful();

        Queue::assertPushed(SendPushNotificationJob::class, function (SendPushNotificationJob $job) use ($user) {
            return $job->userId === $user->id
                && $job->title === 'Custom Title'
                && $job->body === 'Custom Body';
        });
    }

    public function test_sends_to_user_with_active_tokens(): void
    {
        $user = User::factory()->create(['email' => 'active@example.com']);
        UserFcmToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-token-12345',
            'token_hash' => UserFcmToken::hashToken('fcm-token-12345'),
            'platform' => 'ios',
            'is_active' => true,
        ]);

        $serviceMock = Mockery::mock(FirebaseNotificationService::class);
        $serviceMock->shouldReceive('createAndSendToUser')
            ->once()
            ->withArgs(fn ($u, $title, $body) => $u->id === $user->id)
            ->andReturn([
                'total' => 1,
                'successes' => 1,
                'failures' => 0,
                'invalid_tokens' => [],
            ]);
        $this->app->instance(FirebaseNotificationService::class, $serviceMock);

        $this->artisan('notifications:send-test', ['email' => $user->email])
            ->expectsOutputToContain('Target platforms: ios')
            ->expectsOutputToContain('Total: 1, Successes: 1, Failures: 0')
            ->assertSuccessful();
    }
}
