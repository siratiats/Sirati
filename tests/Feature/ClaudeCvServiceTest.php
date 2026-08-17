<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Exceptions\AiRefusalException;
use App\Models\AiCallLog;
use App\Services\Ai\BakeOff\ArabicCvCorpus;
use App\Services\Ai\Schemas\OperationSchemas;
use App\Services\ClaudeCvService;
use App\Services\OpenAiCvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use UnexpectedValueException;

class ClaudeCvServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.anthropic.api_key' => 'test-anthropic-key',
            'services.anthropic.model' => 'claude-sonnet-4-5',
            'services.anthropic.base_url' => 'https://api.anthropic.com/v1',
            'services.anthropic.version' => '2023-06-01',
            'services.cv_ai.response_cache_enabled' => false,
            'services.cv_ai.provider' => 'claude',
        ]);
    }

    public function test_provider_switch_binds_claude_when_configured(): void
    {
        $provider = app(CvAiProvider::class);

        $this->assertInstanceOf(ClaudeCvService::class, $provider);
    }

    public function test_provider_switch_defaults_to_openai(): void
    {
        config(['services.cv_ai.provider' => 'openai']);
        $this->app->forgetInstance(CvAiProvider::class);

        $provider = app(CvAiProvider::class);

        $this->assertInstanceOf(OpenAiCvService::class, $provider);
    }

    public function test_claude_analysis_advice_uses_messages_api_shape(): void
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
            'https://api.anthropic.com/v1/messages' => Http::response([
                'stop_reason' => 'end_turn',
                'content' => [
                    ['type' => 'text', 'text' => json_encode($body, JSON_UNESCAPED_UNICODE)],
                ],
                'usage' => [
                    'input_tokens' => 1500,
                    'output_tokens' => 200,
                    'cache_read_input_tokens' => 0,
                ],
            ], 200),
        ]);

        $result = app(ClaudeCvService::class)->analysisAdvice(
            ['total' => 70, 'grade' => 'B'],
            'سيرة تجريبية Laravel API SQL',
            'مطور Laravel',
        );

        $this->assertSame($body['executive_summary'], $result['executive_summary']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->hasHeader('x-api-key', 'test-anthropic-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && ! $request->hasHeader('Authorization')
                && isset($data['system'])
                && is_string($data['system'])
                && isset($data['max_tokens'])
                && ($data['output_config']['format']['type'] ?? null) === 'json_schema'
                && is_array($data['output_config']['format']['schema'] ?? null)
                && ($data['messages'][0]['role'] ?? null) === 'user'
                && ! collect($data['messages'] ?? [])->contains(fn ($m) => ($m['role'] ?? null) === 'system');
        });

        $log = AiCallLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame('anthropic', $log->provider);
        $this->assertSame('analysis_advice', $log->operation);
        $this->assertSame(1500, $log->input_tokens);
        $this->assertSame(200, $log->output_tokens);
    }

    public function test_claude_refusal_stop_reason_throws(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'stop_reason' => 'refusal',
                'content' => [
                    ['type' => 'text', 'text' => 'I cannot assist with that.'],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ], 200),
        ]);

        $this->expectException(AiRefusalException::class);

        app(ClaudeCvService::class)->enhanceJobDescription('Role', 'Desc', 'en');
    }

    public function test_claude_max_tokens_stop_reason_throws(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'stop_reason' => 'max_tokens',
                'content' => [
                    ['type' => 'text', 'text' => '{"partial":true'],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 4096],
            ], 200),
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('max_tokens');

        app(ClaudeCvService::class)->generateCv(['full_name' => 'Test']);
    }

    public function test_anthropic_output_config_matches_shared_schemas(): void
    {
        $config = OperationSchemas::anthropicOutputConfig('analysis_advice');

        $this->assertSame('json_schema', $config['format']['type']);
        $this->assertSame('object', $config['format']['schema']['type']);
        $this->assertFalse($config['format']['schema']['additionalProperties']);
    }

    public function test_arabic_cv_corpus_has_thirty_fixtures_across_categories(): void
    {
        $all = ArabicCvCorpus::all();

        $this->assertCount(30, $all);

        $valid = array_keys(\App\Services\AtsScoringService::jobKeywords());
        $categories = collect($all)->pluck('category')->unique()->values();

        $this->assertGreaterThanOrEqual(6, $categories->count());
        foreach ($categories as $category) {
            $this->assertContains($category, $valid);
        }

        foreach ($all as $fixture) {
            $this->assertNotSame('', $fixture['resume_text']);
            $this->assertNotSame('', $fixture['target_job_title']);
        }
    }
}
