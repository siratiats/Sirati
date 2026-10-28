<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Google / Apple identity linked to a user. `provider_subject` is the
 * provider's stable `sub` claim; the email is kept only for support.
 */
class SocialAccount extends Model
{
    protected $fillable = [
        'provider',
        'provider_subject',
        'email',
        'refresh_token',
        'client_id',
    ];

    protected $hidden = [
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
