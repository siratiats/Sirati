@extends('layouts.sirati')

@section('html_lang', 'en')
@section('html_dir', 'ltr')
@section('title', trim($__env->yieldContent('admin_title', 'Admin')).' | Sirati Admin')
@section('body_class', 'admin-body')
@section('page_class', 'admin-page')
@section('hide_nav', true)

@section('content')
    @php
        $activeAdminPage = trim($__env->yieldContent('admin_active', 'overview'));
        $adminNav = [
            ['key' => 'overview', 'route' => 'admin.index', 'label' => 'Overview', 'hint' => 'Dashboard'],
            ['key' => 'templates', 'route' => 'admin.cv-templates.index', 'label' => 'CV Templates', 'hint' => 'Designs'],
            ['key' => 'education', 'route' => 'admin.education.index', 'label' => 'Education', 'hint' => 'Content'],
            ['key' => 'jobs', 'route' => 'admin.jobs.index', 'label' => 'Jobs', 'hint' => 'Import & manage'],
            ['key' => 'leads', 'route' => 'admin.leads.index', 'label' => 'Leads', 'hint' => 'Landing'],
            ['key' => 'analyses', 'route' => 'admin.analyses.index', 'label' => 'CV Analyses', 'hint' => 'Scores'],
            ['key' => 'generated-cvs', 'route' => 'admin.generated-cvs.index', 'label' => 'Generated CVs', 'hint' => 'Exports'],
        ];
    @endphp

    <div class="admin-shell">
        <div class="admin-mobile-bar"><a class="admin-sidebar-brand" href="{{ route('admin.index') }}"><span class="mark">S</span><span><strong>Sirati Admin</strong><span>Application management</span></span></a><button class="button button-secondary" type="button" data-admin-menu-toggle aria-controls="admin-sidebar" aria-expanded="false">Menu</button></div>
        <button class="admin-sidebar-backdrop" type="button" data-admin-menu-close aria-label="Close admin navigation"></button>
        <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
            <a class="admin-sidebar-brand" href="{{ route('admin.index') }}">
                <span class="mark">S</span>
                <span>
                    <strong>Sirati Admin</strong>
                    <span>Application management</span>
                </span>
            </a>

            <nav class="admin-side-nav" aria-label="Admin sections">
                @foreach ($adminNav as $item)
                    <a href="{{ route($item['route']) }}" @class(['active' => $activeAdminPage === $item['key']])>
                        <span>{{ $item['label'] }}</span>
                        <small>{{ $item['hint'] }}</small>
                    </a>
                @endforeach
            </nav>

            <div class="admin-sidebar-footer">
                <a class="button button-secondary" href="{{ route('landing') }}">View public site</a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="button button-danger" type="submit" style="width:100%;">Logout</button>
                </form>
            </div>
        </aside>

        <section class="admin-main">
            <header class="admin-topbar">
                <div>
                    <span class="admin-eyebrow">@yield('admin_eyebrow', 'Operations')</span>
                    <h1>@yield('admin_heading', 'Sirati Admin')</h1>
                    <p>@yield('admin_description', 'Manage the data and workflows that power the Sirati application.')</p>
                </div>
                <div class="admin-topbar-actions">
                    @yield('admin_actions')
                </div>
            </header>

            @if (session('status') || session('jobs_import_error'))
                <div class="toast-stack" aria-live="polite">
                    @if (session('status'))
                        <section class="toast toast-success" data-toast>
                            <p>{{ session('status') }}</p>
                        </section>
                    @endif

                    @if (session('jobs_import_error'))
                        <section class="toast toast-error" data-toast>
                            <p>{{ session('jobs_import_error') }}</p>
                        </section>
                    @endif
                </div>
            @endif

            @if (session('jobs_import_errors') && count(session('jobs_import_errors')) > 0)
                <section class="card" style="margin-bottom: 16px; border-color: rgba(250,204,21,.55);">
                    <h3 style="margin: 0 0 8px;">Job Import Warnings</h3>
                    <ul style="margin: 0; padding-left: 18px; color: #92400e;">
                        @foreach (session('jobs_import_errors') as $error)
                            <li>Row {{ $error['row'] ?? '?' }}: {{ $error['message'] ?? '' }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="admin-content">
                @yield('admin_content')
            </div>
        </section>
    </div>
@endsection
