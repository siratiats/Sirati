<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationCampaign extends Model
{
    protected $fillable = [
        'title',
        'body',
        'type',
        'action_type',
        'action_url',
        'audience',
        'sent_by',
        'recipients_queued',
        'delivered',
        'failed',
        'chunks_total',
        'chunks_completed',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'recipients_queued' => 'integer',
            'delivered' => 'integer',
            'failed' => 'integer',
            'chunks_total' => 'integer',
            'chunks_completed' => 'integer',
        ];
    }
}
