<?php

namespace App\Services\Ai\Schemas;

final class EnhanceJobDescriptionSchema
{
    public const NAME = 'enhance_job_description';

    public const MAX_TOKENS = 2048;

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
                'enhanced_description',
                'suggested_keywords',
                'responsibilities',
                'requirements',
            ],
            'properties' => [
                'enhanced_description' => ['type' => 'string'],
                'suggested_keywords' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'responsibilities' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'requirements' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }
}
