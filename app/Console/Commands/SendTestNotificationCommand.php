<?php

namespace App\Console\Commands;

use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Services\FirebaseNotificationService;
use Illuminate\Console\Command;
use Throwable;

class SendTestNotificationCommand extends Command
{
    protected $signature = 'notifications:send-test 
        {email : The email address of the recipient user}
        {--title=إشعار تجريبي من سيرتي : Notification title}
        {--body=تم إرسال هذا الإشعار للتحقق من تكامل Firebase بنجاح! : Notification body}
        {--queue : Dispatch via queue worker instead of sending synchronously}';

    protected $description = 'Send a test push notification to a specific user by email';

    public function handle(FirebaseNotificationService $service): int
    {
        $email = trim((string) $this->argument('email'));
        $title = (string) $this->option('title');
        $body = (string) $this->option('body');
        $shouldQueue = (bool) $this->option('queue');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email [{$email}] not found in database.");

            return self::FAILURE;
        }

        $activeTokens = $user->fcmTokens()->where('is_active', true)->get();
        $inactiveCount = $user->fcmTokens()->where('is_active', false)->count();

        $this->info("User found: [ID: {$user->id}] {$user->name} ({$user->email})");
        $this->line("Active FCM tokens: {$activeTokens->count()} | Inactive FCM tokens: {$inactiveCount}");

        if ($activeTokens->isNotEmpty()) {
            $platforms = $activeTokens->pluck('platform')->filter()->unique()->implode(', ');
            $this->line("Target platforms: " . ($platforms ?: 'unknown'));
        } else {
            $this->warn("⚠️  Warning: User has no active FCM tokens registered.");
            $this->warn("   Make sure to log in to the iOS or Android mobile app so your device token is stored in the database.");
        }

        if ($shouldQueue) {
            SendPushNotificationJob::dispatch(
                $user->id,
                $title,
                $body,
                'test',
                null,
                null,
                ['source' => 'cli_test']
            );

            $this->info("✅ Notification job dispatched to the queue for user ID {$user->id}.");

            return self::SUCCESS;
        }

        $this->info("Sending test notification synchronously...");

        try {
            $result = $service->createAndSendToUser(
                user: $user,
                title: $title,
                body: $body,
                type: 'test',
                data: ['source' => 'cli_test', 'timestamp' => (string) now()->timestamp]
            );

            $this->info(sprintf(
                "✅ Notification processed: Total: %d, Successes: %d, Failures: %d",
                $result['total'],
                $result['successes'],
                $result['failures']
            ));

            if (! empty($result['invalid_tokens'])) {
                $this->warn("Deactivated invalid tokens: " . implode(', ', $result['invalid_tokens']));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("❌ Failed to send notification: " . $e->getMessage());

            return self::FAILURE;
        }
    }
}
