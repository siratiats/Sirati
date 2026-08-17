<?php

namespace App\Services\Ai\Schemas;

use InvalidArgumentException;

/**
 * Resolves OpenAI Structured Output schemas and token limits per CV AI operation.
 */
final class OperationSchemas
{
    /**
     * @return array{type: string, json_schema: array{name: string, strict: true, schema: array<string, mixed>}}
     */
    public static function responseFormat(string $operation): array
    {
        return match ($operation) {
            AnalysisAdviceSchema::NAME => AnalysisAdviceSchema::responseFormat(),
            GenerateCvSchema::NAME => GenerateCvSchema::responseFormat(),
            EnhanceJobDescriptionSchema::NAME => EnhanceJobDescriptionSchema::responseFormat(),
            EnhanceCvFieldSchema::NAME => EnhanceCvFieldSchema::responseFormat(),
            default => throw new InvalidArgumentException("Unknown AI operation schema: {$operation}"),
        };
    }

    public static function maxTokens(string $operation): int
    {
        return match ($operation) {
            AnalysisAdviceSchema::NAME => AnalysisAdviceSchema::MAX_TOKENS,
            GenerateCvSchema::NAME => GenerateCvSchema::MAX_TOKENS,
            EnhanceJobDescriptionSchema::NAME => EnhanceJobDescriptionSchema::MAX_TOKENS,
            EnhanceCvFieldSchema::NAME => EnhanceCvFieldSchema::MAX_TOKENS,
            default => throw new InvalidArgumentException("Unknown AI operation schema: {$operation}"),
        };
    }

    /**
     * Anthropic Messages API shape: output_config.format (not OpenAI response_format).
     *
     * @return array{format: array{type: string, schema: array<string, mixed>}}
     */
    public static function anthropicOutputConfig(string $operation): array
    {
        $schema = match ($operation) {
            AnalysisAdviceSchema::NAME => AnalysisAdviceSchema::schema(),
            GenerateCvSchema::NAME => GenerateCvSchema::schema(),
            EnhanceJobDescriptionSchema::NAME => EnhanceJobDescriptionSchema::schema(),
            EnhanceCvFieldSchema::NAME => EnhanceCvFieldSchema::schema(),
            default => throw new InvalidArgumentException("Unknown AI operation schema: {$operation}"),
        };

        return [
            'format' => [
                'type' => 'json_schema',
                'schema' => $schema,
            ],
        ];
    }

    /**
     * @return list<class-string>
     */
    public static function all(): array
    {
        return [
            AnalysisAdviceSchema::class,
            GenerateCvSchema::class,
            EnhanceJobDescriptionSchema::class,
            EnhanceCvFieldSchema::class,
        ];
    }
}
