<?php

namespace App\Services\Ai;

use UnexpectedValueException;

/**
 * Rejects generate_cv replies that are valid JSON but not a CV (e.g. a model
 * echoing the input payload back). Throwing lets AiProviderFallback try the
 * other provider and keeps the job from marking an empty CV as completed.
 */
final class GenerateCvResultGuard
{
    /**
     * @param  array<array-key, mixed>  $result
     * @return array{cv_markdown: string, headline: string, ats_notes: list<string>, missing_information: list<string>}
     *
     * @throws UnexpectedValueException
     */
    public function enforce(array $result, string $provider): array
    {
        $markdown = $result['cv_markdown'] ?? null;
        if (! is_string($markdown) || trim($markdown) === '') {
            throw new UnexpectedValueException(
                "{$provider} generate_cv reply has no cv_markdown (keys: ".implode(',', array_keys($result)).').'
            );
        }

        return [
            ...$result,
            'cv_markdown' => $markdown,
            'headline' => is_scalar($result['headline'] ?? null) ? trim((string) $result['headline']) : '',
            'ats_notes' => $this->strings($result['ats_notes'] ?? []),
            'missing_information' => $this->strings($result['missing_information'] ?? []),
        ];
    }

    /** @return list<string> */
    private function strings(mixed $values): array
    {
        return is_array($values)
            ? array_values(array_filter(array_map(
                static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
                $values,
            )))
            : [];
    }
}
