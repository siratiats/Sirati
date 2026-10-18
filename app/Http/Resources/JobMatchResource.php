<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'generated_cv_id' => $this->generated_cv_id,
            'applied_generated_cv_id' => $this->applied_generated_cv_id,
            'target_job_title' => $this->target_job_title,
            'job_description_input' => $this->job_description_input,
            'language' => $this->language,
            'score_total' => $this->score_total,
            'grade' => $this->grade,
            'job_match' => $this->job_match,
            'category' => $this->category,
            'criteria' => $this->criteria,
            'keywords_found' => $this->keywords_found,
            'keywords_missing' => $this->keywords_missing,
            'strengths' => $this->strengths,
            'weaknesses' => $this->weaknesses,
            'ai_status' => $this->ai_status->value,
            'ai_feedback' => $this->ai_feedback,
            'ai_error' => $this->ai_error,
            'applied_suggestion_ids' => $this->applied_suggestion_ids ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
