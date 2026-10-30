<?php

namespace App\Cv;

use App\Enums\AiStatus;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\I18n\ServerText;
use App\Services\Subscriptions\SubscriptionGate;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Employer-facing artefacts must not leave the app while they are unfinished
 * or while they still carry internal scoring.
 */
final class CvExportGuard
{
    public const BLOCKED_GENERATING = 'generating';

    public const BLOCKED_MISSING_CONTACT = 'missing_contact';

    public const BLOCKED_UNSTRUCTURED_EXPERIENCE = 'unstructured_experience';

    /**
     * Quality gates run before the paywall on purpose: never ask someone to pay
     * for a CV that is still generating or missing its contact details.
     */
    public static function assertExportable(GeneratedCv $generatedCv, ?User $user = null): void
    {
        $reason = self::blockedReason($generatedCv);

        if ($reason === self::BLOCKED_GENERATING) {
            throw new HttpException(
                409,
                'CV generation is still running. Export is blocked until it finishes.',
            );
        }

        if ($reason !== null) {
            throw new HttpException(
                422,
                'This CV is not ready to send to an employer. Add email and phone, and finish generation so experience is structured.',
            );
        }

        app(SubscriptionGate::class)->assertCanDownload($user ?? $generatedCv->user);
    }

    public static function isUsable(GeneratedCv $generatedCv): bool
    {
        return self::qualityGap($generatedCv) === null;
    }

    /**
     * Why the CV cannot be exported yet (one of the BLOCKED_* codes), or null
     * when it can. The paywall is not part of this: see SubscriptionGate.
     */
    public static function blockedReason(GeneratedCv $generatedCv): ?string
    {
        if (in_array(self::status($generatedCv), [AiStatus::Queued, AiStatus::Processing], true)) {
            return self::BLOCKED_GENERATING;
        }

        return self::qualityGap($generatedCv);
    }

    /**
     * Export readiness for clients, so they explain the gap in the app instead
     * of opening a PDF link the server is going to refuse.
     *
     * @return array{ready: bool, blocked_reason: string|null, message_ar: string|null, message_en: string|null}
     */
    public static function exportState(GeneratedCv $generatedCv): array
    {
        $reason = self::blockedReason($generatedCv);

        return [
            'ready' => $reason === null,
            'blocked_reason' => $reason,
            'message_ar' => $reason === null ? null : self::message($reason, 'ar'),
            'message_en' => $reason === null ? null : self::message($reason, 'en'),
        ];
    }

    public static function message(string $reason, string $language): string
    {
        $key = [
            self::BLOCKED_GENERATING => 'serverCvExportGenerating',
            self::BLOCKED_MISSING_CONTACT => 'serverCvExportMissingContact',
            self::BLOCKED_UNSTRUCTURED_EXPERIENCE => 'serverCvExportUnstructuredExperience',
        ][$reason];

        return ServerText::t($key, [], $language);
    }

    private static function qualityGap(GeneratedCv $generatedCv): ?string
    {
        $document = $generatedCv->cvDocument();
        $email = trim((string) $document->personal->email);
        $phone = trim((string) $document->personal->phone);

        if ($email === '' || $phone === '') {
            return self::BLOCKED_MISSING_CONTACT;
        }

        if (self::status($generatedCv) === AiStatus::Completed || $document->hasStructuredExperience()) {
            return null;
        }

        return self::BLOCKED_UNSTRUCTURED_EXPERIENCE;
    }

    private static function status(GeneratedCv $generatedCv): ?AiStatus
    {
        return $generatedCv->ai_status instanceof AiStatus
            ? $generatedCv->ai_status
            : AiStatus::tryFrom((string) $generatedCv->ai_status);
    }
}
