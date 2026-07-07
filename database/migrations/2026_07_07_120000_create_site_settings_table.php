<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group')->index();
            $table->string('type', 20)->default('text'); // text, textarea, image, url, plain
            $table->string('label');
            $table->text('value_ar')->nullable();
            $table->text('value_en')->nullable();
            $table->text('value')->nullable(); // non-bilingual values (image path, url, plain)
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $order = 0;
        $rows = [];

        foreach ($this->defaults() as $row) {
            $rows[] = [
                'key' => $row['key'],
                'group' => $row['group'],
                'type' => $row['type'],
                'label' => $row['label'],
                'value_ar' => $row['ar'] ?? null,
                'value_en' => $row['en'] ?? null,
                'value' => $row['value'] ?? null,
                'sort_order' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('site_settings')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function defaults(): array
    {
        return [
            // Branding & meta
            ['key' => 'branding.logo_image', 'group' => 'branding', 'type' => 'image', 'label' => 'Logo image (nav & hero)', 'value' => null],
            ['key' => 'branding.brand_name', 'group' => 'branding', 'type' => 'text', 'label' => 'Brand name', 'ar' => 'سيرتي', 'en' => 'Sirati'],
            ['key' => 'branding.logo_letter', 'group' => 'branding', 'type' => 'plain', 'label' => 'Logo letter (used when no image)', 'value' => 'س'],
            ['key' => 'meta.title', 'group' => 'branding', 'type' => 'plain', 'label' => 'Browser tab title', 'value' => 'سيرتي | Sirati'],
            ['key' => 'meta.description', 'group' => 'branding', 'type' => 'plain', 'label' => 'Meta description (SEO)', 'value' => 'سيرتي منصة عربية لتطوير المسار المهني، بناء السيرة الذاتية، تحليلها، واكتشاف فرص العمل في السوق السعودي والخليجي.'],

            // Navigation
            ['key' => 'nav.about', 'group' => 'nav', 'type' => 'text', 'label' => 'Nav: About', 'ar' => 'من نحن', 'en' => 'About'],
            ['key' => 'nav.services', 'group' => 'nav', 'type' => 'text', 'label' => 'Nav: Services', 'ar' => 'خدماتنا', 'en' => 'Services'],
            ['key' => 'nav.social', 'group' => 'nav', 'type' => 'text', 'label' => 'Nav: Follow', 'ar' => 'تابعنا', 'en' => 'Follow'],
            ['key' => 'nav.download', 'group' => 'nav', 'type' => 'text', 'label' => 'Nav: Download', 'ar' => 'تحميل التطبيق', 'en' => 'Download'],

            // Mobile drawer
            ['key' => 'drawer.title', 'group' => 'drawer', 'type' => 'text', 'label' => 'Drawer title', 'ar' => 'القائمة', 'en' => 'Menu'],
            ['key' => 'drawer.subtitle', 'group' => 'drawer', 'type' => 'text', 'label' => 'Drawer subtitle', 'ar' => 'تنقّل بين أقسام المنصة الرئيسية.', 'en' => 'Move between the main parts of the platform.'],
            ['key' => 'drawer.link_about', 'group' => 'drawer', 'type' => 'text', 'label' => 'Drawer link: About', 'ar' => 'من نحن', 'en' => 'About'],
            ['key' => 'drawer.link_workflow', 'group' => 'drawer', 'type' => 'text', 'label' => 'Drawer link: How it works', 'ar' => 'كيف يعمل', 'en' => 'How it works'],
            ['key' => 'drawer.link_services', 'group' => 'drawer', 'type' => 'text', 'label' => 'Drawer link: Services', 'ar' => 'خدماتنا', 'en' => 'Services'],
            ['key' => 'drawer.link_social', 'group' => 'drawer', 'type' => 'text', 'label' => 'Drawer link: Follow', 'ar' => 'تابعنا', 'en' => 'Follow'],
            ['key' => 'drawer.link_download', 'group' => 'drawer', 'type' => 'text', 'label' => 'Drawer link: Start now', 'ar' => 'ابدأ الآن', 'en' => 'Start now'],
            ['key' => 'drawer.download_btn', 'group' => 'drawer', 'type' => 'text', 'label' => 'Drawer button: Download', 'ar' => 'حمّل التطبيق', 'en' => 'Download the app'],

            // Hero
            ['key' => 'hero.tagline', 'group' => 'hero', 'type' => 'text', 'label' => 'Hero tagline', 'ar' => 'منصة المسار المهني', 'en' => 'Career Development Platform'],
            ['key' => 'hero.headline', 'group' => 'hero', 'type' => 'textarea', 'label' => 'Hero headline', 'ar' => 'حلل سيرتك وابن نسخة أقوى.', 'en' => 'Analyze your CV and build a stronger version.'],
            ['key' => 'hero.sub', 'group' => 'hero', 'type' => 'textarea', 'label' => 'Hero subtext', 'ar' => 'أدوات عملية لتحسين السيرة الذاتية والتجهيز للتقديم.', 'en' => 'Practical tools for CV analysis, improvement, and job applications.'],
            ['key' => 'hero.chip1', 'group' => 'hero', 'type' => 'text', 'label' => 'Hero chip 1', 'ar' => 'جاهزة لأنظمة الفرز', 'en' => 'ATS-ready'],
            ['key' => 'hero.chip2', 'group' => 'hero', 'type' => 'text', 'label' => 'Hero chip 2', 'ar' => 'عربية أولاً', 'en' => 'Arabic first'],
            ['key' => 'hero.chip3', 'group' => 'hero', 'type' => 'text', 'label' => 'Hero chip 3', 'ar' => 'وظائف السعودية والخليج', 'en' => 'Saudi and Gulf jobs'],
            ['key' => 'hero.explore', 'group' => 'hero', 'type' => 'text', 'label' => 'Hero: Explore link', 'ar' => 'تعرّف على خدماتنا', 'en' => 'Explore our services'],
            ['key' => 'hero.scroll_hint', 'group' => 'hero', 'type' => 'text', 'label' => 'Hero: Scroll hint', 'ar' => 'اكتشف أكثر', 'en' => 'Discover more'],

            // Proof band
            ['key' => 'proof.item1_title', 'group' => 'proof', 'type' => 'text', 'label' => 'Proof 1 title', 'ar' => 'تحليل واضح', 'en' => 'Clear analysis'],
            ['key' => 'proof.item1_body', 'group' => 'proof', 'type' => 'text', 'label' => 'Proof 1 text', 'ar' => 'اعرف ما الذي يعيق سيرتك الذاتية بسرعة.', 'en' => 'See what is blocking the CV quickly.'],
            ['key' => 'proof.item2_title', 'group' => 'proof', 'type' => 'text', 'label' => 'Proof 2 title', 'ar' => 'تعديلات عملية', 'en' => 'Practical edits'],
            ['key' => 'proof.item2_body', 'group' => 'proof', 'type' => 'text', 'label' => 'Proof 2 text', 'ar' => 'حوّل الملاحظات إلى صياغة أفضل.', 'en' => 'Turn feedback into better wording.'],
            ['key' => 'proof.item3_title', 'group' => 'proof', 'type' => 'text', 'label' => 'Proof 3 title', 'ar' => 'جاهزة للتقديم', 'en' => 'Ready to apply'],
            ['key' => 'proof.item3_body', 'group' => 'proof', 'type' => 'text', 'label' => 'Proof 3 text', 'ar' => 'انتقل من التحليل إلى سيرة ذاتية أقوى.', 'en' => 'Move from analysis to a stronger CV.'],

            // About
            ['key' => 'about.label', 'group' => 'about', 'type' => 'text', 'label' => 'About label', 'ar' => 'من نحن :', 'en' => 'About Us :'],
            ['key' => 'about.title', 'group' => 'about', 'type' => 'textarea', 'label' => 'About title', 'ar' => 'منصة إلكترونية متخصصة في تطوير مسارك المهني', 'en' => 'A digital platform specialized in developing your professional career'],
            ['key' => 'about.body', 'group' => 'about', 'type' => 'textarea', 'label' => 'About body', 'ar' => 'سيرتي منصة إلكترونية متخصصة في تطوير المسار المهني، نقدم مجموعة متكاملة من الخدمات التي تجمع بين الذكاء التقني وفهم متطلبات سوق العمل في المملكة العربية السعودية والخليج. نؤمن بأن كل محترف يستحق أدوات احترافية تفتح له الأبواب.', 'en' => 'Sirati is a digital platform specialized in career development, offering an integrated suite of services that combine technical intelligence with a deep understanding of the job market in Saudi Arabia and the Gulf. We believe every professional deserves the right tools to open doors.'],

            // Workflow
            ['key' => 'workflow.label', 'group' => 'workflow', 'type' => 'text', 'label' => 'Workflow label', 'ar' => 'كيف يعمل', 'en' => 'How it works'],
            ['key' => 'workflow.title', 'group' => 'workflow', 'type' => 'text', 'label' => 'Workflow title', 'ar' => 'ثلاث خطوات نحو سيرة ذاتية أقوى', 'en' => 'Three steps to a better CV'],
            ['key' => 'workflow.body', 'group' => 'workflow', 'type' => 'textarea', 'label' => 'Workflow body', 'ar' => 'ارفع سيرتك الذاتية، راجع التوصيات الواضحة، ثم أنشئ نسخة أقوى وأكثر احترافية.', 'en' => 'Upload your CV, review clear recommendations, then generate a stronger version.'],
            ['key' => 'workflow.step1_title', 'group' => 'workflow', 'type' => 'text', 'label' => 'Step 1 title', 'ar' => 'التحليل', 'en' => 'Analyze'],
            ['key' => 'workflow.step1_body', 'group' => 'workflow', 'type' => 'text', 'label' => 'Step 1 text', 'ar' => 'اكتشف مشاكل أنظمة الفرز الآلي، والكلمات المفتاحية الناقصة، والأقسام الضعيفة.', 'en' => 'Find ATS issues, missing keywords, and weak sections.'],
            ['key' => 'workflow.step2_title', 'group' => 'workflow', 'type' => 'text', 'label' => 'Step 2 title', 'ar' => 'التحسين', 'en' => 'Improve'],
            ['key' => 'workflow.step2_body', 'group' => 'workflow', 'type' => 'text', 'label' => 'Step 2 text', 'ar' => 'حوّل التوصيات إلى صياغة أقوى وجاهزة لأنظار مسؤولي التوظيف.', 'en' => 'Turn recommendations into stronger, recruiter-ready wording.'],
            ['key' => 'workflow.step3_title', 'group' => 'workflow', 'type' => 'text', 'label' => 'Step 3 title', 'ar' => 'التقديم', 'en' => 'Apply'],
            ['key' => 'workflow.step3_body', 'group' => 'workflow', 'type' => 'text', 'label' => 'Step 3 text', 'ar' => 'استخدم سيرة ذاتية نظيفة وجاهزة وتقدّم للوظائف بسرعة أكبر.', 'en' => 'Use a clean generated CV and move faster on applications.'],

            // Services
            ['key' => 'services.label', 'group' => 'services', 'type' => 'text', 'label' => 'Services label', 'ar' => 'خدماتنا :', 'en' => 'Our Services :'],
            ['key' => 'services.title', 'group' => 'services', 'type' => 'textarea', 'label' => 'Services title', 'ar' => 'كل ما تحتاجه لمسارك المهني في مكان واحد', 'en' => 'Everything you need for your career, in one place'],
            ['key' => 'services.card1_icon', 'group' => 'services', 'type' => 'plain', 'label' => 'Service 1 icon (emoji)', 'value' => '📂'],
            ['key' => 'services.card1_title', 'group' => 'services', 'type' => 'text', 'label' => 'Service 1 title', 'ar' => 'بناء السيرة الذاتية الاحترافية', 'en' => 'Professional CV Builder'],
            ['key' => 'services.card1_body', 'group' => 'services', 'type' => 'textarea', 'label' => 'Service 1 text', 'ar' => 'نساعدك في إنشاء سيرة ذاتية وفق المعايير الدولية، مهيأة للنجاح في أنظمة فرز السير الذاتية الآلي، مع إمكانية التعديل عليها لاحقاً.', 'en' => 'We help you create a CV to international standards, optimized for Applicant Tracking Systems, with the ability to edit it at any time.'],
            ['key' => 'services.card2_icon', 'group' => 'services', 'type' => 'plain', 'label' => 'Service 2 icon (emoji)', 'value' => '📈'],
            ['key' => 'services.card2_title', 'group' => 'services', 'type' => 'text', 'label' => 'Service 2 title', 'ar' => 'تحليل السيرة الذاتية', 'en' => 'CV Analysis'],
            ['key' => 'services.card2_body', 'group' => 'services', 'type' => 'textarea', 'label' => 'Service 2 text', 'ar' => 'نحلل سيرتك الذاتية ونكشف نقاط ضعفها أمام أنظمة Applicant Tracking System، ونقدم توصيات عملية لاجتياز الفرز الآلي الذي تعتمده الشركات الكبرى.', 'en' => 'We analyze your CV and reveal its weaknesses against Applicant Tracking Systems, offering practical recommendations to pass automated screening used by top companies.'],
            ['key' => 'services.card3_icon', 'group' => 'services', 'type' => 'plain', 'label' => 'Service 3 icon (emoji)', 'value' => '💼'],
            ['key' => 'services.card3_title', 'group' => 'services', 'type' => 'text', 'label' => 'Service 3 title', 'ar' => 'فرص العمل', 'en' => 'Job Opportunities'],
            ['key' => 'services.card3_body', 'group' => 'services', 'type' => 'textarea', 'label' => 'Service 3 text', 'ar' => 'نوفر قاعدة محدثة من الوظائف في المملكة العربية السعودية، تشمل الجهات الحكومية والقطاع الخاص.', 'en' => 'We provide an updated database of jobs in Saudi Arabia, covering both government entities and the private sector.'],
            ['key' => 'services.card4_icon', 'group' => 'services', 'type' => 'plain', 'label' => 'Service 4 icon (emoji)', 'value' => '📚'],
            ['key' => 'services.card4_title', 'group' => 'services', 'type' => 'text', 'label' => 'Service 4 title', 'ar' => 'التعليم والتوجيه المهني', 'en' => 'Education & Career Guidance'],
            ['key' => 'services.card4_body', 'group' => 'services', 'type' => 'textarea', 'label' => 'Service 4 text', 'ar' => 'نقدم محتوى تعليمياً مستمراً وإرشاداً متخصصاً لمساعدتك في بناء مسارك المهني وتطويره نحو الأفضل.', 'en' => 'We provide continuous educational content and specialized guidance to help you build and advance your professional career.'],

            // Social
            ['key' => 'social.label', 'group' => 'social', 'type' => 'text', 'label' => 'Social label', 'ar' => 'تابعنا :', 'en' => 'Follow Us :'],
            ['key' => 'social.title', 'group' => 'social', 'type' => 'text', 'label' => 'Social title', 'ar' => 'نحن على منصاتك المفضلة', 'en' => "We're on your favourite platforms"],
            ['key' => 'social.body', 'group' => 'social', 'type' => 'textarea', 'label' => 'Social body', 'ar' => 'تابع سيرتي وابق على اطلاع بأحدث فرص العمل والنصائح المهنية والمحتوى التعليمي.', 'en' => 'Follow Sirati and stay up to date with the latest job opportunities, career tips, and educational content.'],
            ['key' => 'social.tiktok_url', 'group' => 'social', 'type' => 'url', 'label' => 'TikTok URL', 'value' => 'https://www.tiktok.com/@sirati'],
            ['key' => 'social.instagram_url', 'group' => 'social', 'type' => 'url', 'label' => 'Instagram URL', 'value' => 'https://www.instagram.com/sirati'],
            ['key' => 'social.linkedin_url', 'group' => 'social', 'type' => 'url', 'label' => 'LinkedIn URL', 'value' => 'https://www.linkedin.com/company/sirati'],
            ['key' => 'social.email', 'group' => 'social', 'type' => 'url', 'label' => 'Contact email', 'value' => 'hello@sirati.app'],
            ['key' => 'social.email_label', 'group' => 'social', 'type' => 'text', 'label' => 'Email card label', 'ar' => 'تواصل معنا عبر الإيميل', 'en' => 'Contact us via Email'],

            // Download
            ['key' => 'download.title', 'group' => 'download', 'type' => 'text', 'label' => 'Download title', 'ar' => 'حمّل التطبيق الآن', 'en' => 'Download the App Now'],
            ['key' => 'download.body', 'group' => 'download', 'type' => 'textarea', 'label' => 'Download body', 'ar' => 'جميع الخدمات متاحة داخل التطبيق. ابدأ مسيرتك المهنية الصحيحة اليوم.', 'en' => 'All services are available inside the app. Start your professional journey the right way, today.'],
            ['key' => 'download.ios_kicker', 'group' => 'download', 'type' => 'text', 'label' => 'App Store kicker', 'ar' => 'قريباً على', 'en' => 'Coming soon on'],
            ['key' => 'download.ios_name', 'group' => 'download', 'type' => 'text', 'label' => 'App Store name', 'ar' => 'App Store', 'en' => 'App Store'],
            ['key' => 'download.android_kicker', 'group' => 'download', 'type' => 'text', 'label' => 'Google Play kicker', 'ar' => 'قريباً على', 'en' => 'Coming soon on'],
            ['key' => 'download.android_name', 'group' => 'download', 'type' => 'text', 'label' => 'Google Play name', 'ar' => 'Google Play', 'en' => 'Google Play'],

            // Sticky CTA + footer
            ['key' => 'cta.download', 'group' => 'footer', 'type' => 'text', 'label' => 'Mobile sticky CTA', 'ar' => 'حمّل التطبيق الآن', 'en' => 'Download the app'],
            ['key' => 'footer.rights', 'group' => 'footer', 'type' => 'text', 'label' => 'Footer rights text', 'ar' => 'جميع الحقوق محفوظة.', 'en' => 'All rights reserved. Our services are available inside the app only.'],
        ];
    }
};
