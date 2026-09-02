<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>سيرتي | Siratie</title>
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
    <img id="nav-logo-img" src="" alt="سيرتي">
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
  <img id="hero-logo" src="" alt="سيرتي" class="hero-logo">
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
  document.getElementById('nav-logo-img').src = logoSrc;
  document.getElementById('hero-logo').src = logoSrc;

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