<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'admin_id',
        'action',
        'reason',
        'previous_premium_until',
        'new_premium_until',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'previous_premium_until' => 'datetime',
            'new_premium_until' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
