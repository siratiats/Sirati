<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'group',
        'type',
        'label',
        'value_ar',
        'value_en',
        'value',
        'sort_order',
    ];
}
