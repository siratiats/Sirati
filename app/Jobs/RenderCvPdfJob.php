<?php

namespace App\Jobs;

use App\Cv\CvExportGuard;
use App\Enums\AiStatus;
use App\Models\GeneratedCv;
use App\Services\CvTemplateRenderer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RenderCvPdfJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public int $generatedCvId, public string $fingerprint)
    {
        $this->onQueue(config('cv_pdf.queue', 'default'));
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->generatedCvId.':'.$this->fingerprint;
    }

    public function handle(CvTemplateRenderer $renderer): void
    {
        $cv = GeneratedCv::find($this->generatedCvId);
        if ($cv === null || $cv->ai_status !== AiStatus::Completed || CvExportGuard::blockedReason($cv) !== null) {
            return;
        }

        $language = $cv->language === 'en' ? 'en' : 'ar';
        $template = $renderer->resolve(null, $language);
        // A more recent edit has its own job. Never publish an obsolete snapshot.
        if ($renderer->pdfFingerprint($cv, $template, $language) !== $this->fingerprint) {
            return;
        }

        $renderer->renderPdfBlob($cv, $template, $language);
    }
}
