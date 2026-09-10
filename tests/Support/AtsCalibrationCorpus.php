<?php

namespace Tests\Support;

/**
 * Anonymised calibration corpus for AtsScoringService (SIRATI-90).
 *
 * PDPL compliance: all personal names, employer names, emails, phone numbers,
 * and exact locations are synthetic/anonymised.
 *
 * Structure:
 * - 17 Strong CVs (real metrics, proper sections, accredited degrees/licences)
 * - 15 Weak CVs (vague duties, missing sections, no metrics, or keyword-stuffed junk)
 * - 17 Arabic CVs (idiomatic Arabic headings, clitics, Arabic metrics)
 * - Spans 8+ professions including 3 outside the 8 hardcoded categories:
 *   - Registered ICU Nurse (Healthcare — off-taxonomy)
 *   - Civil Site Engineer (Engineering/Construction — off-taxonomy)
 *   - Secondary School Teacher (Education — off-taxonomy)
 *   - Software Developer (Software — in-taxonomy)
 *   - Marketing Specialist (Marketing — in-taxonomy)
 *   - Financial Analyst (Finance — in-taxonomy)
 *   - HR Specialist (HR — in-taxonomy)
 *   - E-commerce Store Manager (E-commerce — in-taxonomy)
 *   - Data Analyst (Data — in-taxonomy)
 *   - Customer Care Specialist (General — in-taxonomy)
 * - Adversarial CVs from ATS_SCORING_REVIEW.md & SPRINT6_REVIEW_ROUND1.md:
 *   - adversarial_junk: Keyword-stuffed junk CV with headers and furniture
 *   - adversarial_nurse: 7-yr ICU nurse with zero errors and 50 resuscitations
 *   - adversarial_arabic_dev: Arabic Laravel developer with 10k users and 40% latency reduction
 */
final class AtsCalibrationCorpus
{
    /**
     * Exact adversarial junk CV from ATS_SCORING_REVIEW.md §1 and SPRINT6_REVIEW_ROUND1.md.
     * Contains marketing buzzword run-on sentence repeated across sections, email, phone,
     * degree and certifications, but zero real achievements or employers.
     */
    public static function adversarialJunk(): array
    {
        return [
            'id' => 'adversarial_junk',
            'profession' => 'marketing',
            'target_job_title' => 'Marketing Specialist',
            'category' => 'marketing',
            'is_off_taxonomy' => false,
            'quality' => 'junk',
            'language' => 'en',
            'text' => <<<'CV'
John Doe
Marketing Specialist
john@example.com | +966551234567 | linkedin.com/in/johndoe

Summary
I worked at a company from 2019 to 2025 and did marketing and campaign and brand and content and social media and seo and sem and ppc and google ads and meta ads and analytics.

Experience
Marketing Specialist, Company, 2019 - 2025
I worked at a company from 2019 to 2025 and did marketing and campaign and brand and content and social media and seo and sem and ppc and google ads and meta ads and analytics.

Education
Bachelor degree 2018
AWS certified

Skills
Marketing, campaign, brand, content, social media, seo, sem, ppc, google ads, meta ads, analytics
CV,
        ];
    }

    /**
     * Exact adversarial nurse CV from ATS_SCORING_REVIEW.md §1 and SPRINT6_REVIEW_ROUND1.md.
     * ICU nurse with 7 years experience, 0 medication errors, 50+ resuscitations, BSN, SCFHS.
     */
    public static function adversarialNurse(): array
    {
        return [
            'id' => 'adversarial_nurse',
            'profession' => 'nurse',
            'target_job_title' => 'Registered Nurse',
            'category' => 'healthcare',
            'is_off_taxonomy' => true,
            'quality' => 'strong',
            'language' => 'en',
            'text' => <<<'CV'
Sarah Al-Otaibi
Registered Nurse
sarah.nurse@example.com | +966501234567 | linkedin.com/in/sarah-nurse | Riyadh, Saudi Arabia

Professional Summary
Compassionate and dedicated Registered Nurse with 7 years of intensive care unit (ICU) experience in tertiary hospital settings. Proficient in hemodynamic monitoring, ventilator management, and emergency patient resuscitation. Proven track record in patient advocacy and multidisciplinary care coordination.

Experience
ICU Staff Nurse, King Faisal Specialist Hospital, 2018 - 2025
- Managed critical care for 3-4 high-acuity patients per shift in a 24-bed medical-surgical ICU.
- Administered complex IV medications, titrating vasoactive infusions with zero medication administration errors across 7 years.
- Participated in over 50 emergency resuscitations and code blue activations.
- Trained and precepted 12 newly graduated nurses and nursing interns.

Education
Bachelor of Science in Nursing (BSN), King Saud University, 2018

Certifications & Licences
- Saudi Commission for Health Specialties (SCFHS) Nursing License
- Basic Life Support (BLS) certified
- Advanced Cardiovascular Life Support (ACLS) certified
CV,
        ];
    }

    /**
     * Exact adversarial Arabic developer CV from ATS_SCORING_REVIEW.md §1 and SPRINT6_REVIEW_ROUND1.md.
     * PHP/Laravel developer with 4 years experience, 10k users, 40% latency reduction, 25% error reduction.
     */
    public static function adversarialArabicDev(): array
    {
        return [
            'id' => 'adversarial_arabic_dev',
            'profession' => 'software',
            'target_job_title' => 'مطوّر برمجيات',
            'category' => 'software',
            'is_off_taxonomy' => false,
            'quality' => 'strong',
            'language' => 'ar',
            'text' => <<<'CV'
أحمد المحمدي
مطوّر برمجيات
ahmed@example.com | +966541234567 | linkedin.com/in/ahmed-dev | الرياض

الملخص المهني
مطوّر برمجيات بخبرة 4 سنوات في بناء تطبيقات الويب وواجهات برمجة التطبيقات باستخدام PHP وLaravel وVue.js. أركز على كتابة كود نظيف وقابل للصيانة وتحسين أداء قواعد البيانات.

الخبرة العملية
مطوّر برمجيات، شركة تقنية، 2021 - 2025
- طورت أكثر من 15 واجهة برمجة تطبيقات RESTful باستخدام Laravel وMySQL تخدم 10000 مستخدم نشط.
- حسّنت استعلامات قاعدة البيانات وخفّضت زمن استجابة الخادم بنسبة 40%.
- عملت ضمن فريق Agile/Scrum وطبقت ممارسات CI/CD وGit لتقليل أخطاء الإنتاج بنسبة 25%.

المؤهلات العلمية
بكالوريوس علوم الحاسب، جامعة الملك عبد العزيز، 2020

المهارات التقنية
PHP, Laravel, MySQL, JavaScript, Vue.js, Git, Docker, RESTful APIs, CI/CD
CV,
        ];
    }

    /**
     * The headline benchmark CV from ATS_SCORING_REVIEW.md §1 & §2.
     * Registered Nurse, 7 yrs ICU, real metrics, BSN degree + BLS/ACLS/SCFHS licence.
     */
    public static function benchmarkNurse(): array
    {
        return [
            'id' => 'benchmark_nurse',
            'profession' => 'nurse',
            'target_job_title' => 'Registered Nurse - Intensive Care Unit (ICU)',
            'category' => 'healthcare',
            'is_off_taxonomy' => true,
            'quality' => 'strong',
            'language' => 'en',
            'text' => <<<'CV'
Fatima Al-Harbi, RN, BSN
Riyadh, Saudi Arabia | fatima.alharbi@example.com | +966551234567 | linkedin.com/in/fatima-alharbi-rn

Professional Summary
Compassionate and detail-oriented Critical Care Registered Nurse with 7+ years of intensive care unit (ICU) experience in tertiary hospital settings. Proven expertise in invasive hemodynamic monitoring, mechanical ventilation management, and emergency resuscitation. Consistently maintained a 98% patient safety compliance rate across high-acuity shifts.

Professional Experience

Senior ICU Staff Nurse | King Fahad Medical City, Riyadh
2020 – Present
- Managed critical care nursing for 3–4 high-acuity post-surgical and cardiac patients per 12-hour shift.
- Reduced catheter-associated urinary tract infections (CAUTI) by 35% across the 24-bed ICU ward by implementing strict central line bundles.
- Administered advanced IV vasoactive titrations, arterial line monitoring, and continuous renal replacement therapy (CRRT).
- Led a multidisciplinary rapid response team of 6 clinicians during 50+ cardiac and respiratory emergency resuscitations.
- Mentored and trained 14 newly graduated nurses and junior staff on ICU clinical protocols and documentation.

Staff Nurse - Medical/Surgical ICU | National Guard Health Affairs, Jeddah
2017 – 2020
- Monitored critically ill adult patients requiring mechanical ventilation, ECMO support, and tracheostomy care.
- Improved patient handoff efficiency by 25% by introducing structured SBAR communication protocols.
- Coordinated with intensivists, pharmacists, and respiratory therapists to develop individualised patient care plans.
- Conducted 100+ family counselling and palliative care education sessions with empathy and cultural sensitivity.

Education
Bachelor of Science in Nursing (BSN) | King Saud University, Riyadh
2013 – 2017
- Graduated with Second Class Honours, GPA: 4.60 / 5.00

Certifications & Licences
- Saudi Commission for Health Specialties (SCFHS) Registered Nurse License — Valid through 2027
- Basic Life Support (BLS) Certification — American Heart Association, 2024
- Advanced Cardiovascular Life Support (ACLS) Certification — American Heart Association, 2023
- Certified Critical Care Registered Nurse (CCRN) — 2022

Technical Competencies & Clinical Skills
- Clinical Skills: Hemodynamic Monitoring, Mechanical Ventilation, Arterial Blood Gas (ABG) Analysis, CRRT, TPN Administration, Medication Administration
- EHR Systems: Epic Systems, Cerner EHR, BestCare
- Languages: Arabic (Native), English (Full Professional Proficiency)
CV,
        ];
    }

    /**
     * The headline Arabic developer benchmark CV from ATS_SCORING_REVIEW.md §1 & §5.
     * Uses standard Arabic headings (الملخص المهني, الخبرات المهنية, المؤهلات العلمية).
     */
    public static function benchmarkArabicDev(): array
    {
        return [
            'id' => 'benchmark_arabic_dev',
            'profession' => 'software',
            'target_job_title' => 'مهندس برمجيات أول',
            'category' => 'software',
            'is_off_taxonomy' => false,
            'quality' => 'strong',
            'language' => 'ar',
            'text' => <<<'CV'
عبد الله محمد السالم
الرياض، المملكة العربية السعودية | abdullah.salem@example.com | +966509876543 | linkedin.com/in/abdullah-salem-dev

الملخص المهني
مهندس برمجيات أول بخبرة 6 سنوات في تصميم وبناء المنصات السحابية والأنظمة الخلفية عالية الكفاءة. متخصص في تطوير واجهات برمجة التطبيقات RESTful API باستخدام Laravel و Python وقواعد البيانات العلائقية. نجحتُ في تحسين أداء المعالجة بنسبة 40% وإدارة خدمات تخدم أكثر من 500,000 مستخدم نشط.

الخبرات المهنية

مهندس برمجيات أول — شركة الحلول السحابية، الرياض
2021 — حتى الآن
- قدتُ فريقاً من 6 مطورين لتطوير معمارية الخدمات المصغرة Microservices بالاعتماد على Laravel و Docker.
- طوّرتُ واجهات برمجة التطبيقات API متقدمة تعالج 2,000,000 طلب يومياً بزمن استجابة أقل من 65ms.
- حسّنتُ استعلامات SQL وهيكلة الفهارس مما أدى إلى خفض زمن استرجاع البيانات بنسبة 45%.
- أطلقتُ خطوط أتمتة CI/CD باستخدام GitHub Actions مما قلص دورة النشر والتحديث بنسبة 50%.

مطور تطبيقات خلفية — مؤسسة النظم الرقمية، الرياض
2018 — 2021
- صممتُ ونفذتُ واجهات التكامل مع بوابات الدفع الإلكتروني وحلول الفوترة المتوافقة مع متطلبات ZATCA.
- أنشأتُ منظومة مراقبة وتتبع للأخطاء خفضت زمن استكشاف الأعطال بنسبة 30%.
- تعاونتُ مع فرق المنتجات والواجهات الأمامية وفق منهجية Agile و Scrum لتسليم الميزات بدقة.

المؤهلات العلمية

بكالوريوس علوم الحاسب والمعلومات
جامعة الملك فهد للبترول والمعادن، الظهران | 2014 — 2018
- مرتبة الشرف الأولى، المعدل 3.85 من 4.00

المهارات التقنية
- لغات وأطر عمل: PHP, Laravel, Python, SQL, JavaScript, Flutter, REST API
- أدوات وبنية تحتية: Docker, Git, Redis, PostgreSQL, MySQL, Linux, CI/CD, AWS
- منهجيات: Agile, Scrum, Test-Driven Development, Object-Oriented Programming

الشهادات المهنية
- شهادة ممارس سحابي معتمد من AWS (AWS Certified Solutions Architect) — 2023
- شهادة إدارة المشاريع الاحترافية Scrum Master (PSM I) — 2021
CV,
        ];
    }

    /**
     * The headline junk CV from ATS_SCORING_REVIEW.md §1.
     * One run-on sentence of marketing buzzwords, zero achievements, zero employer.
     */
    public static function benchmarkJunk(): array
    {
        return [
            'id' => 'benchmark_junk',
            'profession' => 'marketing',
            'target_job_title' => 'Marketing Specialist',
            'category' => 'marketing',
            'is_off_taxonomy' => false,
            'quality' => 'junk',
            'language' => 'en',
            'text' => <<<'CV'
I worked at a company from 2019 to 2025 and did marketing and campaign and brand and content and social media and seo and sem and ppc and google ads and meta ads and analytics.
CV,
        ];
    }

    /**
     * Zero-metric administrative CV from ATS_SCORING_REVIEW.md §3.
     * Contains telephone, PO box, postal code, years, but zero real achievements.
     */
    public static function benchmarkZeroMetricAdmin(): array
    {
        return [
            'id' => 'benchmark_zero_metric_admin',
            'profession' => 'admin',
            'target_job_title' => 'Administrative Assistant',
            'category' => 'management',
            'is_off_taxonomy' => false,
            'quality' => 'weak',
            'language' => 'en',
            'text' => <<<'CV'
Huda Nasser
Riyadh, Saudi Arabia | huda.admin@example.com | Phone: +966 55 123 4567 | PO Box: 31952, Postal Code: 34423

Summary
Administrative assistant with experience in filing and correspondence.

Experience
Office Clerk | Trading Company
2019 – 2025
- Responsible for filing and answering the telephone.
- Greeted visitors and assisted with general office duties.
- Typed letters and managed incoming mail.

Education
High School Diploma, 2018
CV,
        ];
    }

    /**
     * Paired English / Arabic equivalent CVs for Invariant 3.
     */
    public static function pairedEquivalentCvs(): array
    {
        $en = <<<'CV'
Tariq Al-Ghamdi
Riyadh, Saudi Arabia | tariq.ghamdi@example.com | +966501112233 | linkedin.com/in/tariq-ghamdi

Professional Summary
Senior Financial Analyst with 5+ years of experience in corporate financial planning, variance analysis, and forecasting within Saudi enterprises. Proven ability to optimize operational budgets and improve reporting accuracy by 30%.

Professional Experience

Senior Financial Analyst | National Investment Group, Riyadh
2021 – Present
- Led the annual budgeting and financial forecasting process for 4 corporate business units with $15M in revenue.
- Reduced monthly financial closing cycle time by 35% through SQL query automation and Power BI dashboard integration.
- Analyzed quarterly financial statements and identified $250,000 in redundant operational expenditures.
- Implemented financial models for capital investment appraisal achieving 99% projection accuracy.

Financial Analyst | Commercial Retail Co., Riyadh
2019 – 2021
- Prepared monthly P&L forecasts, cash flow statements, and balance sheet variance analyses.
- Automated 12 recurring management reports using advanced Excel, saving 15 hours per week.
- Evaluated supplier contracts and negotiated terms resulting in 10% procurement savings.

Education
Bachelor of Science in Finance | King Saud University, Riyadh
2015 – 2019

Certifications
- Chartered Financial Analyst (CFA) Level II Candidate — 2023
- Certified Management Accountant (CMA) — 2021

Technical Skills
- Financial Analysis, Financial Modeling, Budgeting, Forecasting, Accounting, Variance Analysis
- Tools: Excel, SQL, Power BI, SAP ERP
CV;

        $ar = <<<'CV'
طارق الغامدي
الرياض، المملكة العربية السعودية | tariq.ghamdi@example.com | +966501112233 | linkedin.com/in/tariq-ghamdi

الملخص المهني
محلل مالي أول بخبرة تزيد عن 5 سنوات في التخطيط المالي وتحليل التباين وإعداد الموازنات التقديرية للشركات في المملكة. أمتلك سجلاً مثبتاً في تحسين كفاءة الميزانيات التشغيلية ورفع دقة التقارير بنسبة 30%.

الخبرات المهنية

محلل مالي أول — المجموعة الوطنية للاستثمار، الرياض
2021 — حتى الآن
- أدرتُ إعداد الميزانية السنوية والتوقعات المالية لأربع وحدات أعمال بإجمالي إيرادات 15 مليون ريال.
- خفضتُ مدة الإقفال المالي الشهري بنسبة 35% عبر أتمتة الاستعلامات ودمج لوحات Power BI.
- حللتُ القوائم المالية الفصلية ونجحتُ في خفض تكاليف تشغيلية مكررة بقيمة 250,000 ريال.
- نفذتُ نماذج مالية لتقييم الاستثمارات الرأسمالية بدقة توقعات بلغت 99%.

محلل مالي — شركة التجزئة التجارية، الرياض
2019 — 2021
- أعددتُ تقارير الأرباح والخسائر الشهرية وتدفقات النقدية وتحليلات التباين للميزانية العمومية.
- أتمتتُ 12 تقريراً دورياً للإدارة باستخدام Excel المتقدم، مما وفر 15 ساعة عمل أسبوعياً.
- قيمتُ عقود الموردين وفاوضتُ على الشروط محققاً وفراً بنسبة 10% في تكاليف المشتريات.

المؤهلات العلمية

بكالوريوس في العلوم المالية
جامعة الملك سعود، الرياض | 2015 — 2019

الشهادات المهنية
- مرشح المستوى الثاني لشهادة المحلل المالي المعتمد CFA — 2023
- محاسب إداري معتمد CMA — 2021

المهارات التقنية
- التحليل المالي، النمذجة المالية، الموازنات، التنبؤ المالي، المحاسبة، تدقيق الحسابات
- الأدوات: Excel, SQL, Power BI, SAP ERP
CV;

        return [
            'en' => [
                'profession' => 'finance',
                'target_job_title' => 'Senior Financial Analyst',
                'text' => $en,
            ],
            'ar' => [
                'profession' => 'finance',
                'target_job_title' => 'محلل مالي أول',
                'text' => $ar,
            ],
        ];
    }

    /**
     * Additional representative CVs across professions: 17 strong, 15 weak.
     * Includes 17 Arabic CVs and 3 off-taxonomy professions (Nurse, Civil Engineer, Teacher).
     *
     * @return list<array{id: string, profession: string, target_job_title: string, quality: string, language: string, is_off_taxonomy: bool, text: string}>
     */
    public static function corpus(): array
    {
        return [
            // Strong #1: Benchmark Nurse (off-taxonomy)
            self::benchmarkNurse(),

            // Strong #2: Benchmark Arabic Dev (in-taxonomy, Arabic)
            self::benchmarkArabicDev(),

            // Strong #3: Adversarial Nurse (off-taxonomy)
            self::adversarialNurse(),

            // Strong #4: Adversarial Arabic Dev (in-taxonomy, Arabic)
            self::adversarialArabicDev(),

            // Strong #5: Civil Engineer (off-taxonomy)
            [
                'id' => 'strong_civil_engineer',
                'profession' => 'civil_engineer',
                'target_job_title' => 'Civil Site Engineer',
                'quality' => 'strong',
                'language' => 'en',
                'is_off_taxonomy' => true,
                'text' => <<<'CV'
Omar Al-Zahrani, PE
Dammam, Saudi Arabia | omar.zahrani@example.com | +966503334455 | linkedin.com/in/omar-zahrani-civil

Professional Summary
Licensed Civil Project Engineer with 6+ years of experience leading commercial structural execution and infrastructure development across Saudi Arabia. Expert in site management, structural concrete, QA/QC standards, and contractor supervision.

Professional Experience

Senior Site Civil Engineer | Saudi Binladin Construction, Khobar
2021 – Present
- Supervised the construction of a $40M commercial development complex, completing structural phases 3 weeks ahead of schedule.
- Reduced concrete pouring material waste by 20% through rigorous mix design quality audits.
- Managed 8 subcontractor packages and a daily site workforce of 120+ skilled labourers with zero lost-time incidents (LTI).
- Resolved 45+ structural site engineering RFIs in coordination with consulting engineering teams.

Site Engineer | Al-Kifah Contracting, Dammam
2018 – 2021
- Inspected daily structural rebar, formwork, and earthworks across residential tower projects.
- Improved weekly project milestone reporting accuracy by 30% by digitizing site tracking logs.
- Coordinated with municipal safety inspectors to achieve 100% compliance on safety audits.

Education
Bachelor of Science in Civil Engineering | King Fahd University of Petroleum and Minerals (KFUPM)
2014 – 2018

Certifications & Licences
- Saudi Council of Engineers (SCE) Professional Engineer License
- Project Management Professional (PMP) Certification — 2022
- OSHA 30-Hour Construction Safety Certification

Skills & Competencies
- Site Supervision, Reinforced Concrete, Structural Steel, Foundation Engineering, QA/QC, Safety Audits
- Tools: AutoCAD, Primavera P6, Revit Structure, Civil 3D
CV,
            ],

            // Strong #6: Teacher (off-taxonomy)
            [
                'id' => 'strong_teacher',
                'profession' => 'teacher',
                'target_job_title' => 'Secondary Mathematics Teacher',
                'quality' => 'strong',
                'language' => 'en',
                'is_off_taxonomy' => true,
                'text' => <<<'CV'
Reem Al-Otaibi
Riyadh, Saudi Arabia | reem.otaibi@example.com | +966552223344 | linkedin.com/in/reem-otaibi-edu

Professional Summary
Dedicated Secondary School Mathematics Teacher with 6 years of experience instructing advanced algebra, calculus, and geometry. Skilled in differentiated instruction, interactive STEM curricula, and student assessment analytics.

Professional Experience

Secondary Mathematics Teacher | Riyadh Advanced International School
2020 – Present
- Instructed 150+ students across grades 10–12 in advanced calculus, algebra, and statistics.
- Raised student standardized math testing scores by 22% over a two-year academic cycle.
- Integrated digital math platforms (GeoGebra, Khan Academy), increasing student engagement by 40%.
- Organized annual regional mathematics Olympiad team, coaching 5 students to top-10 district finishes.

Mathematics Educator | Modern Educational Academy, Riyadh
2018 – 2020
- Developed comprehensive lesson plans aligned with Saudi Ministry of Education national standards.
- Reduced student failure rate by 30% by establishing weekly after-school peer tutoring clinics.
- Conducted 80+ parent-teacher progress evaluations with clear actionable student growth roadmaps.

Education
Bachelor of Education in Mathematics | Princess Nourah bint Abdulrahman University
2014 – 2018
- Graduated with First Class Honours

Certifications & Licences
- Professional Teacher License | Education and Training Evaluation Commission (ETEC)
- Cambridge International Diploma for Teachers and Trainers (CIDTT)

Skills & Competencies
- Curriculum Design, STEM Integration, Student Assessment, Differentiated Instruction, Classroom Management
- Platforms: Google Classroom, Blackboard LMS, GeoGebra, Microsoft Teams
CV,
            ],

            // Strong #7: Financial Analyst (in-taxonomy)
            [
                'id' => 'strong_finance',
                'profession' => 'finance',
                'target_job_title' => 'Senior Financial Analyst',
                'quality' => 'strong',
                'language' => 'en',
                'is_off_taxonomy' => false,
                'text' => self::pairedEquivalentCvs()['en']['text'],
            ],

            // Strong #8: Marketing Specialist (in-taxonomy)
            [
                'id' => 'strong_marketing',
                'profession' => 'marketing',
                'target_job_title' => 'Digital Marketing Manager',
                'quality' => 'strong',
                'language' => 'en',
                'is_off_taxonomy' => false,
                'text' => <<<'CV'
Sultan Al-Dosari
Riyadh, Saudi Arabia | sultan.dosari@example.com | +966556677889 | linkedin.com/in/sultan-marketing

Professional Summary
Data-driven Digital Marketing Manager with 5+ years of experience leading multi-channel acquisition campaigns across the GCC. Proven track record in scaling organic search traffic, paid media performance, and customer acquisition.

Professional Experience

Digital Marketing Manager | Jahez Partner Brands, Riyadh
2021 – Present
- Managed an annual digital marketing advertising budget of 3,500,000 SAR across Google Ads and Meta.
- Increased organic search traffic by 65% in 12 months by optimizing technical SEO and content architecture.
- Reduced blended customer acquisition cost (CAC) by 28% through targeted PPC and retargeting workflows.
- Directed a team of 4 specialists across content, performance marketing, and creative production.

Growth Marketing Specialist | E-Commerce Hub, Riyadh
2019 – 2021
- Launched 40+ paid social campaigns resulting in 180% return on ad spend (ROAS).
- Improved email marketing conversion rate by 32% via automated behavioural nurture sequences.
- Analyzed marketing funnel metrics using Google Analytics and Google Tag Manager.

Education
Bachelor of Business Administration in Marketing | King Saud University
2015 – 2019

Certifications
- Google Ads Search & Display Certified Professional — 2023
- Meta Certified Digital Marketing Associate — 2022
- HubSpot Inbound Marketing Certification

Skills & Competencies
- SEO, SEM, PPC, Google Ads, Meta Ads, Marketing Analytics, Brand Strategy, Campaign Management
- Tools: Google Analytics 4, SEMrush, HubSpot, Mailchimp, Adjust
CV,
            ],

            // Strong #9: Arabic Nurse (off-taxonomy, Arabic)
            [
                'id' => 'strong_arabic_nurse',
                'profession' => 'nurse',
                'target_job_title' => 'أخصائية تمريض عناية مركزة',
                'quality' => 'strong',
                'language' => 'ar',
                'is_off_taxonomy' => true,
                'text' => <<<'CV'
منى بنت خالد العتيبي
الرياض، المملكة العربية السعودية | mona.otaibi@example.com | +966508889900 | linkedin.com/in/mona-nurse-sa

الملخص المهني
أخصائية تمريض عناية مركزة بخبرة 6 سنوات في رعاية الحالات الحرجة بمستشفيات رعاية متقدمة. أمتلك كفاءة عالية في المراقبة الديناميكية الدموية وإدارة أجهزة التنفس الاصطناعي وتطبيق بروتوكولات الإنعاش القلبي الرئوي. حققت نسبة 98% في الالتزام بمعايير السلامة ومكافحة العدوى.

الخبرات المهنية

أخصائية تمريض أول — مستشفى الملك خالد الجامعي، الرياض
2020 — حتى الآن
- أدرت الرعاية التمريضية لـ 4 مرضى من ذوي الحالات الحرجة في كل وردية داخل وحدة عناية مركزة تضم 20 سريراً.
- شاركت في أكثر من 40 عملية إنعاش قلبي رئوي طارئة وحالات كود بلو بنجاح وكفاءة عالية.
- دربت 10 ممرضات حديثات التخرج على مهارات التعامل مع أجهزة الغسيل الكلوي المستمر والتغذية الوريدية.
- خفضت نسبة أخطاء صرف وتناول الأدوية إلى صفر بالمئة عبر الالتزام الصارم بالتحقق الإلكتروني.

ممرضة عناية مركزة — مدينة الأمير سلطان الطبية، الرياض
2018 — 2020
- تابعت رعاية مرضى الجراحة العامة وحالات ما بعد العمليات المعقدة وضبط محاليل الأدوية الوريدية الحساسة.
- نسقت مع الأطباء الاستشاريين وأخصائيي العلاج التنفسي لتنفيذ خطط علاجية متكاملة شملت أكثر من 150 مريضاً.
- وثقت السجلات الطبية التمريضية بدقة متناهية عبر نظام السجلات الصحية الإلكترونية BestCare.

المؤهلات العلمية

بكالوريوس علوم التمريض
جامعة الملك سعود، الرياض | 2014 — 2018

الشهادات والتراخيص المهنية
- تصنيف وترخيص الهيئة السعودية للتخصصات الصحية (SCFHS) — أخصائي تمريض
- شهادة دعم الحياة الأساسي (BLS) من جمعية القلب الأمريكية — 2024
- شهادة دعم الحياة القلبي المتقدم (ACLS) — 2023

المهارات السريرية
- العناية الحرجة، المراقبة القلبية، قسطرة الشرايين، الإنعاش القلبي، إدارة مسالك الهواء، جودة الرعاية
- الأنظمة: BestCare, Epic EHR
CV,
            ],

            // Strong #10: Arabic Civil Engineer (off-taxonomy, Arabic)
            [
                'id' => 'strong_arabic_civil_engineer',
                'profession' => 'civil_engineer',
                'target_job_title' => 'مهندس موقع مدني',
                'quality' => 'strong',
                'language' => 'ar',
                'is_off_taxonomy' => true,
                'text' => <<<'CV'
سعود بن عبد العزيز القحطاني
الدمام، المملكة العربية السعودية | saud.qahtani@example.com | +966554443322 | linkedin.com/in/saud-civil-pe

الملخص المهني
مهندس موقع مدني معتمد بخبرة 7 سنوات في الإشراف على تنفيذ المشاريع الإنشائية والبنية التحتية في المملكة. متخصص في إدارة مقاولي الباطن، مراقبة ضبط وتوكيد الجودة QA/QC، ومطابقة المواصفات الفنية لكود البناء السعودي.

الخبرات المهنية

مهندس موقع أول — شركة المقاولات الإنشائية الحديثة، الخبر
2020 — حتى الآن
- أشرفتُ على تنفيذ مشروع مجمع تجاري متكامل بقيمة 50 مليون ريال، وسلمتُ الأعمال الهيكلية قبل الموعد بـ 4 أسابيع.
- خفضتُ هدر حديد التسليح والخرسانة الجاهزة بنسبة 18% عبر التدقيق الفني للمخططات التنفيذية Shop Drawings.
- قدتُ فريقاً ميدانياً من 12 مهندساً ومشرفاً و100 عامل مع تحقيق 500 ألف ساعة عمل خالية من الإصابات المهنية.
- نسقتُ مع المكاتب الاستشارية لإغلاق أكثر من 70 طلب فحص واستفسار موقعي RFI بنجاح.

مهندس مدني تنفيذي — مؤسسة البناء والتعمير، الدمام
2017 — 2020
- فحصتُ أعمال الخرسانة المسلحة والأساسات والردم لـ 15 برجاً سكنياً.
- رفعتُ نسبة مطابقة الجودة في فحوصات الموقع إلى 96% وفق مواصفات أرامكو والبلدية.
- أعددتُ تقارير الإنجاز الأسبوعية وحساب الكميات والمستخلصات المالية للمقاولين بدقة.

المؤهلات العلمية

بكالوريوس الهندسة المدنية
جامعة الملك فهد للبترول والمعادن، الظهران | 2013 — 2017

الشهادات والاعتمادات
- عضوية واعتماد الهيئة السعودية للمهندسين (SCE) — درجة مهندس محترف
- شهادة إدارة المشاريع الاحترافية (PMP) — 2022
- شهادة السلامة والصحة المهنية OSHA 30

المهارات الفنية
- الإشراف الموقعي، إدارة العقود، الخرسانة المسلحة، ضبط الجودة، كود البناء السعودي
- البرامج: AutoCAD, Primavera P6, Civil 3D
CV,
            ],

            // Strong #11: Arabic Teacher (off-taxonomy, Arabic)
            [
                'id' => 'strong_arabic_teacher',
                'profession' => 'teacher',
                'target_job_title' => 'معلم رياضيات ثانوي',
                'quality' => 'strong',
                'language' => 'ar',
                'is_off_taxonomy' => true,
                'text' => <<<'CV'
إبراهيم بن محمد الشهري
أبها، المملكة العربية السعودية | ibrahim.shehri@example.com | +966502221199 | linkedin.com/in/ibrahim-teacher

الملخص المهني
معلم رياضيات للمرحلة الثانوية بخبرة تزيد عن 8 سنوات في التدريس التفاعلي وتطوير مناهج STEM. حاصل على الرخصة المهنية التعليمية ومتميز في رفع مستويات التحصيل الدراسي للطلاب في اختبارات القدرات والتحصيلي الوطنية.

الخبرات المهنية

معلم رياضيات أول — مدرسة الرواد الأهلية، أبها
2019 — حتى الآن
- درّستُ مقررات الجبر والتفاضل والتكامل لأكثر من 180 طالباً سنوياً في الصفوف الثانوية.
- رفعتُ متوسط درجات الطلاب في اختبار القدرات العامة بنسبة 24% خلال عامين دراسيين متتاليين.
- أطلقتُ مبادرة الفصول الذكية باستخدام تطبيقات GeoGebra ومنصة مدرستي، مما زاد تفاعل الطلاب بنسبة 35%.
- أشرفتُ على تدريب 15 طالباً للمشاركة في الأولمبياد الوطني للرياضيات وحقق 3 طلاب مراكز متقدمة.

معلم رياضيات — مدارس الفصحى، عسير
2016 — 2019
- أعددتُ خططاً تدريسية فصلية متوافقة مع معايير وزارة التعليم وهيئة تقويم التعليم والتدريب.
- خفضتُ نسبة التعثر الدراسي بنسبة 30% من خلال تنظيم ورش تقوية مسائية دورية.
- نسقتُ أكثر من 60 لقاءً مع أولياء الأمور لمتابعة النمو الأكاديمي والتربوي للطلاب.

المؤهلات العلمية

بكالوريوس التربية في الرياضيات
جامعة الملك خالد، أبها | 2012 — 2016
- مرتبة الشرف الثانية

الرخص والشهادات المهنية
- الرخصة المهنية التعليمية للمعلمين من هيئة تقويم التعليم والتدريب (ETEC)
- شهادة المعلم الرقمي المعتمد من مايكروسوفت (MIE) — 2021

المهارات التربوية والتقنية
- تخطيط الدروس، التقويم المستمر، استراتيجيات التعلم النشط، التعليم المتمايز، إدارة الصف
- المنصات: منصة مدرستي، Google Classroom, GeoGebra
CV,
            ],

            // Strong #12: Arabic HR Specialist (in-taxonomy, Arabic)
            [
                'id' => 'strong_arabic_hr',
                'profession' => 'hr',
                'target_job_title' => 'أخصائي موارد بشرية أول',
                'quality' => 'strong',
                'language' => 'ar',
                'is_off_taxonomy' => false,
                'text' => <<<'CV'
نورة بنت فيصل السبيعي
الرياض، المملكة العربية السعودية | noura.subaie@example.com | +966543332211 | linkedin.com/in/noura-hr-sa

الملخص المهني
أخصائية موارد بشرية أولى بخبرة 6 سنوات في استقطاب الكفاءات، إدارة علاقات الموظفين، والامتثال للوائح العمل السعودية. متخصصة في منصات قوى ومدد والتأمينات الاجتماعية، ونجحتُ في خفض معدل الدوران الوظيفي بنسبة 20% ورفع نسبة التوطين.

الخبرات المهنية

أخصائي موارد بشرية أول — شركة التقنية المتقدمة، الرياض
2021 — حتى الآن
- أدرتُ عمليات التوظيف واستقطاب الكفاءات لأكثر من 60 وظيفة تقنية وإدارية خلال عام واحد.
- خفضتُ تكلفة الاستقطاب بنسبة 25% من خلال تفعيل قنوات التوظيف المباشر ومنصة LinkedIn Recruiter.
- طوّرتُ نظام تقييم الأداء السنوي KPI لـ 200 موظف، مما رفع مؤشر الرضا الوظيفي بنسبة 15%.
- أشرفتُ على إدارة منصات قوى ومدد ومطابقة عقود العمل والامتثال لنظام حماية الأجور بنسبة 100%.

أخصائية شؤون موظفين — مجموعة الضيافة الحديثة، الرياض
2018 — 2021
- باشرتُ إجراءات تهيئة وتأهيل 80 موظفاً جديداً ونظمت برامج التدريب والتوجيه الوظيفي.
- نسقتُ مع التأمينات الاجتماعية وصندوق تنمية الموارد البشرية (هدف) للاستفادة من برامج الدعم.
- حدثتُ اللائحة الداخلية للشركة بما يتوافق مع تعديلات نظام العمل السعودي.

المؤهلات العلمية

بكالوريوس إدارة الموارد البشرية
جامعة الأميرة نورة بنت عبد الرحمن | 2014 — 2018

الشهادات المهنية
- شهادة محترف الموارد البشرية المعتمد من CIPD (المستوى الخامس) — 2022
- شهادة محترف معتمد من معهد SHRM (SHRM-CP) — 2021

المهارات التخصصية
- استقطاب المواهب، تقييم الأداء، إدارة علاقات الموظفين، سياسات العمل، التوطين ونطاقات
- الأنظمة: منصة قوى، مدد، التأمينات الاجتماعية، SAP SuccessFactors
CV,
            ],

            // Strong #13: Arabic Marketing (in-taxonomy, Arabic)
            [
                'id' => 'strong_arabic_marketing',
                'profession' => 'marketing',
                'target_job_title' => 'أخصائي تسويق رقمي',
                'quality' => 'strong',
                'language' => 'ar',
                'is_off_taxonomy' => false,
                'text' => <<<'CV'
فيصل بن حمد الدوسري
الرياض، المملكة العربية السعودية | faisal.dosari@example.com | +966557778899 | linkedin.com/in/faisal-marketing-sa

الملخص المهني
أخصائي تسويق رقمي بخبرة 5 سنوات في قيادة الحملات الإعلانية وتحسين محركات البحث SEO والتسويق عبر منصات التواصل الاجتماعي. أمتلك سجلاً حافلاً في مضاعفة المبيعات عبر الإنترنت وتحقيق عائد إعلاني ROAS تجاوز 4 أضعاف الميزانية المصروفة.

الخبرات المهنية

أخصائي تسويق رقمي أول — شركة المتاجر الإلكترونية، الرياض
2021 — حتى الآن
- أدرتُ ميزانيات إعلانية بقيمة 2,500,000 ريال سنوياً على منصات Google Ads وSnapchat وMeta.
- رفعتُ المبيعات الإلكترونية بنسبة 45% من خلال تحسين مسارات التحويل وتجارب A/B Testing.
- حسّنتُ تصنيف الموقع في محركات البحث SEO للكلمات المفتاحية الأكثر ربحية مما زاد الزيارات المجانية بنسبة 70%.
- أطلقتُ حملات بريد إلكتروني ورسائل SMS ترويجية مخصصة استهدفت أكثر من 80,000 عميل نشط.

مسؤول تسويق رقمي — وكالة الإبداع التسويقي، جدة
2019 — 2021
- أنشأتُ وأدرتُ 30 حملة تسويقية لعملاء تجاريين في قطاعات التجزئة والمطاعم.
- أنتجتُ محتوى إعلانياً إبداعياً حقق أكثر من 3 ملايين مشاهدة وتفاعل واسع على تيك توك وإكس.
- حللتُ مؤشرات الأداء التسويقية وأعددتُ تقارير تفصيلية أسبوعية للعملاء باستخدام Google Analytics.

المؤهلات العلمية

بكالوريوس التسويق والاتصال التسويقي
جامعة الملك عبد العزيز، جدة | 2015 — 2019

الشهادات المهنية
- شهادة إعلانات جوجل المعتمدة (Google Ads Search & Measurement) — 2023
- شهادة تسويق معتمد من منصة Meta — 2022
- شهادة HubSpot للتسويق بالمحتوى والتحليلات

المهارات الأساسية
- تحسين محركات البحث SEO، إعلانات Google Ads، الحملات الإعلانية المدفوعة PPC، إدارة الماركة، تحليلات الويب
- الأدوات: Google Analytics 4, SEMrush, Meta Business Suite, Mailchimp
CV,
            ],

            // Strong #14: Arabic E-commerce Manager (in-taxonomy, Arabic)
            [
                'id' => 'strong_arabic_ecommerce',
                'profession' => 'ecommerce',
                'target_job_title' => 'مدير متجر إلكتروني',
                'quality' => 'strong',
                'language' => 'ar',
                'is_off_taxonomy' => false,
                'text' => <<<'CV'
ماجد بن عبد الله الرويلي
الرياض، المملكة العربية السعودية | majed.ruwaili@example.com | +966562223344 | linkedin.com/in/majed-ecom

الملخص المهني
مدير متجر إلكتروني بخبرة 5 سنوات في إدارة منصات التجارة الإلكترونية (سلة، زد، Shopify) في السوق السعودي. متخصص في تحسين معدل التحويل CR، إدارة سلاسل الإمداد والمخزون، وتطوير تجربة المستخدم لزيادة متوسط قيمة السلة الشرائية AOV بنسبة 25%.

الخبرات المهنية

مدير متجر إلكتروني — شركة التجزئة الذكية، الرياض
2021 — حتى الآن
- أدرتُ العمليات اليومية لمتجر إلكتروني يتجاوز حجم مبيعاته 12 مليون ريال سنوياً مع أكثر من 60,000 طلب منجز.
- رفعتُ معدل التحويل Conversion Rate من 1.8% إلى 3.2% من خلال تحسين صفحات الهبوط وتبسيط خطوات الدفع.
- خفضتُ معدل التخلي عن سلة المشتريات بنسبة 30% عبر تطبيق رسائل التذكير التلقائية عبر الواتساب والبريد.
- فاوضتُ شركات الشحن والخدمات اللوجستية محققاً وفراً بنسبة 15% في تكلفة التوصيل لكل طلب.

مشرف متجر رقمي — علامة العطور الفاخرة، جدة
2019 — 2021
- رفعتُ قوائم المنتجات وتوصيفها وصورها بما يتوافق مع أفضل معايير تجربة المستخدم.
- أدرتُ خدمة العملاء وحللتُ شكاوى المرتجعات مما رفع تقييم رضا العملاء إلى 4.7 من 5 نجوم.
- نسقتُ حملات التخفيضات الكبرى (يوم التأسيس، اليوم الوطني، البلاك فرايداي) محققاً نمواً في المبيعات بنسبة 80%.

المؤهلات العلمية

بكالوريوس نظم المعلومات الإدارية
جامعة الإمام محمد بن سعود الإسلامية، الرياض | 2015 — 2019

الشهادات والاعتمادات
- شهادة محترف التجارة الإلكترونية المعتمد
- شهادة إعلانات Google Analytics 4 وتحليل مسارات الشراء — 2022

المهارات المهنية
- التجارة الإلكترونية، إدارة المخزون، منصة سلة، منصة زد، Shopify، بوابات الدفع، تحسين السلة
- الأدوات: Google Analytics, Salla, Zid, Hotjar, Klaviyo
CV,
            ],

            // Strong #15: Arabic Data Analyst (in-taxonomy, Arabic)
            [
                'id' => 'strong_arabic_data',
                'profession' => 'data',
                'target_job_title' => 'محلل بيانات أول',
                'quality' => 'strong',
                'language' => 'ar',
                'is_off_taxonomy' => false,
                'text' => <<<'CV'
ياسر بن صالح الغامدي
جدة، المملكة العربية السعودية | yasser.data@example.com | +966504445566 | linkedin.com/in/yasser-data-sa

الملخص المهني
محلل بيانات أول بخبرة 5 سنوات في تحويل البيانات المعقدة إلى رؤى استراتيجية تدعم اتخاذ القرارات التنفيذية. متمكن من بناء لوحات المعلومات التفاعلية باستخدام Power BI وكتابة استعلامات SQL متقدمة وبناء نماذج التنبؤ الإحصائي باستخدام Python.

الخبرات المهنية

محلل بيانات أول — شركة الحلول الرقمية، الرياض
2021 — حتى الآن
- بنيتُ وأطلقتُ أكثر من 20 لوحة تفاعلية على Power BI تخدم الإدارة العليا وتتابع مؤشرات الأداء اللحظية لأكثر من 500,000 عملية.
- حسّنتُ عمليات استخراج وتحويل وتحميل البيانات ETL مما قلص وقت إعداد التقارير اليومية بنسبة 60%.
- نفذتُ نماذج تقسيم وتجزئة العملاء Customer Segmentation مما ساهم في زيادة معدل الاحتفاظ بالعملاء بنسبة 18%.
- أتمتتُ استعلامات قواعد البيانات SQL الكبيرة وخفضت وقت معالجة الاستعلامات الضخمة بنسبة 40%.

محلل بيانات الأعمال — شركة الخدمات اللوجستية، جدة
2019 — 2021
- حللتُ بيانات سلاسل الإمداد وأوقات التوصيل وكشفتُ عن نقاط الاختناق في مسارات النقل موفراً 400,000 ريال.
- أعددتُ تقارير أسبوعية تفصيلية باستخدام Excel و SQL لقياس دقة مواعيد التسليم.
- طوّرتُ نموذجاً تنبؤياً للطلب الموسمي خفض نسبة نقص المخزون بنسبة 25%.

المؤهلات العلمية

بكالوريوس علوم الحاسب والمعلومات
جامعة الملك عبد العزيز، جدة | 2015 — 2019

الشهادات المهنية
- شهادة محلل بيانات معتمد من مايكروسوفت (Microsoft Certified: Power BI Data Analyst Associate) — 2023
- شهادة محترف تحليلات البيانات من Google (Google Data Analytics Professional) — 2022

المهارات التقنية
- تحليل البيانات، ذكاء الأعمال، النمذجة الإحصائية، بناء التقارير، هندسة البيانات
- الأدوات: SQL, Python, Power BI, Tableau, Excel, PostgreSQL
CV,
            ],

            // Strong #16: Arabic Customer Care (general, Arabic)
            [
                'id' => 'strong_arabic_customer_care',
                'profession' => 'customer_care',
                'target_job_title' => 'مشرف خدمة عملاء',
                'quality' => 'strong',
                'language' => 'ar',
                'is_off_taxonomy' => false,
                'text' => <<<'CV'
هند بنت فهد القحطاني
الرياض، المملكة العربية السعودية | hind.care@example.com | +966551113355 | linkedin.com/in/hind-care

الملخص المهني
مشرفة خدمة عملاء بخبرة 6 سنوات في إدارة مراكز الاتصال ودعم العملاء في قطاع التجزئة والخدمات. متخصصة في تدريب فرق العمل ورفع كفاءة الاستجابة السريعة ومعالجة الشكاوى بنسبة نجاح بلغت 95% ومؤشر رضا عملاء CSAT تجاوز 94%.

الخبرات المهنية

مشرفة فريق خدمة العملاء — شركة الاتصالات والخدمات، الرياض
2021 — حتى الآن
- أشرفتُ على فريق عمل مكون من 14 ممثل خدمة عملاء يعالج أكثر من 20,000 تذكرة واستفسار شهرياً.
- خفضتُ متوسط زمن الاستجابة الأول First Response Time بنسبة 40% عبر إعادة هيكلة قنوات التواصل وتفعيل الردود الذكية.
- حققتُ معدل حل الشكاوى من أول اتصال First Contact Resolution بنسبة 88%.
- صممتُ ونفذتُ برنامجاً تدريبياً لتهيئة 25 موظفاً جديداً مما خفض أخطاء التوجيه بنسبة 35%.

أخصائية تجربة وخدمة عملاء — منصة التوصيل السريع، الرياض
2018 — 2021
- تعاملتُ مع مكالمات وشكاوى العملاء والشركاء بمعدل 70 معاملة يومياً مع الحفاظ على تقييم رضا 4.8 من 5.
- شاركتُ في إعداد دليل سياسات وإجراءات خدمة العملاء ومعالجة الاعتراضات.
- نسقتُ مع فرق التشغيل والجودة لتحسين تجربة العميل وتقليل طلبات الإلغاء.

المؤهلات العلمية

بكالوريوس إدارة الأعمال
جامعة الملك سعود، الرياض | 2014 — 2018

الشهادات المهنية
- شهادة إدارة تجربة العملاء المعتمدة (CCXP) — 2022
- شهادة مهارات الاتصال المتقدم والتعامل مع الجمهور — 2020

المهارات الأساسية
- خدمة العملاء، إدارة الفريق، حل المشكلات، معالجة الشكاوى، مؤشرات رضا العملاء CSAT، التواصل الفعال
- الأدوات: Zendesk, Salesforce Service Cloud, Freshdesk, Avaya
CV,
            ],

            // Strong #17: Paired Arabic Finance
            [
                'id' => 'strong_arabic_finance',
                'profession' => 'finance',
                'target_job_title' => 'محلل مالي أول',
                'quality' => 'strong',
                'language' => 'ar',
                'is_off_taxonomy' => false,
                'text' => self::pairedEquivalentCvs()['ar']['text'],
            ],

            // Weak #1: Nurse (off-taxonomy)
            [
                'id' => 'weak_nurse',
                'profession' => 'nurse',
                'target_job_title' => 'Registered Nurse',
                'quality' => 'weak',
                'language' => 'en',
                'is_off_taxonomy' => true,
                'text' => <<<'CV'
Maha
Nurse looking for a job.
Experience:
Worked in a clinic. Helped doctors and took care of patients. Took vitals and answered calls.
Education:
Nursing school diploma.
CV,
            ],

            // Weak #2: Software (in-taxonomy)
            [
                'id' => 'weak_software',
                'profession' => 'software',
                'target_job_title' => 'Software Developer',
                'quality' => 'weak',
                'language' => 'en',
                'is_off_taxonomy' => false,
                'text' => <<<'CV'
Ali
Programmer.
Experience:
Worked at a shop. Wrote some code and fixed computers. Looked at websites.
Skills:
Computers, internet, typing.
Education:
College degree.
CV,
            ],

            // Weak #3: Civil Engineer (off-taxonomy)
            [
                'id' => 'weak_civil_engineer',
                'profession' => 'civil_engineer',
                'target_job_title' => 'Civil Site Engineer',
                'quality' => 'weak',
                'language' => 'en',
                'is_off_taxonomy' => true,
                'text' => <<<'CV'
Fahad
Civil engineer looking for site work.
Experience:
Was at a construction site. Looked at concrete and talked to workers. Checked blueprints.
Education:
University degree in engineering.
CV,
            ],

            // Weak #4: Teacher (off-taxonomy)
            [
                'id' => 'weak_teacher',
                'profession' => 'teacher',
                'target_job_title' => 'Secondary Mathematics Teacher',
                'quality' => 'weak',
                'language' => 'en',
                'is_off_taxonomy' => true,
                'text' => <<<'CV'
Noura
Teacher.
Experience:
Taught math in a private school. Handed out homework and corrected exams.
Education:
Degree in science.
CV,
            ],

            // Weak #5: Marketing (in-taxonomy)
            [
                'id' => 'weak_marketing',
                'profession' => 'marketing',
                'target_job_title' => 'Marketing Specialist',
                'quality' => 'weak',
                'language' => 'en',
                'is_off_taxonomy' => false,
                'text' => <<<'CV'
Khaled
Marketing guy.
Experience:
Did some social media posting. Put pictures on Instagram and replied to messages.
Skills:
Social media, phone calls.
CV,
            ],

            // Weak #6: Finance (in-taxonomy)
            [
                'id' => 'weak_finance',
                'profession' => 'finance',
                'target_job_title' => 'Senior Financial Analyst',
                'quality' => 'weak',
                'language' => 'en',
                'is_off_taxonomy' => false,
                'text' => <<<'CV'
Saud
Finance worker.
Experience:
Helped accountant. Entered receipts in excel and counted cash.
Education:
Diploma.
CV,
            ],

            // Weak #7: Arabic Nurse (off-taxonomy, Arabic)
            [
                'id' => 'weak_arabic_nurse',
                'profession' => 'nurse',
                'target_job_title' => 'أخصائي تمريض',
                'quality' => 'weak',
                'language' => 'ar',
                'is_off_taxonomy' => true,
                'text' => <<<'CV'
أمل
ممرضة أبحث عن عمل في مستشفى.
الخبرة:
اشتغلت في مستوصف خاص. ساعدت الطبيب وقست الضغط وضربت إبر واستقبلت المرضى.
التعليم:
دبلوم تمريض.
CV,
            ],

            // Weak #8: Arabic Civil Engineer (off-taxonomy, Arabic)
            [
                'id' => 'weak_arabic_civil_engineer',
                'profession' => 'civil_engineer',
                'target_job_title' => 'مهندس موقع مدني',
                'quality' => 'weak',
                'language' => 'ar',
                'is_off_taxonomy' => true,
                'text' => <<<'CV'
بدر
مهندس مدني.
الخبرة:
كنت في الموقع وأشرفت على العمال وشفت صب الخرسانة وتابعت المقاولين وتكلمت مع الاستشاري.
التعليم:
بكالوريوس هندسة.
CV,
            ],

            // Weak #9: Arabic Teacher (off-taxonomy, Arabic)
            [
                'id' => 'weak_arabic_teacher',
                'profession' => 'teacher',
                'target_job_title' => 'معلم رياضيات',
                'quality' => 'weak',
                'language' => 'ar',
                'is_off_taxonomy' => true,
                'text' => <<<'CV'
سعد
معلم.
الخبرة:
درست مادة الرياضيات في مدرسة وشرحت الدروس وصححت الدفاتر وحضرت الطابور الصباحي.
التعليم:
شهادة جامعية.
CV,
            ],

            // Weak #10: Arabic Software (in-taxonomy, Arabic)
            [
                'id' => 'weak_arabic_software',
                'profession' => 'software',
                'target_job_title' => 'مبرمج تطبيقات',
                'quality' => 'weak',
                'language' => 'ar',
                'is_off_taxonomy' => false,
                'text' => <<<'CV'
حسام
مبرمج كمبيوتر ومواقع.
الخبرة:
سويت مواقع بسيطة وبرمجت صفحات وعدلت على كود وجربت البرامج وشفت قواعد البيانات.
المهارات:
حاسب آلي، إنترنت، كتابة سريعة.
التعليم:
دبلوم حاسب.
CV,
            ],

            // Weak #11: Arabic HR (in-taxonomy, Arabic)
            [
                'id' => 'weak_arabic_hr',
                'profession' => 'hr',
                'target_job_title' => 'أخصائي موارد بشرية',
                'quality' => 'weak',
                'language' => 'ar',
                'is_off_taxonomy' => false,
                'text' => <<<'CV'
منيرة
موارد بشرية.
الخبرة:
اشتغلت في مكتب وسويت مقابلات وحفظت ملفات الموظفين وسجلت الإجازات وكلمت المتقدمين.
التعليم:
دبلوم إدارة.
CV,
            ],

            // Weak #12: Arabic Admin (general, Arabic)
            [
                'id' => 'weak_arabic_admin',
                'profession' => 'admin',
                'target_job_title' => 'موظف إداري',
                'quality' => 'weak',
                'language' => 'ar',
                'is_off_taxonomy' => false,
                'text' => <<<'CV'
تركي
إداري أبحث عن وظيفة مناسبة.
الخبرة:
طبعت أوراق ورديت على التلفون ورتبت الملفات واستقبلت المراجعين ووزعت المعاملات.
التعليم:
ثانوية عامة.
CV,
            ],

            // Benchmark Junk (Junk #1)
            self::benchmarkJunk(),

            // Adversarial Junk (Junk #2 - verbatim review CV)
            self::adversarialJunk(),

            // Benchmark Zero Metric Admin (Weak #13)
            self::benchmarkZeroMetricAdmin(),
        ];
    }
}
