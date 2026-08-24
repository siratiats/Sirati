<?php

namespace App\Http\Resources;

use App\Enums\AiStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class GeneratedCvResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $signedUrl = URL::temporarySignedRoute(
            'api.generated-cvs.pdf',
            now()->addDays(7),
            ['generatedCv' => $this->id]
        );

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'linkedin' => $this->linkedin,
            'location' => $this->location,
            'target_job_title' => $this->target_job_title,
            'job_description_input' => $this->job_description_input,
            'language' => $this->language,
            'summary_input' => $this->summary_input,
            'skills_input' => $this->skills_input,
            'experience_input' => $this->experience_input,
            'education_input' => $this->education_input,
            'certifications_input' => $this->certifications_input,
            'generated_markdown' => $this->generated_markdown,
            'ai_status' => $this->ai_status instanceof AiStatus
                ? $this->ai_status->value
                : $this->ai_status,
            'ai_output' => $this->ai_output,
            'ai_error' => $this->ai_error,
            'score_total' => $this->score_total,
            'grade' => $this->grade,
            'criteria' => $this->mobileList($this->criteria),
            'pdf_url' => $signedUrl,
            'template_pdf_url' => $signedUrl,
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
