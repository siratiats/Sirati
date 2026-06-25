<?php

namespace App\Models;

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
        'published_at',
        'sort_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }
}
