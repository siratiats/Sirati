<!DOCTYPE html>
<html lang="@yield('html_lang', 'ar')" dir="@yield('html_dir', 'rtl')">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sirati')</title>
    <style>
        :root {
            --sir-primary: #0ea5e9;
            --sir-primary-strong: #2563eb;
            --sir-success: #22c55e;
            --sir-warning: #f59e0b;
            --sir-danger: #ef4444;
            --sir-info: #38bdf8;
            --sir-neutral: #94a3b8;
            --sir-surface: #0f172a;
            --sir-surface-2: #111c35;
            --sir-border: #1e3a5f;
        }
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
        .mark {
            display: grid;
            place-items: center;
            width: 40px;
            height: 40px;
            border-radius: 14px;
            border: 1px solid #1e3a5f;
            background: linear-gradient(135deg, #12384f, #0e8f86);
        }
        .mark svg { width: 24px; height: 24px; display: block; }
        .mark path,
        .mark circle { fill: none; stroke: #fff; }
        .mark path { stroke-width: 10; stroke-linecap: round; stroke-linejoin: round; }
        .mark circle { fill: #fff; stroke: none; }
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
            gap: 8px;
            padding: 12px 18px;
            border-radius: 14px;
            border: 1px solid transparent;
            background: linear-gradient(135deg, #0284c7, #4f46e5);
            color: #fff;
            font: inherit;
            font-weight: 900;
            cursor: pointer;
        }
        .button:disabled { cursor: not-allowed; opacity: .55; }
        .button-secondary { background: #0f172a; border-color: #1e3a5f; color: #bfdbfe; }
        .button-danger { background: rgba(127,29,29,.72); border-color: rgba(248,113,113,.45); color: #fecaca; }
        .button-loading::before {
            content: "";
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,.35);
            border-top-color: #fff;
            border-radius: 999px;
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .error { color: #fca5a5; font-size: 12px; font-weight: 700; }
        .alert { padding: 12px 14px; border-radius: 14px; border: 1px solid rgba(245,158,11,.35); background: rgba(120,53,15,.25); color: #fde68a; margin-bottom: 16px; }
        .admin-anchors {
            position: sticky;
            top: 10px;
            z-index: 20;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 18px;
            padding: 10px;
            border: 1px solid rgba(30,58,95,.78);
            border-radius: 18px;
            background: rgba(2,6,23,.88);
            backdrop-filter: blur(12px);
        }
        .admin-anchors a, .chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            border: 1px solid rgba(56,189,248,.2);
            border-radius: 999px;
            background: rgba(15,23,42,.78);
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 800;
            padding: 6px 11px;
        }
        .chip-active { border-color: rgba(56,189,248,.55); background: rgba(14,165,233,.18); color: #e0f2fe; }
        .admin-section-header { display: flex; align-items: start; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        .admin-section-header p { margin-bottom: 0; }
        .metric-row { display: flex; flex-wrap: wrap; gap: 10px; margin: 14px 0 0; }
        .metric-card { border: 1px solid rgba(30,58,95,.78); border-radius: 14px; background: rgba(2,6,23,.28); padding: 12px 14px; min-width: 120px; }
        .metric-card strong { display: block; color: #f8fafc; font-size: 22px; line-height: 1.2; }
        .metric-card span { color: #94a3b8; font-size: 12px; font-weight: 800; }
        .filter-panel { display: grid; gap: 12px; margin: 16px 0; padding: 14px; border: 1px solid rgba(30,58,95,.72); border-radius: 16px; background: rgba(2,6,23,.24); }
        .filter-row { display: flex; flex-wrap: wrap; align-items: end; gap: 10px; }
        .filter-row label { min-width: 170px; flex: 1 1 170px; }
        .filter-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .badge { display: inline-flex; align-items: center; border-radius: 999px; border: 1px solid rgba(148,163,184,.28); padding: 4px 9px; font-size: 12px; font-weight: 900; white-space: nowrap; }
        .badge-success { background: rgba(34,197,94,.12); border-color: rgba(34,197,94,.32); color: #86efac; }
        .badge-warning { background: rgba(245,158,11,.13); border-color: rgba(245,158,11,.34); color: #fde68a; }
        .badge-danger { background: rgba(239,68,68,.13); border-color: rgba(239,68,68,.34); color: #fecaca; }
        .badge-info { background: rgba(56,189,248,.12); border-color: rgba(56,189,248,.32); color: #bae6fd; }
        .badge-neutral { background: rgba(148,163,184,.12); color: #cbd5e1; }
        .empty-state { border: 1px dashed rgba(148,163,184,.45); border-radius: 16px; background: rgba(2,6,23,.2); padding: 28px; text-align: center; }
        .empty-state-icon { display: grid; place-items: center; width: 46px; height: 46px; margin: 0 auto 12px; border-radius: 999px; background: rgba(56,189,248,.12); color: #7dd3fc; font-size: 24px; font-weight: 900; }
        .empty-state h3 { color: #f8fafc; }
        .pagination-wrap { margin-top: 14px; color: #cbd5e1; font-size: 13px; }
        .pagination-wrap nav > div:first-child { display: none; }
        .sync-card { border: 1px solid rgba(56,189,248,.24); border-radius: 16px; background: rgba(14,165,233,.08); padding: 14px; }
        .sync-card p { margin-bottom: 8px; }
        .bulk-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin: 12px 0; padding: 12px; border: 1px solid rgba(30,58,95,.72); border-radius: 16px; background: rgba(2,6,23,.28); }
        .bulk-bar > div { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .bulk-bar select { min-width: 190px; }
        .table-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .table-check { width: auto; }
        dialog.admin-drawer {
            width: min(720px, calc(100vw - 28px));
            max-height: calc(100vh - 40px);
            overflow: auto;
            border: 1px solid rgba(56,189,248,.28);
            border-radius: 18px;
            background: #0f172a;
            color: #f8fafc;
            padding: 0;
            box-shadow: 0 24px 90px rgba(2,8,23,.6);
        }
        dialog.admin-drawer::backdrop { background: rgba(2,6,23,.72); }
        .drawer-body { padding: 20px; }
        .drawer-header { display: flex; align-items: start; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
        .drawer-close { border: 1px solid rgba(148,163,184,.28); border-radius: 999px; background: rgba(2,6,23,.34); color: #e2e8f0; cursor: pointer; font: inherit; font-weight: 900; padding: 5px 11px; }
        .toast-stack { position: fixed; top: 18px; left: 18px; z-index: 50; display: grid; gap: 10px; width: min(380px, calc(100vw - 36px)); }
        .toast { border: 1px solid rgba(148,163,184,.28); border-radius: 16px; background: rgba(15,23,42,.96); padding: 14px 16px; box-shadow: 0 18px 40px rgba(2,8,23,.34); }
        .toast p { margin-bottom: 0; font-size: 14px; }
        .toast-success { border-color: rgba(34,197,94,.42); }
        .toast-success p { color: #bbf7d0; }
        .toast-error { border-color: rgba(239,68,68,.48); }
        .toast-error p { color: #fecaca; }
        .toast-info { border-color: rgba(56,189,248,.38); }
        .toast-info p { color: #bae6fd; }
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
        .admin-body {
            background: #f4f7fb;
            color: #172033;
        }
        .admin-body .page.admin-page {
            width: 100%;
            margin: 0;
            padding: 0;
        }
        .admin-shell {
            display: grid;
            grid-template-columns: 286px minmax(0, 1fr);
            min-height: 100vh;
            direction: ltr;
            background:
                radial-gradient(circle at 78% 0%, rgba(14,165,233,.12), transparent 28rem),
                #f4f7fb;
        }
        .admin-sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 22px 18px;
            background: #07111f;
            color: #f8fafc;
            border-inline-end: 1px solid rgba(15,23,42,.1);
            direction: ltr;
            overflow-y: auto;
        }
        .admin-sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 8px 18px;
            border-bottom: 1px solid rgba(148,163,184,.18);
        }
        .admin-sidebar-brand .mark {
            background: #0ea5e9;
            border-color: rgba(255,255,255,.16);
            color: #fff;
        }
        .admin-sidebar-brand strong { display: block; font-size: 18px; line-height: 1.2; }
        .admin-sidebar-brand span:last-child { display: block; color: #94a3b8; font-size: 12px; font-weight: 800; }
        .admin-side-nav {
            display: grid;
            gap: 5px;
            margin: 0;
        }
        .admin-side-nav a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-height: 42px;
            padding: 9px 11px;
            border-radius: 10px;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 850;
            border: 1px solid transparent;
        }
        .admin-side-nav a:hover,
        .admin-side-nav a:focus,
        .admin-side-nav a.active {
            background: rgba(14,165,233,.12);
            border-color: rgba(14,165,233,.22);
            color: #fff;
            outline: none;
        }
        .admin-side-nav small { color: #64748b; font-size: 11px; font-weight: 900; }
        .admin-sidebar-footer {
            margin-top: auto;
            display: grid;
            gap: 8px;
            padding-top: 12px;
            border-top: 1px solid rgba(148,163,184,.18);
        }
        .admin-main {
            direction: ltr;
            min-width: 0;
            padding: 28px;
        }
        .admin-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 18px;
        }
        .admin-eyebrow {
            display: inline-flex;
            margin-bottom: 8px;
            color: #0369a1;
            font-size: 13px;
            font-weight: 900;
        }
        .admin-topbar h1 {
            margin: 0;
            color: #0f172a;
            font-size: 30px;
            line-height: 1.18;
            letter-spacing: 0;
        }
        .admin-topbar p {
            max-width: 70ch;
            color: #475569;
            margin: 8px 0 0;
        }
        .admin-topbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
            flex: 0 0 auto;
        }
        .admin-kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
        .admin-kpi-card {
            min-height: 118px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid #d9e2ec;
            border-radius: 14px;
            background: #fff;
            padding: 18px;
            box-shadow: 0 8px 20px rgba(15,23,42,.05);
        }
        .admin-kpi-card span { color: #64748b; font-size: 13px; font-weight: 900; }
        .admin-kpi-card strong { color: #0f766e; font-size: 34px; line-height: 1; }
        .admin-kpi-card .muted { color: #64748b; font-size: 12px; margin: 0; }
        .admin-content { display: grid; gap: 16px; }
        .admin-body .hero-card,
        .admin-body .card {
            border: 1px solid #d9e2ec;
            border-radius: 14px;
            background: #fff;
            color: #172033;
            padding: 22px;
            box-shadow: 0 8px 20px rgba(15,23,42,.05);
        }
        .admin-body .card { margin-top: 0 !important; }
        .admin-body h2,
        .admin-body h3 { color: #0f172a; }
        .admin-body p,
        .admin-body .muted { color: #64748b; }
        .admin-body label { color: #334155; }
        .admin-body input,
        .admin-body textarea,
        .admin-body select {
            border-color: #cbd5e1;
            background: #fff;
            color: #0f172a;
        }
        .admin-body input:focus,
        .admin-body textarea:focus,
        .admin-body select:focus {
            border-color: #0ea5e9;
            outline: 3px solid rgba(14,165,233,.16);
        }
        .admin-body .button {
            background: #0369a1;
            border-color: #0369a1;
            border-radius: 10px;
            color: #fff;
            box-shadow: none;
        }
        .admin-body .button-secondary {
            background: #fff;
            border-color: #cbd5e1;
            color: #0f172a;
        }
        .admin-body .button-danger {
            background: #b91c1c;
            border-color: #b91c1c;
            color: #fff;
        }
        .admin-body .filter-panel,
        .admin-body .bulk-bar,
        .admin-body .recommendation-block,
        .admin-body .sync-card {
            border-color: #d9e2ec;
            background: #f8fafc;
        }
        .admin-body .metric-card {
            background: #fff;
            border-color: #d9e2ec;
        }
        .admin-body .metric-card strong { color: #0f766e; }
        .admin-body .metric-card span { color: #64748b; }
        .admin-body .table-wrap {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
        }
        .admin-body table { min-width: 900px; }
        .admin-body th,
        .admin-body td {
            border-bottom-color: #e2e8f0;
            color: #334155;
            text-align: left;
        }
        .admin-body th {
            color: #475569;
            background: #f8fafc;
        }
        .admin-body tr:last-child td { border-bottom: 0; }
        .admin-body .empty-state {
            border-color: #cbd5e1;
            background: #f8fafc;
        }
        .admin-body .empty-state h3 { color: #0f172a; }
        .admin-body .badge-success { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
        .admin-body .badge-warning { color: #92400e; background: #fef3c7; border-color: #fde68a; }
        .admin-body .badge-danger { color: #991b1b; background: #fee2e2; border-color: #fecaca; }
        .admin-body .badge-info { color: #075985; background: #e0f2fe; border-color: #bae6fd; }
        .admin-body .badge-neutral { color: #475569; background: #f1f5f9; border-color: #e2e8f0; }
        .admin-body .chip {
            background: #fff;
            border-color: #cbd5e1;
            color: #334155;
        }
        .admin-body .chip-active {
            background: #e0f2fe;
            border-color: #7dd3fc;
            color: #075985;
        }
        .admin-body dialog.admin-drawer {
            background: #fff;
            color: #172033;
            border-color: #cbd5e1;
        }
        .admin-body dialog.admin-drawer::backdrop { background: rgba(15,23,42,.42); }
        .admin-body .drawer-close {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }
        .admin-body .pagination-wrap { color: #475569; }
        .admin-work-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
            margin-top: 10px;
        }
        .admin-work-card {
            display: block;
            min-height: 170px;
            border: 1px solid #d9e2ec;
            border-radius: 14px;
            background: #fff;
            padding: 18px;
            color: #172033;
            transition: border-color .18s ease, transform .18s ease;
        }
        .admin-work-card:hover,
        .admin-work-card:focus {
            border-color: #7dd3fc;
            transform: translateY(-2px);
            outline: none;
        }
        .admin-work-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            height: 34px;
            margin-bottom: 14px;
            border-radius: 10px;
            background: #e0f2fe;
            color: #075985;
            font-size: 12px;
            font-weight: 900;
        }
        .admin-work-card h3 { margin-bottom: 8px; }
        .admin-work-card p { margin-bottom: 0; }
        .admin-mobile-bar { display: none; }
        .admin-sidebar-backdrop { display: none; }
        .field-error { color: #b91c1c; font-size: 12px; font-weight: 800; margin-top: 4px; }
        .admin-body [aria-invalid="true"] { border-color: #ef4444; }
        @media (max-width: 1080px) { .admin-mobile-bar { display: flex; position: sticky; top: 0; z-index: 42; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; background: #fff; border-bottom: 1px solid #d9e2ec; } }
        @media (max-width: 1080px) { .admin-sidebar { position: fixed; inset: 0 auto 0 0; width: min(320px, 86vw); height: 100vh; z-index: 44; transform: translateX(-100%); transition: transform .2s ease; } .admin-body.admin-menu-open .admin-sidebar { transform: translateX(0); } }
        @media (max-width: 1080px) { .admin-sidebar-backdrop { position: fixed; inset: 0; z-index: 43; display: none; border: 0; background: rgba(15,23,42,.48); } .admin-body.admin-menu-open .admin-sidebar-backdrop { display: block; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
        @media (max-width: 1080px) {
            .admin-shell { grid-template-columns: 1fr; }
            .admin-sidebar { position: relative; height: auto; }
            .admin-side-nav { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .admin-sidebar-footer { margin-top: 0; }
        }
        @media (max-width: 820px) { .grid-2, .grid-3, .admin-kpi-grid { grid-template-columns: 1fr; } .nav, .admin-section-header, .admin-topbar { align-items: flex-start; flex-direction: column; } .admin-main { padding: 18px; } .admin-side-nav { grid-template-columns: 1fr; } }
        @media (max-width: 1080px) { .admin-body .admin-sidebar { position: fixed; height: 100vh; transform: translateX(-100%); } .admin-body.admin-menu-open .admin-sidebar { transform: translateX(0); } }
    </style>
</head>
<body class="@yield('body_class')">
    <main class="page @yield('page_class')">
        @hasSection('hide_nav')
        @else
        <nav class="nav">
            <a class="brand" href="{{ route('landing') }}">
                <span class="mark" aria-hidden="true">
                    <svg viewBox="0 0 100 100" role="img" focusable="false">
                        <path d="M22 55 C26 64 34 73 41 73 C47 73 51 66 56 58 C63 47 69 36 75 27"></path>
                        <circle cx="78" cy="23" r="6"></circle>
                    </svg>
                </span>
                <span>Sirati</span>
            </a>
            <div class="links">
                <a href="{{ route('landing') }}">Ø§Ù„Ø±Ø¦ÙŠØ³ÙŠØ©</a>
                <a href="{{ route('analyses.create') }}">ÙØ­Øµ Ø§Ù„Ø³ÙŠØ±Ø©</a>
                <a href="{{ route('generated-cvs.create') }}">Ø¥Ù†Ø´Ø§Ø¡ Ø³ÙŠØ±Ø© Ù…ØªÙˆØ§ÙÙ‚Ø© Ù…Ø¹ ATS</a>
                <a href="{{ route('admin.index') }}">Ù„ÙˆØ­Ø© Ø§Ù„Ø¥Ø¯Ø§Ø±Ø©</a>
                @auth
                    <form method="POST" action="{{ route('admin.logout') }}" style="display: inline;">
                        @csrf
                        <button class="button button-secondary" type="submit" style="padding: 8px 12px; border-radius: 999px; font-size: 14px;">Ø®Ø±ÙˆØ¬</button>
                    </form>
                @endauth
            </div>
        </nav>
        @endif

        @yield('content')
    </main>
    <script>
        document.querySelectorAll('[data-loading-form]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('[data-loading-button]');
                const label = form.querySelector('[data-loading-label]');
                let seconds = 0;

                if (!button || !label) return;

                button.disabled = true;
                button.classList.add('button-loading');
                const render = () => {
                    const loadingText = document.body.classList.contains('admin-body') ? 'Working' : 'Ø¬Ø§Ø±ÙŠ Ø§Ù„ØªÙ†ÙÙŠØ°â€¦ / Working';
                    label.textContent = `${loadingText}â€¦ ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
                    seconds += 1;
                };
                render();
                window.setInterval(render, 1000);
            });
        });

        document.querySelectorAll('[data-toast]').forEach((toast) => {
            window.setTimeout(() => toast.remove(), 6000);
        });


        const adminToast=(m,t='success')=>{let s=document.querySelector('.toast-stack')||document.body.appendChild(Object.assign(document.createElement('div'),{className:'toast-stack'}));let n=document.createElement('section');n.className='toast toast-'+t;n.innerHTML='<p>'+m+'</p>';s.appendChild(n);setTimeout(()=>n.remove(),6000)};
        document.querySelectorAll('[data-ajax-form]').forEach(f=>f.addEventListener('submit',async e=>{e.preventDefault();let b=f.querySelector('button[type="submit"]');if(b)b.disabled=true;let r=await fetch(f.action,{method:f.method,body:new FormData(f),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});let p=await r.json();if(!r.ok){if(b)b.disabled=false;adminToast(p.message||'Request failed','error');return}document.querySelector(f.dataset.ajaxTarget)?.insertAdjacentHTML(f.dataset.ajaxPrepend==='false'?'beforeend':'afterbegin',p.row_html||'');document.querySelector(f.dataset.ajaxDialogTarget)?.insertAdjacentHTML('beforeend',p.dialog_html||'');document.querySelector(f.dataset.ajaxEmptyTarget)?.remove();adminToast(p.message||'Saved');f.reset();f.closest('dialog')?.close();if(b)b.disabled=false}));
        document.querySelector('[data-admin-menu-toggle]')?.addEventListener('click',()=>{document.body.classList.toggle('admin-menu-open');document.querySelector('[data-admin-menu-toggle]')?.setAttribute('aria-expanded',document.body.classList.contains('admin-menu-open')?'true':'false')});document.querySelectorAll('[data-admin-menu-close],.admin-side-nav a').forEach(x=>x.addEventListener('click',()=>{document.body.classList.remove('admin-menu-open');document.querySelector('[data-admin-menu-toggle]')?.setAttribute('aria-expanded','false')}));
        document.querySelectorAll('[data-check-all]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                document.querySelectorAll(checkbox.dataset.checkAll).forEach((item) => {
                    item.checked = checkbox.checked;
                });
            });
        });
    </script>
</body>
</html>


