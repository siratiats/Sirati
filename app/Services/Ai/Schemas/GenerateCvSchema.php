<?php

namespace App\Services\Ai\Schemas;

/**
 * Structured-output schema for full CV generation.
 *
 * Deliberately narrow: `cv_markdown` already contains the summary, the skills
 * block and the experience bullets. Asking the model to emit them a second
 * time as `professional_summary` / `core_skills` /
 * `improved_experience_bullets` roughly doubled the decoded output — the single
 * biggest driver of generation latency, and worse in Arabic, which costs ~2-3x
 * the tokens per word. Nothing read those keys back. Do not re-add a field
 * unless something actually consumes it.
 */
final class GenerateCvSchema
{
    public const NAME = 'generate_cv';

    public const MAX_TOKENS = 4096;

    /**
     * @return array{type: string, json_schema: array{name: string, strict: true, schema: array<string, mixed>}}
     */
    public static function responseFormat(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => self::NAME,
                'strict' => true,
                'schema' => self::schema(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'cv_markdown',
                'headline',
                'ats_notes',
                'missing_information',
            ],
            'properties' => [
                'cv_markdown' => ['type' => 'string'],
                'headline' => ['type' => 'string'],
                'ats_notes' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'missing_information' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }
}
