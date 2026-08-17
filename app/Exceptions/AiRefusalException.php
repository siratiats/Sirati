<?php

namespace App\Exceptions;

use UnexpectedValueException;

/**
 * OpenAI Structured Outputs refused to answer (message.refusal present).
 * Do not retry — the model declined the request.
 */
class AiRefusalException extends UnexpectedValueException
{
}
