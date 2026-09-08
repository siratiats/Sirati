<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionWebhookEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'app_user_id',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
