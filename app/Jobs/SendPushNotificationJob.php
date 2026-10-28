<?php

namespace App\Jobs;

use App\Models\NotificationDecision;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // A retry cannot tell whether FCM already delivered, so it would push twice.
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly string $type = 'info',
        public readonly ?string $actionType = null,
        public readonly ?string $actionUrl = null,
        public readonly array $data = [],
    ) {}

    public function handle(FirebaseNotificationService $service): void
    {
        $decision = $this->decision();
        if ($decision !== null && $decision->status !== 'queued') {
            return;
        }

        $user = User::find($this->userId);

        if (! $user) {
            Log::warning('[FCM Job] User not found', ['user_id' => $this->userId]);
            $this->closeDecision($decision, false, 'user_missing');

            return;
        }

        try {
            $result = $service->createAndSendToUser(
                user: $user,
                title: $this->title,
                body: $this->body,
                type: $this->type,
                actionType: $this->actionType,
                actionUrl: $this->actionUrl,
                data: $this->data,
            );
        } catch (Throwable $exception) {
            $this->closeDecision($decision, false, 'send_exception');

            throw $exception;
        }

        $delivered = (int) ($result['successes'] ?? 0) > 0;
        $this->closeDecision($decision, $delivered, $delivered ? null : 'fcm_no_success');
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[FCM Job] Failed to send push notification', [
            'user_id' => $this->userId,
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);

        $this->closeDecision($this->decision(), false, 'job_failed');
    }

    private function decision(): ?NotificationDecision
    {
        $id = $this->data['decision_id'] ?? null;

        return $id === null ? null : NotificationDecision::query()->find((int) $id);
    }

    private function closeDecision(?NotificationDecision $decision, bool $delivered, ?string $reason): void
    {
        if ($decision === null) {
            return;
        }

        NotificationDecision::query()
            ->whereKey($decision->id)
            ->where('status', 'queued')
            ->update([
                'status' => $delivered ? 'accepted' : 'failed',
                'accepted_at' => $delivered ? now() : null,
                'failed_at' => $delivered ? null : now(),
                'skip_reason' => $reason,
            ]);
    }
}
