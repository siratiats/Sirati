<?php

namespace App\Services\Ai\Schemas;

final class JobMatchAdviceSchema
{
    public const NAME = 'job_match_advice';

    public const MAX_TOKENS = 3072;

    public const MAX_SUGGESTIONS = 8;

    public const MAX_STRENGTHS = 6;

    public const MAX_GAPS = 6;

    public const MAX_RATIONALE_CHARS = 240;

    /**
     * Anthropic rejects maxItems/maxLength; retain the limits in descriptions
     * and enforce them in JobMatchResultGuard, as with SDK schema transforms.
     *
     * @return array<string, mixed>
     */
    public static function anthropicSchema(): array
    {
        $schema = self::schema();
        foreach (['strengths', 'gaps', 'suggestions'] as $field) {
            unset($schema['properties'][$field]['maxItems']);
        }
        unset($schema['properties']['suggestions']['items']['properties']['rationale']['maxLength']);

        return $schema;
    }

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
                'strengths',
                'gaps',
                'suggestions',
            ],
            'properties' => [
                'executive_summary' => ['type' => 'string'],
                'strengths' => [
                    'type' => 'array',
                    'maxItems' => self::MAX_STRENGTHS,
                    'description' => 'At most '.self::MAX_STRENGTHS.' concise strengths.',
                    'items' => ['type' => 'string'],
                ],
                'gaps' => [
                    'type' => 'array',
                    'maxItems' => self::MAX_GAPS,
                    'description' => 'At most '.self::MAX_GAPS.' concise gaps.',
                    'items' => ['type' => 'string'],
                ],
                'suggestions' => [
                    'type' => 'array',
                    'maxItems' => self::MAX_SUGGESTIONS,
                    'description' => 'At most '.self::MAX_SUGGESTIONS.' suggestions, highest impact first.',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => [
                            'key',
                            'kind',
                            'target',
                            'before',
                            'after',
                            'rationale',
                            'jd_terms',
                            'needs_user_fact',
                        ],
                        'properties' => [
                            'key' => ['type' => 'string'],
                            'kind' => ['type' => 'string', 'enum' => ['replace', 'append_skill', 'append_bullet']],
                            'target' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => [
                                    'section',
                                    'entry_id',
                                    'field',
                                    'bullet_index',
                                ],
                                'properties' => [
                                    'section' => [
                                        'type' => 'string',
                                        'enum' => [
                                            'summary',
                                            'skills',
                                            'experience',
                                            'education',
                                            'certifications',
                                            'projects',
                                            'languages',
                                            'personal',
                                            'custom_sections',
                                        ],
                                    ],
                                    'entry_id' => ['type' => ['string', 'null']],
                                    'field' => ['type' => ['string', 'null']],
                                    'bullet_index' => ['type' => ['integer', 'null']],
                                ],
                            ],
                            'before' => ['type' => ['string', 'null']],
                            'after' => ['type' => 'string'],
                            'rationale' => [
                                'type' => 'string',
                                'maxLength' => self::MAX_RATIONALE_CHARS,
                                'description' => 'A concise reason of at most '.self::MAX_RATIONALE_CHARS.' characters.',
                            ],
                            'jd_terms' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'needs_user_fact' => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
