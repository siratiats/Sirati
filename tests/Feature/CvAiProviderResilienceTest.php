<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Exceptions\AiTruncationException;
use App\Services\Ai\AiCallContext;
use App\Services\Ai\CachedCvAiProvider;
use App\Services\ClaudeCvService;
use App\Services\DeepInfraCvService;
use App\Services\OpenAiCvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CvAiProviderResilienceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: class-string<CvAiProvider>}>
     */
    public static function providerDrivers(): array
    {
        return [
            OpenAiCvService::class => [OpenAiCvService::class],
            ClaudeCvService::class => [ClaudeCvService::class],
            DeepInfraCvService::class => [DeepInfraCvService::class],
        ];
    }

    public function test_every_concrete_provider_is_covered_by_the_retry_and_truncation_invariants(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(self::providerDrivers()),
            $this->concreteProviders(),
            'A new CvAiProvider driver must be added to providerDrivers() so retry, truncation, and identity coverage cannot skip it.',
        );
    }

    /**
     * @param  class-string<CvAiProvider>  $class
     */
    #[DataProvider('providerDrivers')]
    public function test_provider_records_serving_identity_on_success(string $class): void
    {
        AiCallContext::clear();
        $spec = $this->driverSpec($class);
        $this->configureDriver($class);

        Http::fake([
            $spec['url'] => Http::response($spec['success'], 200),
        ]);

        app($class)->generateCv(['full_name' => 'Retry User', 'language' => 'en']);

        [$provider, $model] = AiCallContext::pull();

        $this->assertSame($this->expectedProviderName($class), $provider);
        $this->assertNotSame('', (string) $model);
    }

    /**
     * @param  class-string<CvAiProvider>  $class
     */
    #[DataProvider('providerDrivers')]
    public function test_provider_retries_transient_http_failures_then_succeeds(string $class): void
    {
        Sleep::fake();
        $spec = $this->driverSpec($class);
        $this->configureDriver($class);

        Http::fake([
            $spec['url'] => Http::sequence()
                ->push(['error' => 'rate limited'], 429, ['Retry-After' => '0'])
                ->push($spec['success'], 200),
        ]);

        $result = app($class)->generateCv(['full_name' => 'Retry User', 'language' => 'en']);

        $this->assertSame('# Retry User', $result['cv_markdown']);
        Http::assertSentCount(2);
    }

    /**
     * @param  class-string<CvAiProvider>  $class
     */
    #[DataProvider('providerDrivers')]
    public function test_provider_retries_connection_errors_then_succeeds(string $class): void
    {
        Sleep::fake();
        $spec = $this->driverSpec($class);
        $this->configureDriver($class);
        $attempts = 0;

        Http::fake(function () use (&$attempts, $spec) {
            $attempts++;
            if ($attempts === 1) {
                throw new ConnectionException('connection reset');
            }

            return Http::response($spec['success'], 200);
        });

        $result = app($class)->generateCv(['full_name' => 'Retry User', 'language' => 'en']);

        $this->assertSame('# Retry User', $result['cv_markdown']);
        $this->assertSame(2, $attempts);
    }

    /**
     * @param  class-string<CvAiProvider>  $class
     */
    #[DataProvider('providerDrivers')]
    public function test_provider_does_not_retry_client_errors(string $class): void
    {
        Sleep::fake();
        $spec = $this->driverSpec($class);
        $this->configureDriver($class);

        Http::fake([
            $spec['url'] => Http::response(['error' => 'bad request'], 400),
        ]);

        try {
            app($class)->generateCv(['full_name' => 'Retry User', 'language' => 'en']);
            $this->fail("{$class} should throw on HTTP 400.");
        } catch (RequestException $exception) {
            $this->assertSame(400, $exception->response->status());
        }

        Http::assertSentCount(1);
    }

    /**
     * @param  class-string<CvAiProvider>  $class
     */
    #[DataProvider('providerDrivers')]
    public function test_provider_treats_truncation_as_non_retryable(string $class): void
    {
        Sleep::fake();
        $spec = $this->driverSpec($class);
        $this->configureDriver($class);

        Http::fake([
            $spec['url'] => Http::response($spec['truncated'], 200),
        ]);

        try {
            app($class)->generateCv(['full_name' => 'Long User', 'language' => 'en']);
            $this->fail("{$class} should throw AiTruncationException.");
        } catch (AiTruncationException $exception) {
            $this->assertStringStartsWith(AiTruncationException::CODE, $exception->getMessage());
            $this->assertStringContainsString('too long', $exception->getMessage());
            $this->assertStringContainsString('أطول', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    /**
     * @return list<class-string<CvAiProvider>>
     */
    private function concreteProviders(): array
    {
        $found = [];

        foreach ([app_path('Services'), app_path('Services/Ai')] as $dir) {
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
                $relative = substr($file, strlen(app_path()) + 1);
                $class = 'App\\'.str_replace(['/', '\\'], '\\', substr($relative, 0, -4));
                if (! class_exists($class) || ! is_subclass_of($class, CvAiProvider::class)) {
                    continue;
                }
                if ($class === CachedCvAiProvider::class) {
                    continue;
                }
                $found[] = $class;
            }
        }

        sort($found);

        return $found;
    }

    /**
     * @param  class-string<CvAiProvider>  $class
     */
    private function expectedProviderName(string $class): string
    {
        return match ($class) {
            OpenAiCvService::class => 'openai',
            ClaudeCvService::class => 'anthropic',
            DeepInfraCvService::class => 'deepinfra',
            default => $this->fail("No identity fixture for {$class}."),
        };
    }

    /**
     * @param  class-string<CvAiProvider>  $class
     */
    private function configureDriver(string $class): void
    {
        config([
            'services.cv_ai.response_cache_enabled' => false,
            'services.deepinfra.api_key' => '',
            'services.openai.api_key' => '',
            'services.anthropic.api_key' => '',
        ]);

        match ($class) {
            OpenAiCvService::class => config([
                'services.openai.api_key' => 'test-openai-key',
                'services.openai.model' => 'gpt-4.1-mini',
                'services.openai.base_url' => 'https://api.openai.com/v1',
            ]),
            ClaudeCvService::class => config([
                'services.anthropic.api_key' => 'test-anthropic-key',
                'services.anthropic.model' => 'claude-haiku-4-5',
                'services.anthropic.base_url' => 'https://api.anthropic.com/v1',
            ]),
            DeepInfraCvService::class => config([
                'services.deepinfra.api_key' => 'test-deepinfra-key',
                'services.deepinfra.model' => 'Qwen/Qwen2.5-72B-Instruct',
                'services.deepinfra.model_en' => 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo',
                'services.deepinfra.base_url' => 'https://api.deepinfra.com/v1/openai',
            ]),
            default => $this->fail("No configuration fixture for {$class}."),
        };
    }

    /**
     * @param  class-string<CvAiProvider>  $class
     * @return array{url: string, success: array<string, mixed>, truncated: array<string, mixed>}
     */
    private function driverSpec(string $class): array
    {
        $body = [
            'cv_markdown' => '# Retry User',
            'headline' => 'Engineer',
            'ats_notes' => [],
            'missing_information' => [],
        ];
        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return match ($class) {
            OpenAiCvService::class, DeepInfraCvService::class => [
                'url' => $class === OpenAiCvService::class
                    ? 'https://api.openai.com/v1/chat/completions'
                    : 'https://api.deepinfra.com/v1/openai/chat/completions',
                'success' => [
                    'choices' => [[
                        'finish_reason' => 'stop',
                        'message' => ['content' => $encoded],
                    ]],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
                ],
                'truncated' => [
                    'choices' => [[
                        'finish_reason' => 'length',
                        'message' => ['content' => '{"cv_markdown":"partial'],
                    ]],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 4096],
                ],
            ],
            ClaudeCvService::class => [
                'url' => 'https://api.anthropic.com/v1/messages',
                'success' => [
                    'stop_reason' => 'end_turn',
                    'content' => [['type' => 'text', 'text' => $encoded]],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ],
                'truncated' => [
                    'stop_reason' => 'max_tokens',
                    'content' => [['type' => 'text', 'text' => '{"cv_markdown":"partial']],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 4096],
                ],
            ],
            default => $this->fail("No HTTP fixture for {$class}."),
        };
    }
}
