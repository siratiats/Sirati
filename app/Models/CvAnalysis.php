<?php

namespace App\Models;

use App\Enums\AiStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvAnalysis extends Model
{
    protected $fillable = [
        'user_id',
        'idempotency_key',
        'target_job_title',
        'original_filename',
        'input_method',
        'resume_text',
        'score_total',
        'grade',
        'job_match',
        'criteria',
        'strengths',
        'weaknesses',
        'keywords_found',
        'keywords_missing',
        'quick_wins',
        'ai_status',
        'ai_feedback',
        'ai_error',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'strengths' => 'array',
            'weaknesses' => 'array',
            'keywords_found' => 'array',
            'keywords_missing' => 'array',
            'quick_wins' => 'array',
            'ai_feedback' => 'array',
            'ai_status' => AiStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
