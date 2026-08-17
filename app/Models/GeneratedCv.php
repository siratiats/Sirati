<?php

namespace App\Models;

use App\Enums\AiStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedCv extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'linkedin',
        'location',
        'target_job_title',
        'job_description_input',
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
            'ai_status' => AiStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
