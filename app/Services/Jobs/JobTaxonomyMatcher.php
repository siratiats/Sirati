<?php

namespace App\Services\Jobs;

use App\Models\JobTitle;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class JobTaxonomyMatcher
{
    /**
     * Cache active job title taxonomy in memory for fast matching.
     *
     * @var Collection<int, JobTitle>|null
     */
    private ?Collection $taxonomy = null;

    /**
     * Map common Saudi cities in Arabic and English.
     */
    private const SAUDI_CITIES = [
        'riyadh' => ['الرياض', 'riyadh'],
        'jeddah' => ['جدة', 'جده', 'jeddah'],
        'dammam' => ['الدمام', 'dammam'],
        'khobar' => ['الخبر', 'khobar', 'al khobar', 'al-khobar'],
        'mecca' => ['مكة', 'مكه', 'مكة المكرمة', 'mecca', 'makkah'],
        'medina' => ['المدينة', 'المدينه', 'المدينة المنورة', 'medina', 'madinah'],
        'dhahran' => ['الظهران', 'dhahran'],
        'jubail' => ['الجبيل', 'jubail'],
        'tabuk' => ['تبوك', 'tabuk'],
        'qassim' => ['القصيم', 'بريدة', 'عنيزة', 'qassim', 'buraidah'],
        'abha' => ['أبها', 'abha', 'عسير', 'asir'],
        'remote' => ['عن بعد', 'عن_بعد', 'remote', 'work from home', 'telecommute'],
    ];

    /**
     * Match a job posting's title and text against the 73 JobTitle taxonomy.
     *
     * @return array{job_title_id: ?int, category: string, city: ?string, is_remote: bool}
     */
    public function match(string $title, string $body = '', ?string $location = ''): array
    {
        $jobTitle = $this->resolveJobTitle($title, $body);
        $cityInfo = $this->detectCity($location, $title.' '.$body);

        return [
            'job_title_id' => $jobTitle?->id,
            'category' => $jobTitle?->category ?? $this->inferGeneralCategory($title.' '.$body),
            'city' => $cityInfo['city'],
            'is_remote' => $cityInfo['is_remote'],
        ];
    }

    public function resolveJobTitle(string $title, string $body = ''): ?JobTitle
    {
        $taxonomy = $this->getTaxonomy();
        $cleanTitle = $this->normalizeText($title);
        $cleanBody = $this->normalizeText($body);
        $combinedText = $cleanTitle.' '.mb_substr($cleanBody, 0, 1000, 'UTF-8');

        $bestMatch = null;
        $highestScore = 0;

        foreach ($taxonomy as $job) {
            $score = 0;

            // Direct exact name match
            $nameAr = $this->normalizeText($job->name_ar);
            $nameEn = $this->normalizeText($job->name_en);

            if ($nameAr !== '' && str_contains($cleanTitle, $nameAr)) {
                $score += 20;
            } elseif ($nameEn !== '' && str_contains($cleanTitle, $nameEn)) {
                $score += 20;
            } elseif ($nameAr !== '' && str_contains($combinedText, $nameAr)) {
                $score += 10;
            } elseif ($nameEn !== '' && str_contains($combinedText, $nameEn)) {
                $score += 10;
            }

            // Keyword hits
            $keywords = $job->keywords ?: [];
            foreach ($keywords as $kw) {
                $kwClean = $this->normalizeText($kw);
                if ($kwClean === '' || mb_strlen($kwClean) < 2) continue;

                if (str_contains($cleanTitle, $kwClean)) {
                    $score += 8;
                } elseif (str_contains($combinedText, $kwClean)) {
                    $score += 3;
                }
            }

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $job;
            }
        }

        // Require a minimum confidence score of 3
        return $highestScore >= 3 ? $bestMatch : null;
    }

    public function detectCity(?string $location, string $fallbackText = ''): array
    {
        $haystack = $this->normalizeText(($location ?? '').' '.$fallbackText);

        $isRemote = str_contains($haystack, 'عن بعد') ||
            str_contains($haystack, 'remote') ||
            str_contains($haystack, 'work from home');

        foreach (self::SAUDI_CITIES as $cityKey => $synonyms) {
            foreach ($synonyms as $syn) {
                if (str_contains($haystack, $this->normalizeText($syn))) {
                    return [
                        'city' => $cityKey === 'remote' ? null : $cityKey,
                        'is_remote' => $isRemote || $cityKey === 'remote',
                    ];
                }
            }
        }

        return [
            'city' => null,
            'is_remote' => $isRemote,
        ];
    }

    public function normalizeText(?string $text): string
    {
        if ($text === null) return '';

        $text = mb_strtolower(trim($text), 'UTF-8');
        // Normalize Arabic letters: أ/إ/آ -> ا, ة -> ه, ى -> ي
        $text = preg_replace('/[أإآ]/u', 'ا', $text);
        $text = preg_replace('/[ة]/u', 'ه', $text);
        $text = preg_replace('/[ى]/u', 'ي', $text);

        return (string) $text;
    }

    private function inferGeneralCategory(string $text): string
    {
        $lower = $this->normalizeText($text);

        if (Str::contains($lower, ['developer', 'software', 'engineer', 'it', 'برمجه', 'تقنيه', 'مطور', 'مهندس', 'flutter', 'laravel', 'sql', 'qa', 'ux', 'ui'])) {
            return 'tech';
        }
        if (Str::contains($lower, ['accountant', 'finance', 'audit', 'tax', 'محاسب', 'ماليه', 'تدقيق', 'مبيعات', 'sales'])) {
            return 'finance';
        }
        if (Str::contains($lower, ['hr', 'recruitment', 'recruiter', 'موارد بشريه', 'توظيف'])) {
            return 'hr';
        }
        if (Str::contains($lower, ['ecommerce', 'marketing', 'تسويق', 'تجاره الكترونيه', 'سوشيال'])) {
            return 'ecommerce';
        }

        return 'management';
    }

    private function getTaxonomy(): Collection
    {
        return JobTitle::query()->active()->get();
    }
}
