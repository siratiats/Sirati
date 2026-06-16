<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneratedCv extends Model
{
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'linkedin',
        'location',
        'target_job_title',
        'language',
        'summary_input',
        'skills_input',
        'experience_input',
        'education_input',
        'certifications_input',
        'generated_markdown',
        'form_payload',
        'ai_status',
        'ai_output',
        'ai_error',
        'score_total',
        'grade',
        'criteria',
    ];

    protected function casts(): array
    {
        return [
            'form_payload' => 'array',
            'ai_output' => 'array',
            'criteria' => 'array',
        ];
    }
}
