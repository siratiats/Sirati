<?php

namespace App\Services\Jobs;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Profile relevance for the "مناسبة لي" preset, plus display formatting for
 * source type and salary. There is no visible match score: relevance only
 * filters and orders the list.
 */
class JobProfileMatcherService
{
    /**
     * Latest CV/analysis target title per user id, so one request reads it
     * once (SIRATI-97).
     *
     * @var array<int, string>
     */
    private array $userTargetTitleCache = [];

    /**
     * Restrict a JobNews query to jobs in the user's field and order them by
     * relevance. A job qualifies only through the role: the exact job title,
     * the same taxonomy category, or the CV target title in its text. City and
     * remote only order qualifying jobs; they never admit another field's job.
     * A user with no role information gets no results (the app asks them to
     * set a job title instead).
     *
     * Order: exact title, then category, then target-title text; inside each
     * tier same-city first, then remote, then the caller's own ordering.
     */
    public function applyMatchedFilter(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $user->loadMissing('jobTitle');
        $jobTitleId = $user->job_title_id;
        $category = trim((string) $user->jobTitle?->category);
        $targetTitle = $jobTitleId === null ? $this->targetTitleFor($user) : '';
        $city = $this->cityCodeFor($user);

        if (! $this->hasRole($user)) {
            return $query->whereRaw('1 = 0');
        }

        $query->where(function (Builder $q) use ($jobTitleId, $category, $targetTitle): void {
            if ($jobTitleId !== null) {
                $q->orWhere('job_title_id', $jobTitleId);
            }
            if ($category !== '') {
                $q->orWhere('category', $category);
            }
            if ($targetTitle !== '') {
                $q->orWhere('title', 'like', "%{$targetTitle}%")
                    ->orWhere('body', 'like', "%{$targetTitle}%");
            }
        });

        // A CASE needs at least one WHEN; with only a CV target title every
        // qualifying job is in the same tier and there is no tier ordering.
        $tier = null;
        $bindings = [];
        if ($jobTitleId !== null || $category !== '') {
            $tier = 'CASE';
            if ($jobTitleId !== null) {
                $tier .= ' WHEN job_title_id = ? THEN 0';
                $bindings[] = $jobTitleId;
            }
            if ($category !== '') {
                $tier .= ' WHEN category = ? THEN 1';
                $bindings[] = $category;
            }
            $tier .= ' ELSE 2 END';
        }

        $place = 'CASE';
        $placeBindings = [];
        if ($city !== null) {
            $place .= ' WHEN city = ? THEN 0';
            $placeBindings[] = $city;
        }
        $place .= ' WHEN is_remote = 1 THEN 1 ELSE 2 END';

        // Relevance goes before the caller's ordering, ahead of the LIMIT.
        $query->getQuery()->orders = array_merge(
            $tier !== null ? [['type' => 'Raw', 'sql' => $tier]] : [],
            [['type' => 'Raw', 'sql' => $place]],
            $query->getQuery()->orders ?? [],
        );
        $query->addBinding(array_merge($bindings, $placeBindings), 'order');

        return $query;
    }

    /**
     * Whether the profile names a role the "in my field" preset can use: a
     * job title, or a target title from the latest CV or analysis.
     */
    public function hasRole(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        $user->loadMissing('jobTitle');

        return $user->job_title_id !== null
            || trim((string) $user->jobTitle?->category) !== ''
            || $this->targetTitleFor($user) !== '';
    }

    /** The role the preset is based on: the job title, else the CV target title. */
    public function roleNameFor(?User $user, string $language): ?string
    {
        if ($user === null) {
            return null;
        }
        $user->loadMissing('jobTitle');
        $name = $language === 'en' ? $user->jobTitle?->name_en : $user->jobTitle?->name_ar;
        $name = trim((string) ($name ?: $this->targetTitleFor($user)));

        return $name !== '' ? $name : null;
    }

    /**
     * Human-readable label and badge for source_type. A general aggregated
     * post has neither: nothing has verified it.
     *
     * @return array{code: string, label: ?string, badge: ?string}
     */
    public function formatSourceType(string $sourceType, string $language = 'ar'): array
    {
        $isEnglish = $language === 'en';

        return match ($sourceType) {
            'gov' => [
                'code' => 'gov',
                'label' => $isEnglish ? 'Gov / Semi-Gov' : 'جهة حكومية / شبه حكومية',
                'badge' => $isEnglish ? 'Government' : 'جهة حكومية',
            ],
            'enterprise' => [
                'code' => 'enterprise',
                'label' => $isEnglish ? 'Company' : 'شركة',
                'badge' => $isEnglish ? 'Company' : 'شركة',
            ],
            'tamheer' => [
                'code' => 'tamheer',
                'label' => $isEnglish ? 'Tamheer Program' : 'برنامج تمهير',
                'badge' => $isEnglish ? 'Tamheer & training' : 'تمهير وتدريب',
            ],
            'startup' => [
                'code' => 'startup',
                'label' => $isEnglish ? 'Startup' : 'شركة ناشئة',
                'badge' => $isEnglish ? 'Startup' : 'شركة ناشئة',
            ],
            default => [
                'code' => 'general',
                'label' => null,
                'badge' => null,
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

    /** The user's location as a stored city code (e.g. "riyadh"), if recognised. */
    private function cityCodeFor(User $user): ?string
    {
        $location = trim((string) ($user->location ?? ''));
        if ($location === '') {
            return null;
        }

        $city = app(JobTaxonomyMatcher::class)->detectCity($location)['city'] ?? null;

        return is_string($city) && $city !== 'remote' ? $city : null;
    }

    /**
     * Latest CV or analysis target title, cached per instance (SIRATI-97).
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
