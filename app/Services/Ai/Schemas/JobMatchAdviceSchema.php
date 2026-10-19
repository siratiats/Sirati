<?php

namespace App\Services\Ai\Schemas;

final class JobMatchAdviceSchema
{
    public const NAME = 'job_match_advice';

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
                'strengths',
                'gaps',
                'suggestions',
            ],
            'properties' => [
                'executive_summary' => ['type' => 'string'],
                'strengths' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'gaps' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'suggestions' => [
                    'type' => 'array',
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
                            'rationale' => ['type' => 'string'],
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
