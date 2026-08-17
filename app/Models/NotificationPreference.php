<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'enabled',
        'language',
        'timezone_offset_minutes',
        'preferred_time',
        'quiet_hours_start',
        'quiet_hours_end',
        'max_per_week',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'timezone_offset_minutes' => 'integer',
            'max_per_week' => 'integer',
            'last_active_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
