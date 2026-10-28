<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\NotificationDecision;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SendPushNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    private function decision(User $user, string $status): NotificationDecision
    {
        return NotificationDecision::query()->create([
            'user_id' => $user->id,
            'rule_key' => 'matching_job',
            'template_key' => 'matching_job',
            'context' => [],
            'idempotency_key' => 'test:'.$user->id.':'.$status.':'.uniqid(),
            'scheduled_for' => now(),
            'status' => $status,
            'queued_at' => now(),
        ]);
    }

    private function job(User $user, array $data = []): SendPushNotificationJob
    {
        return new SendPushNotificationJob($user->id, 'title', 'body', data: $data);
    }

    public function test_job_is_never_retried_because_a_retry_could_push_twice(): void
    {
        $job = new SendPushNotificationJob(1, 't', 'b');

        $this->assertSame(1, $job->tries);
        $this->assertNull($job->backoff ?? null);
    }

    public function test_a_decision_is_delivered_at_most_once_however_often_the_job_runs(): void
    {
        $user = User::factory()->create();
        $decision = $this->decision($user, 'queued');

        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldReceive('createAndSendToUser')->once()->andReturn(['successes' => 1]);

        foreach (range(1, 4) as $_) {
            $this->job($user, ['decision_id' => (string) $decision->id])->handle($service);
        }

        $this->assertSame('accepted', $decision->fresh()->status);
        $this->assertNotNull($decision->fresh()->accepted_at);
    }

    public function test_decisions_no_longer_queued_are_never_sent(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldNotReceive('createAndSendToUser');

        foreach (['accepted', 'opened', 'converted', 'failed', 'skipped'] as $status) {
            $decision = $this->decision($user, $status);
            $this->job($user, ['decision_id' => (string) $decision->id])->handle($service);
            $this->assertSame($status, $decision->fresh()->status);
        }
    }

    public function test_zero_fcm_successes_marks_the_decision_failed(): void
    {
        $user = User::factory()->create();
        $decision = $this->decision($user, 'queued');

        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldReceive('createAndSendToUser')->once()->andReturn(['successes' => 0]);

        $this->job($user, ['decision_id' => (string) $decision->id])->handle($service);

        $this->assertSame('failed', $decision->fresh()->status);
        $this->assertSame('fcm_no_success', $decision->fresh()->skip_reason);
    }

    public function test_a_send_exception_closes_the_decision_so_it_cannot_be_resent(): void
    {
        $user = User::factory()->create();
        $decision = $this->decision($user, 'queued');

        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldReceive('createAndSendToUser')->once()->andThrow(new RuntimeException('timeout'));

        try {
            $this->job($user, ['decision_id' => (string) $decision->id])->handle($service);
            $this->fail('The send exception must propagate.');
        } catch (RuntimeException) {
        }

        $this->job($user, ['decision_id' => (string) $decision->id])->handle($service);

        $this->assertSame('failed', $decision->fresh()->status);
        $this->assertSame('send_exception', $decision->fresh()->skip_reason);
    }

    public function test_jobs_without_a_decision_still_send(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldReceive('createAndSendToUser')->once()->andReturn(['successes' => 1]);

        $this->job($user)->handle($service);
    }
}
