<?php

namespace App\Services;

use App\Models\MobileNotification;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class FirebaseNotificationService
{
    public const DEFAULT_ANDROID_CHANNEL_ID = 'high_importance_channel';

    /** FCM allows at most 500 tokens per multicast request. */
    private const MULTICAST_CHUNK = 500;

    /** Insert MobileNotification rows in batches to keep queries bounded. */
    private const INSERT_CHUNK = 1000;

    public function __construct(private readonly Messaging $messaging) {}

    /**
     * Bulk broadcast: persist one MobileNotification per user and push to all of
     * their active tokens in a single multicast pass. Used by broadcast jobs so a
     * campaign of N users costs a handful of queries instead of N round-trips.
     *
     * @param  array<int>  $userIds
     */
    public function createAndSendToUsers(
        array $userIds,
        string $title,
        string $body,
        string $type = 'info',
        ?string $actionType = null,
        ?string $actionUrl = null,
    ): array {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if ($userIds === []) {
            return $this->emptyResult();
        }

        $now = now();
        $rows = array_map(static fn (int $id): array => [
            'user_id' => $id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_type' => $actionType,
            'action_url' => $actionUrl,
            'created_at' => $now,
            'updated_at' => $now,
        ], $userIds);

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            MobileNotification::insert($chunk);
        }

        $tokens = UserFcmToken::whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->pluck('token')
            ->all();

        // Broadcasts share one data payload, so per-user notification_id is omitted;
        // tapping opens the notifications screen which lists the user's own records.
        return $this->sendToTokens($tokens, $title, $body, [
            'type' => $type,
            'action_type' => $actionType,
            'action_url' => $actionUrl,
        ]);
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
        $total = 0;
        $successes = 0;
        $failures = 0;
        $invalidTokens = [];

        // FCM caps a multicast at 500 tokens; chunk so any recipient count is safe.
        foreach (array_chunk($tokens, self::MULTICAST_CHUNK) as $chunk) {
            try {
                $report = $this->messaging->sendMulticast($message, $chunk);
            } catch (MessagingException|Throwable $exception) {
                Log::error('Firebase notification send failed.', [
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            $this->deactivateInvalidTokens($report);
            $this->logFailures($report);

            $total += count($chunk);
            $successes += $report->successes()->count();
            $failures += $report->failures()->count();
            $invalidTokens = array_merge(
                $invalidTokens,
                $report->invalidTokens(),
                $report->unknownTokens(),
            );
        }

        return [
            'total' => $total,
            'successes' => $successes,
            'failures' => $failures,
            'invalid_tokens' => array_values(array_unique($invalidTokens)),
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

        $tokenHashes = array_map(
            static fn (string $token): string => UserFcmToken::hashToken($token),
            $tokens,
        );

        UserFcmToken::whereIn('token_hash', $tokenHashes)->update([
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
