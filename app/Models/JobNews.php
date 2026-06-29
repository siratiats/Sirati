<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JobNews extends Model
{
    protected $fillable = [
        'language',
        'title',
        'company',
        'location',
        'body',
        'url',
        'apply_url',
        'published_at',
        'valid_from',
        'valid_until',
        'sort_order',
        'is_published',
        'source',
        'source_row_key',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_published' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = today();

        return $query
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today));
    }
}
