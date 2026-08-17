<?php

namespace App\Services\Ai\Schemas;

final class EnhanceCvFieldSchema
{
    public const NAME = 'enhance_cv_field';

    public const MAX_TOKENS = 2048;

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

    /** @return array<string, mixed> */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'enhanced_text',
                'changes_made',
                'missing_facts',
                'ats_keywords_added',
                'unverified_claims',
            ],
            'properties' => [
                'enhanced_text' => ['type' => 'string'],
                'changes_made' => ['type' => 'array', 'items' => ['type' => 'string']],
                'missing_facts' => ['type' => 'array', 'items' => ['type' => 'string']],
                'ats_keywords_added' => ['type' => 'array', 'items' => ['type' => 'string']],
                'unverified_claims' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['text', 'kind'],
                        'properties' => [
                            'text' => ['type' => 'string'],
                            'kind' => ['type' => 'string', 'enum' => ['date', 'employer']],
                        ],
                    ],
                ],
            ],
        ];
    }
}
