<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class LandingContent
{
    /**
     * @var array<string, mixed>
     */
    private array $content = [
        'meta.description' => 'Sirati helps Arabic-speaking professionals analyze, improve, and generate job-ready CVs.',
        'meta.title' => 'سيرتي | Sirati',
        'branding.brand_name' => ['ar' => 'سيرتي', 'en' => 'Sirati'],
        'branding.logo_letter' => 'س',
        'nav.about' => ['ar' => 'عن سيرتي', 'en' => 'About'],
        'nav.services' => ['ar' => 'الخدمات', 'en' => 'Services'],
        'nav.social' => ['ar' => 'تابعنا', 'en' => 'Social'],
        'nav.download' => ['ar' => 'حمّل التطبيق', 'en' => 'Download'],
        'drawer.title' => ['ar' => 'القائمة', 'en' => 'Menu'],
        'drawer.subtitle' => ['ar' => 'انتقل بسرعة بين أقسام سيرتي.', 'en' => 'Move quickly through Sirati.'],
        'drawer.link_about' => ['ar' => 'عن سيرتي', 'en' => 'About'],
        'drawer.link_workflow' => ['ar' => 'طريقة العمل', 'en' => 'Workflow'],
        'drawer.link_services' => ['ar' => 'الخدمات', 'en' => 'Services'],
        'drawer.link_social' => ['ar' => 'التواصل', 'en' => 'Social'],
        'drawer.link_download' => ['ar' => 'التحميل', 'en' => 'Download'],
        'drawer.download_btn' => ['ar' => 'حمّل التطبيق الآن', 'en' => 'Download now'],
        'hero.tagline' => ['ar' => 'تحسين السيرة الذاتية بوضوح', 'en' => 'Clear CV improvement'],
        'hero.headline' => ['ar' => 'حلّل سيرتك الذاتية واصنع نسخة أقوى للتقديم', 'en' => 'Analyze your CV and create a stronger version for applications'],
        'hero.sub' => ['ar' => 'سيرتي يراجع سيرتك وفق معايير ATS ويحوّل الملاحظات إلى خطوات عملية تساعدك على التقديم بثقة.', 'en' => 'Sirati reviews your CV against ATS expectations and turns feedback into practical next steps.'],
        'hero.chip1' => ['ar' => 'تحليل ATS', 'en' => 'ATS analysis'],
        'hero.chip2' => ['ar' => 'توصيات عملية', 'en' => 'Actionable guidance'],
        'hero.chip3' => ['ar' => 'قوالب احترافية', 'en' => 'Professional templates'],
        'hero.explore' => ['ar' => 'استكشف الخدمات', 'en' => 'Explore services'],
        'hero.scroll_hint' => ['ar' => 'اكتشف المزيد', 'en' => 'Scroll'],
        'proof.item1_title' => ['ar' => 'تحليل واضح', 'en' => 'Clear analysis'],
        'proof.item1_body' => ['ar' => 'نقاط قوة وضعف قابلة للتنفيذ.', 'en' => 'Practical strengths and gaps.'],
        'proof.item2_title' => ['ar' => 'تحسين سريع', 'en' => 'Fast improvement'],
        'proof.item2_body' => ['ar' => 'اقتراحات جاهزة للتطبيق.', 'en' => 'Ready-to-use suggestions.'],
        'proof.item3_title' => ['ar' => 'تقديم بثقة', 'en' => 'Apply confidently'],
        'proof.item3_body' => ['ar' => 'سيرة منظمة ومناسبة للوظيفة.', 'en' => 'A structured, role-ready CV.'],
        'about.label' => ['ar' => 'عن سيرتي', 'en' => 'About Sirati'],
        'about.title' => ['ar' => 'مساعد عملي لتحسين سيرتك الذاتية', 'en' => 'A practical CV improvement assistant'],
        'about.body' => ['ar' => 'نركز على وضوح الخبرات، توافق الكلمات المفتاحية، وجودة الصياغة حتى تكون سيرتك أقرب لما يبحث عنه مسؤولو التوظيف.', 'en' => 'We focus on clarity, keyword fit, and stronger wording so your CV better matches what recruiters expect.'],
        'workflow.label' => ['ar' => 'طريقة العمل', 'en' => 'Workflow'],
        'workflow.title' => ['ar' => 'من السيرة الحالية إلى نسخة جاهزة للتقديم', 'en' => 'From current CV to application-ready draft'],
        'workflow.body' => ['ar' => 'ارفع سيرتك أو الصق النص، راجع نتيجة التحليل، ثم أنشئ نسخة محسنة بحسب هدفك الوظيفي.', 'en' => 'Upload or paste your CV, review the analysis, then generate an improved version for your target role.'],
        'workflow.step1_title' => ['ar' => 'أدخل السيرة', 'en' => 'Add your CV'],
        'workflow.step1_body' => ['ar' => 'ارفع ملفك أو الصق النص مباشرة.', 'en' => 'Upload a file or paste the text.'],
        'workflow.step2_title' => ['ar' => 'راجع التحليل', 'en' => 'Review insights'],
        'workflow.step2_body' => ['ar' => 'احصل على درجة وملاحظات قابلة للتنفيذ.', 'en' => 'Get a score and actionable notes.'],
        'workflow.step3_title' => ['ar' => 'ولّد نسخة محسنة', 'en' => 'Generate a better CV'],
        'workflow.step3_body' => ['ar' => 'استخدم قالبا احترافيا مناسبا للتقديم.', 'en' => 'Use a professional template for applications.'],
        'services.label' => ['ar' => 'الخدمات', 'en' => 'Services'],
        'services.title' => ['ar' => 'كل ما تحتاجه لتحسين ملفك المهني', 'en' => 'Everything needed to improve your profile'],
        'services.card1_title' => ['ar' => 'تحليل السيرة', 'en' => 'CV analysis'],
        'services.card1_body' => ['ar' => 'تقييم واضح للنقاط التي تؤثر على قبول السيرة.', 'en' => 'A clear review of what affects CV screening.'],
        'services.card2_title' => ['ar' => 'تحسين الكلمات المفتاحية', 'en' => 'Keyword fit'],
        'services.card2_body' => ['ar' => 'مواءمة المحتوى مع الوظيفة المستهدفة.', 'en' => 'Align content with the target job.'],
        'services.card3_title' => ['ar' => 'توليد سيرة محسنة', 'en' => 'CV generation'],
        'services.card3_body' => ['ar' => 'صياغة منظمة قابلة للتصدير والمشاركة.', 'en' => 'Structured wording ready to export and share.'],
        'services.card4_title' => ['ar' => 'محتوى تعليمي', 'en' => 'Career content'],
        'services.card4_body' => ['ar' => 'إرشادات تساعدك على تحسين التقديم.', 'en' => 'Guidance that improves your applications.'],
        'social.label' => ['ar' => 'تابعنا', 'en' => 'Follow us'],
        'social.title' => ['ar' => 'ابق على اطلاع بنصائح السيرة والتوظيف', 'en' => 'Stay current with CV and career tips'],
        'social.body' => ['ar' => 'ننشر إرشادات عملية تساعدك على تطوير سيرتك والاستعداد للفرص المناسبة.', 'en' => 'We share practical guidance for better CVs and stronger job applications.'],
        'social.email_label' => ['ar' => 'البريد الإلكتروني', 'en' => 'Email'],
        'social.email' => 'hello@sirati.app',
        'download.title' => ['ar' => 'حمّل التطبيق الآن', 'en' => 'Download the app now'],
        'download.body' => ['ar' => 'ابدأ تحليل سيرتك وتحسينها من هاتفك في أي وقت.', 'en' => 'Start analyzing and improving your CV from your phone anytime.'],
        'download.ios_kicker' => ['ar' => 'قريبا على', 'en' => 'Soon on'],
        'download.ios_name' => ['ar' => 'App Store', 'en' => 'App Store'],
        'download.android_kicker' => ['ar' => 'قريبا على', 'en' => 'Soon on'],
        'download.android_name' => ['ar' => 'Google Play', 'en' => 'Google Play'],
        'cta.download' => ['ar' => 'حمّل التطبيق الآن', 'en' => 'Download now'],
        'footer.rights' => ['ar' => 'جميع الحقوق محفوظة', 'en' => 'All rights reserved'],
    ];

    public function value(string $key, mixed $default = ''): mixed
    {
        $value = $this->content[$key] ?? $default;

        if (is_array($value)) {
            return $value['ar'] ?? $value['en'] ?? $default;
        }

        return $value;
    }

    public function text(string $key, string $locale = 'ar', mixed $default = ''): string
    {
        $value = $this->content[$key] ?? $default;

        if (is_array($value)) {
            return (string) ($value[$locale] ?? $value['ar'] ?? $value['en'] ?? $default);
        }

        return (string) $value;
    }

    public function pair(string $key): HtmlString
    {
        $value = $this->content[$key] ?? '';
        $ar = is_array($value) ? (string) ($value['ar'] ?? '') : (string) $value;
        $en = is_array($value) ? (string) ($value['en'] ?? $ar) : (string) $value;

        return new HtmlString('<span class="ar">'.e($ar).'</span><span class="en-text">'.e($en).'</span>');
    }

    public function image(string $key): ?string
    {
        $value = $this->content[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
