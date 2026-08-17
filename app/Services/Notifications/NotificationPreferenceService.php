<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;

class NotificationPreferenceService
{
    public function forUser(User $user): NotificationPreference
    {
        return NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'enabled' => true,
                'language' => config('smart_notifications.default_language', 'ar'),
                'timezone_offset_minutes' => (int) config('smart_notifications.default_timezone_offset_minutes', 180),
                'preferred_time' => config('smart_notifications.default_preferred_time', '18:30'),
                'quiet_hours_start' => config('smart_notifications.default_quiet_hours_start', '21:00'),
                'quiet_hours_end' => config('smart_notifications.default_quiet_hours_end', '09:00'),
                'max_per_week' => (int) config('smart_notifications.default_max_per_week', 4),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): NotificationPreference
    {
        $preference = $this->forUser($user);
        // Allow last_active_at even though clients rarely set it directly.
        $preference->fill($attributes);
        if (array_key_exists('last_active_at', $attributes)) {
            $preference->last_active_at = $attributes['last_active_at'];
        }
        $preference->save();

        return $preference->refresh();
    }

    public function touchActivity(User $user): NotificationPreference
    {
        $preference = $this->forUser($user);
        $preference->forceFill(['last_active_at' => now()])->save();

        return $preference->refresh();
    }

    public function toArray(NotificationPreference $preference): array
    {
        return [
            'enabled' => (bool) $preference->enabled,
            'language' => $preference->language,
            'timezone_offset_minutes' => (int) $preference->timezone_offset_minutes,
            'preferred_time' => $preference->preferred_time,
            'quiet_hours_start' => $preference->quiet_hours_start,
            'quiet_hours_end' => $preference->quiet_hours_end,
            'max_per_week' => (int) $preference->max_per_week,
            'last_active_at' => $preference->last_active_at?->toISOString(),
        ];
    }
}
