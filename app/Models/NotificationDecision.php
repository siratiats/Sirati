<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDecision extends Model
{
    public const ACTIVE_STATUSES = ['queued', 'accepted', 'opened', 'converted'];

    protected $fillable = [
        'user_id',
        'mobile_notification_id',
        'rule_key',
        'template_key',
        'context',
        'idempotency_key',
        'scheduled_for',
        'status',
        'skip_reason',
        'queued_at',
        'accepted_at',
        'failed_at',
        'opened_at',
        'converted_at',
        'conversion_type',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'scheduled_for' => 'datetime',
            'queued_at' => 'datetime',
            'accepted_at' => 'datetime',
            'failed_at' => 'datetime',
            'opened_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mobileNotification(): BelongsTo
    {
        return $this->belongsTo(MobileNotification::class);
    }
}
