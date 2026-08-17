<?php

namespace App\Services\Notifications;

use App\Models\NotificationDecision;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\DailyNotificationCandidate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NotificationPolicyService
{
    /**
     * @param  Collection<int, NotificationDecision>  $recentDecisions
     * @return array{allowed: bool, reason: ?string, scheduled_for: ?Carbon}
     */
    public function evaluate(
        User $user,
        NotificationPreference $preference,
        DailyNotificationCandidate $candidate,
        Collection $recentDecisions,
        ?Carbon $now = null,
    ): array {
        $now = $now?->copy() ?? now();

        if (! config('smart_notifications.enabled')) {
            return $this->deny('feature_disabled');
        }

        if (! $preference->enabled) {
            return $this->deny('opted_out');
        }

        if ($user->fcmTokens()->where('is_active', true)->doesntExist()) {
            return $this->deny('no_active_token');
        }

        $localNow = $this->localNow($preference, $now);

        if ($this->inQuietHours($preference, $localNow)) {
            return $this->deny('quiet_hours');
        }

        $scheduledFor = $this->nextDeliveryTime($preference, $localNow, $now);
        if ($scheduledFor === null) {
            return $this->deny('outside_delivery_window');
        }

        $recentHours = (int) config('smart_notifications.recent_activity_hours', 3);
        if ($preference->last_active_at !== null
            && $preference->last_active_at->greaterThan($now->copy()->subHours($recentHours))) {
            return $this->deny('recently_active');
        }

        $minGapHours = (int) config('smart_notifications.minimum_gap_hours', 24);
        $lastActiveSend = $recentDecisions
            ->filter(fn (NotificationDecision $d) => in_array($d->status, NotificationDecision::ACTIVE_STATUSES, true))
            ->sortByDesc(fn (NotificationDecision $d) => $d->queued_at ?? $d->created_at)
            ->first();

        if ($lastActiveSend !== null) {
            $anchor = $lastActiveSend->queued_at ?? $lastActiveSend->created_at;
            if ($anchor !== null && $anchor->greaterThan($now->copy()->subHours($minGapHours))) {
                return $this->deny('min_gap');
            }
        }

        $maxPerWeek = (int) ($preference->max_per_week ?: config('smart_notifications.default_max_per_week', 4));
        $weekCount = $recentDecisions
            ->filter(function (NotificationDecision $d) use ($now) {
                if (! in_array($d->status, NotificationDecision::ACTIVE_STATUSES, true)) {
                    return false;
                }
                $anchor = $d->queued_at ?? $d->created_at;

                return $anchor !== null && $anchor->greaterThanOrEqualTo($now->copy()->subDays(7));
            })
            ->count();

        if ($weekCount >= $maxPerWeek) {
            return $this->deny('weekly_cap');
        }

        $cooldownDays = (int) (config('smart_notifications.rule_cooldowns_days.'.$candidate->ruleKey) ?? 3);
        $sameRuleRecent = $recentDecisions
            ->filter(function (NotificationDecision $d) use ($candidate, $now, $cooldownDays) {
                if ($d->rule_key !== $candidate->ruleKey) {
                    return false;
                }
                if (! in_array($d->status, NotificationDecision::ACTIVE_STATUSES, true)) {
                    return false;
                }
                $anchor = $d->queued_at ?? $d->created_at;

                return $anchor !== null && $anchor->greaterThanOrEqualTo($now->copy()->subDays($cooldownDays));
            })
            ->isNotEmpty();

        if ($sameRuleRecent) {
            return $this->deny('rule_cooldown');
        }

        $localDate = $localNow->toDateString();
        $idempotencyKey = $this->idempotencyKey($user->id, $candidate->ruleKey, $localDate);
        if (NotificationDecision::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return $this->deny('already_planned_today');
        }

        return [
            'allowed' => true,
            'reason' => null,
            'scheduled_for' => $scheduledFor,
            'idempotency_key' => $idempotencyKey,
            'local_date' => $localDate,
        ];
    }

    public function idempotencyKey(int $userId, string $ruleKey, string $localDate): string
    {
        return "daily:{$userId}:{$ruleKey}:{$localDate}";
    }

    public function localNow(NotificationPreference $preference, ?Carbon $now = null): Carbon
    {
        $now = $now?->copy() ?? now();
        $offset = (int) $preference->timezone_offset_minutes;

        return $now->copy()->utcOffset($offset);
    }

    public function inQuietHours(NotificationPreference $preference, Carbon $localNow): bool
    {
        $start = $this->minutesOfDay((string) $preference->quiet_hours_start);
        $end = $this->minutesOfDay((string) $preference->quiet_hours_end);
        $current = ($localNow->hour * 60) + $localNow->minute;

        if ($start === $end) {
            return false;
        }

        // Quiet window may wrap midnight (e.g. 21:00–09:00).
        if ($start < $end) {
            return $current >= $start && $current < $end;
        }

        return $current >= $start || $current < $end;
    }

    /**
     * Return a UTC delivery time if we are inside the preferred local window.
     */
    public function nextDeliveryTime(
        NotificationPreference $preference,
        Carbon $localNow,
        Carbon $utcNow,
    ): ?Carbon {
        $preferred = $this->minutesOfDay((string) $preference->preferred_time);
        $window = (int) config('smart_notifications.delivery_window_minutes', 30);
        $current = ($localNow->hour * 60) + $localNow->minute;

        $diff = abs($current - $preferred);
        // Also handle wrap-around near midnight.
        $diff = min($diff, 1440 - $diff);

        if ($diff > $window) {
            return null;
        }

        return $utcNow->copy();
    }

    private function minutesOfDay(string $hhmm): int
    {
        // Accept HH:MM and HH:MM:SS (DB time columns often include seconds).
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($hhmm), $m)) {
            return 18 * 60 + 30;
        }

        $h = max(0, min(23, (int) $m[1]));
        $min = max(0, min(59, (int) $m[2]));

        return ($h * 60) + $min;
    }

    /**
     * @return array{allowed: bool, reason: string, scheduled_for: null}
     */
    private function deny(string $reason): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'scheduled_for' => null,
        ];
    }
}
