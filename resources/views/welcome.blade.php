<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="سيرتي منصة عربية لتطوير المسار المهني، بناء السيرة الذاتية، تحليلها، واكتشاف فرص العمل في السوق السعودي والخليجي.">
    <title>سيرتي | Sirati</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark;
            --teal: #00a898;
            --teal-dark: #007a6f;
            --teal-glow: rgba(0, 168, 152, .2);
            --navy: #0d1b2a;
            --navy-mid: #132236;
            --navy-card: #1a2e42;
            --white: #ffffff;
            --muted: #8aa3ba;
            --font-ar: 'IBM Plex Sans Arabic', sans-serif;
            --font-en: 'Inter', sans-serif;
            --bg: var(--navy);
            --bg-mid: var(--navy-mid);
            --bg-card: var(--navy-card);
            --bg-footer: #080f18;
            --text-main: var(--white);
            --text-muted: var(--muted);
            --border-soft: rgba(0, 168, 152, .12);
            --border-med: rgba(0, 168, 152, .18);
            --nav-bg: rgba(13, 27, 42, .85);
            --shadow-soft: rgba(0, 0, 0, .3);
            --grid-line: rgba(0, 168, 152, .06);
            --btn-store-bg: var(--white);
            --btn-store-fg: var(--navy);
        }

        body.light {
            color-scheme: light;
            --bg: #f6f9fb;
            --bg-mid: #eef3f6;
            --bg-card: #ffffff;
            --bg-footer: #e9eef2;
            --text-main: #0d1b2a;
            --text-muted: #536b7d;
            --border-soft: rgba(0, 168, 152, .18);
            --border-med: rgba(0, 168, 152, .24);
            --nav-bg: rgba(255, 255, 255, .86);
            --shadow-soft: rgba(13, 27, 42, .08);
            --grid-line: rgba(0, 168, 152, .08);
            --btn-store-bg: #0d1b2a;
            --btn-store-fg: #ffffff;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
            background: var(--bg);
            color: var(--text-main);
            direction: rtl;
            font-family: var(--font-ar);
            line-height: 1.8;
            transition: background .35s ease, color .35s ease;
        }
        body.en {
            direction: ltr;
            font-family: var(--font-en);
        }
        a { color: inherit; text-decoration: none; }
        button { cursor: pointer; font: inherit; }

        .site-nav {
            position: fixed;
            inset: 0 0 auto;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 18px 40px;
            background: var(--nav-bg);
            border-bottom: 1px solid var(--border-med);
            backdrop-filter: blur(14px);
        }
        .nav-logo,
        .nav-right,
        .nav-links { display: inline-flex; align-items: center; }
        .nav-logo { gap: 12px; font-weight: 700; }
        .logo-img {
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            color: #fff;
            font-weight: 800;
            box-shadow: 0 0 26px var(--teal-glow);
        }
        .nav-logo span { font-size: 1.3rem; letter-spacing: 0; }
        .nav-links { gap: 10px; color: var(--text-muted); font-size: .9rem; font-weight: 600; }
        .nav-links a { padding: 7px 10px; border-radius: 999px; transition: color .2s ease, background .2s ease; }
        .nav-links a:hover,
        .nav-links a:focus { color: var(--teal); background: rgba(0, 168, 152, .09); outline: none; }
        .nav-right { gap: 10px; }
        .lang-toggle,
        .theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid var(--teal);
            background: transparent;
            color: var(--teal);
            transition: background .2s ease, color .2s ease, transform .25s ease;
        }
        .lang-toggle {
            min-width: 70px;
            padding: 7px 18px;
            border-radius: 20px;
            font-family: var(--font-en);
            font-size: .85rem;
            font-weight: 700;
        }
        .theme-toggle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
        }
        .lang-toggle:hover,
        .theme-toggle:hover { background: var(--teal); color: #fff; }
        .theme-toggle:hover { transform: rotate(15deg); }
        .theme-toggle svg { width: 18px; height: 18px; }
        .theme-toggle .icon-sun { display: none; }
        body.light .theme-toggle .icon-sun { display: block; }
        body.light .theme-toggle .icon-moon { display: none; }

        .hero {
            position: relative;
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 120px 24px 86px;
            text-align: center;
        }
        .hero::before,
        .hero::after {
            position: absolute;
            inset: 0;
            content: '';
            pointer-events: none;
        }
        .hero::before {
            background:
                radial-gradient(ellipse 60% 50% at 50% 30%, rgba(0, 168, 152, .18) 0%, transparent 70%),
                radial-gradient(ellipse 40% 40% at 80% 80%, rgba(245, 166, 35, .07) 0%, transparent 60%);
        }
        .hero::after {
            background-image:
                linear-gradient(var(--grid-line) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-line) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
        }
        .hero-logo,
        .hero-tagline,
        .hero-headline,
        .hero-sub,
        .hero-cta,
        .scroll-hint { position: relative; z-index: 1; }
        .hero-logo {
            display: grid;
            width: 110px;
            height: 110px;
            place-items: center;
            margin-bottom: 32px;
            border-radius: 26px;
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            color: #fff;
            font-size: 3.2rem;
            font-weight: 800;
            box-shadow: 0 0 60px var(--teal-glow), 0 20px 40px rgba(0, 0, 0, .34);
            animation: float-logo 4s ease-in-out infinite;
        }
        @keyframes float-logo {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .hero-tagline {
            margin: 0 0 20px;
            color: var(--teal);
            font-size: clamp(.85rem, 2vw, 1rem);
            font-weight: 600;
            letter-spacing: 2px;
        }
        .hero-headline {
            max-width: 780px;
            margin: 0 0 28px;
            color: var(--text-main);
            font-size: clamp(2rem, 5vw, 3.6rem);
            font-weight: 700;
            line-height: 1.35;
            text-wrap: balance;
        }
        body.en .hero-headline { line-height: 1.25; }
        .accent { color: var(--teal); }
        .hero-sub {
            max-width: 590px;
            margin: 0 0 48px;
            color: var(--text-muted);
            font-size: clamp(1rem, 2vw, 1.15rem);
            line-height: 1.85;
            text-wrap: pretty;
        }
        .hero-cta,
        .download-btns { display: flex; flex-wrap: wrap; justify-content: center; gap: 16px; }
        .btn-store,
        .btn-dl {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-height: 60px;
            border-radius: 14px;
            font-family: var(--font-en);
            font-weight: 700;
            transition: transform .2s ease, box-shadow .2s ease, background .3s ease, color .3s ease;
        }
        .btn-store {
            padding: 14px 28px;
            background: var(--btn-store-bg);
            color: var(--btn-store-fg);
            box-shadow: 0 4px 14px var(--shadow-soft);
        }
        .btn-store.android { background: var(--teal); color: #fff; }
        .btn-store:hover,
        .btn-dl:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0, 168, 152, .22); }
        .btn-store svg,
        .btn-dl svg,
        .social-card svg { width: 24px; height: 24px; flex: 0 0 auto; }
        .store-kicker { display: block; font-size: .72rem; font-weight: 400; line-height: 1.15; opacity: .74; }
        .store-name { display: block; font-size: 1rem; line-height: 1.2; }
        .scroll-hint {
            position: absolute;
            bottom: 34px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: .75rem;
            animation: hint-bounce 2s ease-in-out infinite;
        }
        .scroll-hint svg { width: 20px; opacity: .56; }
        @keyframes hint-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(6px); }
        }

        .section,
        .social-wrap {
            width: min(1100px, calc(100% - 48px));
            margin: 0 auto;
            padding: 100px 0;
        }
        .section-label {
            display: inline-flex;
            align-items: center;
            min-height: 32px;
            margin-bottom: 20px;
            padding: 5px 14px;
            border: 1px solid rgba(0, 168, 152, .3);
            border-radius: 20px;
            color: var(--teal);
            font-family: var(--font-en);
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .divider {
            width: 60px;
            height: 3px;
            margin: 24px 0;
            border-radius: 2px;
            background: var(--teal);
        }
        .section-title {
            max-width: 720px;
            margin: 0 0 20px;
            color: var(--text-main);
            font-size: clamp(1.6rem, 4vw, 2.4rem);
            font-weight: 700;
            line-height: 1.4;
            text-wrap: balance;
        }
        .section-body {
            max-width: 720px;
            margin: 0;
            color: var(--text-muted);
            font-size: 1.05rem;
            line-height: 1.9;
            text-wrap: pretty;
        }
        .services-wrap {
            background: var(--bg-mid);
            transition: background .35s ease;
        }
        .services-inner {
            width: min(1100px, calc(100% - 48px));
            margin: 0 auto;
            padding: 100px 0;
        }
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-top: 56px;
        }
        .service-card {
            position: relative;
            overflow: hidden;
            min-height: 270px;
            padding: 34px 28px;
            border: 1px solid var(--border-soft);
            border-radius: 20px;
            background: var(--bg-card);
            transition: transform .3s ease, border-color .3s ease, background .35s ease;
        }
        .service-card::before {
            position: absolute;
            inset: 0 0 auto auto;
            width: 86px;
            height: 86px;
            border-radius: 50%;
            background: radial-gradient(circle, var(--teal-glow) 0%, transparent 70%);
            content: '';
            transform: translate(24px, -24px);
        }
        .service-card:hover { transform: translateY(-6px); border-color: rgba(0, 168, 152, .42); }
        .service-icon { display: block; margin-bottom: 20px; font-size: 2.35rem; }
        .service-title { margin: 0 0 12px; color: var(--text-main); font-size: 1.15rem; font-weight: 700; }
        .service-desc { margin: 0; color: var(--text-muted); font-size: .93rem; line-height: 1.75; }

        .social-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: flex-start;
            margin-top: 48px;
        }
        .social-card {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            flex: 0 0 auto;
            min-height: 62px;
            padding: 18px 28px;
            border: 1px solid var(--border-soft);
            border-radius: 16px;
            background: var(--bg-card);
            color: var(--text-main);
            font-family: var(--font-en);
            font-size: .95rem;
            font-weight: 600;
            transition: transform .2s ease, border-color .2s ease, background .35s ease;
        }
        .social-card:hover { transform: translateY(-4px); border-color: var(--teal); }

        .download-wrap {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal) 60%, #00c4b3 100%);
        }
        .download-wrap::before {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, .05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .05) 1px, transparent 1px);
            background-size: 40px 40px;
            content: '';
        }
        .download-inner {
            position: relative;
            z-index: 1;
            max-width: 900px;
            margin: 0 auto;
            padding: 90px 24px;
            text-align: center;
        }
        .download-inner h2 { margin: 0 0 16px; color: #fff; font-size: clamp(1.6rem, 4vw, 2.4rem); line-height: 1.35; }
        .download-inner p { max-width: 560px; margin: 0 auto 44px; color: rgba(255, 255, 255, .88); font-size: 1.05rem; line-height: 1.8; }
        .btn-dl { padding: 16px 32px; box-shadow: 0 4px 14px rgba(0, 0, 0, .22); }
        .btn-dl.ios { background: var(--navy); color: #fff; }
        .btn-dl.android { background: #fff; color: var(--navy); }
        footer {
            padding: 48px 24px;
            border-top: 1px solid var(--border-soft);
            background: var(--bg-footer);
            text-align: center;
            transition: background .35s ease;
        }
        footer p { margin: 0; color: var(--text-muted); font-size: .88rem; line-height: 1.8; }
        .teal { color: var(--teal); }

        .fade-in {
            opacity: 1;
            transform: translateY(0);
            transition: opacity .7s ease, transform .7s ease;
        }
        .js .fade-in { opacity: .001; transform: translateY(26px); }
        .js .fade-in.visible { opacity: 1; transform: translateY(0); }
        .ar { display: block; }
        .en-text { display: none; }
        body.en .ar { display: none; }
        body.en .en-text { display: block; }
        span.ar { display: inline; }
        span.en-text { display: none; }
        body.en span.ar { display: none; }
        body.en span.en-text { display: inline; }

        @media (max-width: 820px) {
            .site-nav { padding: 14px 20px; }
            .nav-links { display: none; }
            .hero { padding: 104px 20px 74px; }
            .section,
            .social-wrap,
            .services-inner { width: min(100% - 40px, 1100px); padding-top: 72px; padding-bottom: 72px; }
        }
        @media (max-width: 640px) {
            .nav-logo span { font-size: 1.05rem; }
            .logo-img { width: 38px; height: 38px; }
            .lang-toggle { min-width: 58px; padding-inline: 12px; }
            .hero-logo { width: 92px; height: 92px; font-size: 2.6rem; }
            .hero-cta,
            .download-btns { align-items: stretch; flex-direction: column; width: min(100%, 320px); }
            .btn-store,
            .btn-dl { width: 100%; justify-content: center; }
            .social-grid { flex-direction: column; }
            .social-card { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                animation-duration: .001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .001ms !important;
            }
            .js .fade-in { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>
    <nav class="site-nav" aria-label="التنقل الرئيسي">
        <a href="{{ route('landing') }}" class="nav-logo" aria-label="Sirati">
            <span class="logo-img" aria-hidden="true">س</span>
            <span class="ar">سيرتي</span>
            <span class="en-text">Sirati</span>
        </a>
        <div class="nav-links">
            <a href="#about"><span class="ar">من نحن</span><span class="en-text">About</span></a>
            <a href="#services"><span class="ar">خدماتنا</span><span class="en-text">Services</span></a>
            <a href="#social"><span class="ar">تابعنا</span><span class="en-text">Follow</span></a>
            <a href="#download"><span class="ar">تحميل التطبيق</span><span class="en-text">Download</span></a>
        </div>
        <div class="nav-right">
            <button class="theme-toggle" type="button" onclick="toggleTheme()" aria-label="تبديل المظهر">
                <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            </button>
            <button class="lang-toggle" type="button" onclick="toggleLang()" id="langBtn">EN</button>
        </div>
    </nav>

    <main>
        <section class="hero">
            <div class="hero-logo" aria-hidden="true">س</div>
            <p class="hero-tagline">
                <span class="ar">منصة المسار المهني</span>
                <span class="en-text">Career Development Platform</span>
            </p>
            <h1 class="hero-headline">
                <span class="ar">عندما تسعى لتطوير مسارك المهني،<br>لابد أن تمر على <span class="accent">محطتنا.</span></span>
                <span class="en-text">When you seek to develop your career,<br>you must pass through <span class="accent">our station.</span></span>
            </h1>
            <p class="hero-sub">
                <span class="ar">سيرتي، منصتك المتكاملة لبناء سيرتك الذاتية، وتحليلها، واستكشاف فرص العمل في السوق الخليجي.</span>
                <span class="en-text">Sirati, your all-in-one platform for building your CV, analyzing it, and discovering job opportunities across the Gulf market.</span>
            </p>
            <div class="hero-cta">
                <a href="#download" class="btn-store" aria-label="Download on the App Store">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
                    <span><span class="store-kicker">Download on the</span><span class="store-name">App Store</span></span>
                </a>
                <a href="#download" class="btn-store android" aria-label="Get it on Google Play">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.18 23.76c.3.17.66.2.99.06l12.46-7.2-2.63-2.63-10.82 9.77zm-1.12-20.9C2.03 3.1 2 3.34 2 3.58v16.84c0 .24.03.48.06.7l10.9-10.9-10.9-10.36zM20.43 10.5l-2.78-1.61-2.98 2.98 2.98 2.98 2.8-1.62c.8-.46.8-1.67-.02-2.13zM4.17.24C3.84.1 3.48.13 3.18.3L14.02 10.3 16.65 7.7 4.17.24z"/></svg>
                    <span><span class="store-kicker">Get it on</span><span class="store-name">Google Play</span></span>
                </a>
            </div>
            <a class="scroll-hint" href="#about">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 10l5 5 5-5"/></svg>
                <span class="ar">اكتشف أكثر</span>
                <span class="en-text">Discover more</span>
            </a>
        </section>

        <section id="about">
            <div class="section fade-in">
                <span class="section-label"><span class="ar">من نحن :</span><span class="en-text">About Us :</span></span>
                <div class="divider"></div>
                <h2 class="section-title">
                    <span class="ar">منصة إلكترونية متخصصة في تطوير مسارك المهني</span>
                    <span class="en-text">A digital platform specialized in developing your professional career</span>
                </h2>
                <p class="section-body">
                    <span class="ar">سيرتي منصة إلكترونية متخصصة في تطوير المسار المهني، نقدم مجموعة متكاملة من الخدمات التي تجمع بين الذكاء التقني وفهم متطلبات سوق العمل في المملكة العربية السعودية والخليج. نؤمن بأن كل محترف يستحق أدوات احترافية تفتح له الأبواب.</span>
                    <span class="en-text">Sirati is a digital platform specialized in career development, offering an integrated suite of services that combine technical intelligence with a deep understanding of the job market in Saudi Arabia and the Gulf. We believe every professional deserves the right tools to open doors.</span>
                </p>
            </div>
        </section>

        <section class="services-wrap" id="services">
            <div class="services-inner">
                <div class="fade-in">
                    <span class="section-label"><span class="ar">خدماتنا :</span><span class="en-text">Our Services :</span></span>
                    <div class="divider"></div>
                    <h2 class="section-title">
                        <span class="ar">كل ما تحتاجه لمسارك المهني في مكان واحد</span>
                        <span class="en-text">Everything you need for your career, in one place</span>
                    </h2>
                </div>
                <div class="services-grid">
                    <article class="service-card fade-in">
                        <span class="service-icon" aria-hidden="true">📂</span>
                        <h3 class="service-title"><span class="ar">بناء السيرة الذاتية الاحترافية</span><span class="en-text">Professional CV Builder</span></h3>
                        <p class="service-desc"><span class="ar">نساعدك في إنشاء سيرة ذاتية وفق المعايير الدولية، مهيأة للنجاح في أنظمة فرز السير الذاتية الآلي، مع إمكانية التعديل عليها لاحقاً.</span><span class="en-text">We help you create a CV to international standards, optimized for Applicant Tracking Systems, with the ability to edit it at any time.</span></p>
                    </article>
                    <article class="service-card fade-in">
                        <span class="service-icon" aria-hidden="true">📈</span>
                        <h3 class="service-title"><span class="ar">تحليل السيرة الذاتية</span><span class="en-text">CV Analysis</span></h3>
                        <p class="service-desc"><span class="ar">نحلل سيرتك الذاتية ونكشف نقاط ضعفها أمام أنظمة Applicant Tracking System، ونقدم توصيات عملية لاجتياز الفرز الآلي الذي تعتمده الشركات الكبرى.</span><span class="en-text">We analyze your CV and reveal its weaknesses against Applicant Tracking Systems, offering practical recommendations to pass automated screening used by top companies.</span></p>
                    </article>
                    <article class="service-card fade-in">
                        <span class="service-icon" aria-hidden="true">💼</span>
                        <h3 class="service-title"><span class="ar">فرص العمل</span><span class="en-text">Job Opportunities</span></h3>
                        <p class="service-desc"><span class="ar">نوفر قاعدة محدثة من الوظائف في المملكة العربية السعودية، تشمل الجهات الحكومية والقطاع الخاص.</span><span class="en-text">We provide an updated database of jobs in Saudi Arabia, covering both government entities and the private sector.</span></p>
                    </article>
                    <article class="service-card fade-in">
                        <span class="service-icon" aria-hidden="true">📚</span>
                        <h3 class="service-title"><span class="ar">التعليم والتوجيه المهني</span><span class="en-text">Education & Career Guidance</span></h3>
                        <p class="service-desc"><span class="ar">نقدم محتوى تعليمياً مستمراً وإرشاداً متخصصاً لمساعدتك في بناء مسارك المهني وتطويره نحو الأفضل.</span><span class="en-text">We provide continuous educational content and specialized guidance to help you build and advance your professional career.</span></p>
                    </article>
                </div>
            </div>
        </section>

        <section class="social-wrap" id="social">
            <div class="fade-in">
                <span class="section-label"><span class="ar">تابعنا :</span><span class="en-text">Follow Us :</span></span>
                <div class="divider"></div>
                <h2 class="section-title"><span class="ar">نحن على منصاتك المفضلة</span><span class="en-text">We're on your favourite platforms</span></h2>
                <p class="section-body"><span class="ar">تابع سيرتي وابق على اطلاع بأحدث فرص العمل والنصائح المهنية والمحتوى التعليمي.</span><span class="en-text">Follow Sirati and stay up to date with the latest job opportunities, career tips, and educational content.</span></p>
            </div>
            <div class="social-grid fade-in">
                <a href="#" class="social-card" aria-label="TikTok"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.91a8.16 8.16 0 004.77 1.52V7a4.85 4.85 0 01-1-.31z"/></svg>TikTok</a>
                <a href="#" class="social-card" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>Instagram</a>
                <a href="#" class="social-card" aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>LinkedIn</a>
                <a href="mailto:hello@sirati.app" class="social-card"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 010 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.64 12 9.548l6.545-4.91 1.528-1.145C21.69 2.28 24 3.434 24 5.457z"/></svg><span class="ar">تواصل معنا عبر الإيميل</span><span class="en-text">Contact us via Email</span></a>
            </div>
        </section>

        <section class="download-wrap" id="download">
            <div class="download-inner fade-in">
                <h2><span class="ar">حمّل التطبيق الآن</span><span class="en-text">Download the App Now</span></h2>
                <p><span class="ar">جميع الخدمات متاحة داخل التطبيق. ابدأ مسيرتك المهنية الصحيحة اليوم.</span><span class="en-text">All services are available inside the app. Start your professional journey the right way, today.</span></p>
                <div class="download-btns">
                    <a href="#download" class="btn-dl ios"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg><span><span class="store-kicker">Download on the</span><span class="store-name">App Store</span></span></a>
                    <a href="#download" class="btn-dl android"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.18 23.76c.3.17.66.2.99.06l12.46-7.2-2.63-2.63-10.82 9.77zm-1.12-20.9C2.03 3.1 2 3.34 2 3.58v16.84c0 .24.03.48.06.7l10.9-10.9-10.9-10.36zM20.43 10.5l-2.78-1.61-2.98 2.98 2.98 2.98 2.8-1.62c.8-.46.8-1.67-.02-2.13zM4.17.24C3.84.1 3.48.13 3.18.3L14.02 10.3 16.65 7.7 4.17.24z"/></svg><span><span class="store-kicker">Get it on</span><span class="store-name">Google Play</span></span></a>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <p><span class="ar">© {{ now()->year }} <span class="teal">سيرتي</span> — جميع الحقوق محفوظة.</span><span class="en-text">© {{ now()->year }} <span class="teal">Sirati</span> — All rights reserved. Our services are available inside the app only.</span></p>
    </footer>

    <script>
        document.documentElement.classList.add('js');

        function applyTheme(theme) {
            document.body.classList.toggle('light', theme === 'light');
        }

        function toggleTheme() {
            const isLight = document.body.classList.toggle('light');
            try { localStorage.setItem('sirati-theme', isLight ? 'light' : 'dark'); } catch (e) {}
        }

        (function initTheme() {
            let saved = null;
            try { saved = localStorage.getItem('sirati-theme'); } catch (e) {}
            applyTheme(saved === 'light' ? 'light' : 'dark');
        })();

        let isEn = false;
        function toggleLang() {
            isEn = !isEn;
            document.body.classList.toggle('en', isEn);
            document.documentElement.setAttribute('lang', isEn ? 'en' : 'ar');
            document.documentElement.setAttribute('dir', isEn ? 'ltr' : 'rtl');
            document.getElementById('langBtn').textContent = isEn ? 'ع' : 'EN';
        }

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: .15 });

            document.querySelectorAll('.fade-in').forEach((element) => observer.observe(element));
        } else {
            document.querySelectorAll('.fade-in').forEach((element) => element.classList.add('visible'));
        }
    </script>
</body>
</html>
