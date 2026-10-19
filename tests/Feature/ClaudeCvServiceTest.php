<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Exceptions\AiRefusalException;
use App\Exceptions\AiTruncationException;
use App\Models\AiCallLog;
use App\Services\Ai\AiCallContext;
use App\Services\Ai\BakeOff\ArabicCvCorpus;
use App\Services\Ai\Prompts\JobMatchSystemPrompt;
use App\Services\Ai\Schemas\JobMatchAdviceSchema;
use App\Services\Ai\Schemas\OperationSchemas;
use App\Services\AtsScoringService;
use App\Services\ClaudeCvService;
use App\Services\OpenAiCvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

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

    public function test_job_match_caches_only_the_static_system_block_and_preserves_model_rules(): void
    {
        config(['services.anthropic.model' => 'claude-haiku-5-5']);
        Http::preventStrayRequests();
        Http::fake(['https://api.anthropic.com/v1/messages' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => json_encode([
                'executive_summary' => '', 'strengths' => [], 'gaps' => [], 'suggestions' => [],
            ])]],
        ])]);

        foreach (['ar', 'en'] as $language) {
            foreach (range(1, 2) as $iteration) {
                $snapshot = ['summary' => [$language => fake()->sentence()]];
                $jd = fake()->paragraph();
                app(ClaudeCvService::class)->jobMatchAdvice($snapshot, $jd, $language);
                Http::assertSent(function ($request) use ($snapshot, $jd, $language) {
                    $data = $request->data();
                    if (($data['messages'][0]['content'] ?? null) !== JobMatchSystemPrompt::buildUserPrompt($snapshot, $jd, $language)) {
                        return false;
                    }
                    $this->assertSame('claude-haiku-5-5', $data['model']);
                    $this->assertArrayNotHasKey('temperature', $data);
                    $this->assertArrayNotHasKey('cache_control', $data);
                    $this->assertSame([[
                        'type' => 'text',
                        'text' => JobMatchSystemPrompt::for($language),
                        'cache_control' => ['type' => 'ephemeral'],
                    ]], $data['system']);
                    $this->assertSame([[
                        'role' => 'user',
                        'content' => JobMatchSystemPrompt::buildUserPrompt($snapshot, $jd, $language),
                    ]], $data['messages']);
                    $this->assertSame(OperationSchemas::maxTokens(JobMatchAdviceSchema::NAME), $data['max_tokens']);
                    $this->assertSame(OperationSchemas::anthropicOutputConfig(JobMatchAdviceSchema::NAME), $data['output_config']);

                    return true;
                });
            }
        }
        Http::assertSentCount(4);
    }

    public static function jobMatchUsageCases(): array
    {
        return [
            'cache write' => ['cache_creation_input_tokens'],
            'cache read' => ['cache_read_input_tokens'],
            'uncached' => [null],
        ];
    }

    #[DataProvider('jobMatchUsageCases')]
    public function test_job_match_usage_logs_allowlisted_counts_for_cache_writes_reads_and_uncached_responses(?string $cacheField): void
    {
        Log::spy();
        $usage = ['input_tokens' => random_int(20, 500), 'output_tokens' => random_int(1, 300)];
        if ($cacheField !== null) {
            $usage[$cacheField] = random_int(1024, 4000);
        }
        Http::fake(['https://api.anthropic.com/v1/messages' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => '{"executive_summary":"","strengths":[],"gaps":[],"suggestions":[]}']],
            'usage' => [...$usage, 'untrusted_field' => fake()->email()],
        ])]);
        app(ClaudeCvService::class)->jobMatchAdvice(['summary' => ['en' => fake()->sentence()]], fake()->paragraph(), 'en');

        Log::shouldHaveReceived('info')->with('Claude Job Match usage', \Mockery::on(function ($context) use ($usage) {
            if ($context['input_tokens'] !== $usage['input_tokens'] || $context['output_tokens'] !== $usage['output_tokens']) {
                return false;
            }
            $duration = $context['duration_ms'];
            $this->assertIsInt($duration);
            $this->assertGreaterThanOrEqual(0, $duration);
            $this->assertSame([
                'provider' => 'anthropic',
                'model' => config('services.anthropic.model'),
                'operation' => JobMatchAdviceSchema::NAME,
                'duration_ms' => $duration,
                'input_tokens' => $usage['input_tokens'],
                'output_tokens' => $usage['output_tokens'],
                'cache_creation_input_tokens' => $usage['cache_creation_input_tokens'] ?? 0,
                'cache_read_input_tokens' => $usage['cache_read_input_tokens'] ?? 0,
            ], $context);

            return true;
        }))->once();
        $log = AiCallLog::query()->latest('id')->firstOrFail();
        $this->assertSame($usage['input_tokens'], $log->input_tokens);
        $this->assertSame($usage['output_tokens'], $log->output_tokens);
        $this->assertSame($usage['cache_read_input_tokens'] ?? 0, $log->cached_tokens);
        $this->assertSame(['anthropic', config('services.anthropic.model')], AiCallContext::pull());
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

        $this->expectException(AiTruncationException::class);
        $this->expectExceptionMessage(AiTruncationException::CODE);

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

        $valid = array_keys(AtsScoringService::jobKeywords());
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
