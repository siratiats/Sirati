<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneratedCvResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'linkedin' => $this->linkedin,
            'location' => $this->location,
            'target_job_title' => $this->target_job_title,
            'language' => $this->language,
            'generated_markdown' => $this->generated_markdown,
            'ai_status' => $this->ai_status,
            'ai_output' => $this->ai_output,
            'ai_error' => $this->ai_error,
            'score_total' => $this->score_total,
            'grade' => $this->grade,
            'criteria' => $this->mobileList($this->criteria),
            'pdf_url' => url("/api/generated-cvs/{$this->id}/pdf"),
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
