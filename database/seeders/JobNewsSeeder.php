<?php

namespace Database\Seeders;

use App\Models\JobNews;
use App\Models\JobTitle;
use App\Services\Jobs\JobTaxonomyMatcher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class JobNewsSeeder extends Seeder
{
    public function run(): void
    {
        $matcher = app(JobTaxonomyMatcher::class);
        $now = Carbon::now();

        $jobs = [
            [
                'title' => 'مطور برمجيات أول (Senior Software Engineer)',
                'company' => 'شركة علم (Elm Company)',
                'location' => 'الرياض، المملكة العربية السعودية',
                'body' => "نبحث عن مهندس برمجيات أول ذو خبرة في بناء الأنظمة السحابية والخدمات المصغرة (Microservices).\n\nالمتطلبات:\n- خبرة لا تقل عن 4 سنوات في تطوير البرمجيات.\n- إتقان تقنيات Backend (PHP/Laravel أو Node.js أو Go).\n- فهم عميق لقواعد البيانات SQL و NoSQL وبناء واجهات البرمجة RESTful APIs.\n- مهارات قوية في حل المشكلات والعمل ضمن فرق Agile.",
                'category' => 'tech',
                'url' => 'https://elm.sa/careers',
                'apply_url' => 'https://elm.sa/careers/apply',
                'external_source' => 'saudi_seeder',
                'external_id' => 'elm-se-2026',
            ],
            [
                'title' => 'مطور لارفيل وباك إند (Laravel Backend Developer)',
                'company' => 'جاهز الدولية (Jahez International)',
                'location' => 'الرياض - حي الملز',
                'body' => "تعلن شركة جاهز عن توفر شاغر مطور لارفيل للانضمام إلى فريق التطوير والابتكار.\n\nالمسؤوليات:\n- تطوير وتحسين الأنظمة الخلفية للتطبيق وإدارة قواعد البيانات.\n- بناء واجهات تكامل API عالية الأداء وقابلة للتوسع.\n- العمل على تحسين سرعة واستقرار الخدمات.",
                'category' => 'tech',
                'url' => 'https://jahez.net/careers',
                'apply_url' => 'https://jahez.net/careers',
                'external_source' => 'saudi_seeder',
                'external_id' => 'jahez-laravel-2026',
            ],
            [
                'title' => 'مطور تطبيقات فلاتر (Flutter Mobile Developer) - عن بعد',
                'company' => 'سيرتي لتقنية المعلومات (Sirati Tech)',
                'location' => 'المملكة العربية السعودية (عن بعد / Remote)',
                'body' => "فرصة عمل عن بعد للمطورين المتميزين في بناء تطبيقات الهواتف الذكية بنظام Flutter (iOS & Android).\n\nالمتطلبات:\n- خبرة عملية سنتين على الأقل في Flutter و Dart.\n- نشر تطبيقين على الأقل في متجري App Store و Google Play.\n- فهم State Management (Riverpod / Provider / Bloc).\n- شغف بتصميم تجربة مستخدم سلسة وعصرية.",
                'category' => 'tech',
                'url' => 'https://siratie.com',
                'apply_url' => 'https://siratie.com',
                'external_source' => 'saudi_seeder',
                'external_id' => 'sirati-flutter-2026',
            ],
            [
                'title' => 'أخصائي موارد بشرية واستقطاب مواهب (Talent Acquisition Specialist)',
                'company' => 'مجموعة الشايع (Alshaya Group)',
                'location' => 'جدة، المملكة العربية السعودية',
                'body' => "مطلوب أخصائي استقطاب مواهب لإدارة عمليات التوظيف ومقابلات المرشحين والمتابعة مع مدراء الأقسام.\n\nالمتطلبات:\n- بكالوريوس في الموارد البشرية أو إدارة الأعمال.\n- خبرة سنتين في التوظيف داخل السوق السعودي.\n- الإلمام بأنظمة العمل والتأمينات الاجتماعية وقوى.",
                'category' => 'hr',
                'url' => 'https://www.alshaya.com/careers',
                'apply_url' => 'https://www.alshaya.com/careers',
                'external_source' => 'saudi_seeder',
                'external_id' => 'alshaya-hr-2026',
            ],
            [
                'title' => 'محلل مالي واستثماري (Financial Analyst)',
                'company' => 'مصرف الراجحي (Al Rajhi Bank)',
                'location' => 'الرياض - الإدارة العامة',
                'body' => "يبحث مصرف الراجحي عن محلل مالي لإعداد التقارير المالية والنمذجة ودراسة الجدوى الاستثمارية.\n\nالشروط:\n- درجة البكالوريوس في المالية أو المحاسبة.\n- مهارات ممتازة في النمذجة المالية وبرنامج Excel.\n- يفضل الحصول على شهادة SOCPA أو CFA أو السعي للحصول عليها.",
                'category' => 'finance',
                'url' => 'https://www.alrajhibank.com.sa/careers',
                'apply_url' => 'https://www.alrajhibank.com.sa/careers',
                'external_source' => 'saudi_seeder',
                'external_id' => 'rajhi-fa-2026',
            ],
            [
                'title' => 'مصمم واجهات وتجربة مستخدم (UI/UX Designer)',
                'company' => 'تابي للحلول المالية (Tabby KSA)',
                'location' => 'الرياض / إمكانية العمل الهجين',
                'body' => "انضم إلى فريق التصميم في تابي لابتكار تجارب دفع وشراء فريدة لملايين المستخدمين.\n\nالمتطلبات:\n- خبرة قوية في Figma والتصميم التفاعلي وبناء Design Systems.\n- معرض أعمال (Portfolio) يوضح مشاريع حقيقية ودراسات حالة.\n- الشغف بالتفاصيل الدقيقة والبحث وسلوك المستخدم.",
                'category' => 'tech',
                'url' => 'https://tabby.ai/careers',
                'apply_url' => 'https://tabby.ai/careers',
                'external_source' => 'saudi_seeder',
                'external_id' => 'tabby-uiux-2026',
            ],
            [
                'title' => 'مدير مبيعات وحسابات كبرى (Key Account Manager)',
                'company' => 'شركة الاتصالات السعودية (stc)',
                'location' => 'الدمام والمنطقة الشرقية',
                'body' => "تعلن stc عن فتح باب التقديم لوظيفة مدير مبيعات وحسابات كبرى لقطاع الأعمال.\n\nالمسؤوليات:\n- إدارة وتنمية العلاقات مع كبار العملاء والشركات في المنطقة الشرقية.\n- تحقيق المستهدفات البيعية وتقديم حلول الاتصالات وتقنية المعلومات.",
                'category' => 'finance',
                'url' => 'https://careers.stc.com.sa',
                'apply_url' => 'https://careers.stc.com.sa',
                'external_source' => 'saudi_seeder',
                'external_id' => 'stc-kam-2026',
            ],
            [
                'title' => 'أخصائي تسويق رقمي وإعلانات (Digital Marketing Specialist)',
                'company' => 'تطبيق تمارا (Tamara)',
                'location' => 'الرياض - طريق الملك فهد',
                'body' => "نبحث عن أخصائي تسويق رقمي لإدارة الحملات الإعلانية المدفوعة وتحليل الأداء وتحسين معدلات التحويل.\n\nالمتطلبات:\n- خبرة في Google Ads, TikTok Ads, Meta Ads و SEO.\n- مهارات عالية في تحليل البيانات وتحسين العائد على الإنفاق الإعلاني (ROAS).",
                'category' => 'tech',
                'url' => 'https://tamara.co/careers',
                'apply_url' => 'https://tamara.co/careers',
                'external_source' => 'saudi_seeder',
                'external_id' => 'tamara-mkt-2026',
            ],
            [
                'title' => 'مهندس جودة وضمان برمجيات (QA / Test Automation Engineer)',
                'company' => 'شركة هنجرستيشن (HungerStation)',
                'location' => 'الرياض - واجهة الرياض',
                'body' => "فرصة لمهندسي الجودة لقيادة أتمتة الاختبارات للتطبيقات ومنصات التوصيل.\n\nالمتطلبات:\n- خبرة في كتابة اختبارات الأتمتة (Selenium, Appium, Cypress).\n- معرفة بإجراء اختبارات الحمل والأداء والأمان.",
                'category' => 'tech',
                'url' => 'https://hungerstation.com/careers',
                'apply_url' => 'https://hungerstation.com/careers',
                'external_source' => 'saudi_seeder',
                'external_id' => 'hunger-qa-2026',
            ],
            [
                'title' => 'محاسب عام وتكاليف (General Accountant)',
                'company' => 'شركة لوسيد موتورز (Lucid Motors KSA)',
                'location' => 'جدة - مدينة الملك عبدالله الاقتصادية',
                'body' => "مطلوب محاسب عام لإدارة القيود اليومية والمطابقات البنكية وإقرارات ضريبة القيمة المضافة ZATCA.\n\nالمتطلبات:\n- بكالوريوس محاسبة مع خبرة 2-3 سنوات.\n- إتقان برامج ERP (SAP أو Oracle).\n- معرفة بأنظمة هيئة الزكاة والضريبة والجمارك.",
                'category' => 'finance',
                'url' => 'https://www.lucidmotors.com/careers',
                'apply_url' => 'https://www.lucidmotors.com/careers',
                'external_source' => 'saudi_seeder',
                'external_id' => 'lucid-acc-2026',
            ],
            [
                'title' => 'محلل بيانات وذكاء أعمال (Data & BI Analyst)',
                'company' => 'أرامكو لزيوت الأساس - لوبريف (Luberef)',
                'location' => 'ينبع / جدة',
                'body' => "تعلن لوبريف عن رغبتها في استقطاب محلل بيانات لبناء لوحات القيادة (Power BI) واستخراج الرؤى الاستراتيجية.\n\nالمتطلبات:\n- إتقان SQL و Python للتحليل.\n- خبرة في نمذجة البيانات وتطوير لوحات Power BI / Tableau.",
                'category' => 'tech',
                'url' => 'https://www.luberef.com/careers',
                'apply_url' => 'https://www.luberef.com/careers',
                'external_source' => 'saudi_seeder',
                'external_id' => 'luberef-data-2026',
            ],
            [
                'title' => 'مدير مشاريع تقنية (Technical Project Manager / Scrum Master)',
                'company' => 'سدير للأدوية والتقنية (Sudair Pharma)',
                'location' => 'الرياض / سدير',
                'body' => "إدارة وتوجيه مشاريع التحول الرقمي ومتابعة فرق التطوير وفق منهجية Agile/Scrum.\n\nالمتطلبات:\n- شهادة PMP أو PMI-ACP أو CSM.\n- خبرة لا تقل عن 4 سنوات في إدارة المشاريع التقنية.",
                'category' => 'tech',
                'url' => 'https://sudairpharma.com/careers',
                'apply_url' => 'https://sudairpharma.com/careers',
                'external_source' => 'saudi_seeder',
                'external_id' => 'sudair-pm-2026',
            ],
        ];

        foreach ($jobs as $index => $item) {
            $match = $matcher->match($item['title'], $item['body'], $item['location']);

            JobNews::query()->updateOrCreate(
                [
                    'external_source' => $item['external_source'],
                    'external_id' => $item['external_id'],
                ],
                [
                    'language' => 'ar',
                    'title' => $item['title'],
                    'company' => $item['company'],
                    'location' => $item['location'],
                    'city' => $match['city'],
                    'is_remote' => $match['is_remote'],
                    'body' => $item['body'],
                    'category' => $match['category'] ?? $item['category'],
                    'job_title_id' => $match['job_title_id'],
                    'url' => $item['url'],
                    'apply_url' => $item['apply_url'],
                    'published_at' => $now->copy()->subHours($index * 4),
                    'valid_from' => $now->copy()->subDays(1),
                    'valid_until' => $now->copy()->addDays(60),
                    'sort_order' => $index + 1,
                    'is_published' => true,
                    'source' => 'Saudi Aggregator',
                ]
            );
        }
    }
}
