<?php

namespace App\Services\Notifications;

use App\Jobs\SendPlannedNotificationJob;
use App\Models\NotificationDecision;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DailyNotificationPlanner
{
    public function __construct(
        private readonly DailyNotificationCandidateService $candidates,
        private readonly NotificationPolicyService $policy,
        private readonly NotificationPreferenceService $preferences,
    ) {}

    /**
     * @return array{planned: int, skipped: int, scanned: int}
     */
    public function run(int $chunkSize = 100): array
    {
        $planned = 0;
        $skipped = 0;
        $scanned = 0;

        if (! config('smart_notifications.enabled')) {
            Log::info('[SmartNotifications] Planner skipped — feature disabled.');

            return compact('planned', 'skipped', 'scanned');
        }

        // Lookup sets (jobs, education, job-title taxonomy) are memoized for the
        // duration of a run. Drop anything held over from a previous run in a
        // long-lived worker so this run sees current content.
        $this->candidates->flushLookupCaches();

        User::query()
            ->whereHas('fcmTokens', fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->chunkById($chunkSize, function ($users) use (&$planned, &$skipped, &$scanned) {
                foreach ($users as $user) {
                    $scanned++;
                    $result = $this->planForUser($user);
                    if ($result === 'planned') {
                        $planned++;
                    } else {
                        $skipped++;
                    }
                }
            });

        Log::info('[SmartNotifications] Planner finished', compact('planned', 'skipped', 'scanned'));

        return compact('planned', 'skipped', 'scanned');
    }

    public function planForUser(User $user): string
    {
        $preference = $this->preferences->forUser($user);
        $candidate = $this->candidates->forUser($user);

        if ($candidate === null) {
            return 'skipped';
        }

        $recent = NotificationDecision::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(14))
            ->get();

        $evaluation = $this->policy->evaluate($user, $preference, $candidate, $recent);

        if (! ($evaluation['allowed'] ?? false)) {
            // Optional audit row is intentionally omitted for high-volume skips.
            return 'skipped';
        }

        $decision = NotificationDecision::query()->create([
            'user_id' => $user->id,
            'rule_key' => $candidate->ruleKey,
            'template_key' => $candidate->templateKey,
            'context' => array_merge($candidate->context, [
                'type' => $candidate->type,
                'action_type' => $candidate->actionType,
                'action_url' => $candidate->actionUrl,
                'language' => $preference->language,
            ]),
            'idempotency_key' => $evaluation['idempotency_key'],
            'scheduled_for' => $evaluation['scheduled_for'],
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        SendPlannedNotificationJob::dispatch($decision->id);

        return 'planned';
    }
}
