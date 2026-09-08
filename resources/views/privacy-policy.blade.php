@extends('layouts.sirati')

@section('title', 'سياسة الخصوصية | Sirati')

@section('content')
    <section class="hero-card">
        <h1>سياسة الخصوصية وحماية البيانات</h1>
        <p>نلتزم بحماية بياناتك الشخصية وفقاً لنظام حماية البيانات الشخصية (PDPL) في المملكة العربية السعودية.</p>
    </section>

    <section class="card" style="margin-top: 18px; line-height: 1.9;">
        <h2>1. ما البيانات التي نجمعها؟</h2>
        <p>
            • <strong>بيانات الحساب والهوية:</strong> الاسم الكامل، البريد الإلكتروني، رقم الجوال (اختياري)، والموقع الجغرافي.<br>
            • <strong>محتوى السيرة الذاتية:</strong> المؤهلات التعليمية، الخبرات المهنية، المهارات، اللغات، والشهادات التي تدخلها أو ترفعها (ملفات PDF / Word).<br>
            • <strong>بيانات التشغيل والتقنية:</strong> رمز الجهاز للإشعارات (FCM Token)، وسجلات الأعطال التقنية المشفرة (عبر Sentry) لتحسين استقرار التطبيق.<br>
            • <strong>بيانات الاشتراكات:</strong> معلومات التحقق من صحة الاشتراكات الرقمية وعمليات الشراء داخل التطبيق (عبر RevenueCat)، دون حفظ أي بيانات لبطاقاتك الائتمانية.
        </p>

        <h2>2. كيف نستخدم البيانات؟</h2>
        <p>
            تُستخدم بياناتك فقط للأغراض الآتية:<br>
            • حساب درجة توافق السيرة مع أنظمة الفرز الآلي (ATS Scoring).<br>
            • إنشاء وتحسين وتنسيق السير الذاتية وتصديرها بصيغة PDF.<br>
            • تقديم توصيات مهنية ومطابقة الوظائف السعودية المناسبة لمسارك.<br>
            • إرسال الإشعارات التنبيهية والتحديثات المهنية بناءً على رغبتك.<br>
            • لا نبيع بياناتك الشخصية أو نؤجرها لأي طرف ثالث لأغراض التسويق أو الإعلانات.
        </p>

        <h2>3. معالجو البيانات من الأطراف الثالثة (Sub-Processors)</h2>
        <p>
            لتقديم خدمات المنصة بأعلى موثوقية، نتعامل مع مزودي خدمات موثوقين وفق اتفاقيات حماية بيانات صارمة:<br>
            • <strong>OpenAI / DeepInfra:</strong> معالجة طلبات الذكاء الاصطناعي لتحسين نصوص السيرة واقتراح التعديلات (تُرسل البيانات بصيغة مشفرة دون تخزينها لتدريب النماذج).<br>
            • <strong>RevenueCat:</strong> إدارة وتأكيد صلاحيات الاشتراكات وتجديدها في متاجر التطبيقات (Apple App Store / Google Play).<br>
            • <strong>Firebase (Google Cloud):</strong> تسليم إشعارات الدفع للهاتف (Push Notifications).<br>
            • <strong>Sentry:</strong> مراقبة الأعطال التقنية وتتبع أخطاء الأداء مع حجب كافة البيانات الحساسة ومحتويات السير تلقائياً.
        </p>

        <h2>4. فترات الاحتفاظ بالبيانات (Retention Policy)</h2>
        <p>
            • <strong>المستخدمون المسجلون:</strong> نحتفظ ببياناتك طوال فترة نشاط حسابك لتمكينك من الوصول إلى سيرك الذاتية وتحليلاتك وتعديلها في أي وقت.<br>
            • <strong>التحليلات المؤقتة لزوار الموقع (Guest Analyses):</strong> يتم حفظ محتوى السيرة المؤقت لزوار الموقع لمدة أقصاها 24 ساعة لتمكين الزائر من الاطلاع على نتيجته، ثم تُحذف تلقائياً من خوادمنا بشكل نهائي.<br>
            • <strong>حذف الحساب:</strong> عند قيامك بحذف حسابك من داخل التطبيق (الإعدادات ← حذف الحساب)، يتم مسح كافة بياناتك وسيرك الذاتية وسجلاتك بصورة نهائية وفورية من قواعد البيانات.
        </p>

        <h2>5. حقوقك بموجب نظام حماية البيانات الشخصية (PDPL)</h2>
        <p>
            يحق لك في أي وقت ممارسة حقوقك النظامية:<br>
            • <strong>حق العلم والوصول:</strong> الاطلاع على بياناتك الشخصية المخزنة لدينا وتصديرها.<br>
            • <strong>حق التصحيح:</strong> تحديث أو تعديل أي بيانات غير دقيقة مباشرة من التطبيق.<br>
            • <strong>حق الإتلاف والمحو:</strong> حذف سيرتك الذاتية أو حذف حسابك وبياناتك بالكامل بنقرة زر.<br>
            • <strong>حق الرجوع عن الموافقة:</strong> إيقاف الإشعارات أو إلغاء الاشتراك في أي وقت.
        </p>

        <h2>6. التواصل والدعم</h2>
        <p>
            لأي استفسارات تتعلق بسياسة الخصوصية أو ممارسة حقوقك في حماية البيانات، يسعدنا تواصلك معنا مباشرة عبر البريد الإلكتروني المعتمد:<br>
            <a href="mailto:support@siratie.com" style="color: #0d9488; font-weight: 700;">support@siratie.com</a>
        </p>

        <hr style="margin: 24px 0; border: 0; border-top: 1px solid #e2e8f0;">

        <h2>English Summary & Compliance Overview</h2>
        <p>
            Sirati complies with the Saudi Personal Data Protection Law (PDPL). We process personal data strictly to provide ATS analysis, CV generation, job recommendations, and account services. We never sell your data or use it for advertising. Third-party sub-processors include <strong>OpenAI / DeepInfra</strong> (AI content generation), <strong>RevenueCat</strong> (in-app subscription entitlement management), <strong>Google Firebase</strong> (push notifications), and <strong>Sentry</strong> (crash diagnostics with sensitive PII scrubbed). Guest CV submissions are retained temporarily for up to 24 hours and permanently pruned. Registered users may permanently delete their entire account and all associated CV data at any time via in-app settings (DELETE /api/auth/account). For privacy questions, contact <a href="mailto:support@siratie.com">support@siratie.com</a>.
        </p>
    </section>
@endsection
