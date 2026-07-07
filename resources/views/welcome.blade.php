<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $cms->value('meta.description') }}">
    <title>{{ $cms->value('meta.title', 'سيرتي | Sirati') }}</title>
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
        .logo-img img,
        .hero-logo img { width: 100%; height: 100%; object-fit: contain; border-radius: inherit; }
        .logo-img.has-image,
        .hero-logo.has-image { background: transparent; box-shadow: none; padding: 4px; }
        .nav-links { gap: 10px; color: var(--text-muted); font-size: .9rem; font-weight: 600; }
        .nav-links a { padding: 7px 10px; border-radius: 999px; transition: color .2s ease, background .2s ease; }
        .nav-links a:hover,
        .nav-links a:focus { color: var(--teal); background: rgba(0, 168, 152, .09); outline: none; }
        .nav-right { gap: 10px; }
        .nav-menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border: 1px solid var(--border-med);
            border-radius: 12px;
            background: rgba(0, 168, 152, .08);
            color: var(--text-main);
        }
        .nav-menu-toggle svg { width: 20px; height: 20px; }
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

        dialog.site-drawer {
            position: fixed;
            inset-block: 0;
            inset-inline-end: 0;
            inset-inline-start: auto;
            width: min(360px, 88vw);
            max-width: 100%;
            height: 100%;
            max-height: 100dvh;
            margin: 0;
            padding: 0;
            border: none;
            background: transparent;
            color: var(--text-main);
            overflow: hidden;
        }
        dialog.site-drawer[open] { animation: drawer-in .25s ease; }
        @keyframes drawer-in { from { opacity: 0; } to { opacity: 1; } }
        dialog.site-drawer::backdrop {
            background: rgba(5, 12, 20, .62);
            backdrop-filter: blur(3px);
        }
        .drawer-shell {
            display: flex;
            flex-direction: column;
            gap: 26px;
            height: 100%;
            padding: 24px;
            background: var(--bg-mid);
            border-inline-start: 1px solid var(--border-med);
            box-shadow: 0 0 60px rgba(0, 0, 0, .4);
            overflow-y: auto;
        }
        .drawer-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }
        .drawer-title {
            margin: 0;
            color: var(--text-main);
            font-size: 1.2rem;
            font-weight: 800;
        }
        .drawer-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 40px;
            height: 40px;
            border: 1px solid var(--border-med);
            border-radius: 12px;
            background: rgba(0, 168, 152, .08);
            color: var(--text-main);
            font-size: 1.5rem;
            line-height: 1;
            transition: background .2s ease, color .2s ease;
        }
        .drawer-close:hover,
        .drawer-close:focus { background: var(--teal); color: #fff; outline: none; }
        .drawer-links { display: grid; gap: 8px; }
        .drawer-links a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid var(--border-soft);
            border-radius: 14px;
            background: var(--bg-card);
            color: var(--text-main);
            font-weight: 700;
            transition: transform .2s ease, border-color .2s ease, background .2s ease;
        }
        .drawer-links a:hover,
        .drawer-links a:focus { border-color: var(--teal); background: rgba(0, 168, 152, .07); outline: none; }
        .drawer-links a span:last-child {
            color: var(--text-muted);
            font-family: var(--font-en);
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .5px;
        }
        .drawer-actions { display: grid; gap: 12px; margin-top: auto; }
        .drawer-actions .btn-store { justify-content: center; min-height: 54px; }

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
            animation: hero-aurora 14s ease-in-out infinite alternate;
        }
        @keyframes hero-aurora {
            0% { transform: translate3d(0, 0, 0) scale(1); opacity: .85; }
            50% { transform: translate3d(-2%, 1.5%, 0) scale(1.06); opacity: 1; }
            100% { transform: translate3d(2%, -1.5%, 0) scale(1.03); opacity: .9; }
        }
        .hero::after {
            background-image:
                linear-gradient(var(--grid-line) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-line) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
        }
        .hero-shell,
        .scroll-hint { position: relative; z-index: 1; }
        .hero-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(300px, 440px);
            align-items: center;
            gap: clamp(32px, 7vw, 86px);
            width: min(1120px, 100%);
            margin: auto;
        }
        .hero-copy { max-width: 680px; text-align: start; }
        .hero-proof { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 26px; }
        .hero-proof span { display: inline-flex; align-items: center; min-height: 34px; padding: 6px 12px; border: 1px solid var(--border-med); border-radius: 999px; background: rgba(0, 168, 152, .08); color: var(--text-main); font-size: .82rem; font-weight: 700; }
        .hero-copy .hero-cta { justify-content: flex-start; }
        .hero-scroll-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 22px;
            border: 1.5px solid var(--teal);
            border-radius: 999px;
            color: var(--teal);
            font-weight: 700;
            letter-spacing: .3px;
            transition: background .25s ease, color .25s ease, transform .25s ease, box-shadow .25s ease;
        }
        .hero-scroll-link svg { width: 18px; height: 18px; transition: transform .25s ease; }
        .hero-scroll-link:hover,
        .hero-scroll-link:focus {
            background: var(--teal);
            color: #fff;
            transform: translateY(-3px);
            box-shadow: 0 12px 30px var(--teal-glow);
            outline: none;
        }
        .hero-scroll-link:hover svg { transform: translateY(3px); }
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
        .hero-visual { position: relative; min-height: 560px; perspective: 1200px; }
        .phone-preview { position: absolute; inset: 20px 34px auto auto; width: min(330px, 88vw); min-height: 520px; border: 1px solid rgba(255,255,255,.12); border-radius: 34px; background: linear-gradient(180deg, #102238, #07111f); box-shadow: 0 26px 70px rgba(0,0,0,.34); padding: 18px; transform: rotateY(-7deg) rotateX(3deg); animation: preview-float 6s ease-in-out infinite; }
        .phone-screen { min-height: 484px; border-radius: 24px; background: var(--bg-card); padding: 18px; overflow: hidden; }
        .preview-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 18px; color: var(--text-muted); font-size: .78rem; font-weight: 700; }
        .score-ring { display: grid; place-items: center; width: 132px; height: 132px; margin: 6px auto 20px; border-radius: 50%; background: conic-gradient(var(--teal) 0 84%, rgba(255,255,255,.1) 84%); color: #fff; font-size: 2rem; font-weight: 800; }
        .score-ring span { display: grid; place-items: center; width: 104px; height: 104px; border-radius: 50%; background: var(--bg-card); }
        .preview-list { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
        .preview-list li { display: flex; justify-content: space-between; gap: 12px; padding: 11px 12px; border: 1px solid var(--border-soft); border-radius: 14px; background: rgba(0, 168, 152, .07); color: var(--text-main); font-size: .86rem; font-weight: 700; }
        .floating-card { position: absolute; left: 0; bottom: 54px; width: 210px; border: 1px solid var(--border-med); border-radius: 18px; background: var(--bg-card); padding: 16px; box-shadow: 0 18px 45px rgba(0,0,0,.24); }
        .floating-card strong { display: block; margin-bottom: 8px; color: var(--text-main); }
        .floating-card p { margin: 0; color: var(--text-muted); font-size: .86rem; line-height: 1.6; }
        @keyframes preview-float { 0%, 100% { transform: rotateY(-7deg) rotateX(3deg) translateY(0); } 50% { transform: rotateY(-7deg) rotateX(3deg) translateY(-12px); } }
        .button-primary { background: var(--teal); color: #fff; }
        .button-outline { border: 1px solid var(--border-med); background: transparent; color: var(--text-main); }
        .proof-band {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            width: min(1120px, calc(100% - 48px));
            margin: -18px auto 0;
            padding: 0 0 30px;
        }
        .proof-item {
            display: grid;
            gap: 6px;
            min-height: 100%;
            padding: 16px 18px;
            border: 1px solid var(--border-soft);
            border-radius: 16px;
            background: var(--bg-card);
        }
        .proof-item strong {
            color: var(--text-main);
            font-size: .96rem;
            font-weight: 800;
        }
        .proof-item span {
            color: var(--text-muted);
            font-size: .88rem;
            line-height: 1.6;
        }
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
            background: linear-gradient(90deg, var(--teal), #00c4b3);
            box-shadow: 0 0 16px var(--teal-glow);
        }
        body.en .divider { margin-left: 0; }
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
        .service-card:hover { transform: translateY(-8px); border-color: rgba(0, 168, 152, .45); box-shadow: 0 22px 50px rgba(0, 0, 0, .28), 0 0 0 1px var(--border-med); }
        .service-card:hover::before { transform: translate(14px, -14px) scale(1.35); transition: transform .4s ease; }
        .service-card .service-icon { transition: transform .3s ease; }
        .service-card:hover .service-icon { transform: scale(1.12) rotate(-4deg); }
        .service-icon { display: block; margin-bottom: 20px; font-size: 2.35rem; }
        .service-title { margin: 0 0 12px; color: var(--text-main); font-size: 1.15rem; font-weight: 700; }
        .service-desc { margin: 0; color: var(--text-muted); font-size: .93rem; line-height: 1.75; }

        .steps-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-top: 42px; }
        .step-card { border: 1px solid var(--border-soft); border-radius: 18px; background: var(--bg-card); padding: 24px; transition: transform .22s ease, border-color .22s ease; }
        .step-card:hover { transform: translateY(-4px); border-color: var(--border-med); }
        .step-number { display: grid; place-items: center; width: 38px; height: 38px; margin-bottom: 16px; border-radius: 999px; background: rgba(0,168,152,.13); color: var(--teal); font-weight: 800; }
        .step-card h3 { margin: 0 0 10px; color: var(--text-main); font-size: 1.05rem; }
        .step-card p { margin: 0; color: var(--text-muted); font-size: .93rem; line-height: 1.75; }
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
        .btn-dl.is-soon { cursor: default; opacity: .96; }
        .btn-dl.is-soon:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(0, 0, 0, .28); }
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
            transform: none;
            transition: opacity .85s cubic-bezier(.22, .61, .36, 1), transform .85s cubic-bezier(.22, .61, .36, 1);
            transition-delay: var(--reveal-delay, 0ms);
        }
        .js .fade-in { opacity: .001; transform: translateY(36px) scale(.98); }
        .js .fade-in.visible { opacity: 1; transform: none; }

        /* Staggered reveal for grouped items */
        .proof-item:nth-child(2), .step-card:nth-child(2), .service-card:nth-child(2) { --reveal-delay: 90ms; }
        .proof-item:nth-child(3), .step-card:nth-child(3), .service-card:nth-child(3) { --reveal-delay: 180ms; }
        .service-card:nth-child(4) { --reveal-delay: 270ms; }

        /* Scroll progress indicator */
        .scroll-progress {
            position: fixed;
            inset: 0 auto auto 0;
            z-index: 200;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--teal), #00c4b3);
            box-shadow: 0 0 12px var(--teal-glow);
            transition: width .12s ease-out;
        }
        .ar { display: block; }
        .en-text { display: none; }
        body.en .ar { display: none; }
        body.en .en-text { display: block; }
        span.ar { display: inline; }
        span.en-text { display: none; }
        body.en span.ar { display: none; }
        body.en span.en-text { display: inline; }

        .mobile-sticky-cta { display: none; }
        .mobile-sticky-cta a { display: inline-flex; align-items: center; justify-content: center; width: 100%; min-height: 52px; border-radius: 16px; background: var(--teal); color: #fff; font-weight: 800; box-shadow: 0 12px 34px rgba(0,0,0,.22); }
        @media (max-width: 820px) {
            .site-nav { padding: 14px 20px; }
            .nav-links { display: none; }
            .nav-menu-toggle { display: inline-flex; }
            .hero { padding: 104px 20px 74px; }
            .hero-shell { grid-template-columns: 1fr; gap: 34px; }
            .hero-copy { text-align: center; margin: 0 auto; }
            .hero-proof, .hero-cta { justify-content: center; }
            .hero-visual { min-height: 500px; }
            .phone-preview { position: relative; inset: auto; margin: 0 auto; transform: none; }
            .floating-card { left: 50%; bottom: 0; transform: translateX(-50%); }
            .steps-grid { grid-template-columns: 1fr; }
            .section,
            .social-wrap,
            .services-inner { width: min(100% - 40px, 1100px); padding-top: 72px; padding-bottom: 72px; }
        }
        @media (max-width: 640px) {
            body { padding-bottom: 84px; }
            .mobile-sticky-cta { position: fixed; inset: auto 16px 16px; z-index: 120; display: block; }
            .hero-visual { min-height: 470px; }
            .phone-preview { width: min(310px, 100%); min-height: 490px; }
            .phone-screen { min-height: 454px; }
            .floating-card { width: min(210px, 82vw); }
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
    <div class="scroll-progress" id="scrollProgress" aria-hidden="true"></div>
    <nav class="site-nav" aria-label="التنقل الرئيسي">
        <a href="{{ route('landing') }}" class="nav-logo" aria-label="{{ $cms->text('branding.brand_name', 'en') ?: 'Sirati' }}">
            <span class="logo-img {{ $cms->image('branding.logo_image') ? 'has-image' : '' }}" aria-hidden="true">
                @if ($cms->image('branding.logo_image'))
                    <img src="{{ $cms->image('branding.logo_image') }}" alt="">
                @else
                    {{ $cms->value('branding.logo_letter', 'س') }}
                @endif
            </span>
            {!! $cms->pair('branding.brand_name') !!}
        </a>
        <div class="nav-links">
            <a href="#about">{!! $cms->pair('nav.about') !!}</a>
            <a href="#services">{!! $cms->pair('nav.services') !!}</a>
            <a href="#social">{!! $cms->pair('nav.social') !!}</a>
            <a href="#download">{!! $cms->pair('nav.download') !!}</a>
        </div>
        <div class="nav-right">
            <button class="theme-toggle" type="button" onclick="toggleTheme()" aria-label="تبديل المظهر">
                <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            </button>
            <button class="lang-toggle" type="button" onclick="toggleLang()" id="langBtn">EN</button>
            <button class="nav-menu-toggle" type="button" id="menuBtn" aria-label="Open menu" aria-controls="siteDrawer" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
        </div>
    </nav>

    <dialog class="site-drawer" id="siteDrawer" aria-label="Site navigation">
        <div class="drawer-shell">
            <div class="drawer-header">
                <div>
                    <p class="drawer-title">{!! $cms->pair('drawer.title') !!}</p>
                    <p class="section-body" style="margin: 4px 0 0; font-size: .9rem;">{!! $cms->pair('drawer.subtitle') !!}</p>
                </div>
                <button type="button" class="drawer-close" aria-label="Close menu">&times;</button>
            </div>
            <div class="drawer-links">
                <a href="#about">{!! $cms->pair('drawer.link_about') !!}<span>Section</span></a>
                <a href="#workflow">{!! $cms->pair('drawer.link_workflow') !!}<span>Section</span></a>
                <a href="#services">{!! $cms->pair('drawer.link_services') !!}<span>Section</span></a>
                <a href="#social">{!! $cms->pair('drawer.link_social') !!}<span>Section</span></a>
                <a href="#download">{!! $cms->pair('drawer.link_download') !!}<span>Section</span></a>
            </div>
            <div class="drawer-actions">
                <a href="#download" class="btn-store button-primary"><span><span class="store-name">{!! $cms->pair('drawer.download_btn') !!}</span></span></a>
            </div>
        </div>
    </dialog>

    <main>
        <section class="hero">
            <div class="hero-shell">
                <div class="hero-copy fade-in">
                    <div class="hero-logo {{ $cms->image('branding.logo_image') ? 'has-image' : '' }}" aria-hidden="true">
                        @if ($cms->image('branding.logo_image'))
                            <img src="{{ $cms->image('branding.logo_image') }}" alt="">
                        @else
                            {{ $cms->value('branding.logo_letter', 'س') }}
                        @endif
                    </div>
                    <p class="hero-tagline">{!! $cms->pair('hero.tagline') !!}</p>
                    <h1 class="hero-headline">{!! $cms->pair('hero.headline') !!}</h1>
                    <p class="hero-sub">{!! $cms->pair('hero.sub') !!}</p>
                    <div class="hero-proof"><span>{!! $cms->pair('hero.chip1') !!}</span><span>{!! $cms->pair('hero.chip2') !!}</span><span>{!! $cms->pair('hero.chip3') !!}</span></div>
                    <a class="hero-scroll-link" href="#services">{!! $cms->pair('hero.explore') !!}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg></a>
                </div>
                <div class="hero-visual fade-in" aria-hidden="true">
                    <div class="phone-preview"><div class="phone-screen">
                        <div class="preview-top"><span>Sirati ATS</span><strong>CV Score</strong></div>
                        <div class="score-ring"><span>84%</span></div>
                        <ul class="preview-list">
                            <li><span>Keywords</span><strong>Good</strong></li>
                            <li><span>Experience</span><strong>Improve</strong></li>
                            <li><span>ATS format</span><strong>Ready</strong></li>
                        </ul>
                    </div></div>
                    <div class="floating-card"><strong>Smart template</strong><p>Clean layout ready for recruiters and screening systems.</p></div>
                </div>
            </div>
            <a class="scroll-hint" href="#about"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 10l5 5 5-5"/></svg>{!! $cms->pair('hero.scroll_hint') !!}</a>
        </section>

        <section class="proof-band" aria-label="Platform highlights">
            <div class="proof-item fade-in"><strong>{!! $cms->pair('proof.item1_title') !!}</strong><span>{!! $cms->pair('proof.item1_body') !!}</span></div>
            <div class="proof-item fade-in"><strong>{!! $cms->pair('proof.item2_title') !!}</strong><span>{!! $cms->pair('proof.item2_body') !!}</span></div>
            <div class="proof-item fade-in"><strong>{!! $cms->pair('proof.item3_title') !!}</strong><span>{!! $cms->pair('proof.item3_body') !!}</span></div>
        </section>

        <section id="about">
            <div class="section fade-in">
                <span class="section-label">{!! $cms->pair('about.label') !!}</span>
                <div class="divider"></div>
                <h2 class="section-title">{!! $cms->pair('about.title') !!}</h2>
                <p class="section-body">{!! $cms->pair('about.body') !!}</p>
            </div>
        </section>

        <section class="section" id="workflow">
            <div class="fade-in">
                <span class="section-label">{!! $cms->pair('workflow.label') !!}</span>
                <div class="divider"></div>
                <h2 class="section-title">{!! $cms->pair('workflow.title') !!}</h2>
                <p class="section-body">{!! $cms->pair('workflow.body') !!}</p>
            </div>
            <div class="steps-grid">
                <article class="step-card fade-in"><span class="step-number">1</span><h3>{!! $cms->pair('workflow.step1_title') !!}</h3><p>{!! $cms->pair('workflow.step1_body') !!}</p></article>
                <article class="step-card fade-in"><span class="step-number">2</span><h3>{!! $cms->pair('workflow.step2_title') !!}</h3><p>{!! $cms->pair('workflow.step2_body') !!}</p></article>
                <article class="step-card fade-in"><span class="step-number">3</span><h3>{!! $cms->pair('workflow.step3_title') !!}</h3><p>{!! $cms->pair('workflow.step3_body') !!}</p></article>
            </div>
        </section>
        <section class="services-wrap" id="services">
            <div class="services-inner">
                <div class="fade-in">
                    <span class="section-label">{!! $cms->pair('services.label') !!}</span>
                    <div class="divider"></div>
                    <h2 class="section-title">{!! $cms->pair('services.title') !!}</h2>
                </div>
                <div class="services-grid">
                    <article class="service-card fade-in">
                        <span class="service-icon" aria-hidden="true">{{ $cms->value('services.card1_icon', '📂') }}</span>
                        <h3 class="service-title">{!! $cms->pair('services.card1_title') !!}</h3>
                        <p class="service-desc">{!! $cms->pair('services.card1_body') !!}</p>
                    </article>
                    <article class="service-card fade-in">
                        <span class="service-icon" aria-hidden="true">{{ $cms->value('services.card2_icon', '📈') }}</span>
                        <h3 class="service-title">{!! $cms->pair('services.card2_title') !!}</h3>
                        <p class="service-desc">{!! $cms->pair('services.card2_body') !!}</p>
                    </article>
                    <article class="service-card fade-in">
                        <span class="service-icon" aria-hidden="true">{{ $cms->value('services.card3_icon', '💼') }}</span>
                        <h3 class="service-title">{!! $cms->pair('services.card3_title') !!}</h3>
                        <p class="service-desc">{!! $cms->pair('services.card3_body') !!}</p>
                    </article>
                    <article class="service-card fade-in">
                        <span class="service-icon" aria-hidden="true">{{ $cms->value('services.card4_icon', '📚') }}</span>
                        <h3 class="service-title">{!! $cms->pair('services.card4_title') !!}</h3>
                        <p class="service-desc">{!! $cms->pair('services.card4_body') !!}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="social-wrap" id="social">
            <div class="fade-in">
                <span class="section-label">{!! $cms->pair('social.label') !!}</span>
                <div class="divider"></div>
                <h2 class="section-title">{!! $cms->pair('social.title') !!}</h2>
                <p class="section-body">{!! $cms->pair('social.body') !!}</p>
            </div>
            <div class="social-grid fade-in">
                <a href="{{ $cms->value('social.tiktok_url', '#') }}" class="social-card" target="_blank" rel="noopener noreferrer" aria-label="TikTok"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.91a8.16 8.16 0 004.77 1.52V7a4.85 4.85 0 01-1-.31z"/></svg>TikTok</a>
                <a href="{{ $cms->value('social.instagram_url', '#') }}" class="social-card" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>Instagram</a>
                <a href="{{ $cms->value('social.linkedin_url', '#') }}" class="social-card" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>LinkedIn</a>
                <a href="mailto:{{ $cms->value('social.email', 'hello@sirati.app') }}" class="social-card"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 010 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.64 12 9.548l6.545-4.91 1.528-1.145C21.69 2.28 24 3.434 24 5.457z"/></svg>{!! $cms->pair('social.email_label') !!}</a>
            </div>
        </section>

        <section class="download-wrap" id="download">
            <div class="download-inner fade-in">
                <h2>{!! $cms->pair('download.title') !!}</h2>
                <p>{!! $cms->pair('download.body') !!}</p>
                <div class="download-btns">
                    <span class="btn-dl ios is-soon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14.84 4.36c0 1.13-.44 2.22-1.22 3.02-.8.83-1.87 1.28-2.98 1.23-.06-1.05.41-2.14 1.2-2.94.76-.78 1.96-1.36 3-1.31zM19.5 17.58c-.62 1.43-.92 2.06-1.72 3.3-1.13 1.73-2.73 3.89-4.73 3.91-1.78.02-2.24-1.17-4.66-1.15-2.42.01-2.93 1.17-4.71 1.15-2-.02-3.53-2-4.66-3.74C.74 18.52.03 15.57.86 12.82c.76-2.51 2.68-4.12 4.49-4.16 1.64-.03 3.19 1.15 4.2 1.15 1 0 2.86-1.43 4.84-1.22.83.03 3.15.33 4.72 2.63-1.24.77-2.03 2.09-2.03 3.56 0 1.8 1.08 3.39 2.42 4.2z"/></svg><span><span class="store-kicker">{!! $cms->pair('download.ios_kicker') !!}</span><span class="store-name">{!! $cms->pair('download.ios_name') !!}</span></span></span>
                    <span class="btn-dl android is-soon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.18 23.76c.3.17.66.2.99.06l12.46-7.2-2.63-2.63-10.82 9.77zm-1.12-20.9C2.03 3.1 2 3.34 2 3.58v16.84c0 .24.03.48.06.7l10.9-10.9-10.9-10.36zM20.43 10.5l-2.78-1.61-2.98 2.98 2.98 2.98 2.8-1.62c.8-.46.8-1.67-.02-2.13zM4.17.24C3.84.1 3.48.13 3.18.3L14.02 10.3 16.65 7.7 4.17.24z"/></svg><span><span class="store-kicker">{!! $cms->pair('download.android_kicker') !!}</span><span class="store-name">{!! $cms->pair('download.android_name') !!}</span></span></span>
                </div>
            </div>
        </section>
    </main>

    <div class="mobile-sticky-cta"><a href="#download">{!! $cms->pair('cta.download') !!}</a></div>
    <footer>
        <p><span class="ar">© {{ now()->year }} <span class="teal">{{ $cms->text('branding.brand_name', 'ar') }}</span> — {{ $cms->text('footer.rights', 'ar') }}</span><span class="en-text">© {{ now()->year }} <span class="teal">{{ $cms->text('branding.brand_name', 'en') }}</span> — {{ $cms->text('footer.rights', 'en') }}</span></p>
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

        (function initScrollProgress() {
            const bar = document.getElementById('scrollProgress');
            if (!bar) return;
            let ticking = false;
            function update() {
                const doc = document.documentElement;
                const max = doc.scrollHeight - doc.clientHeight;
                const pct = max > 0 ? (doc.scrollTop / max) * 100 : 0;
                bar.style.width = pct + '%';
                ticking = false;
            }
            window.addEventListener('scroll', () => {
                if (!ticking) { window.requestAnimationFrame(update); ticking = true; }
            }, { passive: true });
            update();
        })();

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

        (function initDrawer() {
            const drawer = document.getElementById('siteDrawer');
            const menuBtn = document.getElementById('menuBtn');
            if (!drawer || !menuBtn) return;
            const closeBtn = drawer.querySelector('.drawer-close');

            function openDrawer() {
                if (typeof drawer.showModal === 'function') {
                    if (!drawer.open) drawer.showModal();
                } else {
                    drawer.setAttribute('open', '');
                }
                menuBtn.setAttribute('aria-expanded', 'true');
            }

            function closeDrawer() {
                if (typeof drawer.close === 'function' && drawer.open) {
                    drawer.close();
                } else {
                    drawer.removeAttribute('open');
                }
                menuBtn.setAttribute('aria-expanded', 'false');
            }

            menuBtn.addEventListener('click', openDrawer);
            if (closeBtn) closeBtn.addEventListener('click', closeDrawer);

            // Close when the backdrop (outside the shell) is clicked.
            drawer.addEventListener('click', (event) => {
                if (event.target === drawer) closeDrawer();
            });

            // Close after tapping any link inside the drawer.
            drawer.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeDrawer));

            // Keep aria-expanded correct when closed via Esc or dialog.close().
            drawer.addEventListener('close', () => menuBtn.setAttribute('aria-expanded', 'false'));
        })();
    </script>
</body>
</html>
