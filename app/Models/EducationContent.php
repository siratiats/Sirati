<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EducationContent extends Model
{
    protected $fillable = [
        'language',
        'type',
        'title',
        'body',
        'duration_label',
        'target_role',
        'badge',
        'button_label',
        'icon',
        'sort_order',
        'is_featured',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
        ];
    }
}
