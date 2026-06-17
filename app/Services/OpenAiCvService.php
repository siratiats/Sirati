<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class OpenAiCvService
{
    public function isConfigured(): bool
    {
        return filled(config('services.openai.api_key'));
    }

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws UnexpectedValueException
     */
    public function analysisAdvice(array $score, string $resumeText, string $jobTitle): array
    {
        return $this->requestJson(
            'You are an Arabic-first ATS CV advisor. Return only valid JSON. Do not invent experience, dates, certifications, employers, metrics, or skills not present in the CV. Suggest wording improvements and missing keywords only.',
            "Analyze this CV for the target job and produce actionable Arabic advice.\n\nTarget job: {$jobTitle}\n\nDeterministic ATS score JSON:\n".json_encode($score, JSON_UNESCAPED_UNICODE)."\n\nCV text:\n".mb_substr($resumeText, 0, 7000)."\n\nReturn JSON with keys: executive_summary string, top_priorities array of strings, rewritten_summary string|null, keyword_recommendations array of strings, bullet_improvements array of objects {before:string|null, after:string, reason:string}, warnings array of strings."
        );
    }

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws UnexpectedValueException
     */
    public function generateCv(array $data): array
    {
        return $this->requestJson(
            'You are an expert CV writer for Arabic and English ATS-friendly resumes. Return only valid JSON. Use only the user-provided facts. Do not invent employers, degrees, dates, metrics, or certifications. Make the CV concise, keyword-rich, and ATS readable.',
            "Generate an ATS-friendly CV template from these form inputs.\n\nInput JSON:\n".json_encode($data, JSON_UNESCAPED_UNICODE)."\n\nReturn JSON with keys: cv_markdown string, headline string, professional_summary string, core_skills array of strings, improved_experience_bullets array of strings, ats_notes array of strings, missing_information array of strings."
        );
    }

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws UnexpectedValueException
     */
    private function requestJson(string $systemPrompt, string $userPrompt): array
    {
        $response = Http::withToken(config('services.openai.api_key'))
            ->acceptJson()
            ->connectTimeout(config('services.openai.connect_timeout', 5))
            ->timeout(config('services.openai.timeout', 15))
            ->post(rtrim(config('services.openai.base_url'), '/').'/chat/completions', [
                'model' => config('services.openai.model'),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ])
            ->throw()
            ->json();

        $content = data_get($response, 'choices.0.message.content');
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            throw new UnexpectedValueException('OpenAI returned a non-JSON response.');
        }

        return $decoded;
    }
}
