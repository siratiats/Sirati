<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CvTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name_ar',
        'name_en',
        'slug',
        'renderer_key',
        'preview_image_path',
        'language_direction',
        'supported_languages',
        'supported_sections',
        'color_tokens',
        'config_json',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'supported_languages' => 'array',
            'supported_sections' => 'array',
            'color_tokens' => 'array',
            'config_json' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name_ar');
    }

    public function supportsLanguage(string $language): bool
    {
        $languages = $this->supported_languages ?: ['ar', 'en'];

        return in_array($language, $languages, true);
    }

    public function displayName(string $language): string
    {
        return $language === 'en' ? $this->name_en : $this->name_ar;
    }
}
