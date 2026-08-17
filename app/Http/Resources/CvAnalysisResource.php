<?php

namespace App\Http\Resources;

use App\Enums\AiStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CvAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_job_title' => $this->target_job_title,
            'original_filename' => $this->original_filename,
            'input_method' => $this->input_method,
            'score_total' => $this->score_total,
            'grade' => $this->grade,
            'job_match' => $this->job_match,
            'criteria' => $this->mobileList($this->criteria),
            'strengths' => $this->strengths ?? [],
            'weaknesses' => $this->weaknesses ?? [],
            'keywords_found' => $this->keywords_found ?? [],
            'keywords_missing' => $this->keywords_missing ?? [],
            'quick_wins' => $this->quick_wins ?? [],
            'ai_status' => $this->ai_status instanceof AiStatus
                ? $this->ai_status->value
                : $this->ai_status,
            'ai_feedback' => $this->ai_feedback,
            'ai_error' => $this->ai_error,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function mobileList(?array $value): array
    {
        if ($value === null) {
            return [];
        }

        return array_is_list($value) ? $value : array_values($value);
    }
}
