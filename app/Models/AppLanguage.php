<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A language the mobile app can show. Strings go live only through a publish, which bumps `version`;
 * the public strings payload, its cache entry and its ETag are all keyed by that version.
 */
class AppLanguage extends Model
{
    public const DIRECTIONS = ['ltr', 'rtl'];

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'direction',
        'fallback_code',
        'is_enabled',
        'is_default',
        'sort_order',
        'version',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AppTranslation::class, 'language_code', 'code');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(AppTranslationRevision::class, 'language_code', 'code');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    /** The fallback the app should use, never the language itself. */
    public function effectiveFallback(): ?string
    {
        $fallback = $this->fallback_code;

        return ($fallback === null || $fallback === '' || $fallback === $this->code) ? null : $fallback;
    }

    public function etag(): string
    {
        return '"'.$this->code.'-'.$this->version.'"';
    }
}
