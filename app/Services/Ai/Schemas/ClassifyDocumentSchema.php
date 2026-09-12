<?php

namespace App\Services\Ai\Schemas;

final class ClassifyDocumentSchema
{
    public const NAME = 'classify_document';

    public const MAX_TOKENS = 512;

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
                'is_resume',
                'document_type',
                'confidence',
                'reason_ar',
                'reason_en',
            ],
            'properties' => [
                'is_resume' => ['type' => 'boolean'],
                'document_type' => [
                    'type' => 'string',
                    'enum' => [
                        'resume',
                        'bank_receipt',
                        'invoice',
                        'certificate',
                        'contract',
                        'id_document',
                        'academic_transcript',
                        'other_non_resume',
                    ],
                ],
                'confidence' => ['type' => 'number'],
                'reason_ar' => ['type' => 'string'],
                'reason_en' => ['type' => 'string'],
            ],
        ];
    }
}
