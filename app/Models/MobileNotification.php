<?php

namespace App\Models;

use App\Services\I18n\ServerText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileNotification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'title_key',
        'body_key',
        'text_params',
        'action_type',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'text_params' => 'array',
        ];
    }

    /** The title in the reader's app language when it is templated; else as sent (#12). */
    public function displayTitle(?string $language = null): string
    {
        return $this->title_key !== null
            ? ServerText::t($this->title_key, $this->text_params ?? [], $language)
            : (string) $this->title;
    }

    public function displayBody(?string $language = null): string
    {
        return $this->body_key !== null
            ? ServerText::t($this->body_key, $this->text_params ?? [], $language)
            : (string) $this->body;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
