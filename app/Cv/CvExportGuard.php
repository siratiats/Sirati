<?php

namespace App\Cv;

use App\Enums\AiStatus;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionGate;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Employer-facing artefacts must not leave the app while they are unfinished
 * or while they still carry internal scoring.
 */
final class CvExportGuard
{
    /**
     * Quality gates run before the paywall on purpose: never ask someone to pay
     * for a CV that is still generating or missing its contact details.
     */
    public static function assertExportable(GeneratedCv $generatedCv, ?User $user = null): void
    {
        $status = $generatedCv->ai_status instanceof AiStatus
            ? $generatedCv->ai_status
            : AiStatus::tryFrom((string) $generatedCv->ai_status);

        if (in_array($status, [AiStatus::Queued, AiStatus::Processing], true)) {
            throw new HttpException(
                409,
                'CV generation is still running. Export is blocked until it finishes.',
            );
        }

        if (! self::isUsable($generatedCv)) {
            throw new HttpException(
                422,
                'This CV is not ready to send to an employer. Add email and phone, and finish generation so experience is structured.',
            );
        }

        app(SubscriptionGate::class)->assertCanDownload($user ?? $generatedCv->user);
    }

    public static function isUsable(GeneratedCv $generatedCv): bool
    {
        $document = $generatedCv->cvDocument();
        $email = trim((string) $document->personal->email);
        $phone = trim((string) $document->personal->phone);

        if ($email === '' || $phone === '') {
            return false;
        }

        $status = $generatedCv->ai_status instanceof AiStatus
            ? $generatedCv->ai_status
            : AiStatus::tryFrom((string) $generatedCv->ai_status);

        if ($status === AiStatus::Completed) {
            return true;
        }

        return $document->hasStructuredExperience();
    }
}
