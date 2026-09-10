<?php

namespace App\Services\Ai;

use App\Exceptions\AiTruncationException;
use Illuminate\Support\Facades\Log;

/**
 * Shared truncation signal for every CV AI driver.
 *
 * OpenAI-compatible APIs report finish_reason=length; Anthropic reports
 * stop_reason=max_tokens. Both mean the output hit MAX_TOKENS and will do
 * so again on retry.
 */
final class AiOutputTruncation
{
    public static function throwIfTruncated(string $operation, string $model, mixed $reason): void
    {
        if (! is_string($reason) || ! in_array($reason, ['length', 'max_tokens'], true)) {
            return;
        }

        // Privacy: metadata only. Prompts and AI responses contain candidate
        // CV data and must never be added to logs or error reports.
        Log::warning('AI structured output truncated by max_tokens', [
            'operation' => $operation,
            'model' => $model,
            'stop_reason' => $reason,
        ]);

        throw new AiTruncationException;
    }
}
