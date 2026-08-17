<?php

namespace App\Contracts;

interface CvAiProvider
{
    public function isConfigured(): bool;

    /**
     * @param  array<string, mixed>  $score
     * @return array<string, mixed>
     */
    public function analysisAdvice(array $score, string $resumeText, string $jobTitle): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function generateCv(array $data): array;

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public function enhanceCvField(string $field, string $draft, string $jobTitle, string $language): array;
    public function enhanceJobDescription(string $jobTitle, ?string $jobDescription, string $language): array;
}
