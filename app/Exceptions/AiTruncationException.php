<?php

namespace App\Exceptions;

use UnexpectedValueException;

/**
 * The model hit max_tokens / finish_reason=length. Deterministic in input
 * length, so retrying the same request burns the job budget for a certain
 * failure. Fail fast and tell the user the CV is too long.
 */
class AiTruncationException extends UnexpectedValueException
{
    public const CODE = 'cv_too_long';

    public function __construct()
    {
        parent::__construct(
            self::CODE.': The CV is too long to generate in one pass. Shorten the experience, education, or skills text and try again. / السيرة أطول من أن تُولَّد في تمريرة واحدة. اختصر نص الخبرات أو التعليم أو المهارات ثم أعد المحاولة.'
        );
    }
}
