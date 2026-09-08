<?php

namespace App\Services;

use App\Models\CvTemplate;
use App\Models\User;

class EntitlementService
{
    /**
     * Determine if a user is entitled to export a PDF with the given template.
     */
    public function canExportTemplate(?User $user, CvTemplate $template): bool
    {
        if (! $template->isPremium()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $user->isPremium();
    }

    /**
     * Determine if a user can preview the given template.
     * Active templates are previewable by any user (freemium discovery policy).
     * Inactive templates are restricted from preview.
     */
    public function canPreviewTemplate(?User $user, CvTemplate $template): bool
    {
        return (bool) $template->is_active;
    }

    /**
     * Determine if a template preview should display a watermark.
     */
    public function shouldWatermark(?User $user, CvTemplate $template): bool
    {
        return ! $this->canExportTemplate($user, $template);
    }
}
