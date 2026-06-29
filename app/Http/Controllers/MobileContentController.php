<?php

namespace App\Http\Controllers;

use App\Models\CvAnalysis;
use App\Models\CvTemplate;
use App\Models\EducationContent;
use App\Models\GeneratedCv;
use App\Models\JobNews;
use App\Models\MobileNotification;
use Illuminate\Http\Request;

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
                    'title' => $latestGeneratedCv->target_job_title,
                    'score_total' => $latestGeneratedCv->score_total,
                    'updated_at' => $latestGeneratedCv->updated_at?->toISOString(),
                ] : null,
            ],
        ];
    }

    public function myCvs(Request $request): array
    {
        $language = $this->language($request);
        $items = $request->user()->generatedCvs()->latest()->limit(20)->get()->map(function (GeneratedCv $cv) use ($language): array {
            return [
                'id' => $cv->id,
                'title' => $cv->target_job_title ?: $cv->full_name,
                'updated_label' => $this->updatedLabel($cv->updated_at, $language),
                'badge' => 'ATS '.$cv->score_total.'%',
                'score_total' => $cv->score_total,
                'is_draft' => false,
                'can_download' => true,
                'pdf_url' => url("/api/generated-cvs/{$cv->id}/pdf"),
                'template_pdf_url' => url("/api/generated-cvs/{$cv->id}/download"),
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
        $items = JobNews::query()
            ->where('language', $language)
            ->active()
            ->orderBy('sort_order')
            ->latest('published_at')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (JobNews $item): array => $this->jobNewsPayload($item, $language));

        return [
            'data' => [
                'title' => $language === 'en' ? 'Job News' : 'أخبار الوظائف',
                'subtitle' => $language === 'en'
                    ? 'Fresh opportunities and hiring updates for your next step.'
                    : 'فرص وتحديثات توظيف تساعدك في خطوتك القادمة.',
                'items' => $items,
            ],
        ];
    }

    public function jobNewsShow(Request $request, JobNews $jobNews): array
    {
        $language = $this->language($request);
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
        $validUntilLabel = null;
        if ($item->valid_until !== null) {
            $validUntilLabel = $language === 'en'
                ? 'Apply by '.$item->valid_until->format('d M Y')
                : 'التقديم حتى '.$item->valid_until->translatedFormat('d F Y');
        }

        return [
            'id' => $item->id,
            'language' => $item->language,
            'title' => $item->title,
            'company' => $item->company,
            'location' => $item->location,
            'body' => $item->body,
            'url' => $item->url,
            'apply_url' => $item->apply_url,
            'valid_from' => $item->valid_from?->toDateString(),
            'valid_until' => $item->valid_until?->toDateString(),
            'valid_until_label' => $validUntilLabel,
            'published_label' => $item->published_at?->diffForHumans(),
            'published_at' => $item->published_at?->toISOString(),
        ];
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

    private function updatedLabel(?\Illuminate\Support\Carbon $date, string $language): string
    {
        if (! $date) {
            return $language === 'en' ? 'Last updated: unknown' : 'آخر تعديل: غير محدد';
        }

        return $language === 'en'
            ? 'Last updated: '.$date->format('M d, Y')
            : 'آخر تعديل: '.$date->translatedFormat('d F Y');
    }
}
