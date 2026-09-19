<?php

namespace App\Services\Jobs;

use App\Models\JobNews;
use App\Models\JobTitle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class JobProfileMatcherService
{
    /**
     * Per-instance caches so a single request that renders 30 jobs (via
     * calculateMatch() inside a ->map()) does one DB read for the user's
     * skills and one for the latest CV/analysis title, not 30 (SIRATI-97).
     * Keyed by user id.
     *
     * @var array<int, list<string>>
     */
    private array $userSkillsCache = [];

    /**
     * @var array<int, string>
     */
    private array $userTargetTitleCache = [];

    /**
     * Apply intelligent tiered match filter to a JobNews query for a user.
     * Hierarchy:
     * 1. Exact job_title_id match
     * 2. Same taxonomy category match (e.g. tech, finance, health)
     * 3. CV target title keyword match
     * 4. City match or Remote fallback
     */
    public function applyMatchedFilter(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query;
        }

        $user->loadMissing('jobTitle');
        $jobTitleId = $user->job_title_id;
        $category = $user->jobTitle?->category;
        $userLocation = mb_strtolower(trim((string) ($user->location ?? $user->city ?? '')), 'UTF-8');

        // Look up target job title from recent CV if profile title is empty
        $targetTitle = null;
        if ($jobTitleId === null) {
            $targetTitle = $this->targetTitleFor($user);
        }

        $query->where(function (Builder $q) use ($jobTitleId, $category, $userLocation, $targetTitle): void {
            $hasCondition = false;

            if ($jobTitleId !== null) {
                $q->where('job_title_id', $jobTitleId);
                $hasCondition = true;
            }

            if (! empty($category)) {
                if ($hasCondition) {
                    $q->orWhere('category', $category);
                } else {
                    $q->where('category', $category);
                    $hasCondition = true;
                }
            }

            if (! empty($targetTitle)) {
                if ($hasCondition) {
                    $q->orWhere('title', 'like', "%{$targetTitle}%")
                      ->orWhere('body', 'like', "%{$targetTitle}%");
                } else {
                    $q->where(function ($sub) use ($targetTitle): void {
                        $sub->where('title', 'like', "%{$targetTitle}%")
                            ->orWhere('body', 'like', "%{$targetTitle}%");
                    });
                    $hasCondition = true;
                }
            }

            // Also include remote opportunities or same-city opportunities
            if ($hasCondition) {
                $q->orWhere('is_remote', true);
                if ($userLocation !== '') {
                    $q->orWhere('city', $userLocation)
                      ->orWhere('location', 'like', "%{$userLocation}%");
                }
            } else {
                // If user has no title info, fall back to location / remote
                $q->where('is_remote', true);
                if ($userLocation !== '') {
                    $q->orWhere('city', $userLocation)
                      ->orWhere('location', 'like', "%{$userLocation}%");
                }
            }
        });

        return $query;
    }

    /**
     * Compute compatibility match between a job and a user.
     *
     * @return array{
     *     score: int|null,
     *     reasons: list<string>,
     *     matched_skills: list<string>,
     *     missing_skills: list<string>
     * }
     */
    public function calculateMatch(JobNews $job, ?User $user, string $language = 'ar'): array
    {
        if ($user === null) {
            return [
                'score' => null,
                'reasons' => [],
                'matched_skills' => [],
                'missing_skills' => $job->skills_required ?? [],
            ];
        }

        $isEnglish = $language === 'en';
        $score = 0;
        $reasons = [];
        $matchedSkills = [];
        $missingSkills = [];

        $user->loadMissing('jobTitle');
        $userJobTitleId = $user->job_title_id;
        $userLocation = mb_strtolower(trim((string) ($user->location ?? $user->city ?? '')), 'UTF-8');

        // 1. Direct Job Title Match (Up to 45 points)
        if ($userJobTitleId !== null && $job->job_title_id !== null && $userJobTitleId === $job->job_title_id) {
            $score += 45;
            $titleName = $isEnglish
                ? ($user->jobTitle?->name_en ?? 'your target role')
                : ($user->jobTitle?->name_ar ?? 'مسماك الوظيفي');
            $reasons[] = $isEnglish
                ? "Matches your target title ({$titleName})"
                : "يتطابق مع مسماك المستهدف ({$titleName})";
        } elseif ($userJobTitleId !== null && $user->jobTitle !== null) {
            // Partial match on taxonomy category
            $userCategory = $user->jobTitle->category ?? '';
            if ($userCategory !== '' && $job->category === $userCategory) {
                $score += 25;
                $reasons[] = $isEnglish
                    ? 'Matches your career field'
                    : 'ضمن مجالك وقطاعك المهني';
            }
        } else {
            // Check target title from latest CV or analysis (cached per-request).
            $targetTitle = $this->targetTitleFor($user);

            if ($targetTitle !== '') {
                $cleanTarget = mb_strtolower($targetTitle, 'UTF-8');
                $jobTitleClean = mb_strtolower($job->title, 'UTF-8');
                if (str_contains($jobTitleClean, $cleanTarget) || str_contains($cleanTarget, $jobTitleClean)) {
                    $score += 40;
                    $reasons[] = $isEnglish
                        ? "Matches your CV title ({$targetTitle})"
                        : "يتطابق مع مسمى سيرتك الذاتية ({$targetTitle})";
                }
            }
        }

        // 2. City / Remote Location Match (Up to 25 points)
        if ($job->is_remote) {
            $score += 25;
            $reasons[] = $isEnglish ? 'Remote opportunity (work from anywhere)' : 'فرصة عمل عن بُعد تناسبك';
        } elseif ($job->city !== null && $userLocation !== '') {
            $normalizedCity = mb_strtolower($job->city, 'UTF-8');
            if (str_contains($userLocation, $normalizedCity) || (str_contains($userLocation, 'riyadh') && $normalizedCity === 'riyadh')) {
                $score += 25;
                $cityLabel = $isEnglish ? ucfirst($job->city) : ($job->location ?? 'مدينتك');
                $reasons[] = $isEnglish ? "Located in your city ({$cityLabel})" : "في نفس مدينتك ({$cityLabel})";
            }
        }

        // 3. Skills Overlap with User CV / Profile (Up to 30 points)
        $requiredSkills = $job->skills_required ?: [];
        if (! empty($requiredSkills)) {
            $userCvSkills = $this->extractUserSkills($user);

            foreach ($requiredSkills as $skill) {
                $cleanSkill = mb_strtolower(trim($skill), 'UTF-8');
                $found = false;
                foreach ($userCvSkills as $userSkill) {
                    if ($this->skillsMatch($userSkill, $cleanSkill)) {
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    $matchedSkills[] = $skill;
                } else {
                    $missingSkills[] = $skill;
                }
            }

            if (! empty($matchedSkills)) {
                $ratio = count($matchedSkills) / max(1, count($requiredSkills));
                $skillPoints = (int) round($ratio * 30);
                $score += $skillPoints;

                $skillsSnippet = implode(', ', array_slice($matchedSkills, 0, 3));
                $reasons[] = $isEnglish
                    ? "Matches your skills: {$skillsSnippet}"
                    : "تطابق مهاراتك: {$skillsSnippet}";
            }
        } else {
            // If job didn't specify distinct skills, grant base points if title matched
            if ($score >= 40) {
                $score += 15;
            }
        }

        // Bound score between 15% and 98%
        $finalScore = min(98, max(15, $score));

        return [
            'score' => $finalScore,
            'reasons' => $reasons,
            'matched_skills' => $matchedSkills,
            'missing_skills' => $missingSkills,
        ];
    }

    /**
     * Human-readable label and badge for source_type.
     */
    public function formatSourceType(string $sourceType, string $language = 'ar'): array
    {
        $isEnglish = $language === 'en';

        return match ($sourceType) {
            'gov' => [
                'code' => 'gov',
                'label' => $isEnglish ? 'Gov / Semi-Gov' : 'جهة حكومية / شبه حكومية',
                'badge' => '🏛️ ' . ($isEnglish ? 'Verified Gov' : 'جهة حكومية موثقة'),
            ],
            'enterprise' => [
                'code' => 'enterprise',
                'label' => $isEnglish ? 'Top Enterprise' : 'شركة كبرى معتمدة',
                'badge' => '🏢 ' . ($isEnglish ? 'Enterprise' : 'شركة كبرى'),
            ],
            'tamheer' => [
                'code' => 'tamheer',
                'label' => $isEnglish ? 'Tamheer Program' : 'برنامج تمهير الوطني',
                'badge' => '🎓 ' . ($isEnglish ? 'Tamheer' : 'تمهير'),
            ],
            'startup' => [
                'code' => 'startup',
                'label' => $isEnglish ? 'Verified Startup' : 'شركة ناشئة معتمدة',
                'badge' => '🚀 ' . ($isEnglish ? 'Verified Startup' : 'شركة ناشئة'),
            ],
            default => [
                'code' => 'general',
                'label' => $isEnglish ? 'Direct Apply' : 'تقديم مباشر',
                'badge' => '✅ ' . ($isEnglish ? 'Verified Job' : 'وظيفة موثقة'),
            ],
        };
    }

    /**
     * Format salary range nicely.
     */
    public function formatSalary(?int $min, ?int $max, string $language = 'ar'): ?string
    {
        if ($min === null && $max === null) {
            return null;
        }

        $currency = $language === 'en' ? 'SAR' : 'ر.س';

        if ($min !== null && $max !== null) {
            $formattedMin = number_format($min);
            $formattedMax = number_format($max);

            return "{$formattedMin} - {$formattedMax} {$currency}";
        }

        if ($min !== null) {
            $formattedMin = number_format($min);

            return $language === 'en' ? "From {$formattedMin} {$currency}" : "من {$formattedMin} {$currency}";
        }

        $formattedMax = number_format($max);

        return $language === 'en' ? "Up to {$formattedMax} {$currency}" : "حتى {$formattedMax} {$currency}";
    }

    /**
     * True when two skill strings refer to the same skill.
     *
     * Uses exact-or-word-boundary matching, mirroring the fix already applied
     * to AtsScoringService::matchKeyword (SIRATI-98). The previous bidirectional
     * str_contains falsely credited "java" for "javascript", "sql" for
     * "postgresql", and "type" for "typescript" — inflating match_score for
     * every tech job in the list.
     *
     * Match rules, in order:
     *   1. exact string match after normalisation
     *   2. the shorter side appears as a WHOLE WORD inside the longer side,
     *      so "REST APIs" contains the whole token "rest" and "React 18"
     *      contains "react", but "javascript" does NOT contain "java".
     */
    private function skillsMatch(string $a, string $b): bool
    {
        $a = mb_strtolower(trim($a), 'UTF-8');
        $b = mb_strtolower(trim($b), 'UTF-8');
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        [$short, $long] = mb_strlen($a) <= mb_strlen($b) ? [$a, $b] : [$b, $a];
        if ($short === $long) {
            return true;
        }

        // \b with /u honours Unicode word boundaries, so Arabic terms
        // work too (e.g. "لارفيل" whole-word inside "خبرة لارفيل ٥ سنوات").
        return (bool) preg_match('/\b'.preg_quote($short, '/').'\b/u', $long);
    }

    /**
     * Collect user skills from latest GeneratedCv or profile.
     * Cached per instance so a request that renders 30 jobs re-uses the same
     * one-time DB read instead of hitting generated_cvs N times (SIRATI-97).
     *
     * @return list<string>
     */
    private function extractUserSkills(User $user): array
    {
        $key = (int) $user->id;
        if (isset($this->userSkillsCache[$key])) {
            return $this->userSkillsCache[$key];
        }

        $skills = [];

        // Check latest generated CV
        $latestCv = $user->generatedCvs()->latest()->first();
        if ($latestCv !== null && ! empty($latestCv->skills_input)) {
            $raw = is_array($latestCv->skills_input)
                ? implode(',', $latestCv->skills_input)
                : (string) $latestCv->skills_input;

            $parts = preg_split('/[,،\n]+/', $raw);
            foreach ($parts as $part) {
                $trimmed = mb_strtolower(trim($part), 'UTF-8');
                if ($trimmed !== '') {
                    $skills[] = $trimmed;
                }
            }
        }

        // Add keywords from user's registered job title
        if ($user->jobTitle !== null && ! empty($user->jobTitle->keywords)) {
            foreach ($user->jobTitle->keywords as $kw) {
                $clean = mb_strtolower(trim($kw), 'UTF-8');
                if ($clean !== '') {
                    $skills[] = $clean;
                }
            }
        }

        return $this->userSkillsCache[$key] = array_values(array_unique($skills));
    }

    /**
     * Latest CV or analysis target title, cached per instance (SIRATI-97).
     * Called from applyMatchedFilter() and calculateMatch(); with 30 items in
     * a list, the naive path hits generated_cvs + cv_analyses 60 times.
     */
    private function targetTitleFor(User $user): string
    {
        $key = (int) $user->id;
        if (isset($this->userTargetTitleCache[$key])) {
            return $this->userTargetTitleCache[$key];
        }

        $latestCv = $user->generatedCvs()->latest()->first();
        $latestAnalysis = $user->cvAnalyses()->latest()->first();

        return $this->userTargetTitleCache[$key] = trim((string) (
            $latestCv?->target_job_title ?: $latestAnalysis?->target_job_title
        ));
    }
}