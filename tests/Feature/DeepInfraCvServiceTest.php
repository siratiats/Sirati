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

    public function test_deepinfra_enhancement_uses_fast_model(): void
    {
        $body = [
            'enhanced_text' => 'مهارات برمجية احترافية: PHP, Laravel',
            'changes_made' => ['تحسين الصياغة'],
            'missing_facts' => [],
            'ats_keywords_added' => ['Laravel'],
            'unverified_claims' => [],
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
                'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 40],
            ]),
        ]);

        $service = app(DeepInfraCvService::class);
        $result = $service->enhanceCvField('skills', 'PHP, Laravel', 'مطور لارافل', 'ar');

        $this->assertSame($body['enhanced_text'], $result['enhanced_text']);
        $this->assertDatabaseHas(AiCallLog::class, [
            'provider' => 'deepinfra',
            'model' => 'mistralai/Mistral-Small-24B-Instruct-2501',
            'operation' => 'enhance_cv_field',
        ]);
    }

    public function test_deepinfra_english_cv_generation_uses_english_model(): void
    {
        $body = [
            'cv_markdown' => '## John Doe Resume',
            'headline' => 'Senior Software Engineer',
            'professional_summary' => 'Experienced software engineer.',
            'core_skills' => ['PHP', 'Laravel', 'AWS'],
            'improved_experience_bullets' => ['Built high-scale APIs'],
            'ats_notes' => ['ATS Friendly'],
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
                'usage' => ['prompt_tokens' => 180, 'completion_tokens' => 120],
            ]),
        ]);

        $service = app(DeepInfraCvService::class);
        $result = $service->generateCv(['full_name' => 'John Doe', 'language' => 'en']);

        $this->assertSame($body['headline'], $result['headline']);
        $this->assertDatabaseHas(AiCallLog::class, [
            'provider' => 'deepinfra',
            'model' => 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo',
            'operation' => 'generate_cv',
        ]);
    }
}
