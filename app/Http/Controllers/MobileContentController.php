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
                    'status' => $language === 'en' ? 'Active Account' : 'حساب نشط',
                ],
                'stats' => [
                    'generated_cvs' => $user->generatedCvs()->count(),
                    'analyses' => $user->cvAnalyses()->count(),
                    'unread_notifications' => $user->mobileNotifications()->whereNull('read_at')->count(),
                ],
                'primary_action' => [
                    'title' => $language === 'en' ? 'Create ATS-Optimized CV' : 'أنشئ سيرة ذاتية وفق ATS',
                    'subtitle' => $language === 'en'
                        ? 'Build your CV step by step and get a professional design that passes screening systems.'
                        : 'ابن سيرتك خطوة بخطوة واحصل على تصميم احترافي يتجاوز أنظمة الفرز.',
                    'button_label' => $language === 'en' ? 'Start Now' : 'ابدأ الآن',
                ],
                'analysis_action' => [
                    'title' => $language === 'en' ? 'Analyze Your CV with ATS' : 'حلّل سيرتك الذاتية بـ ATS',
                    'subtitle' => $language === 'en'
                        ? 'Upload your CV and discover its strengths and match with target jobs.'
                        : 'ارفع سيرتك واعرف نقاط قوتها ومدى توافقها مع الوظائف المستهدفة.',
                    'button_label' => $language === 'en' ? 'Analyze Now' : 'تحليل الآن',
                ],
                'latest_news' => [
                    'title' => $language === 'en' ? 'New opportunities in tech' : 'فرص عمل جديدة في مجال التقنية',
                    'subtitle' => $language === 'en' ? '2 hours ago · Riyadh' : 'منذ ساعتين · الرياض',
                ],
                'latest_cv' => $latestGeneratedCv ? [
                    'id' => $latestGeneratedCv->id,
                    'title' => $this->displayTitles($request, [$latestGeneratedCv], $language)[$latestGeneratedCv->id] ?? $latestGeneratedCv->target_job_title,
                    'score_total' => $latestGeneratedCv->score_total,
                    'updated_at' => $latestGeneratedCv->updated_at?->toISOString(),
                ] : null,
            ],
        ];
    }

    /**
     * A copy created by applying Job Match edits keeps its original's headline, so the CV
     * lists show it as "<headline> — <job title>" (or "(tailored)") to tell the two apart.
     *
     * @param  iterable<GeneratedCv>  $cvs
     * @return array<int, string> display title by generated CV id
     */
    private function displayTitles(Request $request, iterable $cvs, string $language): array
    {
        $cvs = collect($cvs);
        $matches = JobMatch::where('user_id', $request->user()->id)
            ->whereIn('applied_generated_cv_id', $cvs->pluck('id'))
            ->get(['applied_generated_cv_id', 'target_job_title'])
            ->keyBy('applied_generated_cv_id');

        return $cvs->mapWithKeys(function (GeneratedCv $cv) use ($matches, $language): array {
            $base = (string) ($cv->target_job_title ?: $cv->full_name);
            $match = $matches->get($cv->id);
            if ($match === null) {
                return [$cv->id => $base];
            }
            $job = trim((string) $match->target_job_title);
            $title = $job !== '' && mb_strtolower($job) !== mb_strtolower(trim($base))
                ? $base.' — '.$job
                : $base.($language === 'en' ? ' (tailored)' : ' (مخصصة)');

            return [$cv->id => $title];
        })->all();
    }

    public function myCvs(Request $request): array
    {
        $language = $this->language($request);
        $publicBaseUrl = rtrim((string) config('app.public_url', 'https://siratie.com'), '/');
        $cvs = $request->user()->generatedCvs()->latest()->limit(20)->get();
        $titles = $this->displayTitles($request, $cvs, $language);
        $items = $cvs->map(function (GeneratedCv $cv) use ($language, $publicBaseUrl, $titles): array {
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
                'updated_label' => $this->updatedLabel($cv->updated_at, $language),
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
                'title' => $language === 'en' ? 'My CVs' : 'سيرتي الذاتية',
                'summary' => $language === 'en'
                    ? 'You have '.$items->count().' draft and completed files'
                    : 'لديك '.$items->count().' ملفات مسودة ومكتملة',
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
                'title' => $language === 'en' ? 'CV Designs' : 'تصاميم السيرة الذاتية',
                'subtitle' => $language === 'en'
                    ? 'Choose the design that fits your next application.'
                    : 'اختر التصميم الأنسب لتقديمك القادم.',
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
                'duration' => $content->duration_label ?: ($language === 'en' ? 'Reading time: 10 min' : 'مدة القراءة: ١٠ دقائق'),
            ]);

        $featuredContent = $contents
            ->where('type', $selectedType)
            ->where('is_featured', true)
            ->first();

        $featuredCourse = $featuredContent ? [
            'id' => $featuredContent->id,
            'badge' => $featuredContent->badge ?: ($language === 'en' ? 'Recommended' : 'موصى به لك'),
            'title' => $featuredContent->title,
            'body' => $featuredContent->body,
            'button_label' => $featuredContent->button_label ?: ($language === 'en' ? 'Start Learning' : 'ابدأ التعلم الآن'),
        ] : null;

        $targetRole = $contents->firstWhere('target_role')?->target_role
            ?? ($language === 'en' ? 'Data Analyst' : 'محلل بيانات');

        return [
            'data' => [
                'profile' => [
                    'name' => $language === 'en' ? 'Ahmed' : 'أحمد',
                ],
                'title' => $language === 'en' ? 'Learning & Development' : 'التعلم والتطوير',
                'subtitle' => $language === 'en' ? 'Content tailored to your target job' : 'محتوى مخصص حسب وظيفتك المستهدفة',
                'target_label' => $language === 'en' ? 'Based on your target job' : 'حسب وظيفتك المستهدفة',
                'target_role' => $targetRole,
                'tabs' => [
                    ['key' => 'news', 'label' => $language === 'en' ? 'News' : 'أخبار'],
                    ['key' => 'certificate', 'label' => $language === 'en' ? 'Certificates' : 'شهادات'],
                    ['key' => 'study', 'label' => $language === 'en' ? 'Study' : 'دراسة'],
                ],
                'selected_type' => $selectedType,
                'study_cards' => $studyCards->isNotEmpty() ? $studyCards : [
                    [
                        'icon' => 'book',
                        'title' => $language === 'en' ? 'Big Data Analysis Basics' : 'أساسيات تحليل البيانات الضخمة',
                        'body' => $language === 'en'
                            ? 'Learn the essential tools and methods for working with large datasets.'
                            : 'تعرف على الأدوات والمنهجيات الأساسية للتعامل مع مجموعات البيانات الكبيرة.',
                        'duration' => $language === 'en' ? 'Reading time: 15 min' : 'مدة القراءة: ١٥ دقيقة',
                    ],
                    [
                        'icon' => 'psychology',
                        'title' => $language === 'en' ? 'Analytical Thinking at Work' : 'التفكير التحليلي في بيئة العمل',
                        'body' => $language === 'en'
                            ? 'Turn raw data into effective strategic decisions.'
                            : 'كيفية تحويل البيانات الخام إلى قرارات استراتيجية فعالة ومدروسة.',
                        'duration' => $language === 'en' ? 'Reading time: 10 min' : 'مدة القراءة: ١٠ دقائق',
                    ],
                ],
                'featured_course' => $featuredCourse ?: [
                    'badge' => $language === 'en' ? 'Recommended' : 'موصى به لك',
                    'title' => $language === 'en' ? 'SQL Mastery for Beginners' : 'رحلة احتراف SQL للمبتدئين',
                    'body' => $language === 'en'
                        ? 'A complete learning path from zero to advanced queries.'
                        : 'مسار تعليمي متكامل يأخذك من الصفر حتى بناء استعلامات معقدة.',
                    'button_label' => $language === 'en' ? 'Start Learning' : 'ابدأ التعلم الآن',
                ],
            ],
        ];
    }

    public function educationShow(Request $request, EducationContent $educationContent): array
    {
        abort_unless($educationContent->is_published, 404);

        $language = $this->language($request);

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
                    ?: ($language === 'en' ? 'Start Learning' : 'ابدأ التعلم الآن'),
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

        $query = JobNews::query()
            ->with('jobTitle')
            ->where('language', $language)
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

        // Direct filters
        if ($isVerified === true) {
            $query->where(function ($q): void {
                $q->where('is_verified', true)
                    ->orWhereIn('source_type', ['gov', 'enterprise', 'tamheer']);
            });
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

        if ($region !== null && isset(self::REGION_CITIES[$region])) {
            $query->whereIn('city', self::REGION_CITIES[$region]);
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
            ->map(fn (JobNews $item): array => $this->jobNewsPayload($item, $language))
            ->values();

        return [
            'data' => [
                'title' => $language === 'en' ? 'Job News' : 'أخبار الوظائف',
                'subtitle' => $language === 'en'
                    ? 'Fresh verified opportunities tailored to your profile.'
                    : 'فرص وتحديثات توظيف موثقة ومخصصة لملفك المهني.',
                'selected_category' => $category ?? 'all',
                'selected_preset' => $preset,
                'selected_region' => $region !== null && isset(self::REGION_CITIES[$region]) ? $region : 'all',
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
        $language = $this->language($request);
        $user = $request->user('sanctum') ?? $request->user();
        $today = today();

        abort_unless(
            $jobNews->is_published
                && ($jobNews->valid_from === null || $jobNews->valid_from->lessThanOrEqualTo($today))
                && ($jobNews->valid_until === null || $jobNews->valid_until->greaterThanOrEqualTo($today)),
            404
        );

        return ['data' => $this->jobNewsPayload($jobNews, $language)];
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

        return ['message' => 'تم تعليم كل الإشعارات كمقروءة.'];
    }

    private function jobNewsPayload(JobNews $item, string $language = 'ar'): array
    {
        // Aggregated posts retire on an internal date, not a stated deadline,
        // so only curated listings show "apply by".
        $validUntilLabel = null;
        if ($item->valid_until !== null && $item->external_source === null) {
            $validUntilLabel = $language === 'en'
                ? 'Apply by '.$item->valid_until->format('d M Y')
                : 'التقديم حتى '.$this->latinDigits($item->valid_until->translatedFormat('d F Y'));
        }

        $formatter = app(JobProfileMatcherService::class);
        $sourceInfo = $formatter->formatSourceType($item->source_type ?? 'general', $language);
        $salaryRange = $formatter->formatSalary($item->salary_min, $item->salary_max, $language);

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

    /** Saudi regions as used by the app's region chips, by stored city code. */
    private const REGION_CITIES = [
        'central' => ['riyadh', 'qassim'],
        'eastern' => ['dammam', 'khobar', 'dhahran', 'jubail'],
        'western' => ['jeddah', 'mecca', 'medina'],
        'northern' => ['tabuk'],
        'southern' => ['abha'],
    ];

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
            'title' => $notification->title,
            'body' => $notification->body,
            'action_type' => $notification->action_type,
            'action_url' => $notification->action_url,
            'is_read' => $notification->read_at !== null,
            'created_label' => $notification->created_at?->diffForHumans(),
            'created_at' => $notification->created_at?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
        ];
    }

    private function language(Request $request): string
    {
        return $request->query('lang') === 'en' ? 'en' : 'ar';
    }

    private function updatedLabel(?Carbon $date, string $language): string
    {
        if (! $date) {
            return $language === 'en' ? 'Last updated: unknown' : 'آخر تعديل: غير محدد';
        }

        return $language === 'en'
            ? 'Last updated: '.$date->format('M d, Y')
            : 'آخر تعديل: '.$this->latinDigits($date->translatedFormat('d F Y'));
    }

    /**
     * Force Latin (Western) digits inside a string.
     *
     * Carbon's translatedFormat() with an Arabic locale returns Arabic month
     * names AND Arabic-Indic digits (١٥ يناير ٢٠٢٦). Product decision is that
     * numbers stay 0-9 across the app even in Arabic UI, so we keep the
     * translated month name but transliterate the digits back to Latin.
     */
    private function latinDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            // Extended Arabic-Indic (used by some Farsi/Urdu locales)
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
