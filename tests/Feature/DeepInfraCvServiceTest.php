<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Models\AiCallLog;
use App\Services\DeepInfraCvService;
use App\Services\OpenAiCvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepInfraCvServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.deepinfra.api_key' => 'test-deepinfra-key',
            'services.deepinfra.model' => 'Qwen/Qwen2.5-72B-Instruct',
            'services.deepinfra.base_url' => 'https://api.deepinfra.com/v1/openai',
            'services.cv_ai.response_cache_enabled' => false,
            'services.cv_ai.provider' => 'deepinfra',
        ]);
    }

    public function test_provider_switch_binds_deepinfra_when_configured(): void
    {
        $provider = app(CvAiProvider::class);

        $this->assertInstanceOf(DeepInfraCvService::class, $provider);
    }

    public function test_deepinfra_analysis_advice_returns_valid_output(): void
    {
        $body = [
            'executive_summary' => 'سيرة مناسبة مع تحسينات رقمية مطلوبة.',
            'top_priorities' => ['أضف أرقام إنجاز'],
            'rewritten_summary' => null,
            'keyword_recommendations' => ['Laravel', 'API'],
            'bullet_improvements' => [],
            'warnings' => [],
        ];

        Http::fake([
            'https://api.deepinfra.com/v1/openai/chat/completions' => Http::response([
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'Qwen/Qwen2.5-72B-Instruct',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode($body, JSON_UNESCAPED_UNICODE),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 80,
                ],
            ]),
        ]);

        $service = app(DeepInfraCvService::class);
        $result = $service->analysisAdvice(['total' => 75], 'نص السيرة الذاتية', 'مطوّر لارافل');

        $this->assertSame($body['executive_summary'], $result['executive_summary']);
        $this->assertSame($body['top_priorities'], $result['top_priorities']);

        $this->assertDatabaseHas(AiCallLog::class, [
            'provider' => 'deepinfra',
            'model' => 'Qwen/Qwen2.5-72B-Instruct',
            'operation' => 'analysis_advice',
            'input_tokens' => 120,
            'output_tokens' => 80,
        ]);
    }

    public function test_deepinfra_generate_cv_returns_valid_output(): void
    {
        $body = [
            'cv_markdown' => '## سيرة ذاتية',
            'headline' => 'مطور برمجيات',
            'professional_summary' => 'ملخص مهني',
            'core_skills' => ['PHP', 'Laravel'],
            'improved_experience_bullets' => ['بناء منصات'],
            'ats_notes' => ['متوافق مع ATS'],
            'missing_information' => [],
        ];

        Http::fake([
            'https://api.deepinfra.com/v1/openai/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($body, JSON_UNESCAPED_UNICODE),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 150],
            ]),
        ]);

        $service = app(DeepInfraCvService::class);
        $result = $service->generateCv(['full_name' => 'سالم']);

        $this->assertSame($body['cv_markdown'], $result['cv_markdown']);
        $this->assertSame($body['headline'], $result['headline']);
    }

    public function test_openai_falls_back_to_deepinfra_when_openai_fails(): void
    {
        config([
            'services.cv_ai.provider' => 'openai',
            'services.openai.api_key' => 'broken-key',
            'services.deepinfra.api_key' => 'valid-deepinfra-key',
        ]);

        $body = [
            'cv_markdown' => '## Fallback CV',
            'headline' => 'Fallback Headline',
            'professional_summary' => 'Summary',
            'core_skills' => ['PHP'],
            'improved_experience_bullets' => [],
            'ats_notes' => [],
            'missing_information' => [],
        ];

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(['error' => 'Insufficient quota'], 429),
            'https://api.deepinfra.com/v1/openai/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($body, JSON_UNESCAPED_UNICODE),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ]),
        ]);

        $service = app(OpenAiCvService::class);
        $result = $service->generateCv(['full_name' => 'سالم']);

        $this->assertSame('## Fallback CV', $result['cv_markdown']);
    }
}
