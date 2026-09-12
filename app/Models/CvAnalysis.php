<?php

namespace App\Models;

use App\Enums\AiStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvAnalysis extends Model
{
    /**
     * Transient (non-persisted) warning set when the document classification
     * is uncertain. Only populated on the initial submission response.
     */
    public ?string $classification_warning = null;

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
        'category',
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

    public function setResumeTextAttribute(?string $value): void
    {
        $this->attributes['resume_text'] = $value !== null ? \App\Support\Utf8Sanitizer::sanitize($value) : null;
    }

    public function setOriginalFilenameAttribute(?string $value): void
    {
        $this->attributes['original_filename'] = $value !== null ? \App\Support\Utf8Sanitizer::sanitize($value) : null;
    }

    public function setTargetJobTitleAttribute(?string $value): void
    {
        $this->attributes['target_job_title'] = $value !== null ? \App\Support\Utf8Sanitizer::sanitize($value) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'software' => 'تقنية وبرمجيات',
            'data' => 'علم وتحليل البيانات',
            'ecommerce' => 'التجارة الإلكترونية',
            'marketing' => 'التسويق الرقمي',
            'sales' => 'المبيعات وتطوير الأعمال',
            'finance' => 'المالية والمحاسبة',
            'hr' => 'الموارد البشرية',
            'management' => 'الإدارة والعمليات',
            default => 'تقييم عام (مهارات مهنية مشتركة)',
        };
    }

    public function getCategoryLabelEnAttribute(): string
    {
        return match ($this->category) {
            'software' => 'Software & Engineering',
            'data' => 'Data Science & Analytics',
            'ecommerce' => 'E-Commerce',
            'marketing' => 'Marketing',
            'sales' => 'Sales & Business Development',
            'finance' => 'Finance & Accounting',
            'hr' => 'Human Resources',
            'management' => 'Management & Operations',
            default => 'General Profile (Transferable Skills)',
        };
    }
}
