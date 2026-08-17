<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCallLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'provider',
        'model',
        'operation',
        'input_tokens',
        'output_tokens',
        'cached_tokens',
        'duration_ms',
        'was_response_cache_hit',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cached_tokens' => 'integer',
            'duration_ms' => 'integer',
            'was_response_cache_hit' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
