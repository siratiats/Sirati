<?php

namespace App\Services\Notifications;

use App\Models\CvAnalysis;
use App\Models\EducationContent;
use App\Models\GeneratedCv;
use App\Models\JobNews;
use App\Models\JobTitle;
use App\Models\User;
use App\Support\DailyNotificationCandidate;
use Illuminate\Support\Collection;

class DailyNotificationCandidateService
{
    /**
     * Minimum keyword hits required to accept a job match.
     * Weak matches hurt retention more than a missed ping.
     */
    public const JOB_MATCH_MIN_SCORE = 2;

    /**
     * Minimum keyword hits for education relevance when the user has interest keywords.
     */
    public const EDUCATION_MATCH_MIN_SCORE = 1;

    /**
     * Per-run memoized lookup sets.
     *
     * DailyNotificationPlanner calls forUser() once per user inside a chunked
     * loop. The published-job list, education list, and job-title taxonomy are
     * IDENTICAL for every user on a given day, so querying them per user turned
     * one query into O(users) queries fetching O(users x rows) of body text.
     * These are resolved once per service instance (one planner run) instead.
     *
     * @var Collection<int, JobNews>|null
     */
    private ?Collection $jobCandidatesMemo = null;

    /** Date the job candidate memo was built for, so a run crossing midnight refetches. */
    private ?string $jobCandidatesMemoDate = null;

    /** @var Collection<int, EducationContent>|null */
    private ?Collection $educationItemsMemo = null;

    /** @var Collection<int, JobTitle>|null */
    private ?Collection $jobTitleTaxonomyMemo = null;

    /**
     * Drop memoized lookup sets.
     *
     * Call between runs in long-lived processes (queue workers, Octane) or in
     * tests that mutate JobNews / EducationContent / JobTitle mid-test.
     */
    public function flushLookupCaches(): void
    {
        $this->jobCandidatesMemo = null;
        $this->jobCandidatesMemoDate = null;
        $this->educationItemsMemo = null;
        $this->jobTitleTaxonomyMemo = null;
    }

    /**
     * Published, in-validity job news for today. Memoized per run.
     *
     * @return Collection<int, JobNews>
     */
    private function jobCandidates(): Collection
    {
        $today = today();
        $todayKey = $today->toDateString();

        if ($this->jobCandidatesMemo !== null && $this->jobCandidatesMemoDate === $todayKey) {
            return $this->jobCandidatesMemo;
        }

        $this->jobCandidatesMemoDate = $todayKey;

        return $this->jobCandidatesMemo = JobNews::query()
            ->where('is_published', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
            })
            ->latest('published_at')
            ->latest('id')
            ->limit(100)
            ->get(['id', 'title', 'body', 'company', 'published_at']);
    }

    /**
     * Published education content. Memoized per run.
     *
     * @return Collection<int, EducationContent>
     */
    private function educationItems(): Collection
    {
        return $this->educationItemsMemo ??= EducationContent::query()
            ->where('is_published', true)
            ->latest('id')
            ->limit(50)
            ->get(['id', 'title', 'body', 'target_role']);
    }

    /**
     * Active job-title taxonomy excluding the 'other' catch-all. Memoized per run.
     *
     * @return Collection<int, JobTitle>
     */
    private function jobTitleTaxonomy(): Collection
    {
        return $this->jobTitleTaxonomyMemo ??= JobTitle::query()
            ->active()
            ->where('slug', '!=', 'other')
            ->get(['id', 'slug', 'name_ar', 'name_en', 'keywords']);
    }

    /**
     * Return the highest-priority candidate for a user, or null.
     *
     * matching_job is evaluated before first_analysis so a newly registered user
     * who declared a job title can receive a relevant opening instead of only the
     * generic “analyze your CV” tip. Behavioural CV signals (low score, stale CV,
     * etc.) still outrank job matching when present.
     */
    public function forUser(User $user): ?DailyNotificationCandidate
    {
        return $this->lowAtsScore($user)
            ?? $this->analysisToCv($user)
            ?? $this->staleCv($user)
            ?? $this->matchingJob($user)
            ?? $this->firstAnalysis($user)
            ?? $this->relevantEducation($user)
            ?? $this->dailyTip();
    }

    private function firstAnalysis(User $user): ?DailyNotificationCandidate
    {
        if ($user->cvAnalyses()->exists()) {
            return null;
        }

        return new DailyNotificationCandidate(
            ruleKey: 'first_analysis',
            templateKey: 'first_analysis',
            type: 'tip',
            actionType: 'screen',
            actionUrl: 'cv-analysis',
        );
    }

    private function lowAtsScore(User $user): ?DailyNotificationCandidate
    {
        /** @var CvAnalysis|null $latest */
        $latest = $user->cvAnalyses()->latest('id')->first();
        if ($latest === null || (int) $latest->score_total >= 70) {
            return null;
        }

        $tip = null;
        $wins = $latest->quick_wins;
        if (is_array($wins) && $wins !== []) {
            $first = $wins[0];
            $tip = is_string($first) ? $first : (is_array($first) ? ($first['text'] ?? $first['title'] ?? null) : null);
        }

        return new DailyNotificationCandidate(
            ruleKey: 'low_ats_score',
            templateKey: 'low_ats_score',
            type: 'analysis',
            actionType: 'screen',
            actionUrl: 'analysis/'.$latest->id,
            context: [
                'score' => (int) $latest->score_total,
                'analysis_id' => $latest->id,
                'tip' => $tip,
            ],
        );
    }

    private function analysisToCv(User $user): ?DailyNotificationCandidate
    {
        /** @var CvAnalysis|null $latest */
        $latest = $user->cvAnalyses()->latest('id')->first();
        if ($latest === null) {
            return null;
        }

        $hasGenerated = $user->generatedCvs()
            ->where('created_at', '>=', $latest->created_at)
            ->exists();

        if ($hasGenerated) {
            return null;
        }

        return new DailyNotificationCandidate(
            ruleKey: 'analysis_to_cv',
            templateKey: 'analysis_to_cv',
            type: 'cv',
            actionType: 'screen',
            actionUrl: 'create-cv',
            context: [
                'analysis_id' => $latest->id,
            ],
        );
    }

    private function staleCv(User $user): ?DailyNotificationCandidate
    {
        /** @var GeneratedCv|null $latest */
        $latest = $user->generatedCvs()->latest('id')->first();
        if ($latest === null || $latest->created_at === null) {
            return null;
        }

        if ($latest->created_at->greaterThan(now()->subDays(7))) {
            return null;
        }

        return new DailyNotificationCandidate(
            ruleKey: 'stale_cv',
            templateKey: 'stale_cv',
            type: 'cv',
            actionType: 'screen',
            actionUrl: 'my-cvs',
            context: [
                'cv_id' => $latest->id,
            ],
        );
    }

    private function matchingJob(User $user): ?DailyNotificationCandidate
    {
        $keywords = $this->interestKeywords($user);
        if ($keywords === []) {
            return null;
        }

        $candidates = $this->jobCandidates();

        $minScore = (int) config(
            'smart_notifications.job_match_min_score',
            self::JOB_MATCH_MIN_SCORE,
        );

        $best = null;
        $bestScore = 0;

        foreach ($candidates as $job) {
            $score = $this->keywordHitScore(
                $keywords,
                (string) $job->title.' '.(string) $job->body,
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $job;
            }
        }

        if ($best === null || $bestScore < $minScore) {
            return null;
        }

        return new DailyNotificationCandidate(
            ruleKey: 'matching_job',
            templateKey: 'matching_job',
            type: 'job',
            actionType: 'screen',
            actionUrl: 'job-news/'.$best->id,
            context: [
                'job_id' => $best->id,
                'job_title' => $best->title,
                'company' => $best->company,
                'match_score' => $bestScore,
            ],
        );
    }

    private function relevantEducation(User $user): ?DailyNotificationCandidate
    {
        $keywords = $this->interestKeywords($user);

        $items = $this->educationItems();

        if ($items->isEmpty()) {
            return null;
        }

        if ($keywords === []) {
            /** @var EducationContent $item */
            $item = $items->first();

            return $this->educationCandidate($item);
        }

        $minScore = (int) config(
            'smart_notifications.education_match_min_score',
            self::EDUCATION_MATCH_MIN_SCORE,
        );

        $best = null;
        $bestScore = 0;

        foreach ($items as $item) {
            $score = $this->keywordHitScore(
                $keywords,
                (string) $item->title.' '.(string) $item->body.' '.(string) $item->target_role,
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        if ($best === null || $bestScore < $minScore) {
            return null;
        }

        return $this->educationCandidate($best);
    }

    private function educationCandidate(EducationContent $item): DailyNotificationCandidate
    {
        return new DailyNotificationCandidate(
            ruleKey: 'relevant_education',
            templateKey: 'relevant_education',
            type: 'education',
            actionType: 'screen',
            actionUrl: 'education/'.$item->id,
            context: [
                'education_id' => $item->id,
                'title' => $item->title,
            ],
        );
    }

    private function dailyTip(): DailyNotificationCandidate
    {
        return new DailyNotificationCandidate(
            ruleKey: 'daily_tip',
            templateKey: 'daily_tip',
            type: 'tip',
            actionType: 'screen',
            actionUrl: 'home',
        );
    }

    /**
     * Resolve interest keywords for job/education matching.
     *
     * Order:
     *  1. Titles from recent analyses + generated CVs (behavioural signal)
     *  2. Declared job_title relation (name_ar, name_en, keywords[])
     *  3. job_title_other free text
     *
     * Keywords are grounded in the job_titles taxonomy banks whenever a title
     * maps to a taxonomy row; free-text tokens are a fallback only.
     *
     * @return list<string>
     */
    private function interestKeywords(User $user): array
    {
        $cvTitles = $this->cvDerivedTitles($user);

        if ($cvTitles->isNotEmpty()) {
            return $this->keywordsForFreeTextTitles($cvTitles);
        }

        $user->loadMissing('jobTitle');

        if ($user->jobTitle !== null) {
            return $this->keywordsFromJobTitle($user->jobTitle);
        }

        if (filled($user->job_title_other)) {
            return $this->normalizeKeywords($this->tokensFromText((string) $user->job_title_other));
        }

        return [];
    }

    /**
     * @return Collection<int, string>
     */
    private function cvDerivedTitles(User $user): Collection
    {
        return $user->cvAnalyses()
            ->orderByDesc('id')
            ->limit(5)
            ->pluck('target_job_title')
            ->merge(
                $user->generatedCvs()->orderByDesc('id')->limit(5)->pluck('target_job_title')
            )
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Map free-text titles onto taxonomy keyword banks; fall back to tokens.
     *
     * @param  Collection<int, string>  $titles
     * @return list<string>
     */
    private function keywordsForFreeTextTitles(Collection $titles): array
    {
        $taxonomy = $this->jobTitleTaxonomy();

        $keywords = [];

        foreach ($titles as $title) {
            $bestRow = null;
            $bestScore = 0;

            foreach ($taxonomy as $row) {
                $score = $this->titleTaxonomyScore($title, $row);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestRow = $row;
                }
            }

            if ($bestRow !== null && $bestScore >= 1) {
                $keywords = array_merge($keywords, $this->keywordsFromJobTitle($bestRow));
            } else {
                $keywords = array_merge($keywords, $this->tokensFromText($title));
            }
        }

        return $this->normalizeKeywords($keywords);
    }

    private function titleTaxonomyScore(string $title, JobTitle $row): int
    {
        $haystack = mb_strtolower($title);
        $score = 0;

        foreach ([(string) $row->name_en, (string) $row->name_ar] as $name) {
            $name = mb_strtolower(trim($name));
            if ($name === '') {
                continue;
            }
            if ($haystack === $name) {
                $score += 5;
            } elseif (str_contains($haystack, $name) || str_contains($name, $haystack)) {
                $score += 3;
            }
        }

        foreach ($row->keywords ?? [] as $keyword) {
            $keyword = mb_strtolower(trim((string) $keyword));
            if ($keyword !== '' && mb_strlen($keyword) >= 3 && str_contains($haystack, $keyword)) {
                $score += 1;
            }
        }

        return $score;
    }

    /**
     * @return list<string>
     */
    private function keywordsFromJobTitle(JobTitle $title): array
    {
        $parts = array_merge(
            $title->keywords ?? [],
            [$title->name_ar, $title->name_en],
        );

        return $this->normalizeKeywords($parts);
    }

    /**
     * @param  list<string>  $keywords
     */
    private function keywordHitScore(array $keywords, string $haystack): int
    {
        $haystack = mb_strtolower($haystack);
        $score = 0;

        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($haystack, $keyword)) {
                $score++;
            }
        }

        return $score;
    }

    /**
     * @param  iterable<int, mixed>  $parts
     * @return list<string>
     */
    private function normalizeKeywords(iterable $parts): array
    {
        $out = [];

        foreach ($parts as $part) {
            $value = mb_strtolower(trim((string) $part));
            if ($value === '' || mb_strlen($value) < 2) {
                continue;
            }
            $out[$value] = $value;
        }

        return array_values($out);
    }

    /**
     * @return list<string>
     */
    private function tokensFromText(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];

        return array_values(array_filter(
            $parts,
            fn (string $w): bool => mb_strlen($w) >= 3,
        ));
    }
}
