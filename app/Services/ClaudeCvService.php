<?php

namespace App\Services;

use App\Contracts\CvAiProvider;
use App\Exceptions\AiRefusalException;
use App\Models\AiCallLog;
use App\Services\Ai\EnhanceCvFieldResultGuard;
use App\Services\Ai\Prompts\AnalysisAdviceSystemPrompt;
use App\Services\Ai\Prompts\EnhanceCvFieldSystemPrompt;
use App\Services\Ai\Schemas\OperationSchemas;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

/**
 * Anthropic Messages API driver for CvAiProvider.
 *
 * Experimental bake-off only — production default remains OpenAI unless
 * CV_AI_PROVIDER=claude is set deliberately after a clear quality win.
 */
class ClaudeCvService implements CvAiProvider
{
    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.api_key'));
    }

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws AiRefusalException
     * @throws UnexpectedValueException
     */
    public function analysisAdvice(array $score, string $resumeText, string $jobTitle): array
    {
        return $this->requestJson(
            'analysis_advice',
            (new AnalysisAdviceSystemPrompt)->build(),
            "Analyze this CV for the target job and produce actionable Arabic advice.\n\nTarget job: {$jobTitle}\n\nDeterministic ATS score JSON:\n".json_encode($score, JSON_UNESCAPED_UNICODE)."\n\nCV text:\n".mb_substr($resumeText, 0, 7000)."\n\nReturn JSON with keys: executive_summary string, top_priorities array of strings, rewritten_summary string|null, keyword_recommendations array of strings, bullet_improvements array of objects {before:string|null, after:string, reason:string}, warnings array of strings."
        );
    }

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws AiRefusalException
     * @throws UnexpectedValueException
     */
    public function generateCv(array $data): array
    {
        return $this->requestJson(
            'generate_cv',
            'You are an expert CV writer for Arabic and English ATS-friendly resumes. Return only valid JSON. Use only the user-provided facts. Do not invent employers, degrees, dates, metrics, or certifications. Make the CV concise, keyword-rich, and ATS readable.',
            "Generate an ATS-friendly CV template from these form inputs.\n\nInput JSON:\n".json_encode($data, JSON_UNESCAPED_UNICODE)."\n\nReturn JSON with keys: cv_markdown string, headline string, professional_summary string, core_skills array of strings, improved_experience_bullets array of strings, ats_notes array of strings, missing_information array of strings."
        );
    }

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws AiRefusalException
     * @throws UnexpectedValueException
     */
    public function enhanceCvField(string $field, string $draft, string $jobTitle, string $language): array
    {
        $languageName = $language === 'en' ? 'English' : 'Arabic';
        $result = $this->requestJson(
            'enhance_cv_field',
            EnhanceCvFieldSystemPrompt::for($field),
            "Rewrite the {$field} CV field in {$languageName} for the target role. Keep every fact grounded in the draft.\n\nTarget job title: {$jobTitle}\n\nDraft:\n".mb_substr($draft, 0, 12000)."\n\nReturn enhanced_text, changes_made, missing_facts, ats_keywords_added, and unverified_claims. Return unverified_claims as an empty array; server-side validation populates it.",
        );

        return (new EnhanceCvFieldResultGuard)->enforce($result, $draft, $language);
    }

    public function enhanceJobDescription(string $jobTitle, ?string $jobDescription, string $language): array
    {
        $languageName = $language === 'en' ? 'English' : 'Arabic';

        return $this->requestJson(
            'enhance_job_description',
            'You improve job descriptions for ATS-focused CV tailoring. Return only valid JSON. Do not invent a company, salary, dates, or location. Keep the description useful for matching a CV to the role.',
            "Enhance or complete this job description in {$languageName}.\n\nTarget job title: {$jobTitle}\n\nCurrent job description:\n".mb_substr((string) $jobDescription, 0, 4000)."\n\nReturn JSON with keys: enhanced_description string, suggested_keywords array of strings, responsibilities array of strings, requirements array of strings."
        );
    }

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws AiRefusalException
     * @throws UnexpectedValueException
     */
    private function requestJson(string $operation, string $systemPrompt, string $userPrompt): array
    {
        $model = (string) config('services.anthropic.model');
        $startedAt = hrtime(true);

        $response = Http::withHeaders([
            'x-api-key' => (string) config('services.anthropic.api_key'),
            'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
            'content-type' => 'application/json',
        ])
            ->acceptJson()
            ->connectTimeout(config('services.anthropic.connect_timeout', 5))
            ->timeout(config('services.anthropic.timeout', 30))
            ->post(rtrim((string) config('services.anthropic.base_url'), '/').'/messages', [
                'model' => $model,
                'max_tokens' => OperationSchemas::maxTokens($operation),
                'temperature' => 0.2,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'output_config' => OperationSchemas::anthropicOutputConfig($operation),
            ])
            ->throw()
            ->json();

        $durationMs = (int) max(0, round((hrtime(true) - $startedAt) / 1_000_000));
        $response = is_array($response) ? $response : [];

        $this->logAiCall(
            operation: $operation,
            model: $model,
            response: $response,
            durationMs: $durationMs,
        );

        $stopReason = data_get($response, 'stop_reason');

        if ($stopReason === 'refusal') {
            $refusalText = $this->extractText($response) ?: 'Claude refused to answer.';
            throw new AiRefusalException($refusalText);
        }

        if ($stopReason === 'max_tokens') {
            // Privacy: keep this metadata-only. Prompts and AI responses contain
            // candidate CV data and must never be added to logs or error reports.
            Log::warning('Claude structured output truncated by max_tokens', [
                'operation' => $operation,
                'model' => $model,
                'stop_reason' => 'max_tokens',
            ]);

            throw new UnexpectedValueException('Claude response was truncated (stop_reason=max_tokens).');
        }

        $content = $this->extractText($response);
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            // Privacy: never add the prompt or response content to this context.
            Log::warning('Claude structured output returned non-JSON content despite schema', [
                'operation' => $operation,
                'model' => $model,
                'stop_reason' => $stopReason,
                'content_type' => get_debug_type($content),
            ]);

            throw new UnexpectedValueException('Claude returned a non-JSON response.');
        }

        return $decoded;
    }

    private function extractText(array $response): ?string
    {
        $content = data_get($response, 'content');
        if (! is_array($content)) {
            return null;
        }

        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                return $block['text'];
            }
            // Some structured responses may omit type but still carry text.
            if (is_string($block['text'] ?? null)) {
                return $block['text'];
            }
        }

        return is_string(data_get($response, 'content.0.text'))
            ? (string) data_get($response, 'content.0.text')
            : null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function logAiCall(string $operation, string $model, array $response, int $durationMs): void
    {
        try {
            AiCallLog::create([
                'provider' => 'anthropic',
                'model' => $model,
                'operation' => $operation,
                'input_tokens' => (int) data_get($response, 'usage.input_tokens', 0),
                'output_tokens' => (int) data_get($response, 'usage.output_tokens', 0),
                'cached_tokens' => (int) data_get($response, 'usage.cache_read_input_tokens', 0),
                'duration_ms' => $durationMs,
                'was_response_cache_hit' => false,
                'user_id' => Auth::id(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
