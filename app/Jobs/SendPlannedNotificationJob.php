<?php

namespace App\Jobs;

use App\Models\NotificationDecision;
use App\Services\ErrorReporter;
use App\Services\FirebaseNotificationService;
use App\Services\Notifications\NotificationTemplateService;
use App\Support\DailyNotificationCandidate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers a single planned smart-notification decision.
 *
 * Tries once only: retries would risk duplicate in-app rows if create
 * succeeded but FCM failed mid-flight. Failures are marked on the decision.
 */
class SendPlannedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(private readonly int $decisionId) {}

    public function handle(
        FirebaseNotificationService $firebase,
        NotificationTemplateService $templates,
    ): void {
        $decision = NotificationDecision::query()->find($this->decisionId);
        if ($decision === null) {
            return;
        }

        if (! in_array($decision->status, ['queued', 'planned'], true)) {
            return;
        }

        $user = $decision->user;
        if ($user === null) {
            $decision->forceFill([
                'status' => 'failed',
                'skip_reason' => 'user_missing',
                'failed_at' => now(),
            ])->save();

            return;
        }

        $context = is_array($decision->context) ? $decision->context : [];
        $candidate = new DailyNotificationCandidate(
            ruleKey: $decision->rule_key,
            templateKey: $decision->template_key,
            type: (string) ($context['type'] ?? 'info'),
            actionType: (string) ($context['action_type'] ?? 'screen'),
            actionUrl: (string) ($context['action_url'] ?? 'notifications'),
            context: $context,
        );

        $language = (string) ($context['language'] ?? config('smart_notifications.default_language', 'ar'));
        $copy = $templates->render($candidate, $language === 'en' ? 'en' : 'ar');

        try {
            $notification = $user->mobileNotifications()->create([
                'type' => $candidate->type,
                'title' => $copy['title'],
                'body' => $copy['body'],
                'action_type' => $candidate->actionType,
                'action_url' => $candidate->actionUrl,
            ]);

            $result = $firebase->sendToUser(
                $user,
                $copy['title'],
                $copy['body'],
                [
                    'type' => $candidate->type,
                    'notification_id' => (string) $notification->id,
                    'decision_id' => (string) $decision->id,
                    'rule_key' => $decision->rule_key,
                    'action_type' => $candidate->actionType,
                    'action_url' => $candidate->actionUrl,
                ],
            );

            $successes = (int) ($result['successes'] ?? 0);

            if ($successes === 0) {
                app(ErrorReporter::class)->captureNotificationFailure(
                    new \RuntimeException('FCM returned no successful deliveries.'),
                    'planned_notification_delivery_failed',
                    $user->id,
                );
            }

            $decision->forceFill([
                'mobile_notification_id' => $notification->id,
                'status' => $successes > 0 ? 'accepted' : 'failed',
                'accepted_at' => $successes > 0 ? now() : null,
                'failed_at' => $successes > 0 ? null : now(),
                'skip_reason' => $successes > 0 ? null : 'fcm_no_success',
            ])->save();
        } catch (Throwable $exception) {
            app(ErrorReporter::class)->captureNotificationFailure(
                $exception,
                'planned_notification_send_failed',
                $user->id,
            );

            Log::error('[SmartNotifications] Send failed', [
                'decision_id' => $decision->id,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            $decision->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'skip_reason' => 'send_exception',
            ])->save();
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[SmartNotifications] Job failed permanently', [
            'decision_id' => $this->decisionId,
            'error' => $exception->getMessage(),
        ]);

        NotificationDecision::query()
            ->where('id', $this->decisionId)
            ->whereIn('status', ['queued', 'planned'])
            ->update([
                'status' => 'failed',
                'failed_at' => now(),
                'skip_reason' => 'job_failed',
            ]);
    }
}
