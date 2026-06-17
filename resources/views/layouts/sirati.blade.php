<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sirati')</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at top left, rgba(56,189,248,.18), transparent 30rem), #020617;
            color: #f8fafc;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            line-height: 1.8;
        }
        a { color: inherit; text-decoration: none; }
        .page { width: min(1060px, calc(100% - 32px)); margin: 0 auto; padding: 24px 0 48px; }
        .nav { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 28px; }
        .brand { display: inline-flex; align-items: center; gap: 10px; font-weight: 900; }
        .mark { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 14px; color: #38bdf8; border: 1px solid #1e3a5f; background: #0f172a; }
        .links { display: flex; flex-wrap: wrap; gap: 10px; color: #cbd5e1; font-size: 14px; }
        .links a { padding: 8px 12px; border: 1px solid #1e3a5f; border-radius: 999px; background: rgba(15,23,42,.72); }
        .hero-card, .card {
            border: 1px solid rgba(30,58,95,.9);
            border-radius: 24px;
            background: linear-gradient(145deg, rgba(15,23,42,.96), rgba(17,28,53,.92));
            padding: 24px;
            box-shadow: 0 24px 70px rgba(2,8,23,.32);
        }
        h1 { margin: 0 0 10px; font-size: clamp(30px, 5vw, 52px); line-height: 1.2; }
        h2 { margin: 0 0 14px; font-size: 24px; }
        h3 { margin: 0 0 8px; }
        p { color: #cbd5e1; margin: 0 0 16px; }
        .muted { color: #94a3b8; }
        .grid { display: grid; gap: 18px; }
        .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        form { display: grid; gap: 14px; }
        label { display: grid; gap: 6px; font-size: 13px; font-weight: 800; color: #dbeafe; }
        input, textarea, select {
            width: 100%;
            border: 1px solid #1e3a5f;
            border-radius: 14px;
            background: rgba(2,6,23,.72);
            color: #f8fafc;
            font: inherit;
            padding: 12px 13px;
        }
        textarea { min-height: 130px; resize: vertical; }
        .button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 12px 18px;
            border-radius: 14px;
            border: 1px solid transparent;
            background: linear-gradient(135deg, #0284c7, #4f46e5);
            color: #fff;
            font: inherit;
            font-weight: 900;
            cursor: pointer;
        }
        .button-secondary { background: #0f172a; border-color: #1e3a5f; color: #bfdbfe; }
        .error { color: #fca5a5; font-size: 12px; font-weight: 700; }
        .alert { padding: 12px 14px; border-radius: 14px; border: 1px solid rgba(245,158,11,.35); background: rgba(120,53,15,.25); color: #fde68a; margin-bottom: 16px; }
        .score { display: grid; place-items: center; width: 150px; height: 150px; border-radius: 50%; border: 10px solid #38bdf8; margin: 0 auto 18px; font-size: 34px; font-weight: 900; color: #7dd3fc; }
        .pill { display: inline-flex; padding: 4px 10px; border-radius: 999px; background: rgba(56,189,248,.12); color: #7dd3fc; font-size: 12px; font-weight: 800; margin: 0 0 6px 6px; }
        .recommendations { display: grid; gap: 18px; }
        .recommendation-block { border: 1px solid #1e3a5f; border-radius: 16px; background: rgba(2,6,23,.34); padding: 18px; }
        .recommendation-block h3 { color: #bfdbfe; font-size: 18px; margin-bottom: 10px; }
        .recommendation-block p:last-child { margin-bottom: 0; }
        .priority-list { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
        .priority-list li { display: grid; grid-template-columns: 32px 1fr; gap: 10px; align-items: start; color: #dbeafe; }
        .priority-list strong { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: rgba(56,189,248,.14); color: #7dd3fc; font-size: 13px; }
        .improvement-list { display: grid; gap: 12px; }
        .improvement-item { border: 1px solid rgba(30,58,95,.78); border-radius: 14px; padding: 14px; background: rgba(15,23,42,.58); }
        .improvement-item p { margin-bottom: 8px; }
        .improvement-label { display: inline-flex; margin-inline-end: 6px; color: #93c5fd; font-size: 12px; font-weight: 900; }
        .keyword-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .skill-builder { display: grid; gap: 10px; }
        .skill-entry { display: flex; flex-wrap: wrap; gap: 10px; }
        .skill-entry input { flex: 1 1 240px; }
        .skill-entry .button { min-height: 46px; }
        .skill-chips, .skill-suggestions { display: flex; flex-wrap: wrap; gap: 8px; }
        .skill-chip, .skill-suggestion {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(56,189,248,.28);
            border-radius: 999px;
            background: rgba(56,189,248,.1);
            color: #dbeafe;
            font: inherit;
            font-size: 13px;
            font-weight: 800;
            padding: 7px 11px;
        }
        .skill-chip button, .skill-suggestion { cursor: pointer; }
        .skill-chip button { border: 0; background: transparent; color: #93c5fd; font: inherit; font-weight: 900; padding: 0; }
        .skill-store { min-height: 88px; }
        .js .skill-store { display: none; }
        .bar { height: 10px; border-radius: 999px; background: #0f172a; overflow: hidden; }
        .bar span { display: block; height: 100%; background: linear-gradient(90deg, #38bdf8, #818cf8); }
        .list { margin: 0; padding: 0 18px 0 0; color: #dbeafe; }
        .list li { margin-bottom: 8px; }
        pre.cv { white-space: pre-wrap; direction: ltr; text-align: left; background: #020617; border: 1px solid #1e3a5f; border-radius: 18px; padding: 20px; overflow-x: auto; color: #e2e8f0; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #1e3a5f; text-align: right; vertical-align: top; }
        th { color: #93c5fd; font-size: 12px; }
        td { color: #dbeafe; font-size: 13px; }
        @media (max-width: 820px) { .grid-2, .grid-3 { grid-template-columns: 1fr; } .nav { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
    <main class="page">
        <nav class="nav">
            <a class="brand" href="{{ route('landing') }}"><span class="mark">س</span><span>Sirati</span></a>
            <div class="links">
                <a href="{{ route('landing') }}">الرئيسية</a>
                <a href="{{ route('analyses.create') }}">فحص السيرة</a>
                <a href="{{ route('generated-cvs.create') }}">إنشاء سيرة متوافقة مع ATS</a>
                <a href="{{ route('admin.index') }}">لوحة الإدارة</a>
            </div>
        </nav>

        @yield('content')
    </main>
</body>
</html>
