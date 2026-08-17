<?php

namespace Tests\Feature;

use App\Exceptions\AiRefusalException;
use App\Services\Ai\Schemas\AnalysisAdviceSchema;
use App\Services\Ai\Schemas\EnhanceJobDescriptionSchema;
use App\Services\Ai\Schemas\GenerateCvSchema;
use App\Services\Ai\Schemas\OperationSchemas;
use App\Services\OpenAiCvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use UnexpectedValueException;

class OpenAiStructuredOutputsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'gpt-4.1-mini',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.timeout' => 30,
            'services.cv_ai.response_cache_enabled' => false,
        ]);
    }

    public function test_analysis_advice_uses_structured_outputs_and_returns_decoded_body(): void
    {
        $body = [
            'executive_summary' => 'سيرة قوية في Laravel',
            'top_priorities' => ['أضف أرقام إنجاز'],
            'rewritten_summary' => null,
            'keyword_recommendations' => ['API', 'SQL'],
            'bullet_improvements' => [
                [
                    'before' => 'Worked on APIs',
                    'after' => 'Built REST APIs serving 25 users',
                    'reason' => 'Add metrics',
                ],
            ],
            'warnings' => [],
        ];

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response($this->completionResponse($body), 200),
        ]);

        $result = app(OpenAiCvService::class)->analysisAdvice(
            ['total' => 70, 'grade' => 'B'],
            'Laravel backend developer resume text',
            'Laravel Developer',
        );

        $this->assertSame($body, $result);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['response_format']['type'] ?? null) === 'json_schema'
                && ($data['response_format']['json_schema']['name'] ?? null) === 'analysis_advice'
                && ($data['response_format']['json_schema']['strict'] ?? false) === true
                && ($data['max_tokens'] ?? null) === AnalysisAdviceSchema::MAX_TOKENS
                && ($data['model'] ?? null) === 'gpt-4.1-mini';
        });
    }

    public function test_generate_cv_uses_structured_outputs_and_returns_decoded_body(): void
    {
        $body = [
            'cv_markdown' => '# Salem Sayer',
            'headline' => 'Laravel Developer',
            'professional_summary' => 'Backend developer.',
            'core_skills' => ['Laravel', 'PHP'],
            'improved_experience_bullets' => ['Built APIs'],
            'ats_notes' => ['Keep keywords'],
            'missing_information' => [],
        ];

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response($this->completionResponse($body), 200),
        ]);

        $result = app(OpenAiCvService::class)->generateCv([
            'full_name' => 'Salem Sayer',
            'target_job_title' => 'Laravel Developer',
        ]);

        $this->assertSame($body, $result);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['response_format']['type'] ?? null) === 'json_schema'
                && ($data['response_format']['json_schema']['name'] ?? null) === 'generate_cv'
                && ($data['response_format']['json_schema']['strict'] ?? false) === true
                && ($data['max_tokens'] ?? null) === GenerateCvSchema::MAX_TOKENS;
        });
    }

    public function test_enhance_job_description_uses_structured_outputs_and_returns_decoded_body(): void
    {
        $body = [
            'enhanced_description' => 'Backend engineer building APIs.',
            'suggested_keywords' => ['Laravel', 'API'],
            'responsibilities' => ['Build APIs'],
            'requirements' => ['PHP'],
        ];

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response($this->completionResponse($body), 200),
        ]);

        $result = app(OpenAiCvService::class)->enhanceJobDescription(
            'Laravel Developer',
            'Build REST APIs',
            'en',
        );

        $this->assertSame($body, $result);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['response_format']['type'] ?? null) === 'json_schema'
                && ($data['response_format']['json_schema']['name'] ?? null) === 'enhance_job_description'
                && ($data['response_format']['json_schema']['strict'] ?? false) === true
                && ($data['max_tokens'] ?? null) === EnhanceJobDescriptionSchema::MAX_TOKENS;
        });
    }

    public function test_refusal_response_throws_ai_refusal_exception(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'message' => [
                            'content' => null,
                            'refusal' => 'I cannot assist with that request.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                ],
            ], 200),
        ]);

        $this->expectException(AiRefusalException::class);
        $this->expectExceptionMessage('I cannot assist with that request.');

        app(OpenAiCvService::class)->enhanceJobDescription('Role', 'Desc', 'en');
    }

    public function test_finish_reason_length_throws_unexpected_value_exception(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'truncated')
                    && ($context['finish_reason'] ?? null) === 'length';
            });

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'finish_reason' => 'length',
                        'message' => [
                            'content' => '{"enhanced_description":"partial',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 2048,
                ],
            ], 200),
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('truncated');

        app(OpenAiCvService::class)->enhanceJobDescription('Role', 'Desc', 'en');
    }

    public function test_every_schema_sets_strict_true_and_additional_properties_false(): void
    {
        foreach (OperationSchemas::all() as $schemaClass) {
            $responseFormat = $schemaClass::responseFormat();
            $schema = $schemaClass::schema();

            $this->assertTrue(
                $responseFormat['json_schema']['strict'] ?? false,
                "{$schemaClass} must set strict: true",
            );

            $this->assertSchemaObjectsDisallowAdditionalProperties($schema, $schemaClass);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function assertSchemaObjectsDisallowAdditionalProperties(array $node, string $context): void
    {
        if (($node['type'] ?? null) === 'object') {
            $this->assertArrayHasKey('additionalProperties', $node, "{$context}: object missing additionalProperties");
            $this->assertFalse(
                $node['additionalProperties'],
                "{$context}: additionalProperties must be false",
            );
            $this->assertArrayHasKey('required', $node, "{$context}: object missing required");
            $this->assertArrayHasKey('properties', $node, "{$context}: object missing properties");
            $this->assertEqualsCanonicalizing(
                array_keys($node['properties']),
                $node['required'],
                "{$context}: required must list every property",
            );
        }

        if (isset($node['properties']) && is_array($node['properties'])) {
            foreach ($node['properties'] as $name => $property) {
                if (is_array($property)) {
                    $this->assertSchemaObjectsDisallowAdditionalProperties($property, "{$context}.{$name}");
                }
            }
        }

        if (isset($node['items']) && is_array($node['items'])) {
            $this->assertSchemaObjectsDisallowAdditionalProperties($node['items'], "{$context}[]");
        }
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function completionResponse(array $content, string $finishReason = 'stop'): array
    {
        return [
            'choices' => [
                [
                    'finish_reason' => $finishReason,
                    'message' => [
                        'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
                'prompt_tokens_details' => [
                    'cached_tokens' => 0,
                ],
            ],
        ];
    }
}
