<?php

namespace App\Services\Ai\Schemas;

final class AnalysisAdviceSchema
{
    public const NAME = 'analysis_advice';

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
                'executive_summary',
                'top_priorities',
                'rewritten_summary',
                'keyword_recommendations',
                'bullet_improvements',
                'warnings',
            ],
            'properties' => [
                'executive_summary' => ['type' => 'string'],
                'top_priorities' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'rewritten_summary' => ['type' => ['string', 'null']],
                'keyword_recommendations' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'bullet_improvements' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['before', 'after', 'reason'],
                        'properties' => [
                            'before' => ['type' => ['string', 'null']],
                            'after' => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                        ],
                    ],
                ],
                'warnings' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }
}
