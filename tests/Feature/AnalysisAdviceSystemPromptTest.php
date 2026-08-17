<?php

namespace Tests\Feature;

use App\Services\Ai\Prompts\AnalysisAdviceSystemPrompt;
use App\Services\AtsScoringService;
use App\Services\OpenAiCvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalysisAdviceSystemPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'gpt-4.1-mini',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.cv_ai.response_cache_enabled' => false,
        ]);
    }

    public function test_system_prompt_is_byte_identical_across_different_cv_inputs(): void
    {
        $body = [
            'executive_summary' => 'ملخص',
            'top_priorities' => ['أولوية'],
            'rewritten_summary' => null,
            'keyword_recommendations' => ['Laravel'],
            'bullet_improvements' => [],
            'warnings' => [],
        ];

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'message' => [
                            'content' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 2000,
                    'completion_tokens' => 100,
                    'prompt_tokens_details' => ['cached_tokens' => 1500],
                ],
            ], 200),
        ]);

        $service = app(OpenAiCvService::class);

        $service->analysisAdvice(
            ['total' => 70, 'grade' => 'B', 'criteria' => []],
            'First candidate CV about Laravel APIs and SQL dashboards.',
            'Laravel Backend Developer',
        );

        $service->analysisAdvice(
            ['total' => 55, 'grade' => 'D', 'criteria' => []],
            'Second candidate CV about marketing campaigns and SEO analytics.',
            'Digital Marketing Manager',
        );

        $systemPrompts = [];

        Http::assertSent(function ($request) use (&$systemPrompts): bool {
            $messages = $request->data()['messages'] ?? [];
            foreach ($messages as $message) {
                if (($message['role'] ?? null) === 'system') {
                    $systemPrompts[] = $message['content'] ?? '';
                }
            }

            return true;
        });

        $this->assertCount(2, $systemPrompts);
        $this->assertSame($systemPrompts[0], $systemPrompts[1]);
        $this->assertNotSame('', $systemPrompts[0]);

        // Variable content must live only in the user message.
        $this->assertStringNotContainsString('First candidate CV', $systemPrompts[0]);
        $this->assertStringNotContainsString('Digital Marketing Manager', $systemPrompts[0]);
        $this->assertStringNotContainsString('Laravel Backend Developer', $systemPrompts[0]);
    }

    public function test_static_prefix_exceeds_1024_tokens_with_real_tokenizer(): void
    {
        $prompt = new AnalysisAdviceSystemPrompt;
        $tokenCount = $prompt->tokenCount('gpt-4.1-mini');

        $this->assertGreaterThan(
            1024,
            $tokenCount,
            "Static prefix must exceed 1,024 tokens for OpenAI automatic caching; got {$tokenCount}.",
        );

        // Only the 1,024 floor is a hard requirement for OpenAI automatic caching.
        // The band below is a sanity guard against the prefix collapsing or
        // ballooning; 1,400-1,800 was an estimate in the brief, and Arabic
        // few-shots tokenize heavily, so the ceiling allows real-world headroom.
        // Do NOT shrink prompt content to satisfy this — widen it deliberately.
        $this->assertGreaterThanOrEqual(1400, $tokenCount);
        $this->assertLessThanOrEqual(2400, $tokenCount);
    }

    public function test_rubric_values_in_prompt_match_ats_scoring_service_constants(): void
    {
        $prompt = (new AnalysisAdviceSystemPrompt)->build();

        $this->assertStringStartsWith(AnalysisAdviceSystemPrompt::ROLE_INSTRUCTION, $prompt);

        foreach (AtsScoringService::criteriaMeta() as $key => $meta) {
            $this->assertStringContainsString($key, $prompt);
            $this->assertStringContainsString((string) $meta['max'], $prompt);
            $this->assertStringContainsString($meta['label'], $prompt);
        }

        foreach (AtsScoringService::jobKeywords() as $category => $keywords) {
            $this->assertStringContainsString($category, $prompt);
            foreach ($keywords as $keyword) {
                $this->assertStringContainsString($keyword, $prompt);
            }
        }

        // Explicit max-score guards from the product rubric.
        $this->assertStringContainsString('max 15', $prompt); // format + structure
        $this->assertStringContainsString('max 30', $prompt); // keywords
        $this->assertStringContainsString('max 20', $prompt); // experience
        $this->assertStringContainsString('max 10', $prompt); // education
        $this->assertStringContainsString('max 5', $prompt);  // summary + contact
    }
}
