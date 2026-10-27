<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Snapshot of every published string of a language at one version (the rollback source). */
class AppTranslationRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'language_code',
        'version',
        'snapshot',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
        ];
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(AppLanguage::class, 'language_code', 'code');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
