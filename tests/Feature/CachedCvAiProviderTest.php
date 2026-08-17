<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Models\AiCallLog;
use App\Services\Ai\CachedCvAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CachedCvAiProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.model' => 'gpt-4.1-mini',
            'services.cv_ai.response_cache_enabled' => true,
            'cache.default' => 'array',
        ]);

        Cache::flush();
    }

    public function test_second_identical_call_does_not_reach_underlying_provider(): void
    {
        $payload = $this->sampleEnhanceResult();

        /** @var CvAiProvider&MockInterface $inner */
        $inner = Mockery::mock(CvAiProvider::class);
        $inner->shouldReceive('isConfigured')->andReturn(true);
        $inner->shouldReceive('enhanceJobDescription')
            ->once()
            ->with('Laravel Developer', 'Build REST APIs', 'en')
            ->andReturn($payload);

        $cached = new CachedCvAiProvider($inner);

        $this->assertSame($payload, $cached->enhanceJobDescription('Laravel Developer', 'Build REST APIs', 'en'));
        $this->assertSame($payload, $cached->enhanceJobDescription('Laravel Developer', 'Build REST APIs', 'en'));

        $this->assertSame(1, AiCallLog::query()->where('was_response_cache_hit', true)->count());
        $this->assertTrue(AiCallLog::query()->where('was_response_cache_hit', true)->value('input_tokens') === 0);
        $this->assertSame(0, (int) AiCallLog::query()->where('was_response_cache_hit', true)->value('output_tokens'));
    }

    public function test_whitespace_and_case_only_differences_produce_same_key(): void
    {
        $provider = new CachedCvAiProvider(Mockery::mock(CvAiProvider::class));

        $base = $provider->normalizePayload([
            'job_title' => 'مهندس Laravel',
            'job_description' => 'خبرة في بناء APIs',
            'language' => 'ar',
        ]);
        $whitespace = $provider->normalizePayload([
            'job_title' => "مهندس   Laravel",
            'job_description' => "خبرة   في\tبناء  APIs",
            'language' => 'ar',
        ]);
        $case = $provider->normalizePayload([
            'job_title' => 'مهندس laravel',
            'job_description' => 'خبرة في بناء apis',
            'language' => 'ar',
        ]);

        $this->assertSame($base, $whitespace);
        $this->assertSame($base, $case);

        $this->assertSame(
            $provider->cacheKey('enhance_job_description', $base),
            $provider->cacheKey('enhance_job_description', $whitespace),
        );
        $this->assertSame(
            $provider->cacheKey('enhance_job_description', $base),
            $provider->cacheKey('enhance_job_description', $case),
        );
    }

    public function test_arabic_indic_and_ascii_digits_produce_same_key(): void
    {
        $provider = new CachedCvAiProvider(Mockery::mock(CvAiProvider::class));

        $ascii = $provider->normalizeText('خبرة 5 سنوات و 12 مشروع');
        $arabicIndic = $provider->normalizeText('خبرة ٥ سنوات و ١٢ مشروع');

        $this->assertSame($ascii, $arabicIndic);
        $this->assertSame(
            $provider->cacheKey('analysis_advice', $ascii),
            $provider->cacheKey('analysis_advice', $arabicIndic),
        );
    }

    public function test_diacritic_differences_produce_different_keys(): void
    {
        $provider = new CachedCvAiProvider(Mockery::mock(CvAiProvider::class));

        $plain = $provider->normalizeText('مهندس برمجيات');
        $withDiacritics = $provider->normalizeText('مُهَنْدِس بَرْمَجِيَّات');

        $this->assertNotSame($plain, $withDiacritics);
        $this->assertNotSame(
            $provider->cacheKey('generate_cv', $plain),
            $provider->cacheKey('generate_cv', $withDiacritics),
        );
    }

    public function test_bumping_prompt_version_produces_different_key(): void
    {
        $provider = new CachedCvAiProvider(Mockery::mock(CvAiProvider::class));
        $input = $provider->normalizeText('same input');

        $this->assertNotSame(
            $provider->cacheKey('analysis_advice', $input, promptVersion: '1'),
            $provider->cacheKey('analysis_advice', $input, promptVersion: '2'),
        );
    }

    public function test_different_model_in_config_produces_different_key(): void
    {
        $provider = new CachedCvAiProvider(Mockery::mock(CvAiProvider::class));
        $input = $provider->normalizeText('same input');

        $this->assertNotSame(
            $provider->cacheKey('generate_cv', $input, model: 'gpt-4.1-mini'),
            $provider->cacheKey('generate_cv', $input, model: 'gpt-4.1'),
        );
    }

    public function test_model_default_follows_configured_provider_not_openai(): void
    {
        $input = 'same input';

        config([
            'services.cv_ai.provider' => 'openai',
            'services.openai.model' => 'gpt-4.1-mini',
        ]);
        $openAiKey = (new CachedCvAiProvider(Mockery::mock(CvAiProvider::class)))
            ->cacheKey('analysis_advice', $input);

        config([
            'services.cv_ai.provider' => 'claude',
            'services.anthropic.model' => 'claude-haiku-4-5',
        ]);
        $claudeKey = (new CachedCvAiProvider(Mockery::mock(CvAiProvider::class)))
            ->cacheKey('analysis_advice', $input);

        // Regression guard: the default previously hardcoded the OpenAI model,
        // so a Claude run reused OpenAI's cached responses.
        $this->assertNotSame(
            $openAiKey,
            $claudeKey,
            'Cache key must change when CV_AI_PROVIDER switches vendors.',
        );
    }

    public function test_claude_run_does_not_read_openai_cached_response(): void
    {
        $openAiPayload = $this->sampleEnhanceResult();
        $claudePayload = $this->sampleEnhanceResult();
        $claudePayload['enhanced_description'] = 'Claude-generated description.';

        config([
            'services.cv_ai.provider' => 'openai',
            'services.openai.model' => 'gpt-4.1-mini',
        ]);

        /** @var CvAiProvider&MockInterface $openAiInner */
        $openAiInner = Mockery::mock(CvAiProvider::class);
        $openAiInner->shouldReceive('enhanceJobDescription')->once()->andReturn($openAiPayload);
        $openAi = new CachedCvAiProvider($openAiInner);
        $this->assertSame($openAiPayload, $openAi->enhanceJobDescription('Laravel Developer', 'Build APIs', 'en'));

        config([
            'services.cv_ai.provider' => 'claude',
            'services.anthropic.model' => 'claude-haiku-4-5',
        ]);

        // Must reach the Claude driver rather than serving the OpenAI entry.
        /** @var CvAiProvider&MockInterface $claudeInner */
        $claudeInner = Mockery::mock(CvAiProvider::class);
        $claudeInner->shouldReceive('enhanceJobDescription')->once()->andReturn($claudePayload);
        $claude = new CachedCvAiProvider($claudeInner);

        $this->assertSame($claudePayload, $claude->enhanceJobDescription('Laravel Developer', 'Build APIs', 'en'));
    }

    public function test_cache_hit_log_attributes_the_active_provider(): void
    {
        config([
            'services.cv_ai.provider' => 'claude',
            'services.anthropic.model' => 'claude-haiku-4-5',
        ]);

        $payload = $this->sampleEnhanceResult();

        /** @var CvAiProvider&MockInterface $inner */
        $inner = Mockery::mock(CvAiProvider::class);
        $inner->shouldReceive('enhanceJobDescription')->once()->andReturn($payload);

        $cached = new CachedCvAiProvider($inner);
        $cached->enhanceJobDescription('Laravel Developer', 'Build APIs', 'en');
        $cached->enhanceJobDescription('Laravel Developer', 'Build APIs', 'en');

        $log = AiCallLog::query()->where('was_response_cache_hit', true)->firstOrFail();

        $this->assertSame('anthropic', $log->provider);
        $this->assertSame('claude-haiku-4-5', $log->model);
    }

    public function test_cache_miss_falls_through_and_stores_result(): void
    {
        $payload = $this->sampleGenerateResult();

        /** @var CvAiProvider&MockInterface $inner */
        $inner = Mockery::mock(CvAiProvider::class);
        $inner->shouldReceive('generateCv')
            ->once()
            ->andReturn($payload);

        $cached = new CachedCvAiProvider($inner);
        $data = [
            'full_name' => 'Salem Sayer',
            'target_job_title' => 'Laravel Developer',
        ];

        $result = $cached->generateCv($data);

        $this->assertSame($payload, $result);

        $key = $cached->cacheKey('generate_cv', $cached->normalizePayload($data));
        $this->assertSame($payload, Cache::get($key));
        $this->assertSame(0, AiCallLog::query()->where('was_response_cache_hit', true)->count());
    }

    public function test_large_payload_round_trips_through_database_cache_intact(): void
    {
        config(['cache.default' => 'database']);

        // mediumText (16MB) is already large enough for CV payloads; assert the column exists.
        $this->assertTrue(Schema::hasTable('cache'));
        $column = collect(Schema::getColumns('cache'))->firstWhere('name', 'value');
        $this->assertNotNull($column);

        $markdown = str_repeat("# Salem Sayer — Laravel Backend Developer\n\n".str_repeat('Experience bullet with metrics. ', 20)."\n", 40);
        $this->assertGreaterThan(20_000, strlen($markdown));

        $payload = [
            'cv_markdown' => $markdown,
            'headline' => 'Laravel Backend Developer',
            'professional_summary' => 'Backend developer focused on APIs.',
            'core_skills' => ['Laravel', 'PHP', 'SQL', 'API'],
            'improved_experience_bullets' => array_fill(0, 20, 'Delivered measurable backend improvements.'),
            'ats_notes' => ['Include keywords', 'Keep bullets quantified'],
            'missing_information' => [],
        ];

        /** @var CvAiProvider&MockInterface $inner */
        $inner = Mockery::mock(CvAiProvider::class);
        $inner->shouldReceive('generateCv')
            ->once()
            ->andReturn($payload);

        $cached = new CachedCvAiProvider($inner);
        $input = [
            'full_name' => 'Salem Sayer',
            'target_job_title' => 'Laravel Backend Developer',
            'experience_input' => str_repeat('Built Laravel APIs with measurable outcomes. ', 50),
        ];

        $first = $cached->generateCv($input);
        $second = $cached->generateCv($input);

        $this->assertSame($payload, $first);
        $this->assertSame($payload, $second);
        $this->assertSame($markdown, $second['cv_markdown']);
        $this->assertGreaterThan(20_000, strlen($second['cv_markdown']));

        $key = $cached->cacheKey('generate_cv', $cached->normalizePayload($input));
        $prefix = (string) config('cache.prefix', '');
        $stored = DB::table('cache')->where('key', $prefix.$key)->orWhere('key', $key)->exists();
        $this->assertTrue($stored, 'Expected serialized payload to persist in the cache table.');
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleEnhanceResult(): array
    {
        return [
            'enhanced_description' => 'Backend engineer building APIs.',
            'suggested_keywords' => ['Laravel', 'API'],
            'responsibilities' => ['Build APIs'],
            'requirements' => ['PHP'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleGenerateResult(): array
    {
        return [
            'cv_markdown' => '# Test CV',
            'headline' => 'Developer',
            'professional_summary' => 'Summary',
            'core_skills' => ['Laravel'],
            'improved_experience_bullets' => ['Built APIs'],
            'ats_notes' => ['Keep keywords'],
            'missing_information' => [],
        ];
    }
}
