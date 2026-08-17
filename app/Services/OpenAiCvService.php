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

class OpenAiCvService implements CvAiProvider
{
    public function isConfigured(): bool
    {
        return filled(config('services.openai.api_key'));
    }

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws AiRefusalException
     * @throws UnexpectedValueException
     */
    public function analysisAdvice(array $score, string $resumeText, string $jobTitle): array
    {
        // System prefix is static (cacheable); all per-request values stay in the user message.
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
        $model = (string) config('services.openai.model');
        $startedAt = hrtime(true);

        $response = Http::withToken(config('services.openai.api_key'))
            ->acceptJson()
            ->connectTimeout(config('services.openai.connect_timeout', 5))
            ->timeout(config('services.openai.timeout', 30))
            ->post(rtrim(config('services.openai.base_url'), '/').'/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'max_tokens' => OperationSchemas::maxTokens($operation),
                'response_format' => OperationSchemas::responseFormat($operation),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
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

        if (data_get($response, 'choices.0.finish_reason') === 'length') {
            // Privacy: keep this metadata-only. Prompts and AI responses contain
            // candidate CV data and must never be added to logs or error reports.
            Log::warning('OpenAI structured output truncated by max_tokens', [
                'operation' => $operation,
                'model' => $model,
                'finish_reason' => 'length',
            ]);

            throw new UnexpectedValueException('OpenAI response was truncated (finish_reason=length).');
        }

        $refusal = data_get($response, 'choices.0.message.refusal');
        if (is_string($refusal) && $refusal !== '') {
            throw new AiRefusalException($refusal);
        }

        $content = data_get($response, 'choices.0.message.content');
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            // Defensive fallback: with strict Structured Outputs this should be unreachable.
            // A hit here likely means an OpenAI API contract change.
            // Privacy: never add the prompt or response content to this context.
            Log::warning('OpenAI structured output returned non-JSON content despite schema', [
                'operation' => $operation,
                'model' => $model,
                'finish_reason' => data_get($response, 'choices.0.finish_reason'),
                'content_type' => get_debug_type($content),
            ]);

            throw new UnexpectedValueException('OpenAI returned a non-JSON response.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function logAiCall(string $operation, string $model, array $response, int $durationMs): void
    {
        try {
            AiCallLog::create([
                'provider' => 'openai',
                'model' => $model,
                'operation' => $operation,
                'input_tokens' => (int) data_get($response, 'usage.prompt_tokens', 0),
                'output_tokens' => (int) data_get($response, 'usage.completion_tokens', 0),
                'cached_tokens' => (int) data_get($response, 'usage.prompt_tokens_details.cached_tokens', 0),
                'duration_ms' => $durationMs,
                'was_response_cache_hit' => false,
                'user_id' => Auth::id(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
