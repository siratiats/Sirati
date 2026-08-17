<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFcmToken extends Model
{
    public const MAX_TOKEN_LENGTH = 512;

    protected $fillable = [
        'user_id',
        'token',
        'device_id',
        'platform',
        'app_version',
        'is_active',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function setTokenAttribute(string $token): void
    {
        $this->attributes['token'] = $token;
        $this->attributes['token_hash'] = self::hashToken($token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
