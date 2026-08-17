<?php

namespace App\Services\Ai\BakeOff;

/**
 * 30 anonymised Arabic/MENA CV fixtures for the OpenAI vs Claude quality bake-off.
 *
 * Names, employers, and contact details are synthetic. Content is realistic for
 * Gulf/MENA hiring contexts across the 8 AtsScoringService categories.
 *
 * @phpstan-type Fixture array{
 *   id: string,
 *   category: string,
 *   target_job_title: string,
 *   resume_text: string
 * }
 */
final class ArabicCvCorpus
{
    /**
     * @return list<Fixture>
     */
    public static function all(): array
    {
        return [
            self::cv('cv-01', 'software', 'مطور Laravel', <<<'CV'
أحمد فهد العتيبي
الرياض، السعودية | email@example.com | +966500000001 | linkedin.com/in/example

ملخص
مطور خلفية بخبرة 4 سنوات في بناء واجهات برمجة تطبيقات Laravel وخدمات REST للقطاع التقني.

المهارات
PHP, Laravel, API, SQL, Git, Agile, Scrum, Backend, MySQL

الخبرات
مطور Backend — شركة تقنية بالرياض، 2021–2025
- طورت APIs لخدمة 30 مستخدماً داخلياً.
- حسّنت زمن التقارير بنسبة 28% عبر استعلامات SQL محسّنة.
- شاركت في مراجعات كود ضمن دورة Agile.

التعليم
بكالوريوس علوم حاسب، جامعة الملك سعود، 2020

الشهادات
AWS Cloud Practitioner
CV),
            self::cv('cv-02', 'software', 'مهندس برمجيات', <<<'CV'
نورة سعد القحطاني
جدة | email@example.com | +966500000002

نبذة
مهندسة برمجيات تركز على تطوير الأنظمة وحلول الواجهة الخلفية باستخدام Python وNode.

المهارات
Python, Node, API, SQL, Docker, Git, Backend

الخبرة
مهندسة برمجيات — شركة خدمات رقمية، 2019–2024
- بنيت خدمات backend لتكامل أنظمة داخلية.
- خفضت أعطال الإنتاج عبر مراقبة السجلات.
- وثقت واجهات API للفرق الأخرى.

التعليم
بكالوريوس هندسة برمجيات، 2018
CV),
            self::cv('cv-03', 'software', 'مطور تطبيقات جوال Flutter', <<<'CV'
خالد منصور الحربي
الدمام | email@example.com | +966500000003

ملخص مهني
مطور جوال بخبرة في Flutter وواجهات المستخدم لتطبيقات B2C في السوق السعودي.

المهارات
Flutter, Dart, Mobile, API, Git, Firebase, UI

الخبرات
مطور Flutter — استوديو تطبيقات، 2020–2025
- أطلقت تطبيقين على iOS وAndroid لعملاء تجزئة.
- ربطت التطبيق بواجهات API وFirebase.
- حسّنت زمن التحميل الأولي بنسبة 20%.

التعليم
دبلوم تقنية معلومات، 2019
CV),
            self::cv('cv-04', 'software', 'مطور واجهات أمامية', <<<'CV'
سارة علي الزهراني
الرياض | email@example.com | +966500000004

ملخص
مطورة Frontend متخصصة في React وJavaScript لواجهات عربية/إنجليزية.

المهارات
JavaScript, React, CSS, HTML, Git, Frontend, API

الخبرة
مطورة واجهات — وكالة رقمية، 2021–2025
- طورت لوحات تحكم React لفرق العمليات.
- حسّنت تجربة RTL للواجهات العربية.
- تعاونت مع فريق backend عبر REST API.

التعليم
بكالوريوس تقنية معلومات، 2020
CV),
            self::cv('cv-05', 'marketing', 'أخصائي تسويق رقمي', <<<'CV'
ريم عبدالله الشمري
الرياض | email@example.com | +966500000005

ملخص
أخصائية تسويق رقمي بخبرة في SEO وGoogle Ads وحملات المحتوى للعلامات التجارية في الخليج.

المهارات
Marketing, SEO, SEM, Google Ads, Meta Ads, Analytics, Content, Campaign

الخبرات
أخصائية تسويق رقمي — وكالة إعلانية، 2020–2025
- أدرت حملات Google Ads وMeta Ads لعملاء تجزئة.
- رفعت زيارات الموقع العضوية عبر SEO.
- أعددت تقارير analytics أسبوعية.

التعليم
بكالوريوس تسويق، 2019
CV),
            self::cv('cv-06', 'marketing', 'أخصائي وسائل تواصل', <<<'CV'
فيصل نايف الدوسري
الخبر | email@example.com | +966500000006

نبذة
صانع محتوى ومدير حسابات تواصل اجتماعي لعلامات محلية.

المهارات
Social media, Content, Brand, Campaign, Instagram, Analytics

الخبرة
أخصائي Social Media — علامة تجزئة، 2021–2024
- أنشأت محتوى يومي لمنصات التواصل.
- رفعت التفاعل عبر حملات قصيرة.
- نسقت مع فريق brand على الرسائل.

التعليم
بكالوريوس إعلام، 2020
CV),
            self::cv('cv-07', 'marketing', 'مدير تسويق', <<<'CV'
هند محمد العتيبي
الرياض | email@example.com | +966500000007 | linkedin.com/in/example

ملخص
مديرة تسويق بخبرة قيادة فرق وحملات متعددة القنوات وميزانيات شهرية.

المهارات
Marketing, Strategy, Campaign, Brand, Team, Budget, Analytics

الخبرات
مديرة تسويق — شركة خدمات، 2018–2025
- قدت فريق تسويق من 6 أشخاص.
- وضعت خطة campaign ربع سنوية.
- راقبت budget التسويق والأداء.

التعليم
ماجستير إدارة أعمال، 2017
CV),
            self::cv('cv-08', 'sales', 'مندوب مبيعات', <<<'CV'
عبدالرحمن يوسف المطيري
الرياض | email@example.com | +966500000008

ملخص
مندوب مبيعات B2B بخبرة في prospecting وCRM وتحقيق أهداف المبيعات.

المهارات
Sales, CRM, Prospecting, Negotiation, Pipeline, Quota

الخبرة
مندوب مبيعات — شركة حلول أعمال، 2020–2025
- حققت 90% من quota السنوي في 2024.
- أدرت pipeline عبر Salesforce.
- أغلقت عقوداً مع عملاء جدد في الرياض.

التعليم
بكالوريوس إدارة أعمال، 2019
CV),
            self::cv('cv-09', 'sales', 'مدير حسابات', <<<'CV'
لينا فهد العنزي
جدة | email@example.com | +966500000009

نبذة
مديرة حسابات تركز على الاحتفاظ بالعملاء ونمو الإيراد من الحسابات الحالية.

المهارات
Account management, CRM, Revenue, Clients, Negotiation, Pipeline

الخبرات
مديرة حسابات — شركة SaaS إقليمية، 2019–2025
- أدرت 15 حساباً رئيسياً.
- ساهمت في تجديد عقود سنوية.
- نسقت مع فرق الدعم لتسريع الحلول.

التعليم
بكالوريوس تسويق، 2018
CV),
            self::cv('cv-10', 'sales', 'مدير تطوير أعمال', <<<'CV'
ماجد سعيد الغامدي
الرياض | email@example.com | +966500000010

ملخص
مدير تطوير أعمال يبني الشراكات ويوسّع pipeline في أسواق الخليج.

المهارات
Business development, Partnerships, Sales, Pipeline, Negotiation, Strategy

الخبرة
مدير تطوير أعمال — شركة تقنية، 2017–2025
- افتتحت قنوات شركاء في السعودية والإمارات.
- بنيت pipeline فرص جديدة ربع سنوياً.
- قدت مفاوضات عقود متعددة السنوات.

التعليم
بكالوريوس اقتصاد، 2016
CV),
            self::cv('cv-11', 'data', 'محلل بيانات', <<<'CV'
أمل خالد الحربي
الرياض | email@example.com | +966500000011

ملخص
محللة بيانات تبني لوحات dashboard وتقارير باستخدام SQL وExcel وPower BI.

المهارات
SQL, Excel, Power BI, Dashboard, Analytics, Reporting, Data analysis

الخبرات
محللة بيانات — جهة تشغيلية، 2021–2025
- أنشأت dashboard لفريق العمليات.
- أتمت تقارير أسبوعية عبر SQL.
- قدمت insights لخفض زمن المعالجة.

التعليم
بكالوريوس إحصاء، 2020
CV),
            self::cv('cv-12', 'data', 'محلل ذكاء أعمال', <<<'CV'
يوسف تركي الشهري
الدمام | email@example.com | +966500000012

نبذة
محلل BI متخصص في Tableau وPower BI وSQL لتقارير الإدارة.

المهارات
Tableau, Power BI, SQL, Dashboard, Reporting, Analytics, KPI

الخبرة
محلل BI — مجموعة صناعية، 2019–2024
- طورت لوحات KPI للإدارة العليا.
- وحدت مصادر البيانات لتقارير شهرية.
- درّبت المستخدمين على قراءة dashboard.

التعليم
بكالوريوس نظم معلومات، 2018
CV),
            self::cv('cv-13', 'data', 'عالم بيانات', <<<'CV'
ميساء أحمد البقمي
الرياض | email@example.com | +966500000013

ملخص
عالمة بيانات تعمل على نماذج machine learning وتحليلات تنبؤية باستخدام Python.

المهارات
Python, Machine learning, Data analysis, SQL, Statistics, Modeling

الخبرات
عالمة بيانات — مختبر تحليلات، 2020–2025
- بنيت نماذج تصنيف لسلوك العملاء.
- حسّنت دقة النموذج عبر تجارب منتظمة.
- وثقت النتائج لفرق المنتج.

التعليم
ماجستير علوم بيانات، 2019
CV),
            self::cv('cv-14', 'finance', 'محاسب', <<<'CV'
سلطان ناصر العجلان
الرياض | email@example.com | +966500000014

ملخص
محاسب بخبرة في القيود اليومية والتقارير الشهرية وExcel.

المهارات
Accounting, Excel, Ledger, Audit, Bookkeeping, Reporting

الخبرة
محاسب — شركة تجارية، 2019–2025
- أعددت القيود اليومية والإقفالات الشهرية.
- دعمت تجهيز ملفات audit السنوية.
- حسّنت قوالب Excel للتقارير.

التعليم
بكالوريوس محاسبة، 2018
CV),
            self::cv('cv-15', 'finance', 'محلل مالي', <<<'CV'
دانة فواز الرشيد
الرياض | email@example.com | +966500000015 | linkedin.com/in/example

ملخص
محللة مالية تركز على forecast والميزانية وتحليل P&L.

المهارات
Financial analysis, Forecast, Budget, Excel, SQL, P&L, Reporting

الخبرات
محللة مالية — شركة قابضة، 2020–2025
- أعددت forecast ربع سنوي.
- حلّلت انحرافات budget مقابل الفعلي.
- قدمت تقارير للإدارة المالية.

التعليم
بكالوريوس مالية، 2019
CV),
            self::cv('cv-16', 'finance', 'مدقق', <<<'CV'
بندر عبدالعزيز السبيعي
جدة | email@example.com | +966500000016

نبذة
مدقق داخلي يراجع الضوابط والامتثال ويوثق الملاحظات.

المهارات
Audit, Compliance, Controls, Risk, Accounting, Reporting

الخبرة
مدقق — مكتب مراجعة، 2018–2024
- نفذت مراجعات داخلية لأقسام متعددة.
- وثقت ملاحظات الضوابط والتوصيات.
- تابعت إغلاق نقاط audit مع الإدارات.

التعليم
بكالوريوس محاسبة، 2017
CV),
            self::cv('cv-17', 'hr', 'أخصائي موارد بشرية', <<<'CV'
شيماء فهد المطيري
الرياض | email@example.com | +966500000017

ملخص
أخصائية موارد بشرية تدعم onboarding والعلاقات الوظيفية والسياسات.

المهارات
HR, Onboarding, Employee relations, Policies, HRIS, Recruitment

الخبرات
أخصائية موارد بشرية — شركة خدمات، 2020–2025
- أدرت onboarding لموظفين جدد.
- حدّثت سياسات داخلية بالتنسيق مع الإدارة.
- دعمت employee relations اليومية.

التعليم
بكالوريوس موارد بشرية، 2019
CV),
            self::cv('cv-18', 'hr', 'مسؤول توظيف', <<<'CV'
طلال سعيد العتيبي
الرياض | email@example.com | +966500000018

ملخص
مسؤول توظيف يركز على talent acquisition والمقابلات وsourcing.

المهارات
Recruitment, Talent acquisition, Sourcing, Interviews, Hiring, HR

الخبرة
مسؤول توظيف — شركة تقنية، 2019–2025
- أغلقت 40+ وظيفة سنوياً في المتوسط.
- بنيت قنوات sourcing للمرشحين.
- نسقت مقابلات مع مديري التوظيف.

التعليم
بكالوريوس إدارة أعمال، 2018
CV),
            self::cv('cv-19', 'hr', 'مدير موارد بشرية', <<<'CV'
منيرة خالد الدوسري
الخبر | email@example.com | +966500000019

نبذة
مديرة موارد بشرية تقود فريق HR وتدير performance management والسياسات.

المهارات
HR, Leadership, Policies, Performance management, Team, Strategy

الخبرات
مديرة موارد بشرية — مجموعة شركات، 2016–2025
- قدت فريق HR من 5 أشخاص.
- أطلقت دورة تقييم أداء سنوية.
- راجعت سياسات التوظيف والامتثال.

التعليم
ماجستير موارد بشرية، 2015
CV),
            self::cv('cv-20', 'management', 'مدير مشاريع', <<<'CV'
راكان محمد الشهري
الرياض | email@example.com | +966500000020 | linkedin.com/in/example

ملخص
مدير مشاريع Agile يدير أصحاب المصلحة والخطط والجداول.

المهارات
Project management, Agile, Planning, Stakeholders, Roadmap, Scrum

الخبرة
مدير مشاريع — شركة حلول، 2018–2025
- أدرت 4 مشاريع رقمية متزامنة.
- نسقت مع stakeholders أسبوعياً.
- حسّنت الالتزام بالمواعيد عبر roadmap واضح.

التعليم
بكالوريوس هندسة صناعية، PMP 2019
CV),
            self::cv('cv-21', 'management', 'مدير عمليات', <<<'CV'
جواهر سعد الحربي
جدة | email@example.com | +966500000021

ملخص
مديرة عمليات تحسّن العمليات وتتابع مؤشرات الأداء للفرق التشغيلية.

المهارات
Operations, Process, Efficiency, Planning, Team, KPI

الخبرات
مديرة عمليات — مركز خدمة، 2017–2025
- خفضت زمن معالجة الطلبات بنسبة 18%.
- أعدت إجراءات عمل موحدة.
- قدت فريق عمليات من 12 موظفاً.

التعليم
بكالوريوس إدارة، 2016
CV),
            self::cv('cv-22', 'management', 'مدير منتج', <<<'CV'
عادل فيصل القحطاني
الرياض | email@example.com | +966500000022

نبذة
مدير منتج يضع roadmap وينسق بين الهندسة والأعمال.

المهارات
Product, Roadmap, Stakeholders, Strategy, Backlog, Agile

الخبرة
مدير منتج — منصة رقمية، 2019–2025
- أطلقت 3 ميزات رئيسية خلال عامين.
- أدرت backlog مع فرق التطوير.
- جمعت متطلبات stakeholders.

التعليم
بكالوريوس علوم حاسب، 2018
CV),
            self::cv('cv-23', 'ecommerce', 'أخصائي تجارة إلكترونية', <<<'CV'
نوف عبدالرحمن العتيبي
الرياض | email@example.com | +966500000023

ملخص
أخصائية تجارة إلكترونية تدير المتجر الإلكتروني وقوائم المنتجات والتحويل.

المهارات
Ecommerce, Shopify, Product listing, Conversion, Cart, Campaign, Catalog

الخبرات
أخصائية Ecommerce — علامة أزياء، 2021–2025
- حسّنت product listing على Shopify.
- رفعت conversion عبر تحسين صفحات المنتج.
- أدرت حملات ترويج موسمية.

التعليم
بكالوريوس تسويق، 2020
CV),
            self::cv('cv-24', 'ecommerce', 'مدير تجارة إلكترونية', <<<'CV'
مشعل تركي الغامدي
جدة | email@example.com | +966500000024

ملخص
مدير تجارة إلكترونية يقود marketplace والحملات ومؤشرات التحويل.

المهارات
Ecommerce, Marketplace, Conversion, Campaign, Catalog, Retail, Analytics

الخبرة
مدير Ecommerce — متجر إقليمي، 2018–2025
- أدرت قنوات marketplace متعددة.
- راقبت conversion ومعدل التخلي عن السلة.
- نسقت مع فرق content واللوجستيات.

التعليم
بكالوريوس إدارة أعمال، 2017
CV),
            self::cv('cv-25', 'ecommerce', 'أخصائي أسواق إلكترونية', <<<'CV'
هيفاء سعد الدوسري
الرياض | email@example.com | +966500000025

نبذة
أخصائية marketplace تركز على Amazon وproduct listing وتحسين الظهور.

المهارات
Amazon, Marketplace, Product listing, Retail, Campaign, Catalog, SEO

الخبرات
أخصائية Marketplace — موزع تجزئة، 2020–2025
- أدرت قوائم منتجات على Amazon.
- حسّنت ظهور المنتجات عبر محتوى listing.
- تابعت أداء الحملات الإعلانية على المنصة.

التعليم
دبلوم تسويق رقمي، 2019
CV),
            self::cv('cv-26', 'software', 'مهندس DevOps', <<<'CV'
عمر حسن العتيبي
الرياض | email@example.com | +966500000026

ملخص
مهندس DevOps يدير CI/CD وDocker والبيئات السحابية.

المهارات
DevOps, Docker, CI/CD, Cloud, Linux, Git, Backend

الخبرة
مهندس DevOps — شركة SaaS، 2019–2025
- أتمت خطوط CI/CD لخدمات متعددة.
- خفّض زمن النشر بنسبة 40%.
- راقب استقرار البيئات السحابية.

التعليم
بكالوريوس شبكات، 2018
CV),
            self::cv('cv-27', 'marketing', 'أخصائي SEO', <<<'CV'
غادة منصور الحربي
الدمام | email@example.com | +966500000027

ملخص
أخصائية SEO تحسّن الظهور العضوي والمحتوى والكلمات المفتاحية.

المهارات
SEO, Content, Keywords, Analytics, Search, Campaign, Marketing

الخبرات
أخصائية SEO — وكالة محتوى، 2020–2025
- رفعت ترتيب صفحات رئيسية لكلمات مستهدفة.
- راجعت محتوى عربي/إنجليزي لمحركات البحث.
- أعددت تقارير analytics شهرية.

التعليم
بكالوريوس لغة إنجليزية، 2019
CV),
            self::cv('cv-28', 'sales', 'مبيعات داخلية', <<<'CV'
وليد ناصر الشمري
الرياض | email@example.com | +966500000028

نبذة
مسؤول مبيعات داخلية يتابع العملاء المحتملين عبر الهاتف وCRM.

المهارات
Inside sales, CRM, Leads, Pipeline, Sales, Prospecting

الخبرة
Inside Sales — شركة برمجيات، 2021–2025
- تابعت leads يومياً عبر HubSpot.
- حوّلت فرصاً إلى meetings لفريق الميدان.
- حققت أهداف المكالمات الأسبوعية.

التعليم
دبلوم مبيعات، 2020
CV),
            self::cv('cv-29', 'finance', 'أخصائي رواتب', <<<'CV'
إيمان فهد القحطاني
الرياض | email@example.com | +966500000029

ملخص
أخصائية رواتب تدير مسير الرواتب والامتثال والتعويضات الأساسية.

المهارات
Payroll, Compensation, Accounting, Compliance, Excel, HRIS

الخبرات
أخصائية رواتب — شركة متوسطة، 2019–2025
- أعددت مسير رواتب شهري لأكثر من 200 موظف.
- راجعت الاستقطاعات والامتثال.
- نسقت مع المحاسبة على الترحيل.

التعليم
بكالوريوس محاسبة، 2018
CV),
            self::cv('cv-30', 'management', 'قائد فريق', <<<'CV'
زياد عبدالإله المطيري
جدة | email@example.com | +966500000030 | linkedin.com/in/example

ملخص
قائد فريق تشغيلي يدرب الأعضاء ويتابع التسليم اليومي.

المهارات
Leadership, Team, Coaching, Delivery, Planning, Operations

الخبرة
قائد فريق — مركز عمليات، 2018–2025
- قدت فريقاً من 8 موظفين.
- حسّنت جودة التسليم عبر مراجعات أسبوعية.
- درّبت أعضاء جدد خلال onboarding.

التعليم
بكالوريوس إدارة أعمال، 2017
CV),
        ];
    }

    /**
     * @return Fixture
     */
    private static function cv(string $id, string $category, string $targetJobTitle, string $resumeText): array
    {
        return [
            'id' => $id,
            'category' => $category,
            'target_job_title' => $targetJobTitle,
            'resume_text' => trim($resumeText),
        ];
    }
}
