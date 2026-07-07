<?php

namespace App\Jobs;

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

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly int $userId,
        private readonly string $title,
        private readonly string $body,
        private readonly string $type = 'info',
        private readonly ?string $actionType = null,
        private readonly ?string $actionUrl = null,
        private readonly array $data = [],
    ) {}

    public function handle(FirebaseNotificationService $service): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            Log::warning('[FCM Job] User not found', ['user_id' => $this->userId]);

            return;
        }

        $service->createAndSendToUser(
            user: $user,
            title: $this->title,
            body: $this->body,
            type: $this->type,
            actionType: $this->actionType,
            actionUrl: $this->actionUrl,
            data: $this->data,
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[FCM Job] Failed to send push notification', [
            'user_id' => $this->userId,
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);
    }
}
