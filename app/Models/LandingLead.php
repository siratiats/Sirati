<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingLead extends Model
{
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'role_interest',
        'target_job_title',
        'notes',
        'source',
        'ip_address',
        'user_agent',
    ];
}
