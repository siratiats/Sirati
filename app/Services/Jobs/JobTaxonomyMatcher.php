<?php

namespace App\Services\Jobs;

use App\Models\JobTitle;
use App\Services\AtsScoringService;
use Illuminate\Support\Collection;

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
     * Match a job posting's title and text against the active JobTitle taxonomy.
     *
     * @return array{job_title_id: ?int, category: string, city: ?string, is_remote: bool}
     */
    public function match(string $title, string $body = '', ?string $location = ''): array
    {
        $jobTitle = $this->resolveJobTitle($title, $body);
        $cityInfo = $this->detectCity($location, $title.' '.$body);

        return [
            'job_title_id' => $jobTitle?->id,
            'category' => $jobTitle?->category ?? AtsScoringService::inferCategory($title),
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
        $bestNameLength = 0;

        foreach ($taxonomy as $job) {
            // "Other" is a user choice, never an automatic match.
            if ($job->slug === 'other') {
                continue;
            }

            $score = 0;
            $nameLength = 0;

            // Exact name beats containment; a longer contained name is more specific.
            foreach ([$this->normalizeText($job->name_ar), $this->normalizeText($job->name_en)] as $name) {
                if ($name === '') {
                    continue;
                }
                if ($cleanTitle === $name) {
                    $score = max($score, 100);
                } elseif ($this->containsPhrase($cleanTitle, $name)) {
                    $score = max($score, 20);
                } elseif ($this->containsPhrase($combinedText, $name)) {
                    $score = max($score, 10);
                } else {
                    continue;
                }
                $nameLength = max($nameLength, mb_strlen($name, 'UTF-8'));
            }

            // Keyword hits; a multi-word phrase in the title counts per word.
            $keywords = $job->keywords ?: [];
            foreach ($keywords as $kw) {
                $kwClean = $this->normalizeText($kw);
                if ($kwClean === '' || mb_strlen($kwClean) < 2) continue;

                if ($this->containsPhrase($cleanTitle, $kwClean)) {
                    $score += 8 * count(preg_split('/\s+/u', $kwClean) ?: [$kwClean]);
                } elseif ($this->containsPhrase($combinedText, $kwClean)) {
                    $score += 3;
                }
            }

            // Ties keep the earlier title (taxonomy is ordered by sort_order, id).
            if ($score > $highestScore || ($score === $highestScore && $score > 0 && $nameLength > $bestNameLength)) {
                $highestScore = $score;
                $bestNameLength = $nameLength;
                $bestMatch = $job;
            }
        }

        // Require a minimum confidence score of 3
        return $highestScore >= 3 ? $bestMatch : null;
    }

    /**
     * Whole-word/phrase match on normalized text. Allows Arabic attached prefixes
     * (ال، و، ب، ل) and common suffixes (ه، ات، ين، ون) so "الصيدلي" matches "صيدلي".
     */
    private function containsPhrase(string $haystack, string $phrase): bool
    {
        $pattern = '/(?<![\p{L}\p{N}])(?:وال|بال|لل|ال|و|ب|ل)?'
            .preg_quote($phrase, '/')
            .'(?:ه|ات|ين|ون)?(?![\p{L}\p{N}])/u';

        return (bool) preg_match($pattern, $haystack);
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

    private function getTaxonomy(): Collection
    {
        return JobTitle::query()->active()->orderBy('sort_order')->orderBy('id')->get();
    }
}
