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

class DeepInfraCvService implements CvAiProvider
{
    public function isConfigured(): bool
    {
        return filled(config('services.deepinfra.api_key'));
    }

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws AiRefusalException
     * @throws UnexpectedValueException
     */
    public function analysisAdvice(array $score, string $resumeText, string $jobTitle): array
    {
        $model = (string) config('services.deepinfra.model_ar', config('services.deepinfra.model', 'Qwen/Qwen2.5-72B-Instruct'));

        return $this->requestJson(
            'analysis_advice',
            (new AnalysisAdviceSystemPrompt)->build()."\n\nYou MUST return a valid JSON object matching the requested structure. No markdown fences, no conversational prose outside JSON.",
            "Analyze this CV for the target job and produce actionable Arabic advice.\n\nTarget job: {$jobTitle}\n\nDeterministic ATS score JSON:\n".json_encode($score, JSON_UNESCAPED_UNICODE)."\n\nCV text:\n".mb_substr($resumeText, 0, 7000)."\n\nReturn JSON with keys: executive_summary string, top_priorities array of strings, rewritten_summary string|null, keyword_recommendations array of strings, bullet_improvements array of objects {before:string|null, after:string, reason:string}, warnings array of strings.",
            $model
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
        $language = strtolower((string) ($data['language'] ?? 'ar'));
        $model = $language === 'en'
            ? (string) config('services.deepinfra.model_en', 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo')
            : (string) config('services.deepinfra.model_ar', config('services.deepinfra.model', 'Qwen/Qwen2.5-72B-Instruct'));

        return $this->requestJson(
            'generate_cv',
            "You are an expert CV writer for Arabic and English ATS-friendly resumes. Return only valid JSON. Use only the user-provided facts. Do not invent employers, degrees, dates, metrics, or certifications. Make the CV concise, keyword-rich, and ATS readable.\n\nYou MUST return a valid JSON object with keys: cv_markdown string, headline string, ats_notes array of strings, missing_information array of strings.",
            "Generate an ATS-friendly CV template from these form inputs.\n\nInput JSON:\n".json_encode($data, JSON_UNESCAPED_UNICODE)."\n\nReturn JSON with keys: cv_markdown string, headline string, ats_notes array of strings, missing_information array of strings. cv_markdown must be the complete CV; do not repeat its sections in other keys.",
            $model
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
        $model = (string) config('services.deepinfra.fast_model', 'mistralai/Mistral-Small-24B-Instruct-2501');

        $result = $this->requestJson(
            'enhance_cv_field',
            EnhanceCvFieldSystemPrompt::for($field)."\n\nYou MUST return a valid JSON object with keys: enhanced_text string, changes_made array of strings, missing_facts array of strings, ats_keywords_added array of strings, unverified_claims array.",
            "Rewrite the {$field} CV field in {$languageName} for the target role. Keep every fact grounded in the draft.\n\nTarget job title: {$jobTitle}\n\nDraft:\n".mb_substr($draft, 0, 12000)."\n\nReturn enhanced_text, changes_made, missing_facts, ats_keywords_added, and unverified_claims. Return unverified_claims as an empty array; server-side validation populates it.",
            $model
        );

        return (new EnhanceCvFieldResultGuard)->enforce($result, $draft, $language);
    }

    public function enhanceJobDescription(string $jobTitle, ?string $jobDescription, string $language): array
    {
        $languageName = $language === 'en' ? 'English' : 'Arabic';
        $model = (string) config('services.deepinfra.fast_model', 'mistralai/Mistral-Small-24B-Instruct-2501');

        return $this->requestJson(
            'enhance_job_description',
            "You improve job descriptions for ATS-focused CV tailoring. Return only valid JSON. Do not invent a company, salary, dates, or location. Keep the description useful for matching a CV to the role.\n\nYou MUST return a valid JSON object with keys: enhanced_description string, suggested_keywords array of strings, responsibilities array of strings, requirements array of strings.",
            "Enhance or complete this job description in {$languageName}.\n\nTarget job title: {$jobTitle}\n\nCurrent job description:\n".mb_substr((string) $jobDescription, 0, 4000)."\n\nReturn JSON with keys: enhanced_description string, suggested_keywords array of strings, responsibilities array of strings, requirements array of strings.",
            $model
        );
    }

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws AiRefusalException
     * @throws UnexpectedValueException
     */
    private function requestJson(string $operation, string $systemPrompt, string $userPrompt, ?string $overrideModel = null): array
    {
        $model = $overrideModel ?: (string) config('services.deepinfra.model', 'Qwen/Qwen2.5-72B-Instruct');
        $startedAt = hrtime(true);

        $response = Http::withToken(config('services.deepinfra.api_key'))
            ->acceptJson()
            ->connectTimeout(config('services.deepinfra.connect_timeout', 5))
            ->timeout(config('services.deepinfra.timeout', 45))
            ->post(rtrim(config('services.deepinfra.base_url', 'https://api.deepinfra.com/v1/openai'), '/').'/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'max_tokens' => OperationSchemas::maxTokens($operation),
                'response_format' => ['type' => 'json_object'],
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

        $refusal = data_get($response, 'choices.0.message.refusal');
        if (is_string($refusal) && $refusal !== '') {
            throw new AiRefusalException($refusal);
        }

        $content = data_get($response, 'choices.0.message.content');
        $decoded = is_string($content) ? $this->cleanAndDecodeJson($content) : null;

        if (! is_array($decoded)) {
            Log::warning('DeepInfra output returned non-JSON content', [
                'operation' => $operation,
                'model' => $model,
                'content_type' => get_debug_type($content),
            ]);

            throw new UnexpectedValueException('DeepInfra returned a non-JSON response.');
        }

        return $decoded;
    }

    /**
     * Clean markdown blocks if present and decode JSON.
     */
    private function cleanAndDecodeJson(string $raw): ?array
    {
        $cleaned = trim($raw);
        if (str_starts_with($cleaned, '```json')) {
            $cleaned = substr($cleaned, 7);
        } elseif (str_starts_with($cleaned, '```')) {
            $cleaned = substr($cleaned, 3);
        }
        if (str_ends_with($cleaned, '```')) {
            $cleaned = substr($cleaned, 0, -3);
        }
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try extracting first balanced JSON object if wrapped in extra prose
        if (preg_match('/\{[\s\S]*\}/', $cleaned, $matches)) {
            $extracted = json_decode($matches[0], true);
            if (is_array($extracted)) {
                return $extracted;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function logAiCall(string $operation, string $model, array $response, int $durationMs): void
    {
        try {
            AiCallLog::create([
                'provider' => 'deepinfra',
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
