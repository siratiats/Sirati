<?php

namespace App\Services;

use App\Models\MobileNotification;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Throwable;

class FirebaseNotificationService
{
    public const DEFAULT_ANDROID_CHANNEL_ID = 'high_importance_channel';

    public function __construct(private readonly Messaging $messaging)
    {
    }

    public function createAndSendToUser(
        User $user,
        string $title,
        string $body,
        string $type = 'info',
        ?string $actionType = null,
        ?string $actionUrl = null,
        array $data = [],
    ): array {
        $notification = $user->mobileNotifications()->create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_type' => $actionType,
            'action_url' => $actionUrl,
        ]);

        return $this->sendToUser($user, $title, $body, array_merge($data, [
            'type' => $type,
            'notification_id' => (string) $notification->id,
            'action_type' => $actionType,
            'action_url' => $actionUrl,
        ]));
    }

    public function sendToUser(
        User $user,
        string $title,
        string $body,
        array $data = [],
    ): array {
        $tokens = $user->fcmTokens()
            ->where('is_active', true)
            ->pluck('token')
            ->all();

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendDataOnlyToUser(User $user, array $data): array
    {
        $tokens = $user->fcmTokens()
            ->where('is_active', true)
            ->pluck('token')
            ->all();

        return $this->sendDataOnlyToTokens($tokens, $data);
    }

    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
    ): array {
        $tokens = $this->uniqueTokens($tokens);

        if ($tokens === []) {
            return $this->emptyResult();
        }

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($this->normalizeData($data))
            ->withAndroidConfig([
                'priority' => 'high',
                'notification' => [
                    'channel_id' => self::DEFAULT_ANDROID_CHANNEL_ID,
                    'sound' => 'default',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ])
            ->withApnsConfig([
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                    ],
                ],
            ]);

        return $this->sendMulticast($message, $tokens);
    }

    public function sendDataOnlyToTokens(array $tokens, array $data): array
    {
        $tokens = $this->uniqueTokens($tokens);

        if ($tokens === []) {
            return $this->emptyResult();
        }

        $message = CloudMessage::new()
            ->withData($this->normalizeData($data))
            ->withAndroidConfig([
                'priority' => 'high',
            ])
            ->withApnsConfig([
                'headers' => [
                    'apns-priority' => '5',
                ],
                'payload' => [
                    'aps' => [
                        'content-available' => 1,
                    ],
                ],
            ]);

        return $this->sendMulticast($message, $tokens);
    }

    private function sendMulticast(CloudMessage $message, array $tokens): array
    {
        try {
            $report = $this->messaging->sendMulticast($message, $tokens);
        } catch (MessagingException|Throwable $exception) {
            Log::error('Firebase notification send failed.', [
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->deactivateInvalidTokens($report);
        $this->logFailures($report);

        return [
            'total' => count($tokens),
            'successes' => $report->successes()->count(),
            'failures' => $report->failures()->count(),
            'invalid_tokens' => array_values(array_unique(array_merge(
                $report->invalidTokens(),
                $report->unknownTokens(),
            ))),
        ];
    }

    private function deactivateInvalidTokens(MulticastSendReport $report): void
    {
        $tokens = array_values(array_unique(array_merge(
            $report->invalidTokens(),
            $report->unknownTokens(),
        )));

        if ($tokens === []) {
            return;
        }

        UserFcmToken::whereIn('token', $tokens)->update([
            'is_active' => false,
            'last_seen_at' => now(),
        ]);
    }

    private function logFailures(MulticastSendReport $report): void
    {
        foreach ($report->failures()->getItems() as $failure) {
            $error = $failure->error();

            Log::warning('Firebase notification token send failed.', [
                'token' => $failure->target()->value(),
                'error' => $error?->getMessage(),
                'unknown_token' => $failure->messageWasSentToUnknownToken(),
                'invalid_token' => $failure->messageTargetWasInvalid(),
            ]);
        }
    }

    private function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalized[(string) $key] = is_scalar($value)
                ? (string) $value
                : json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $normalized;
    }

    private function uniqueTokens(array $tokens): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($token): string => trim((string) $token), $tokens),
            static fn (string $token): bool => $token !== '',
        )));
    }

    private function emptyResult(): array
    {
        return [
            'total' => 0,
            'successes' => 0,
            'failures' => 0,
            'invalid_tokens' => [],
        ];
    }
}
