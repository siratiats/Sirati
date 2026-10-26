<?php

namespace App\Observers;

use App\Enums\AiStatus;
use App\Jobs\RenderCvPdfJob;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\CvTemplateRenderer;
use Illuminate\Support\Facades\DB;

class GeneratedCvObserver
{
    public function created(GeneratedCv $cv): void
    {
        $this->dispatch($cv);
    }

    public function updated(GeneratedCv $cv): void
    {
        $this->dispatch($cv);
    }

    public function deleted(GeneratedCv $cv): void
    {
        app(CvTemplateRenderer::class)->deletePdfArtifacts($cv->id);
    }

    public function userUpdated(User $user): void
    {
        if (! $user->wasChanged('avatar_path')) {
            return;
        }

        // uploadAvatar always assigns a new UUID path, including replacements.
        $user->generatedCvs()->where('ai_status', AiStatus::Completed->value)
            ->each(fn (GeneratedCv $cv) => $this->dispatch($cv));
    }

    private function dispatch(GeneratedCv $cv): void
    {
        if ($cv->ai_status !== AiStatus::Completed) {
            return;
        }

        $id = $cv->id;
        // Acquire the unique-job lock only after commit; rollbacks must not
        // suppress a later successful completion with the same fingerprint.
        DB::afterCommit(function () use ($id): void {
            $current = GeneratedCv::find($id);
            if ($current === null || $current->ai_status !== AiStatus::Completed) {
                return;
            }
            $renderer = app(CvTemplateRenderer::class);
            $language = $current->language === 'en' ? 'en' : 'ar';
            $template = $renderer->resolve(null, $language);
            RenderCvPdfJob::dispatch($current->id, $renderer->pdfFingerprint($current, $template, $language));
        });
    }
}
