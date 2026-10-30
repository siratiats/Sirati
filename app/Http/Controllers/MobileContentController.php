<?php

namespace App\Http\Controllers;

use App\Cv\CvExportGuard;
use App\Http\Resources\JobTitleResource;
use App\Models\CvTemplate;
use App\Models\EducationContent;
use App\Models\GeneratedCv;
use App\Models\JobMatch;
use App\Models\JobNews;
use App\Models\JobTitle;
use App\Models\MobileNotification;
use App\Models\User;
use App\Services\Jobs\ApplyLinkExtractor;
use App\Services\Jobs\JobProfileMatcherService;
use App\Services\I18n\ServerText;
use App\Services\Jobs\SaudiCities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class MobileContentController extends Controller
{
    public function dashboard(Request $request): array
    {
        $language = $this->language($request);
        $user = $request->user();
        $latestGeneratedCv = $user->generatedCvs()->latest()->first();

        return [
            'data' => [
                'profile' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $this->text('serverDashboardStatusActive'),
                ],
                'stats' => [
                    'generated_cvs' => $user->generatedCvs()->count(),
                    'analyses' => $user->cvAnalyses()->count(),
                    'unread_notifications' => $user->mobileNotifications()->whereNull('read_at')->count(),
                ],
                'primary_action' => [
                    'title' => $this->text('serverDashboardCreateCvTitle'),
                    'subtitle' => $this->text('serverDashboardCreateCvSubtitle'),
                    'button_label' => $this->text('serverDashboardCreateCvButton'),
                ],
                'analysis_action' => [
                    'title' => $this->text('serverDashboardAnalyzeTitle'),
                    'subtitle' => $this->text('serverDashboardAnalyzeSubtitle'),
                    'button_label' => $this->text('serverDashboardAnalyzeButton'),
                ],
                'latest_news' => $this->latestNews($language),
                'latest_cv' => $latestGeneratedCv ? [
                    'id' => $latestGeneratedCv->id,
                    'title' => $this->displayTitles($request, [$latestGeneratedCv])[$latestGeneratedCv->id] ?? $latestGeneratedCv->target_job_title,
                    'score_total' => $latestGeneratedCv->score_total,
                    'updated_at' => $latestGeneratedCv->updated_at?->toISOString(),
                ] : null,
            ],
        ];
    }

    /**
     * The newest job the feed would show in this language, as stored. Null when
     * there is none: the home screen never gets invented news.
     *
     * @return array{id: int, title: string, location: ?string, published_at: string}|null
     */
    private function latestNews(string $language): ?array
    {
        $item = JobNews::query()
            ->where(fn ($q) => $q->where('language', $language)->orWhereNotNull('external_source'))
            ->active()
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->latest('id')
            ->first();

        return $item === null ? null : [
            'id' => $item->id,
            'title' => $item->title,
            'location' => $item->location,
            'published_at' => $item->published_at->toISOString(),
        ];
    }

    /**
     * A copy created by applying Job Match edits keeps its original's headline, so the CV
     * lists show it as "<headline> — <job title>" (or "(tailored)") to tell the two apart.
     *
     * @param  iterable<GeneratedCv>  $cvs
     * @return array<int, string> display title by generated CV id
     */
    private function displayTitles(Request $request, iterable $cvs): array
    {
        $cvs = collect($cvs);
        $matches = JobMatch::where('user_id', $request->user()->id)
            ->whereIn('applied_generated_cv_id', $cvs->pluck('id'))
            ->get(['applied_generated_cv_id', 'target_job_title'])
            ->keyBy('applied_generated_cv_id');

        return $cvs->mapWithKeys(function (GeneratedCv $cv) use ($matches): array {
            $base = (string) ($cv->target_job_title ?: $cv->full_name);
            $match = $matches->get($cv->id);
            if ($match === null) {
                return [$cv->id => $base];
            }
            $job = trim((string) $match->target_job_title);
            $title = $job !== '' && mb_strtolower($job) !== mb_strtolower(trim($base))
                ? $base.' — '.$job
                : $this->text('serverMyCvsTailoredTitle', ['title' => $base]);

            return [$cv->id => $title];
        })->all();
    }

    public function myCvs(Request $request): array
    {
        $publicBaseUrl = rtrim((string) config('app.public_url', 'https://siratie.com'), '/');
        $cvs = $request->user()->generatedCvs()->latest()->limit(20)->get();
        $titles = $this->displayTitles($request, $cvs);
        $items = $cvs->map(function (GeneratedCv $cv) use ($publicBaseUrl, $titles): array {
            $signedPath = URL::temporarySignedRoute(
                'api.generated-cvs.pdf',
                now()->addDays(7),
                ['generatedCv' => $cv->id],
                false
            );

            $export = CvExportGuard::exportState($cv);

            return [
                'id' => $cv->id,
                'title' => $titles[$cv->id] ?? ($cv->target_job_title ?: $cv->full_name),
                // The CV's own language, not the app's: templates and text direction follow it (#21).
                'language' => $cv->language,
                'updated_label' => $this->updatedLabel($cv->updated_at),
                'badge' => $cv->score_total === null ? '' : 'ATS '.$cv->score_total.'%',
                'score_total' => $cv->score_total,
                'is_draft' => $export['blocked_reason'] === CvExportGuard::BLOCKED_GENERATING,
                'can_download' => true,
                'export' => $export,
                'pdf_url' => $publicBaseUrl.$signedPath,
                'template_pdf_url' => $publicBaseUrl.$signedPath,
            ];
        })->values();

        return [
            'data' => [
                'title' => $this->text('serverMyCvsTitle'),
                'summary' => $this->text('serverMyCvsSummary', ['count' => $items->count()]),
                'items' => $items,
            ],
        ];
    }

    public function jobTitles(): AnonymousResourceCollection
    {
        $titles = Cache::remember('mobile.job_titles.active.v1', now()->addDay(), function () {
            return JobTitle::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name_ar')
                ->get();
        });

        return JobTitleResource::collection($titles);
    }

    public function cvTemplates(Request $request): array
    {
        $language = $this->language($request);

        $items = CvTemplate::query()
            ->active()
            ->ordered()
            ->get()
            ->filter(fn (CvTemplate $template): bool => $template->supportsLanguage($language))
            ->map(fn (CvTemplate $template): array => [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->displayName($language),
                'name_ar' => $template->name_ar,
                'name_en' => $template->name_en,
                'preview_image_url' => $template->preview_image_path ? asset('storage/'.$template->preview_image_path) : null,
                'language_direction' => $template->language_direction,
                'supported_languages' => $template->supported_languages ?: ['ar', 'en'],
                'supported_sections' => $template->supported_sections ?: [],
                'is_default' => $template->is_default,
            ])
            ->values();

        return [
            'data' => [
                'title' => $this->text('serverCvTemplatesTitle'),
                'subtitle' => $this->text('serverCvTemplatesSubtitle'),
                'items' => $items,
            ],
        ];
    }

    public function education(Request $request): array
    {
        $language = $this->language($request);
        $selectedType = in_array($request->query('type'), ['news', 'certificate', 'study'], true)
            ? $request->query('type')
            : 'study';
        $contents = EducationContent::query()
            ->where('language', $language)
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->latest()
            ->get();

        $studyCards = $contents
            ->where('type', $selectedType)
            ->where('is_featured', false)
            ->take(6)
            ->values()
            ->map(fn (EducationContent $content): array => [
                'id' => $content->id,
                'icon' => $content->icon ?: 'book',
                'title' => $content->title,
                'body' => $content->body,
                'duration' => $content->duration_label ?: null,
            ]);

        $featuredContent = $contents
            ->where('type', $selectedType)
            ->where('is_featured', true)
            ->first();

        $featuredCourse = $featuredContent ? [
            'id' => $featuredContent->id,
            // Only a badge an editor wrote: "Recommended for you" would claim a personal pick.
            'badge' => $featuredContent->badge ?: null,
            'title' => $featuredContent->title,
            'body' => $featuredContent->body,
            'button_label' => $featuredContent->button_label ?: $this->text('serverEducationStartLearning'),
        ] : null;

        // The signed-in user's own role, never a content row's or a default one.
        $targetRole = app(JobProfileMatcherService::class)
            ->roleNameFor($request->user('sanctum') ?? $request->user(), $language);

        return [
            'data' => [
                'title' => $this->text('serverEducationTitle'),
                'subtitle' => $this->text('serverEducationSubtitle'),
                'target_label' => $this->text('serverEducationTargetLabel'),
                'target_role' => $targetRole,
                'tabs' => [
                    ['key' => 'news', 'label' => $this->text('serverEducationTabNews')],
                    ['key' => 'certificate', 'label' => $this->text('serverEducationTabCertificates')],
                    ['key' => 'study', 'label' => $this->text('serverEducationTabStudy')],
                ],
                'selected_type' => $selectedType,
                // Stored content only: an empty catalog is an empty list, not sample courses.
                'study_cards' => $studyCards->all(),
                'featured_course' => $featuredCourse,
            ],
        ];
    }

    public function educationShow(Request $request, EducationContent $educationContent): array
    {
        abort_unless($educationContent->is_published, 404);

        return [
            'data' => [
                'id' => $educationContent->id,
                'language' => $educationContent->language,
                'type' => $educationContent->type,
                'title' => $educationContent->title,
                'body' => $educationContent->body,
                'duration' => $educationContent->duration_label,
                'target_role' => $educationContent->target_role,
                'badge' => $educationContent->badge,
                'button_label' => $educationContent->button_label
                    ?: $this->text('serverEducationStartLearning'),
                'icon' => $educationContent->icon,
                'is_featured' => $educationContent->is_featured,
                'created_at' => $educationContent->created_at?->toISOString(),
                'updated_at' => $educationContent->updated_at?->toISOString(),
            ],
        ];
    }

    public function jobNews(Request $request): array
    {
        $language = $this->language($request);
        $user = $request->user('sanctum') ?? $request->user();
        $category = $this->jobNewsCategory($request->query('category'));
        $preset = trim((string) $request->query('preset', 'all'));
        $search = trim((string) ($request->query('q') ?? $request->query('query', '')));
        $jobTitleId = $request->filled('job_title_id') ? (int) $request->query('job_title_id') : null;
        $city = $request->filled('city') ? trim((string) $request->query('city')) : null;
        $region = $request->filled('region') ? trim((string) $request->query('region')) : null;
        $isRemote = $request->boolean('is_remote', false);
        $isVerified = $request->has('is_verified') ? $request->boolean('is_verified') : null;
        $sourceType = $request->filled('source_type') ? trim((string) $request->query('source_type')) : null;
        $employmentType = $request->filled('employment_type') ? trim((string) $request->query('employment_type')) : null;
        $experienceLevel = $request->filled('experience_level') ? trim((string) $request->query('experience_level')) : null;

        // One instance so its per-request cache (CV target title) serves both
        // the filter and the profile context (SIRATI-97).
        $matcher = app(JobProfileMatcherService::class);

        // Curated listings are written per app language; aggregated posts carry
        // the language they were written in and are shown in both.
        $query = JobNews::query()
            ->with('jobTitle')
            ->where(fn ($q) => $q->where('language', $language)->orWhereNotNull('external_source'))
            ->active()
            ->orderBy('sort_order')
            // Newest day first; within a day, posts the user can act on
            // (a link or an email) come before ones without.
            ->orderByRaw('DATE(published_at) DESC')
            ->orderByRaw('CASE WHEN apply_url IS NULL AND apply_email IS NULL THEN 1 ELSE 0 END')
            ->latest('published_at')
            ->latest('id');

        // Preset handling
        if ($preset === 'gov') {
            $query->where('source_type', 'gov');
        } elseif ($preset === 'enterprise') {
            $query->where('source_type', 'enterprise');
        } elseif ($preset === 'tamheer') {
            $query->where(function ($q): void {
                $q->where('source_type', 'tamheer')
                    ->orWhere('employment_type', 'tamheer');
            });
        } elseif ($preset === 'remote') {
            $query->where('is_remote', true);
        } elseif ($preset === 'matched') {
            $matcher->applyMatchedFilter($query, $user);
        }

        // Direct filters. Verified means the row was verified: a government or
        // company source type alone verifies nothing.
        if ($isVerified === true) {
            $query->verified();
        }

        if ($sourceType !== null && $sourceType !== 'all') {
            $query->where('source_type', $sourceType);
        }

        if ($employmentType !== null && $employmentType !== 'all') {
            $query->where('employment_type', $employmentType);
        }

        if ($experienceLevel !== null && $experienceLevel !== 'all') {
            $query->where('experience_level', $experienceLevel);
        }

        if ($jobTitleId !== null) {
            $query->where('job_title_id', $jobTitleId);
        }

        if ($city !== null && $city !== 'all') {
            $query->where('city', $city);
        }

        if (SaudiCities::isRegion($region)) {
            $query->whereIn('city', SaudiCities::codesInRegion($region));
        }

        if ($request->has('is_remote') && $isRemote) {
            $query->where('is_remote', true);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($user !== null) {
            $user->loadMissing('jobTitle');
        }

        $items = $query
            ->limit($category === null ? 30 : 80)
            ->get()
            ->filter(fn (JobNews $item): bool => $category === null || $this->inferJobCategory($item) === $category)
            ->take(30)
            ->map(fn (JobNews $item): array => $this->jobNewsPayload($item))
            ->values();

        return [
            'data' => [
                // The feed mixes verified and unverified rows, so it never claims either (#15).
                'title' => $this->text('serverJobsTitle'),
                'subtitle' => $this->text('serverJobsSubtitle'),
                'selected_category' => $category ?? 'all',
                'selected_preset' => $preset,
                'selected_region' => SaudiCities::isRegion($region) ? $region : 'all',
                'profile_context' => [
                    'has_profile' => $matcher->hasRole($user),
                    'job_title_id' => $user?->job_title_id,
                    'job_title_name' => $matcher->roleNameFor($user, $language),
                    'location' => $user?->location,
                ],
                'items' => $items,
            ],
        ];
    }

    public function jobNewsShow(Request $request, JobNews $jobNews): array
    {
        $today = today();

        abort_unless(
            $jobNews->is_published
                && ($jobNews->valid_from === null || $jobNews->valid_from->lessThanOrEqualTo($today))
                && ($jobNews->valid_until === null || $jobNews->valid_until->greaterThanOrEqualTo($today)),
            404
        );

        return ['data' => $this->jobNewsPayload($jobNews)];
    }

    public function notifications(Request $request): array
    {
        $items = $request->user()->mobileNotifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (MobileNotification $notification): array => $this->notificationPayload($notification));

        return [
            'data' => [
                'unread_count' => $request->user()->mobileNotifications()->whereNull('read_at')->count(),
                'items' => $items,
            ],
        ];
    }

    public function markNotificationRead(Request $request, MobileNotification $notification): array
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return ['data' => $this->notificationPayload($notification->refresh())];
    }

    public function markAllNotificationsRead(Request $request): array
    {
        $request->user()->mobileNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ['message' => $this->text('serverNotificationsAllRead')];
    }

    private function jobNewsPayload(JobNews $item): array
    {
        // Aggregated posts retire on an internal date, not a stated deadline,
        // so only curated listings show "apply by".
        $validUntilLabel = null;
        if ($item->valid_until !== null && $item->external_source === null) {
            $validUntilLabel = $this->text('serverJobsApplyBy', ['date' => app(ServerText::class)->date($item->valid_until)]);
        }

        $formatter = app(JobProfileMatcherService::class);
        $sourceInfo = $formatter->formatSourceType($item->source_type ?? 'general');
        $salaryRange = $formatter->formatSalary($item->salary_min, $item->salary_max);

        // Re-checked on every response, so rows stored under older rules can
        // never offer Telegram or a social page as "Apply".
        $links = app(ApplyLinkExtractor::class);
        $apply = $links->resolve($item->apply_url, $item->apply_email);
        $applyInstructions = $apply['method'] === ApplyLinkExtractor::METHOD_NONE
            ? $links->applyInstructions((string) $item->body)
            : null;

        return [
            'id' => $item->id,
            'language' => $item->language,
            'title' => $item->title,
            'company' => $item->company,
            'location' => $item->location,
            'city' => $item->city,
            'is_remote' => (bool) $item->is_remote,
            'body' => $item->body,
            'category' => $this->inferJobCategory($item),
            'job_title_id' => $item->job_title_id,
            'url' => $item->url,
            'source_url' => $item->url,
            'source_name' => $item->source,
            'apply_method' => $apply['method'],
            'apply_url' => $apply['url'],
            'apply_email' => $apply['email'],
            'apply_instructions' => $applyInstructions,
            'valid_from' => $item->valid_from?->toDateString(),
            'valid_until' => $item->valid_until?->toDateString(),
            'valid_until_label' => $validUntilLabel,
            'published_label' => $item->published_at?->diffForHumans(),
            'published_at' => $item->published_at?->toISOString(),
            'is_verified' => (bool) $item->is_verified,
            'source_type' => $item->source_type ?? 'general',
            'source_type_label' => $sourceInfo['label'],
            'source_badge' => $sourceInfo['badge'],
            'employment_type' => $item->employment_type ?? 'full_time',
            'experience_level' => $item->experience_level,
            'salary_range' => $salaryRange,
            'skills_required' => $item->skills_required ?? [],
        ];
    }

    private function jobNewsCategory($value): ?string
    {
        $category = trim((string) $value);

        return in_array($category, ['tech', 'finance', 'health'], true) ? $category : null;
    }

    private function inferJobCategory(JobNews $item): string
    {
        $text = mb_strtolower(implode(' ', array_filter([
            $item->title,
            $item->company,
            $item->location,
            $item->body,
        ])), 'UTF-8');

        $keywordMap = [
            'tech' => ['developer', 'engineer', 'software', 'frontend', 'backend', 'full stack', 'flutter', 'laravel', 'data', 'cyber', 'cloud', 'devops', 'programmer', 'it ', 'qa ', 'ui ', 'ux '],
            'finance' => ['finance', 'financial', 'accountant', 'accounting', 'bank', 'banking', 'investment', 'treasury', 'payroll', 'audit', 'auditor', 'tax'],
            'health' => ['health', 'healthcare', 'medical', 'doctor', 'nurse', 'nursing', 'clinic', 'hospital', 'pharma', 'pharmacy', 'patient', 'laboratory'],
        ];

        foreach ($keywordMap as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $category;
                }
            }
        }

        return 'general';
    }

    private function notificationPayload(MobileNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->displayTitle(),
            'body' => $notification->displayBody(),
            'action_type' => $notification->action_type,
            'action_url' => $notification->action_url,
            'is_read' => $notification->read_at !== null,
            'created_label' => $notification->created_at?->diffForHumans(),
            'created_at' => $notification->created_at?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
        ];
    }

    /**
     * The content language (ar or en) rows are read in: ?lang= when it names one,
     * else the bundled language the caller's app language falls back to.
     */
    private function language(Request $request): string
    {
        $lang = $request->query('lang');

        return in_array($lang, ['ar', 'en'], true) ? $lang : app(ServerText::class)->contentLanguage();
    }

    /** A catalog label in the caller's app language (#12). */
    private function text(string $key, array $params = []): string
    {
        return app(ServerText::class)->get($key, $params);
    }

    private function updatedLabel(?Carbon $date): string
    {
        if (! $date) {
            return $this->text('serverMyCvsUpdatedUnknown');
        }

        return $this->text('serverMyCvsUpdated', ['date' => app(ServerText::class)->date($date)]);
    }
}
