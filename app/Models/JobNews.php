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
        'city',
        'is_remote',
        'body',
        'category',
        'job_title_id',
        'url',
        'apply_url',
        'published_at',
        'valid_from',
        'valid_until',
        'sort_order',
        'is_published',
        'source',
        'source_row_key',
        'external_source',
        'external_id',
        'is_verified',
        'source_type',
        'employment_type',
        'experience_level',
        'salary_min',
        'salary_max',
        'skills_required',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_published' => 'boolean',
            'is_remote' => 'boolean',
            'is_verified' => 'boolean',
            'job_title_id' => 'integer',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'skills_required' => 'array',
        ];
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = today();

        return $query
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today));
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeSourceType(Builder $query, string $type): Builder
    {
        return $query->where('source_type', $type);
    }
}
