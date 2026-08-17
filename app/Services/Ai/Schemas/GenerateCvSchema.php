<?php

namespace App\Services\Ai\Schemas;

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
                'professional_summary',
                'core_skills',
                'improved_experience_bullets',
                'ats_notes',
                'missing_information',
            ],
            'properties' => [
                'cv_markdown' => ['type' => 'string'],
                'headline' => ['type' => 'string'],
                'professional_summary' => ['type' => 'string'],
                'core_skills' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'improved_experience_bullets' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
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
