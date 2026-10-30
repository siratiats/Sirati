<?php

namespace App\Http\Resources;

use App\Cv\CvExportGuard;
use App\Enums\AiStatus;
use App\Services\I18n\ServerText;
use App\Services\Subscriptions\SubscriptionGate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class GeneratedCvResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $publicBaseUrl = rtrim((string) config('app.public_url', 'https://siratie.com'), '/');
        $signedPath = URL::temporarySignedRoute(
            'api.generated-cvs.pdf',
            now()->addDays(7),
            ['generatedCv' => $this->id],
            false
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
            'document' => $this->cvDocument()->toArray(),
            'missing_translations' => $this->cvDocument()->missingTranslations(),
            'generated_markdown' => $this->generated_markdown,
            'ai_status' => $this->ai_status instanceof AiStatus
                ? $this->ai_status->value
                : $this->ai_status,
            'ai_output' => $this->ai_output,
            'ai_error' => $this->ai_error,
            'score_total' => $this->score_total,
            'grade' => $this->grade,
            'criteria' => $this->mobileList($this->criteria),
            'pdf_url' => $publicBaseUrl.$signedPath,
            'template_pdf_url' => $publicBaseUrl.$signedPath,
            'access' => $this->accessBlock($request),
            'export' => CvExportGuard::exportState($this->resource),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * The download button should look locked before it is tapped, not after a
     * 402 comes back. pdf_url stays in the payload because it is still the
     * correct URL the moment the subscription starts.
     *
     * @return array<string, mixed>
     */
    private function accessBlock(Request $request): array
    {
        $gate = app(SubscriptionGate::class);
        $user = $request->user() ?? $this->user;
        $canDownload = $gate->allowsDownload($user);

        return [
            'can_preview' => true,
            'can_download' => $canDownload,
            'is_watermarked' => ! $canDownload,
            'download_locked_reason' => $canDownload ? null : 'subscription_required',
            'message' => $canDownload ? null : ServerText::t('serverCvDownloadLocked'),
            'message_ar' => $canDownload ? null : ServerText::t('serverCvDownloadLocked', [], 'ar'),
            'message_en' => $canDownload ? null : ServerText::t('serverCvDownloadLocked', [], 'en'),
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
