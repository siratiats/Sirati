<?php

namespace Tests\Feature;

use App\Models\AiCallLog;
use App\Services\OpenAiCvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AiCallLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_openai_call_writes_ai_call_log_row(): void
    {
        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'gpt-4.1-mini',
            'services.openai.base_url' => 'https://api.openai.com/v1',
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'enhanced_description' => 'Backend engineer building APIs.',
                                'suggested_keywords' => ['Laravel', 'API'],
                                'responsibilities' => ['Build APIs'],
                                'requirements' => ['PHP'],
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 45,
                    'prompt_tokens_details' => [
                        'cached_tokens' => 12,
                    ],
                ],
            ], 200),
        ]);

        $result = app(OpenAiCvService::class)->enhanceJobDescription(
            'Laravel Developer',
            'Build REST APIs',
            'en',
        );

        $this->assertSame('Backend engineer building APIs.', $result['enhanced_description']);

        $log = AiCallLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame('openai', $log->provider);
        $this->assertSame('gpt-4.1-mini', $log->model);
        $this->assertSame('enhance_job_description', $log->operation);
        $this->assertSame(120, $log->input_tokens);
        $this->assertSame(45, $log->output_tokens);
        $this->assertSame(12, $log->cached_tokens);
        $this->assertGreaterThanOrEqual(0, $log->duration_ms);
        $this->assertFalse($log->was_response_cache_hit);
        $this->assertNull($log->user_id);
        $this->assertNotNull($log->created_at);
    }

    public function test_logging_failure_does_not_propagate_to_caller(): void
    {
        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'gpt-4.1-mini',
            'services.openai.base_url' => 'https://api.openai.com/v1',
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'cv_markdown' => '# Test CV',
                                'headline' => 'Developer',
                                'professional_summary' => 'Summary',
                                'core_skills' => ['Laravel'],
                                'improved_experience_bullets' => ['Built APIs'],
                                'ats_notes' => ['Keep keywords'],
                                'missing_information' => [],
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 80,
                    'completion_tokens' => 30,
                ],
            ], 200),
        ]);

        AiCallLog::creating(function (): void {
            throw new RuntimeException('forced logging failure');
        });

        $result = app(OpenAiCvService::class)->generateCv([
            'full_name' => 'Test User',
            'target_job_title' => 'Developer',
        ]);

        $this->assertSame('# Test CV', $result['cv_markdown']);
        $this->assertSame(0, AiCallLog::query()->count());
    }
}
