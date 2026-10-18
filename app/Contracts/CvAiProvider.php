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

    /**
     * Classifies whether the provided document text is a genuine CV/resume or a non-resume
     * (e.g. bank receipt, invoice, certificate, contract, ID document).
     *
     * @return array{is_resume: bool, document_type: string, confidence: float, reason_ar: string, reason_en: string}
     */
    public function classifyDocument(string $text): array;

    /**
     * @param  array<string, mixed>  $cvSnapshot
     * @return array{
     *     executive_summary: string,
     *     strengths: list<string>,
     *     gaps: list<string>,
     *     suggestions: list<array{
     *         key: string,
     *         target: array{
     *             section: string,
     *             entry_id: string|null,
     *             field: string|null,
     *             bullet_index: int|null
     *         },
     *         before: string|null,
     *         after: string,
     *         rationale: string,
     *         jd_terms: list<string>,
     *         needs_user_fact: bool
     *     }>
     * }
     */
    public function jobMatchAdvice(array $cvSnapshot, string $jobDescription, string $language): array;
}
