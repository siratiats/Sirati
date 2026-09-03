<?php

namespace App\Models;

use App\Cv\CvDocument;
use App\Enums\AiStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedCv extends Model
{
    protected $fillable = [
        'user_id',
        'idempotency_key',
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
        'document',
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
            'document' => 'array',
            'ai_output' => 'array',
            'criteria' => 'array',
            'ai_status' => AiStatus::class,
        ];
    }

    public function cvDocument(): CvDocument
    {
        if (is_array($this->document) && $this->document !== []) {
            return CvDocument::fromArray($this->document);
        }

        return CvDocument::fromLegacy($this->attributesToArray());
    }

    public function duplicateForUser(?int $userId = null): self
    {
        $copy = $this->replicate(['idempotency_key']);
        $copy->user_id = $userId ?? $this->user_id;
        $copy->idempotency_key = null;
        $copy->document = $this->cvDocument()->duplicate()->toArray();
        $copy->save();

        return $copy;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
