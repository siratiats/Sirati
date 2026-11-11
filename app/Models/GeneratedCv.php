<?php

namespace App\Models;

use App\Cv\CvDocument;
use App\Cv\CvTextLayout;
use App\Enums\AiStatus;
use App\Support\Utf8Sanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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

    private ?CvDocument $memoizedCvDocument = null;

    /**
     * Every new CV is logged in `cv_creations`; the free daily quota counts
     * that log, so deleting a CV never frees a slot.
     */
    /**
     * A new CV and its `cv_creations` row are written together: if the log
     * cannot be written the CV is not created either, so the free quota can
     * never be skipped (fails closed).
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            return parent::save($options);
        }

        return DB::transaction(fn (): bool => parent::save($options));
    }

    protected static function booted(): void
    {
        static::created(function (GeneratedCv $cv): void {
            if ($cv->user_id === null) {
                return;
            }
            DB::table('cv_creations')->insert([
                'user_id' => $cv->user_id,
                'created_at' => $cv->created_at ?? now(),
            ]);
        });
    }

    public function setAttribute($key, $value)
    {
        $this->memoizedCvDocument = null;

        if (is_string($value)) {
            $value = Utf8Sanitizer::sanitize($value);
            if ($key === 'summary_input') {
                $value = CvTextLayout::unwrapText($value);
            }
        } elseif (is_array($value) && in_array($key, ['document', 'form_payload'], true)) {
            $value = Utf8Sanitizer::sanitizeDeep($value);
            if ($key === 'document') {
                $value = CvTextLayout::tidyDocument($value);
            }
        }

        return parent::setAttribute($key, $value);
    }

    public function setRawAttributes(array $attributes, $sync = false)
    {
        $this->memoizedCvDocument = null;

        return parent::setRawAttributes($attributes, $sync);
    }

    public function refresh()
    {
        $this->memoizedCvDocument = null;

        return parent::refresh();
    }

    public function cvDocument(): CvDocument
    {
        if ($this->memoizedCvDocument !== null) {
            return $this->memoizedCvDocument;
        }

        if (is_array($this->document) && $this->document !== []) {
            return $this->memoizedCvDocument = CvDocument::fromArray($this->document);
        }

        return $this->memoizedCvDocument = CvDocument::fromLegacy($this->attributesToArray());
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
