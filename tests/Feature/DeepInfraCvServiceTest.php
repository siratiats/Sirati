<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Models\AiCallLog;
use App\Services\Ai\AiHttpRetry;
use App\Services\Ai\CachedCvAiProvider;
use App\Services\DeepInfraCvService;
use App\Services\OpenAiCvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
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
            'model' => 'Qwen/Qwen2.5-72B-Instruct',
            'operation' => 'enhance_cv_field',
        ]);
    }

    public function test_deepinfra_english_enhancement_uses_english_fast_model(): void
    {
        $body = [
            'enhanced_text' => 'Professional skills: PHP, Laravel',
            'changes_made' => ['Improved phrasing'],
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
        $result = $service->enhanceCvField('skills', 'PHP, Laravel', 'Laravel Developer', 'en');

        $this->assertSame($body['enhanced_text'], $result['enhanced_text']);
        $this->assertDatabaseHas(AiCallLog::class, [
            'provider' => 'deepinfra',
            'model' => 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo',
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

    public function test_generate_cv_falls_back_to_openai_when_deepinfra_times_out(): void
    {
        Sleep::fake();
        config([
            'services.openai.api_key' => 'test-openai-key',
            'services.openai.model' => 'gpt-4.1-mini',
            'services.openai.base_url' => 'https://api.openai.com/v1',
        ]);

        $body = [
            'cv_markdown' => '# Recovered User',
            'headline' => 'Engineer',
            'ats_notes' => [],
            'missing_information' => [],
        ];

        Http::fake(function ($request) use ($body) {
            if (str_contains($request->url(), 'deepinfra')) {
                throw new ConnectionException('cURL error 28: Operation timed out after 45001 milliseconds with 0 bytes received');
            }

            return Http::response([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => json_encode($body, JSON_UNESCAPED_UNICODE)],
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ], 200);
        });

        $result = app(DeepInfraCvService::class)->generateCv(['full_name' => 'Recovered User', 'language' => 'en']);

        $this->assertSame('# Recovered User', $result['cv_markdown']);
        $this->assertDatabaseHas(AiCallLog::class, [
            'provider' => 'openai',
            'operation' => 'generate_cv',
        ]);
    }

    public function test_cached_generate_cv_does_not_file_openai_fallback_under_deepinfra(): void
    {
        Sleep::fake();
        config([
            'services.cv_ai.response_cache_enabled' => true,
            'cache.default' => 'array',
            'services.openai.api_key' => 'test-openai-key',
            'services.openai.model' => 'gpt-4.1-mini',
            'services.openai.base_url' => 'https://api.openai.com/v1',
        ]);
        Cache::flush();

        $body = [
            'cv_markdown' => '# Recovered User',
            'headline' => 'Engineer',
            'ats_notes' => [],
            'missing_information' => [],
        ];
        $data = ['full_name' => 'Recovered User', 'language' => 'en'];

        $deepinfraCalls = 0;
        Http::fake(function ($request) use ($body, &$deepinfraCalls) {
            if (str_contains($request->url(), 'deepinfra')) {
                $deepinfraCalls++;
                throw new ConnectionException('cURL error 28: Operation timed out after 45001 milliseconds with 0 bytes received');
            }

            return Http::response([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => json_encode($body, JSON_UNESCAPED_UNICODE)],
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ], 200);
        });

        $cached = new CachedCvAiProvider(app(DeepInfraCvService::class));

        $this->assertSame('# Recovered User', $cached->generateCv($data)['cv_markdown']);
        $this->assertSame('# Recovered User', $cached->generateCv($data)['cv_markdown']);

        $this->assertSame(
            AiHttpRetry::TIMES * 2,
            $deepinfraCalls,
            'A DeepInfra-configured repeat must retry DeepInfra rather than serving the OpenAI fallback from cache.',
        );

        $configuredKey = $cached->cacheKey('generate_cv', $cached->normalizePayload($data));
        $this->assertNull(Cache::get($configuredKey));
    }

    public function test_generate_cv_does_not_recurse_when_both_providers_time_out(): void
    {
        Sleep::fake();
        config([
            'services.openai.api_key' => 'test-openai-key',
            'services.openai.model' => 'gpt-4.1-mini',
            'services.openai.base_url' => 'https://api.openai.com/v1',
        ]);

        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->expectException(ConnectionException::class);
        try {
            app(DeepInfraCvService::class)->generateCv(['full_name' => 'User', 'language' => 'en']);
        } finally {
            $this->assertSame(
                AiHttpRetry::TIMES * 2,
                $calls,
                'Each provider may HTTP-retry, but fallback must not recurse back to DeepInfra.',
            );
        }
    }
}
