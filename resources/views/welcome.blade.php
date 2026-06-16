<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sirati منصة عربية تساعدك على تحليل سيرتك الذاتية وتحسينها لأنظمة ATS بشكل احترافي.">
    <title>Sirati | سيرتك الذكية تبدأ هنا</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #020617;
            --card: #0f172a;
            --card-strong: #111c35;
            --muted: #94a3b8;
            --line: #1e3a5f;
            --primary: #38bdf8;
            --secondary: #818cf8;
            --text: #f8fafc;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(56, 189, 248, .22), transparent 30rem),
                radial-gradient(circle at bottom right, rgba(129, 140, 248, .2), transparent 34rem),
                var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            line-height: 1.8;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .page {
            width: min(1160px, calc(100% - 32px));
            margin: 0 auto;
        }

        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 0;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 900;
            letter-spacing: .02em;
        }

        .brand-mark {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border: 1px solid rgba(56, 189, 248, .45);
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(56, 189, 248, .18), rgba(129, 140, 248, .22));
            color: var(--primary);
            box-shadow: 0 0 28px rgba(56, 189, 248, .18);
        }

        .nav-links {
            display: flex;
            gap: 18px;
            color: var(--muted);
            font-size: 14px;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(340px, .92fr);
            gap: 34px;
            align-items: center;
            padding: 58px 0 34px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border: 1px solid rgba(56, 189, 248, .35);
            border-radius: 999px;
            background: rgba(14, 116, 144, .18);
            color: #bae6fd;
            font-size: 13px;
            font-weight: 800;
        }

        h1 {
            max-width: 760px;
            margin: 18px 0 12px;
            font-size: clamp(36px, 6vw, 68px);
            font-weight: 900;
            line-height: 1.15;
            letter-spacing: -.04em;
        }

        .gradient-text {
            background: linear-gradient(135deg, var(--primary), var(--secondary), #f472b6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero-copy {
            max-width: 680px;
            color: #cbd5e1;
            font-size: 19px;
            margin: 0 0 24px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 26px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 11px 20px;
            border-radius: 14px;
            border: 1px solid transparent;
            cursor: pointer;
            font: inherit;
            font-weight: 900;
        }

        .button-primary {
            background: linear-gradient(135deg, #0284c7, #4f46e5);
            color: white;
            box-shadow: 0 16px 40px rgba(37, 99, 235, .28);
        }

        .button-secondary {
            border-color: rgba(148, 163, 184, .26);
            background: rgba(15, 23, 42, .72);
            color: #dbeafe;
        }

        .metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            max-width: 680px;
        }

        .metric {
            padding: 14px;
            border: 1px solid rgba(30, 58, 95, .85);
            border-radius: 16px;
            background: rgba(15, 23, 42, .72);
        }

        .metric strong {
            display: block;
            color: var(--primary);
            font-size: 24px;
            line-height: 1.1;
        }

        .metric span {
            color: var(--muted);
            font-size: 13px;
        }

        .lead-card {
            border: 1px solid rgba(56, 189, 248, .25);
            border-radius: 28px;
            background: linear-gradient(145deg, rgba(15, 23, 42, .96), rgba(17, 28, 53, .96));
            padding: 24px;
            box-shadow: 0 24px 80px rgba(2, 8, 23, .38);
        }

        .lead-card h2 {
            margin: 0 0 8px;
            font-size: 24px;
            line-height: 1.35;
        }

        .lead-card p {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 14px;
        }

        .status,
        .error-box {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 14px;
        }

        .status {
            border: 1px solid rgba(34, 197, 94, .35);
            background: rgba(5, 46, 22, .45);
            color: #bbf7d0;
        }

        .error-box {
            border: 1px solid rgba(239, 68, 68, .36);
            background: rgba(127, 29, 29, .25);
            color: #fecaca;
        }

        form {
            display: grid;
            gap: 14px;
        }

        label {
            display: grid;
            gap: 6px;
            color: #dbeafe;
            font-size: 13px;
            font-weight: 800;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid rgba(30, 58, 95, .95);
            border-radius: 14px;
            background: rgba(2, 6, 23, .66);
            color: var(--text);
            font: inherit;
            padding: 12px 13px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        textarea {
            min-height: 92px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: rgba(56, 189, 248, .78);
            box-shadow: 0 0 0 4px rgba(56, 189, 248, .12);
        }

        .field-error {
            color: #fca5a5;
            font-size: 12px;
            font-weight: 700;
        }

        .section {
            padding: 40px 0;
        }

        .section-title {
            margin: 0 0 10px;
            font-size: clamp(26px, 4vw, 40px);
            line-height: 1.25;
        }

        .section-copy {
            max-width: 760px;
            margin: 0 0 24px;
            color: var(--muted);
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .card {
            border: 1px solid rgba(30, 58, 95, .82);
            border-radius: 22px;
            background: rgba(15, 23, 42, .72);
            padding: 20px;
        }

        .card-icon {
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            margin-bottom: 12px;
            border-radius: 15px;
            background: rgba(56, 189, 248, .12);
            color: var(--primary);
            font-size: 16px;
            font-weight: 900;
        }

        .card h3,
        .workflow-step h3 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .card p,
        .workflow-step p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .workflow {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            counter-reset: step;
        }

        .workflow-step {
            border: 1px solid rgba(129, 140, 248, .28);
            border-radius: 20px;
            background: rgba(17, 24, 39, .78);
            padding: 18px;
            counter-increment: step;
        }

        .workflow-step::before {
            content: counter(step);
            display: grid;
            width: 32px;
            height: 32px;
            place-items: center;
            margin-bottom: 12px;
            border-radius: 50%;
            background: rgba(129, 140, 248, .22);
            color: #c4b5fd;
            font-weight: 900;
        }

        .footer {
            padding: 32px 0 44px;
            color: #64748b;
            text-align: center;
            font-size: 13px;
        }

        @media (max-width: 900px) {
            .hero,
            .cards,
            .workflow {
                grid-template-columns: 1fr;
            }

            .nav-links {
                display: none;
            }

            .metrics {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <nav class="nav" aria-label="التنقل الرئيسي">
            <a class="brand" href="{{ route('landing') }}" aria-label="Sirati">
                <span class="brand-mark">س</span>
                <span>Sirati</span>
            </a>

            <div class="nav-links">
                <a href="#features">المزايا</a>
                <a href="#workflow">طريقة العمل</a>
                <a href="{{ route('analyses.create') }}">فحص السيرة</a>
                <a href="{{ route('generated-cvs.create') }}">إنشاء سيرة ذاتية</a>
                <a href="#join">جرّب مبكراً</a>
            </div>
        </nav>

        <section class="hero">
            <div>
                <span class="eyebrow">منصة عربية لتحسين السيرة الذاتية بشكل احترافي</span>
                <h1>اعرف قوة سيرتك قبل أن ترسلها، وابنِ نسخة <span class="gradient-text">جاهزة لأنظمة ATS</span>.</h1>
                <p class="hero-copy">
                    Sirati يساعدك على فحص سيرتك الحالية، اكتشاف الكلمات المفتاحية الناقصة، وتحسين الملخص والخبرات لتناسب الوظيفة المستهدفة.
                </p>

                <div class="actions">
                    <a class="button button-primary" href="{{ route('analyses.create') }}">ابدأ فحص السيرة</a>
                    <a class="button button-secondary" href="{{ route('analyses.create', ['demo' => 1]) }}">جرّب سيرة تجريبية</a>
                    <a class="button button-secondary" href="{{ route('generated-cvs.create') }}">أنشئ سيرة متوافقة مع ATS</a>
                    <a class="button button-primary" href="#join">انضم لقائمة التجربة</a>
                    <a class="button button-secondary" href="#workflow">شاهد طريقة العمل</a>
                </div>

                <div class="metrics" aria-label="مؤشرات المنتج">
                    <div class="metric">
                        <strong>100</strong>
                        <span>درجة تحليل واضحة للسيرة</span>
                    </div>
                    <div class="metric">
                        <strong>7</strong>
                        <span>معايير ATS أساسية</span>
                    </div>
                    <div class="metric">
                        <strong>عربي</strong>
                        <span>واجهة RTL من البداية</span>
                    </div>
                </div>
            </div>

            <aside id="join" class="lead-card" aria-label="نموذج الانضمام للتجربة">
                <h2>احجز وصولك المبكر</h2>
                <p>اترك بياناتك وسنرسل لك رابط التجربة الأولى عند جاهزية النسخة الأولية.</p>

                @if (session('status'))
                    <div class="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="error-box">راجع الحقول المطلوبة وحاول مرة أخرى.</div>
                @endif

                <form method="POST" action="{{ route('landing-leads.store') }}">
                    @csrf

                    <label>
                        الاسم الكامل
                        <input name="full_name" value="{{ old('full_name') }}" autocomplete="name" required>
                        @error('full_name') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        البريد الإلكتروني
                        <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                        @error('email') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        رقم الجوال
                        <input name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="+966 5x xxx xxxx">
                        @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        ما الذي يهمك أكثر؟
                        <select name="role_interest" required>
                            <option value="both" @selected(old('role_interest', 'both') === 'both')>فحص السيرة وإنشاء سيرة جديدة</option>
                            <option value="analyze" @selected(old('role_interest') === 'analyze')>فحص سيرتي الحالية</option>
                            <option value="create" @selected(old('role_interest') === 'create')>إنشاء سيرة ذاتية جديدة</option>
                        </select>
                        @error('role_interest') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        الوظيفة المستهدفة
                        <input name="target_job_title" value="{{ old('target_job_title') }}" placeholder="مثال: محلل بيانات، مطور Backend، أخصائي تسويق">
                        @error('target_job_title') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        ملاحظات اختيارية
                        <textarea name="notes" placeholder="ما أكبر مشكلة تواجهك مع سيرتك الذاتية؟">{{ old('notes') }}</textarea>
                        @error('notes') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <button class="button button-primary" type="submit">أرسل لي رابط التجربة</button>
                </form>
            </aside>
        </section>

        <section id="features" class="section">
            <h2 class="section-title">نسخة أولية لاختبار الطلب بسرعة</h2>
            <p class="section-copy">
                نبدأ بصفحة رئيسية تجمع المهتمين، ثم نبني تحليل ATS وتوليد السيرة خطوة بخطوة عندما تثبت الفكرة اهتماماً حقيقياً.
            </p>

            <div class="cards">
                <article class="card">
                    <div class="card-icon">ATS</div>
                    <h3>تحليل ATS واضح</h3>
                    <p>درجة عامة، معايير مفصلة، كلمات ناقصة، ونقاط تحسين قابلة للتنفيذ.</p>
                </article>
                <article class="card">
                    <div class="card-icon">UP</div>
                    <h3>تحسين احترافي</h3>
                    <p>اقتراحات للملخص المهني، المهارات، والخبرات بدون تغيير الحقائق التي يكتبها المستخدم.</p>
                </article>
                <article class="card">
                    <div class="card-icon">PDF</div>
                    <h3>سيرة جاهزة لاحقاً</h3>
                    <p>بعد التحقق من الطلب، سيتم إنشاء سيرة منظمة قابلة للتصدير ومناسبة للتقديم.</p>
                </article>
            </div>
        </section>

        <section id="workflow" class="section">
            <h2 class="section-title">طريقة عمل Sirati المقترحة</h2>
            <p class="section-copy">
                المرحلة الأولى تقيس اهتمام المستخدمين. المراحل التالية تضيف واجهة برمجة تطبيقات، ولوحة تحكم، وتحليل السير، ثم تطبيقاً للهواتف.
            </p>

            <div class="workflow">
                <article class="workflow-step">
                    <h3>الصفحة الرئيسية</h3>
                    <p>جمع بيانات المهتمين والوظائف المستهدفة لقياس الطلب.</p>
                </article>
                <article class="workflow-step">
                    <h3>تحليل السيرة</h3>
                    <p>رفع أو لصق نص السيرة وحساب درجة التوافق مع ATS وفق معايير ثابتة.</p>
                </article>
                <article class="workflow-step">
                    <h3>اقتراحات تحسين</h3>
                    <p>شرح نقاط الضعف واقتراح كلمات وعبارات محسنة.</p>
                </article>
                <article class="workflow-step">
                    <h3>إنشاء السيرة</h3>
                    <p>معالج خطوات لإنشاء سيرة محسنة قابلة للتصدير.</p>
                </article>
            </div>
        </section>
    </main>

    <footer class="footer">
        Sirati © {{ now()->year }} — نسخة تحقق أولية للمنتج
    </footer>
</body>
</html>
