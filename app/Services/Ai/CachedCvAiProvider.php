<?php

namespace App\Services\Ai;

use App\Contracts\CvAiProvider;
use App\Models\AiCallLog;
use App\Support\ArabicIndicDigits;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Response-level cache decorator for CV AI operations.
 *
 * Caches full JSON responses so identical inputs skip the remote API.
 *
 * IMPORTANT — PROMPT_VERSION:
 * Bump {@see self::PROMPT_VERSION} whenever prompt text or response schema
 * changes. Cache keys include this constant; failing to bump it after a prompt
 * edit will serve stale AI output until the TTL expires. This is the only
 * mechanism that invalidates cache entries after a prompt change.
 */
class CachedCvAiProvider implements CvAiProvider
{
    /**
     * Bump by hand whenever prompt text or schema changes so stale cache
     * entries are not reused after a prompt edit.
     */
    public const PROMPT_VERSION = '4';

    /** Normalized provider name ('openai' | 'anthropic') the wrapped driver talks to. */
    private readonly string $provider;

    /** Model identifier for the wrapped driver. */
    private readonly string $model;

    /**
     * @param  string|null  $provider  Defaults to the active CV_AI_PROVIDER driver.
     * @param  string|null  $model  Defaults to the model configured for that driver.
     */
    public function __construct(
        private readonly CvAiProvider $inner,
        ?string $provider = null,
        ?string $model = null,
    ) {
        $this->provider = $provider ?? self::activeProvider();
        $this->model = $model ?? self::modelForProvider($this->provider);
    }

    /**
     * Normalize config('services.cv_ai.provider') to a canonical provider name.
     */
    public static function activeProvider(): string
    {
        return match (strtolower((string) config('services.cv_ai.provider', 'openai'))) {
            'claude', 'anthropic' => 'anthropic',
            default => 'openai',
        };
    }

    /**
     * Resolve the configured model for a canonical provider name.
     */
    public static function modelForProvider(string $provider): string
    {
        return $provider === 'anthropic'
            ? (string) config('services.anthropic.model')
            : (string) config('services.openai.model');
    }

    public function isConfigured(): bool
    {
        return $this->inner->isConfigured();
    }

    public function analysisAdvice(array $score, string $resumeText, string $jobTitle): array
    {
        return $this->remember(
            operation: 'analysis_advice',
            payload: [
                'score' => $score,
                'resume_text' => $resumeText,
                'job_title' => $jobTitle,
            ],
            callback: fn (): array => $this->inner->analysisAdvice($score, $resumeText, $jobTitle),
        );
    }

    public function generateCv(array $data): array
    {
        return $this->remember(
            operation: 'generate_cv',
            payload: $data,
            callback: fn (): array => $this->inner->generateCv($data),
        );
    }

    public function enhanceCvField(string $field, string $draft, string $jobTitle, string $language): array
    {
        return $this->remember(
            operation: 'enhance_cv_field',
            payload: [
                'field' => $field,
                'draft' => $draft,
                'job_title' => $jobTitle,
                'language' => $language,
            ],
            callback: fn (): array => $this->inner->enhanceCvField($field, $draft, $jobTitle, $language),
        );
    }

    public function enhanceJobDescription(string $jobTitle, ?string $jobDescription, string $language): array
    {
        return $this->remember(
            operation: 'enhance_job_description',
            payload: [
                'job_title' => $jobTitle,
                'job_description' => $jobDescription,
                'language' => $language,
            ],
            callback: fn (): array => $this->inner->enhanceJobDescription($jobTitle, $jobDescription, $language),
        );
    }

    /**
     * Build a cache key for an operation and already-normalized input string.
     *
     * The key includes BOTH the provider and the model. Without the provider,
     * switching CV_AI_PROVIDER would reuse entries written by the other vendor —
     * which would silently make a Claude-vs-OpenAI bake-off compare OpenAI
     * against its own cached output.
     *
     * @param  string|null  $model  Defaults to the wrapped driver's model
     * @param  string|null  $promptVersion  Defaults to PROMPT_VERSION
     * @param  string|null  $provider  Defaults to the wrapped driver's provider
     */
    public function cacheKey(
        string $operation,
        string $normalizedInput,
        ?string $model = null,
        ?string $promptVersion = null,
        ?string $provider = null,
    ): string {
        $provider ??= $this->provider;
        $model ??= $this->model;
        $promptVersion ??= self::PROMPT_VERSION;

        return 'cv_ai:'.$operation.':'.hash(
            'sha256',
            implode('|', [$normalizedInput, $provider, $model, $promptVersion]),
        );
    }

    /**
     * Normalize free text for cache key material.
     *
     * - Collapses Unicode whitespace runs to a single space, then trims
     * - Uses mb_* / Unicode-aware regex only (never strtolower/trim)
     * - Lowercases with mb_strtolower for case-insensitive Latin collisions
     * - Maps Arabic-Indic digits (٠-٩) to ASCII
     * - Does NOT strip Arabic diacritics
     */
    public function normalizeText(string $text): string
    {
        $text = ArabicIndicDigits::normalize($text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = preg_replace('/^\s+|\s+$/u', '', $text) ?? $text;

        return $text;
    }

    /**
     * Serialize a payload into the normalized input string used in cache keys.
     */
    public function normalizePayload(mixed $payload): string
    {
        return json_encode(
            $this->normalizeValue($payload),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function remember(string $operation, mixed $payload, callable $callback): array
    {
        $normalizedInput = $this->normalizePayload($payload);
        $key = $this->cacheKey($operation, $normalizedInput);
        $ttl = (int) config("services.cv_ai.response_cache_ttl.{$operation}", 60 * 60 * 24);

        $cached = Cache::get($key);
        if (is_array($cached)) {
            $this->logCacheHit($operation);

            return $cached;
        }

        $result = $callback();

        Cache::put($key, $result, $ttl);

        return $result;
    }

    private function logCacheHit(string $operation): void
    {
        try {
            AiCallLog::create([
                'provider' => $this->provider,
                'model' => $this->model,
                'operation' => $operation,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cached_tokens' => 0,
                'duration_ms' => 0,
                'was_response_cache_hit' => true,
                'user_id' => Auth::id(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->normalizeText($value);
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalizedKey = is_string($key) ? $this->normalizeText($key) : $key;
                $normalized[$normalizedKey] = $this->normalizeValue($item);
            }

            if (! array_is_list($normalized)) {
                ksort($normalized);
            }

            return $normalized;
        }

        return $value;
    }
}
