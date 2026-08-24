<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Models\User;
use App\Services\Ai\CachedCvAiProvider;
use App\Services\Ai\EnhanceCvFieldResultGuard;
use App\Services\OpenAiCvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class EnhanceCvFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_endpoint_returns_schema_shape_for_each_field_type(): void
    {
        $provider = Mockery::mock(CvAiProvider::class);
        $provider->shouldReceive('isConfigured')->times(5)->andReturn(true);
        $provider->shouldReceive('enhanceCvField')->times(5)->andReturn([
            'enhanced_text' => 'Improved grounded draft.',
            'changes_made' => ['Improved clarity'],
            'missing_facts' => ['Add a measurable outcome'],
            'ats_keywords_added' => ['API'],
            'unverified_claims' => [],
        ]);
        $this->app->instance(CvAiProvider::class, $provider);
        $user = User::factory()->create();

        foreach (['summary', 'skills', 'experience', 'education', 'certifications'] as $field) {
            $response = $this->actingAs($user)->postJson('/api/generated-cvs/enhance-field', [
                'field' => $field,
                'draft' => 'Built and maintained internal APIs.',
                'job_title' => 'Backend Developer',
                'language' => 'en',
            ]);

            $response->assertOk()->assertJsonStructure([
                'data' => ['enhanced_text', 'changes_made', 'missing_facts', 'ats_keywords_added', 'unverified_claims'],
            ]);
        }
    }

    public function test_endpoint_returns_fallback_response_when_ai_provider_throws_exception(): void
    {
        $provider = Mockery::mock(CvAiProvider::class);
        $provider->shouldReceive('isConfigured')->once()->andReturn(true);
        $provider->shouldReceive('enhanceCvField')->once()->andThrow(new \RuntimeException('Connection timed out.'));
        $this->app->instance(CvAiProvider::class, $provider);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/generated-cvs/enhance-field', [
            'field' => 'summary',
            'draft' => 'Senior software engineer with 8 years experience.',
            'job_title' => 'Senior Developer',
            'language' => 'en',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.enhanced_text', 'Senior software engineer with 8 years experience.')
            ->assertJsonStructure([
                'data' => ['enhanced_text', 'changes_made', 'missing_facts', 'ats_keywords_added', 'unverified_claims'],
            ]);
    }
    public function test_validation_rejects_unknown_fields_and_short_drafts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/generated-cvs/enhance-field', [
            'field' => 'projects',
            'draft' => 'short',
            'job_title' => 'Developer',
            'language' => 'en',
        ])->assertUnprocessable()->assertJsonValidationErrors(['field', 'draft']);
    }

    public function test_unauthenticated_and_unverified_users_are_rejected(): void
    {
        $payload = [
            'field' => 'skills',
            'draft' => 'Laravel, PHP, SQL',
            'job_title' => 'Backend Developer',
            'language' => 'en',
        ];

        $this->postJson('/api/generated-cvs/enhance-field', $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())
            ->postJson('/api/generated-cvs/enhance-field', $payload)
            ->assertForbidden();
    }

    public function test_endpoint_is_throttled_after_twenty_requests_per_minute(): void
    {
        $provider = Mockery::mock(CvAiProvider::class);
        $provider->shouldReceive('isConfigured')->times(20)->andReturn(false);
        $this->app->instance(CvAiProvider::class, $provider);
        $user = User::factory()->create();
        $payload = [
            'field' => 'skills',
            'draft' => 'Laravel, PHP, SQL',
            'job_title' => 'Backend Developer',
            'language' => 'en',
        ];

        for ($index = 0; $index < 20; $index++) {
            $this->actingAs($user)->postJson('/api/generated-cvs/enhance-field', $payload)->assertOk();
        }

        $this->actingAs($user)->postJson('/api/generated-cvs/enhance-field', $payload)
            ->assertTooManyRequests();
    }

    public function test_fabrication_guard_reports_unsupported_employer_and_date_without_altering_text(): void
    {
        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.test/v1',
            'services.openai.model' => 'gpt-4.1-mini',
        ]);
        Http::fake([
            'api.openai.test/*' => Http::response([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => [
                        'content' => json_encode([
                            'enhanced_text' => 'Built internal APIs at Acme Corp in 2024.',
                            'changes_made' => ['Made the bullet stronger'],
                            'missing_facts' => [],
                            'ats_keywords_added' => ['API'],
                            'unverified_claims' => [],
                        ]),
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $result = app(OpenAiCvService::class)->enhanceCvField(
            'experience',
            'Built internal APIs for several teams.',
            'Backend Developer',
            'en',
        );

        $this->assertSame('Built internal APIs at Acme Corp in 2024.', $result['enhanced_text']);
        $this->assertStringContainsString(
            'Acme Corp',
            implode(' ', $result['missing_facts']),
        );
        $this->assertStringContainsString(
            '2024',
            implode(' ', $result['missing_facts']),
        );
        $this->assertSame([
            ['text' => '2024', 'kind' => 'date'],
            ['text' => 'Acme Corp', 'kind' => 'employer'],
        ], $result['unverified_claims']);
    }

    public function test_fabrication_guard_does_not_treat_lowercase_for_phrase_as_employer(): void
    {
        $draft = 'Managed delivery for the retail team';
        $enhanced = 'Managed delivery for the retail team across 3 regions';

        $result = $this->guard($enhanced, $draft);

        $this->assertSame($enhanced, $result['enhanced_text']);
        $this->assertSame([], $result['unverified_claims']);
        $this->assertSame([], $result['missing_facts']);
    }

    public function test_fabrication_guard_normalizes_arabic_indic_digits_for_dates(): void
    {
        $draft = 'عملت خلال ٢٠٢٤ على تطوير الأنظمة.';
        $enhanced = 'Worked in 2024 on systems.';

        $result = $this->guard($enhanced, $draft);

        $this->assertSame($enhanced, $result['enhanced_text']);
        $this->assertSame([], $result['unverified_claims']);
    }

    public function test_fabrication_guard_reports_exact_proper_noun_employer_span(): void
    {
        $draft = 'Built internal APIs.';
        $enhanced = 'Built APIs at Acme Corp';

        $result = $this->guard($enhanced, $draft);

        $this->assertSame($enhanced, $result['enhanced_text']);
        $this->assertSame([
            ['text' => 'Acme Corp', 'kind' => 'employer'],
        ], $result['unverified_claims']);
        $this->assertStringContainsString('Acme Corp', implode(' ', $result['missing_facts']));
    }

    public function test_fabrication_guard_reports_exact_arabic_employer_span_without_altering_text(): void
    {
        $draft = 'طورت واجهات داخلية.';
        $enhanced = 'طورت واجهات في شركة أكمي.';

        $result = $this->guard($enhanced, $draft, 'ar');

        $this->assertSame($enhanced, $result['enhanced_text']);
        $this->assertSame([
            ['text' => 'أكمي', 'kind' => 'employer'],
        ], $result['unverified_claims']);
        $this->assertStringContainsString('أكمي', implode(' ', $result['missing_facts']));
    }

    public function test_fabrication_guard_preserves_model_whitespace_byte_for_byte(): void
    {
        $enhanced = "  Built APIs at Acme Corp in 2024.\r\n";

        $result = $this->guard($enhanced, 'Built internal APIs.');

        $this->assertSame($enhanced, $result['enhanced_text']);
    }

    public function test_response_cache_key_includes_field_and_language(): void
    {
        $inner = Mockery::mock(CvAiProvider::class);
        $inner->shouldReceive('enhanceCvField')->times(3)->andReturn([
            'enhanced_text' => 'Improved',
            'changes_made' => [],
            'missing_facts' => [],
            'ats_keywords_added' => [],
            'unverified_claims' => [],
        ]);
        $cached = new CachedCvAiProvider($inner);

        $cached->enhanceCvField('skills', 'Laravel and PHP', 'Developer', 'ar');
        $cached->enhanceCvField('skills', 'Laravel and PHP', 'Developer', 'ar');
        $cached->enhanceCvField('experience', 'Laravel and PHP', 'Developer', 'ar');
        $cached->enhanceCvField('skills', 'Laravel and PHP', 'Developer', 'en');

        $this->addToAssertionCount(1);
    }

    /** @return array<string, mixed> */
    private function guard(string $enhanced, string $draft, string $language = 'en'): array
    {
        return (new EnhanceCvFieldResultGuard)->enforce([
            'enhanced_text' => $enhanced,
            'changes_made' => [],
            'missing_facts' => [],
            'ats_keywords_added' => [],
            'unverified_claims' => [],
        ], $draft, $language);
    }
}
