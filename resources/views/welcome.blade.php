<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>سيرتي | Siratie</title>
<link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --teal:       #00A898;
    --teal-dark:  #007A6F;
    --teal-glow:  #00A89833;
    --navy:       #0D1B2A;
    --navy-mid:   #132236;
    --navy-card:  #1A2E42;
    --amber:      #F5A623;
    --white:      #FFFFFF;
    --offwhite:   #F0F4F8;
    --muted:      #8AA3BA;
    --font-ar:    'IBM Plex Sans Arabic', sans-serif;
    --font-en:    'Inter', sans-serif;

    /* ── THEME TOKENS (default = dark) ── */
    --bg:           var(--navy);
    --bg-mid:       var(--navy-mid);
    --bg-card:      var(--navy-card);
    --bg-footer:    #080F18;
    --text-main:    var(--white);
    --text-muted:   var(--muted);
    --border-soft:  rgba(0,168,152,0.12);
    --border-med:   rgba(0,168,152,0.15);
    --nav-bg:       rgba(13,27,42,0.85);
    --shadow-soft:  rgba(0,0,0,0.3);
    --grid-line:    rgba(0,168,152,0.06);
    --btn-store-bg: var(--white);
    --btn-store-fg: var(--navy);
  }

  /* ── LIGHT MODE TOKENS ── */
  body.light {
    --bg:           #F6F9FB;
    --bg-mid:       #EEF3F6;
    --bg-card:      #FFFFFF;
    --bg-footer:    #E9EEF2;
    --text-main:    #0D1B2A;
    --text-muted:   #5B7385;
    --border-soft:  rgba(0,168,152,0.18);
    --border-med:   rgba(0,168,152,0.22);
    --nav-bg:       rgba(255,255,255,0.85);
    --shadow-soft:  rgba(13,27,42,0.08);
    --grid-line:    rgba(0,168,152,0.08);
    --btn-store-bg: #0D1B2A;
    --btn-store-fg: #FFFFFF;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  html { scroll-behavior: smooth; }

  body {
    font-family: var(--font-ar);
    background: var(--bg);
    color: var(--text-main);
    overflow-x: hidden;
    direction: rtl;
    transition: background 0.35s ease, color 0.35s ease;
  }

  body.en {
    font-family: var(--font-en);
    direction: ltr;
  }

  /* ── NAV ── */
  nav {
    position: fixed; top: 0; right: 0; left: 0; z-index: 100;
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 40px;
    background: var(--nav-bg);
    backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--border-med);
    transition: background 0.3s;
  }

  .nav-right {
    display: flex; align-items: center; gap: 10px;
  }

  .nav-logo {
    display: flex; align-items: center; gap: 12px;
    text-decoration: none;
  }
  .nav-logo img { width: 44px; height: 44px; border-radius: 10px; }
  .nav-logo span {
    font-size: 1.3rem; font-weight: 700; color: var(--text-main);
    letter-spacing: 0.5px;
  }
  body.en .nav-logo span { font-family: var(--font-en); }

  .lang-toggle {
    background: transparent;
    border: 1.5px solid var(--teal);
    color: var(--teal);
    padding: 7px 20px;
    border-radius: 20px;
    font-size: 0.85rem; font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
    font-family: var(--font-en);
    letter-spacing: 0.5px;
  }
  .lang-toggle:hover { background: var(--teal); color: var(--white); }

  /* ── THEME TOGGLE ── */
  .theme-toggle {
    display: flex; align-items: center; justify-content: center;
    width: 34px; height: 34px;
    background: transparent;
    border: 1.5px solid var(--teal);
    color: var(--teal);
    border-radius: 50%;
    cursor: pointer;
    transition: background 0.2s, color 0.2s, transform 0.3s;
    flex-shrink: 0;
  }
  .theme-toggle:hover { background: var(--teal); color: var(--white); transform: rotate(15deg); }
  .theme-toggle svg { width: 18px; height: 18px; }
  .theme-toggle .icon-sun { display: none; }
  .theme-toggle .icon-moon { display: block; }
  body.light .theme-toggle .icon-sun { display: block; }
  body.light .theme-toggle .icon-moon { display: none; }

  /* ── HERO ── */
  .hero {
    min-height: 100vh;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    text-align: center;
    padding: 120px 24px 80px;
    position: relative;
    overflow: hidden;
  }

  /* animated mesh background */
  .hero::before {
    content: '';
    position: absolute; inset: 0;
    background:
      radial-gradient(ellipse 60% 50% at 50% 30%, rgba(0,168,152,0.18) 0%, transparent 70%),
      radial-gradient(ellipse 40% 40% at 80% 80%, rgba(245,166,35,0.07) 0%, transparent 60%);
    pointer-events: none;
  }

  /* subtle grid lines */
  .hero::after {
    content: '';
    position: absolute; inset: 0;
    background-image:
      linear-gradient(var(--grid-line) 1px, transparent 1px),
      linear-gradient(90deg, var(--grid-line) 1px, transparent 1px);
    background-size: 60px 60px;
    pointer-events: none;
    mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
  }

  .hero-logo {
    width: 110px; height: 110px;
    border-radius: 26px;
    box-shadow: 0 0 60px var(--teal-glow), 0 20px 40px rgba(0,0,0,0.4);
    margin-bottom: 32px;
    animation: floatLogo 4s ease-in-out infinite;
    position: relative; z-index: 1;
  }
  @keyframes floatLogo {
    0%,100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
  }

  .hero-tagline {
    font-size: clamp(0.85rem, 2vw, 1rem);
    font-weight: 500;
    color: var(--teal);
    letter-spacing: 3px;
    text-transform: uppercase;
    margin-bottom: 20px;
    position: relative; z-index: 1;
  }
  body.en .hero-tagline { font-family: var(--font-en); }

  .hero-headline {
    font-size: clamp(2rem, 5vw, 3.6rem);
    font-weight: 700;
    line-height: 1.35;
    color: var(--text-main);
    max-width: 780px;
    margin-bottom: 28px;
    position: relative; z-index: 1;
  }
  body.en .hero-headline { font-family: var(--font-en); line-height: 1.25; }

  .hero-headline .accent { color: var(--teal); }

  .hero-sub {
    font-size: clamp(1rem, 2vw, 1.15rem);
    color: var(--text-muted);
    font-weight: 400;
    max-width: 560px;
    line-height: 1.8;
    margin-bottom: 48px;
    position: relative; z-index: 1;
  }

  .hero-cta {
    display: flex; gap: 16px; flex-wrap: wrap;
    justify-content: center;
    position: relative; z-index: 1;
  }

  .btn-store {
    display: flex; align-items: center; gap: 12px;
    background: var(--btn-store-bg);
    color: var(--btn-store-fg);
    padding: 14px 28px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    font-family: var(--font-en);
    transition: transform 0.2s, box-shadow 0.2s, background 0.3s, color 0.3s;
    box-shadow: 0 4px 20px var(--shadow-soft);
  }
  .btn-store:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0,168,152,0.25); }
  .btn-store svg { width: 22px; height: 22px; flex-shrink: 0; }

  .btn-store.android {
    background: var(--teal);
    color: var(--white);
  }

  /* scroll indicator */
  .scroll-hint {
    position: absolute; bottom: 36px;
    display: flex; flex-direction: column; align-items: center;
    gap: 8px; color: var(--text-muted); font-size: 0.78rem;
    animation: bounce 2s ease-in-out infinite;
    z-index: 1;
  }
  .scroll-hint svg { width: 20px; opacity: 0.5; }
  @keyframes bounce { 0%,100%{transform:translateY(0)} 50%{transform:translateY(6px)} }

  /* ── ABOUT ── */
  .section {
    padding: 100px 24px;
    max-width: 1100px;
    margin: 0 auto;
  }

  .section-label {
    display: inline-block;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--teal);
    border: 1px solid rgba(0,168,152,0.3);
    padding: 5px 14px;
    border-radius: 20px;
    margin-bottom: 20px;
    font-family: var(--font-en);
  }

  .section-title {
    font-size: clamp(1.6rem, 4vw, 2.4rem);
    font-weight: 700;
    color: var(--text-main);
    line-height: 1.4;
    margin-bottom: 20px;
    max-width: 680px;
  }

  .section-body {
    font-size: 1.05rem;
    color: var(--text-muted);
    line-height: 1.9;
    max-width: 680px;
  }

  /* ── SERVICES ── */
  .services-wrap { background: var(--bg-mid); transition: background 0.35s ease; }
  .services-inner {
    padding: 100px 24px;
    max-width: 1100px;
    margin: 0 auto;
  }

  .services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    margin-top: 56px;
  }

  .service-card {
    background: var(--bg-card);
    border: 1px solid var(--border-soft);
    border-radius: 20px;
    padding: 36px 28px;
    position: relative;
    overflow: hidden;
    transition: transform 0.3s, border-color 0.3s, box-shadow 0.3s, background 0.35s ease;
  }
  .service-card::before {
    content: '';
    position: absolute; top: 0; right: 0;
    width: 80px; height: 80px;
    background: radial-gradient(circle, var(--teal-glow) 0%, transparent 70%);
    border-radius: 50%;
    transform: translate(20px, -20px);
    pointer-events: none;
  }
  .service-card:hover {
    transform: translateY(-6px);
    border-color: rgba(0,168,152,0.4);
    box-shadow: 0 20px 40px var(--shadow-soft), 0 0 30px var(--teal-glow);
  }

  .service-icon {
    font-size: 2.4rem;
    margin-bottom: 20px;
    display: block;
  }

  .service-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 12px;
  }

  .service-desc {
    font-size: 0.92rem;
    color: var(--text-muted);
    line-height: 1.75;
  }

  /* ── SOCIAL ── */
  .social-wrap { padding: 100px 24px; max-width: 1100px; margin: 0 auto; }

  .social-grid {
    display: flex; flex-wrap: wrap; gap: 16px;
    margin-top: 48px; justify-content: flex-start;
  }

  .social-card {
    display: flex; align-items: center; gap: 14px;
    background: var(--bg-card);
    border: 1px solid var(--border-soft);
    border-radius: 16px;
    padding: 18px 28px;
    text-decoration: none;
    color: var(--text-main);
    font-size: 0.95rem;
    font-weight: 500;
    transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s, background 0.35s ease;
    flex: 0 0 auto;
    font-family: var(--font-en);
  }
  .social-card svg { width: 24px; height: 24px; flex-shrink: 0; }
  .social-card:hover {
    transform: translateY(-4px);
    border-color: var(--teal);
    box-shadow: 0 10px 30px rgba(0,168,152,0.15);
  }

  /* ── DOWNLOAD CTA ── */
  .download-wrap {
    background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal) 60%, #00C4B3 100%);
    position: relative; overflow: hidden;
  }
  .download-wrap::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
      linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
  }
  .download-inner {
    padding: 90px 24px;
    max-width: 900px;
    margin: 0 auto;
    text-align: center;
    position: relative; z-index: 1;
  }
  .download-inner h2 {
    font-size: clamp(1.6rem, 4vw, 2.4rem);
    font-weight: 700; color: var(--white);
    margin-bottom: 16px;
  }
  .download-inner p {
    color: rgba(255,255,255,0.85);
    font-size: 1.05rem; margin-bottom: 44px;
    max-width: 520px; margin-left: auto; margin-right: auto;
    line-height: 1.8;
  }
  .download-btns {
    display: flex; gap: 16px; flex-wrap: wrap;
    justify-content: center;
  }
  .btn-dl {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 32px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
    font-size: 1rem;
    font-family: var(--font-en);
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 20px rgba(0,0,0,0.25);
  }
  .btn-dl svg { width: 24px; height: 24px; flex-shrink: 0; }
  .btn-dl.ios { background: var(--navy); color: var(--white); }
  .btn-dl.android { background: var(--white); color: var(--navy); }
  .btn-dl:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,0.3); }

  /* ── FOOTER ── */
  footer {
    background: var(--bg-footer);
    padding: 48px 24px;
    text-align: center;
    border-top: 1px solid var(--border-soft);
    transition: background 0.35s ease;
  }
  footer p { color: var(--text-muted); font-size: 0.88rem; line-height: 1.8; }
  footer .teal { color: var(--teal); }

  /* ── DIVIDER ── */
  .divider {
    width: 60px; height: 3px;
    background: var(--teal);
    border-radius: 2px;
    margin: 24px 0;
  }
  body:not(.en) .divider { margin-right: 0; }
  body.en .divider { margin-left: 0; }

  /* ── FADE IN ── */
  .fade-in {
    opacity: 0; transform: translateY(30px);
    transition: opacity 0.7s ease, transform 0.7s ease;
  }
  .fade-in.visible { opacity: 1; transform: translateY(0); }

  /* ── MOBILE ── */
  @media (max-width: 640px) {
    nav { padding: 14px 20px; }
    .nav-right { gap: 8px; }
    .hero { padding: 100px 20px 70px; }
    .section, .social-wrap { padding: 70px 20px; }
    .services-inner { padding: 70px 20px; }
    .btn-store { padding: 12px 20px; font-size: 0.88rem; }
    .services-grid { grid-template-columns: 1fr; }
    .social-grid { flex-direction: column; }
    .social-card { width: 100%; }
  }

  /* ── HIDDEN LANG ── */
  .ar { display: block; }
  .en-text { display: none; }
  body.en .ar { display: none; }
  body.en .en-text { display: block; }

  /* inline spans */
  span.ar { display: inline; }
  span.en-text { display: none; }
  body.en span.ar { display: none; }
  body.en span.en-text { display: inline; }
</style>
</head>
<body>

<!-- NAV -->
<nav>
  <a href="#" class="nav-logo">
    <img id="nav-logo-img" src="{{ asset('images/logo.png') }}" alt="سيرتي">
    <span class="ar">سيرتي</span>
    <span class="en-text">Siratie</span>
  </a>
  <div class="nav-right">
    <button class="theme-toggle" onclick="toggleTheme()" id="themeBtn" aria-label="تبديل المظهر">
      <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
      <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
    </button>
    <button class="lang-toggle" onclick="toggleLang()" id="langBtn">EN</button>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <img id="hero-logo" src="{{ asset('images/logo.png') }}" alt="سيرتي" class="hero-logo">
  <p class="hero-tagline">
    <span class="ar">منصة المسار المهني</span>
    <span class="en-text">Career Development Platform</span>
  </p>
  <h1 class="hero-headline">
    <span class="ar">عندما تسعى لتطوير مسارك المهني،<br>لا بد أن تمر على <span class="accent">محطتنا.</span></span>
    <span class="en-text">When you seek to develop your career,<br>you must pass through <span class="accent">our station.</span></span>
  </h1>
  <p class="hero-sub">
    <span class="ar">سيرتي، منصتك المتكاملة لبناء سيرتك الذاتية، وتحليلها، واستكشاف فرص العمل في السوق الخليجي.</span>
    <span class="en-text">Siratie, your all-in-one platform for building your CV, analyzing it, and discovering job opportunities across the Gulf market.</span>
  </p>
  <div class="hero-cta">
    <a href="#" class="btn-store">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
      <div>
        <div style="font-size:0.7rem;opacity:0.7;font-weight:400">Download on the</div>
        <div style="font-size:1rem;font-weight:700">App Store</div>
      </div>
    </a>
    <a href="#" class="btn-store android">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.18 23.76c.3.17.66.2.99.06l12.46-7.2-2.63-2.63-10.82 9.77zm-1.12-20.9C2.03 3.1 2 3.34 2 3.58v16.84c0 .24.03.48.06.7l10.9-10.9-10.9-10.36zM20.43 10.5l-2.78-1.61-2.98 2.98 2.98 2.98 2.8-1.62c.8-.46.8-1.67-.02-2.13zM4.17.24C3.84.1 3.48.13 3.18.3L14.02 10.3 16.65 7.7 4.17.24z"/></svg>
      <div>
        <div style="font-size:0.7rem;opacity:0.85;font-weight:400">Get it on</div>
        <div style="font-size:1rem;font-weight:700">Google Play</div>
      </div>
    </a>
  </div>
  <div class="scroll-hint">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 10l5 5 5-5"/></svg>
    <span class="ar" style="font-size:0.75rem">اكتشف أكثر</span>
    <span class="en-text" style="font-size:0.75rem">Discover more</span>
  </div>
</section>

<!-- ABOUT -->
<section id="about">
  <div class="section fade-in">
    <span class="section-label">
      <span class="ar">من نحن:</span>
      <span class="en-text">About Us:</span>
    </span>
    <div class="divider"></div>
    <h2 class="section-title">
      <span class="ar">منصة إلكترونية متخصصة في تطوير مسارك المهني</span>
      <span class="en-text">A digital platform specialized in developing your professional career</span>
    </h2>
    <p class="section-body">
      <span class="ar">سيرتي، منصة إلكترونية متخصصة في تطوير المسار المهني، نقدم مجموعة متكاملة من الخدمات التي تجمع بين الذكاء التقني وفهم متطلبات سوق العمل في المملكة العربية السعودية والخليج. نؤمن بأن كل محترف يستحق أدوات احترافية تفتح له الأبواب.</span>
      <span class="en-text">Siratie is a digital platform specialized in career development, offering an integrated suite of services that combine technical intelligence with a deep understanding of the job market in Saudi Arabia and the Gulf. We believe every professional deserves the right tools to open doors.</span>
    </p>
  </div>
</section>

<!-- SERVICES -->
<div class="services-wrap">
  <div class="services-inner">
    <div class="fade-in">
      <span class="section-label">
        <span class="ar">خدماتنا:</span>
        <span class="en-text">Our Services:</span>
      </span>
      <div class="divider"></div>
      <h2 class="section-title">
        <span class="ar">كل ما تحتاجه لمسارك المهني في مكان واحد</span>
        <span class="en-text">Everything you need for your career, in one place</span>
      </h2>
    </div>
    <div class="services-grid">

      <div class="service-card fade-in" style="transition-delay:0.1s">
        <span class="service-icon">📂</span>
        <h3 class="service-title">
          <span class="ar">بناء السيرة الذاتية الاحترافية</span>
          <span class="en-text">Professional CV Builder</span>
        </h3>
        <p class="service-desc">
          <span class="ar">نساعدك في إنشاء سيرة ذاتية وفق المعايير الدولية، مُهيَّأة للنجاح في الأنظمة الآلية لفرز السير الذاتية (Applicant Tracking System)، مع إمكانية التعديل عليها لاحقاً.</span>
          <span class="en-text">We help you create a CV to international standards, optimized for Applicant Tracking Systems, with the ability to edit it at any time.</span>
        </p>
      </div>

      <div class="service-card fade-in" style="transition-delay:0.2s">
        <span class="service-icon">📈</span>
        <h3 class="service-title">
          <span class="ar">تحليل السيرة الذاتية</span>
          <span class="en-text">CV Analysis</span>
        </h3>
        <p class="service-desc">
          <span class="ar">نحلّل سيرتك الذاتية ونكشف نقاط ضعفها أمام الأنظمة الآلية لفرز السير الذاتية (ATS)، ونقدم توصيات عملية لاجتياز الفرز الآلي الذي تعتمده الشركات الكبرى.</span>
          <span class="en-text">We analyze your CV and reveal its weaknesses against Applicant Tracking Systems, offering practical recommendations to pass automated screening used by top companies.</span>
        </p>
      </div>

      <div class="service-card fade-in" style="transition-delay:0.3s">
        <span class="service-icon">💼</span>
        <h3 class="service-title">
          <span class="ar">فرص العمل</span>
          <span class="en-text">Job Opportunities</span>
        </h3>
        <p class="service-desc">
          <span class="ar">نوفر قاعدة محدّثة من الوظائف في المملكة العربية السعودية، تشمل الجهات الحكومية والقطاع الخاص.</span>
          <span class="en-text">We provide an updated database of jobs in Saudi Arabia, covering both government entities and the private sector.</span>
        </p>
      </div>

      <div class="service-card fade-in" style="transition-delay:0.4s">
        <span class="service-icon">📚</span>
        <h3 class="service-title">
          <span class="ar">التعليم والتوجيه المهني</span>
          <span class="en-text">Education & Career Guidance</span>
        </h3>
        <p class="service-desc">
          <span class="ar">نقدم محتوى تعليمياً مستمراً وإرشاداً متخصصاً لمساعدتك في بناء مسارك المهني وتطويره نحو الأفضل.</span>
          <span class="en-text">We provide continuous educational content and specialized guidance to help you build and advance your professional career.</span>
        </p>
      </div>

    </div>
  </div>
</div>

<!-- SOCIAL -->
<div class="social-wrap">
  <div class="fade-in">
    <span class="section-label">
      <span class="ar">تابعنا:</span>
      <span class="en-text">Follow Us:</span>
    </span>
    <div class="divider"></div>
    <h2 class="section-title">
      <span class="ar">نحن على منصاتك المفضلة</span>
      <span class="en-text">We're on your favourite platforms</span>
    </h2>
    <p class="section-body" style="margin-bottom: 0;">
      <span class="ar">تابع سيرتي وابقَ على اطلاع بأحدث فرص العمل والنصائح المهنية والمحتوى التعليمي.</span>
      <span class="en-text">Follow Siratie and stay up to date with the latest job opportunities, career tips, and educational content.</span>
    </p>
  </div>
  <div class="social-grid fade-in" style="transition-delay:0.2s">

    <!-- TikTok -->
    <a href="#" class="social-card">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.91a8.16 8.16 0 004.77 1.52V7a4.85 4.85 0 01-1-.31z"/></svg>
      TikTok
    </a>

    <!-- Instagram -->
    <a href="#" class="social-card">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
      Instagram
    </a>

    <!-- LinkedIn -->
    <a href="https://www.linkedin.com/company/sirati.sa/" class="social-card" target="_blank" rel="noopener noreferrer">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
      LinkedIn
    </a>

    <!-- Jadeer / جميل -->
    <a href="#" class="social-card">
      <svg viewBox="0 0 24 24" fill="currentColor">
  <path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.64 12 9.548l6.545-4.91 1.528-1.145C21.69 2.28 24 3.434 24 5.457z"/>
</svg>
      <span class="ar">تواصل معنا عبر البريد الإلكتروني</span>
      <span class="en-text">Contact us via email</span>
    </a>

  </div>
</div>

<!-- DOWNLOAD CTA -->
<div class="download-wrap">
  <div class="download-inner fade-in">
    <h2>
      <span class="ar">حمّل التطبيق الآن</span>
      <span class="en-text">Download the App Now</span>
    </h2>
    <p>
      <span class="ar">جميع الخدمات متاحة داخل التطبيق. ابدأ مسيرتك المهنية بالطريقة الصحيحة اليوم.</span>
      <span class="en-text">All services are available inside the app. Start your professional journey the right way, today.</span>
    </p>
    <div class="download-btns">
      <a href="#" class="btn-dl ios">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
        <div>
          <div style="font-size:0.72rem;opacity:0.75;font-weight:400">Download on the</div>
          <div>App Store</div>
        </div>
      </a>
      <a href="#" class="btn-dl android">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.18 23.76c.3.17.66.2.99.06l12.46-7.2-2.63-2.63-10.82 9.77zm-1.12-20.9C2.03 3.1 2 3.34 2 3.58v16.84c0 .24.03.48.06.7l10.9-10.9-10.9-10.36zM20.43 10.5l-2.78-1.61-2.98 2.98 2.98 2.98 2.8-1.62c.8-.46.8-1.67-.02-2.13zM4.17.24C3.84.1 3.48.13 3.18.3L14.02 10.3 16.65 7.7 4.17.24z"/></svg>
        <div>
          <div style="font-size:0.72rem;opacity:0.75;font-weight:400">Get it on</div>
          <div>Google Play</div>
        </div>
      </a>
    </div>
  </div>
</div>

<!-- FOOTER -->
<footer>
  <p>
    <span class="ar">© 2026 <span class="teal">سيرتي</span> — جميع الحقوق محفوظة.</span>
    <span class="en-text">© 2026 <span class="teal">Siratie</span> — All rights reserved. Our services are available inside the app only.</span>
  </p>
</footer>

<script>
  // ── Embed logo ──
  const LOGO_B64 = "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";
  const logoSrc = "data:image/png;base64," + LOGO_B64;
  const navLogo = document.getElementById('nav-logo-img');
  const heroLogo = document.getElementById('hero-logo');
  if (navLogo) {
    if (!navLogo.getAttribute('src') || navLogo.getAttribute('src').startsWith('{')) {
      navLogo.src = logoSrc;
    }
    navLogo.onerror = function() { this.src = logoSrc; };
  }
  if (heroLogo) {
    if (!heroLogo.getAttribute('src') || heroLogo.getAttribute('src').startsWith('{')) {
      heroLogo.src = logoSrc;
    }
    heroLogo.onerror = function() { this.src = logoSrc; };
  }

  // ── Theme toggle (dark default) ──
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

  // ── Language toggle ──
  let isEn = false;
  function toggleLang() {
    isEn = !isEn;
    document.body.classList.toggle('en', isEn);
    document.documentElement.setAttribute('lang', isEn ? 'en' : 'ar');
    document.documentElement.setAttribute('dir', isEn ? 'ltr' : 'rtl');
    document.getElementById('langBtn').textContent = isEn ? 'ع' : 'EN';
  }

  // ── Scroll fade-in ──
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        observer.unobserve(e.target);
      }
    });
  }, { threshold: 0.15 });

  document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
</script>
</body>
</html>