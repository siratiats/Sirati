<?php

namespace App\Services\Ai;

/**
 * Single source for the generation-timeout stack.
 *
 * Invariant (enforced by /up against live env, not only the test suite):
 * HTTP budget + fallback budget < job timeout < client poll.
 *
 * The budget bounds when a new HTTP attempt may start, not when the last one
 * ends, so each layer can overrun by one Retry-After/backoff sleep (5s).
 * Default worst case per job attempt is therefore ~160s, not 150s
 * (100+5 + 50+5), leaving a 20s margin inside the 180s job timeout.
 *
 * A persistent double-provider outage (tries=3, backoff=[10,30]) reaches
 * failed() at ~520s. The client poll stops at 210s — a ~310s gap covered by
 * the push notification, not a new defect.
 *
 * HTTP retries are also bounded by a wall-clock budget so a slow OpenAI hang
 * cannot consume the DeepInfra fallback's window inside the same job attempt.
 */
final class AiTimeouts
{
    /** Wall-clock seconds allowed for one primary provider call. */
    public const HTTP_BUDGET_SECONDS = 50;

    /**
     * Wall-clock seconds allowed for a DeepInfra call (primary or fallback).
     * Must exceed {@see self::DEEPINFRA_GENERATE_TIMEOUT} so a fast 429 can
     * still retry, but a full hang cannot start a second 90s wait.
     */
    public const FALLBACK_BUDGET_SECONDS = 100;

    /** DeepInfra timeout for generate_cv / analysis_advice. Qwen 72B / Llama 70B often exceed 45s. */
    public const DEEPINFRA_GENERATE_TIMEOUT = 90;

    /** GenerateCv* job $timeout. */
    public const JOB_SECONDS = 180;

    /**
     * Flutter CvApiService.pollingTimeout default, in seconds.
     * Must stay in sync with flutter_app/lib/shared/services/cv_api_service.dart.
     */
    public const CLIENT_POLL_SECONDS = 210;

    public const HTTP_RETRY_TIMES = 3;

    public const HTTP_MAX_DELAY_MS = 5_000;

    public static function httpPlusFallbackBudgetSeconds(): int
    {
        return self::HTTP_BUDGET_SECONDS + self::FALLBACK_BUDGET_SECONDS;
    }

    public static function httpSleepOverrunSeconds(): int
    {
        return (int) ceil(self::HTTP_MAX_DELAY_MS / 1000);
    }

    /**
     * Worst wall-clock for one provider layer: the budget gates whether a
     * retry may start, but an in-flight attempt still runs to its timeout.
     * When the attempt fits in the budget, a late retry can add one sleep.
     */
    public static function layerWorstCaseSeconds(int $budgetSeconds, int $attemptTimeoutSeconds): int
    {
        if ($attemptTimeoutSeconds > $budgetSeconds) {
            return $attemptTimeoutSeconds;
        }

        return $budgetSeconds + self::httpSleepOverrunSeconds();
    }

    /**
     * Live worst case for one GenerateCv* job attempt (DeepInfra layer + OpenAI
     * layer, each able to overrun by one sleep). Reads production env knobs.
     */
    public static function worstCaseJobAttemptSeconds(): int
    {
        $deepinfraAttempt = (int) config(
            'services.deepinfra.generate_timeout',
            self::DEEPINFRA_GENERATE_TIMEOUT,
        );
        $openaiAttempt = (int) config('services.openai.timeout', 30);

        return self::layerWorstCaseSeconds(self::FALLBACK_BUDGET_SECONDS, $deepinfraAttempt)
            + self::layerWorstCaseSeconds(self::HTTP_BUDGET_SECONDS, $openaiAttempt);
    }
}
