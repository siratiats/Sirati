@extends('layouts.admin')

@section('html_lang', 'en')
@section('html_dir', 'ltr')
@section('title', trim($__env->yieldContent('admin_title', 'Admin')).' | Siratie Admin')
@section('body_class', 'admin-body')

@section('content')
    @php
        $activeAdminPage = trim($__env->yieldContent('admin_active', 'overview'));
        // Items whose route is not registered (e.g. Localization before it ships) are hidden.
        $adminNavGroups = [
            'Monitor' => [
                ['key' => 'overview', 'route' => 'admin.index', 'label' => 'Overview', 'icon' => 'overview'],
                ['key' => 'health', 'route' => 'admin.health.index', 'label' => 'System health', 'icon' => 'health'],
                ['key' => 'users', 'route' => 'admin.users.index', 'label' => 'Users', 'icon' => 'users'],
            ],
            'Content' => [
                ['key' => 'templates', 'route' => 'admin.cv-templates.index', 'label' => 'CV templates', 'icon' => 'file'],
                ['key' => 'education', 'route' => 'admin.education.index', 'label' => 'Education', 'icon' => 'education'],
                ['key' => 'jobs', 'route' => 'admin.jobs.index', 'label' => 'Jobs', 'icon' => 'briefcase'],
                ['key' => 'notifications', 'route' => 'admin.notifications.index', 'label' => 'Notifications', 'icon' => 'bell'],
            ],
            'Localization' => [
                ['key' => 'languages', 'route' => 'admin.languages.index', 'label' => 'Languages', 'icon' => 'languages'],
                ['key' => 'labels', 'route' => 'admin.labels.index', 'label' => 'Labels', 'icon' => 'tag'],
            ],
            'Growth' => [
                ['key' => 'leads', 'route' => 'admin.leads.index', 'label' => 'Leads', 'icon' => 'funnel'],
                ['key' => 'analyses', 'route' => 'admin.analyses.index', 'label' => 'CV analyses', 'icon' => 'bar-chart'],
                ['key' => 'generated-cvs', 'route' => 'admin.generated-cvs.index', 'label' => 'Generated CVs', 'icon' => 'sparkles'],
            ],
        ];
        $adminNavGroups = array_filter(array_map(
            fn (array $items) => array_values(array_filter($items, fn (array $item) => \Illuminate\Support\Facades\Route::has($item['route']))),
            $adminNavGroups,
        ));

        $environment = (string) app()->environment();
        $environmentClass = in_array($environment, ['production', 'staging', 'local', 'testing'], true) ? 'env-'.$environment : 'env-other';
        $adminUser = auth()->user();
        $initials = collect(preg_split('/\s+/', trim((string) ($adminUser?->name ?: $adminUser?->email ?: 'Admin'))))
            ->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
        $publicHost = parse_url((string) config('app.public_url'), PHP_URL_HOST) ?: 'siratie.com';
        $heading = trim($__env->yieldContent('admin_heading', trim($__env->yieldContent('admin_title', 'Admin'))));
        $crumb = trim($__env->yieldContent('admin_eyebrow'));
    @endphp

    <div class="admin-shell">
        <button class="admin-backdrop" type="button" data-admin-menu-close aria-label="Close navigation"></button>

        <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
            <a class="admin-brand" href="{{ route('admin.index') }}">
                <span class="admin-brand-mark">@include('admin.partials.icon', ['name' => 'logo'])</span>
                <span>
                    <strong>Siratie Admin</strong>
                    <small>Application management</small>
                </span>
            </a>

            <nav class="admin-nav" aria-label="Admin sections">
                @foreach ($adminNavGroups as $group => $items)
                    <div class="admin-nav-group">
                        <h2>{{ $group }}</h2>
                        <ul>
                            @foreach ($items as $item)
                                <li>
                                    <a href="{{ route($item['route']) }}" @if ($activeAdminPage === $item['key']) aria-current="page" @endif>
                                        @include('admin.partials.icon', ['name' => $item['icon']])
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            <div class="admin-sidebar-footer">
                <a href="{{ route('landing') }}" target="_blank" rel="noopener">
                    <span>@include('admin.partials.icon', ['name' => 'external']) View site</span>
                    <small>{{ $publicHost }}</small>
                </a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit"><span>@include('admin.partials.icon', ['name' => 'logout']) Logout</span></button>
                </form>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="admin-topbar-title">
                    <button class="icon-button admin-menu-button" type="button" data-admin-menu-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open navigation">
                        @include('admin.partials.icon', ['name' => 'menu', 'class' => 'icon-lg'])
                    </button>
                    <h1>{!! $heading !!}</h1>
                    @if ($crumb !== '')
                        <span class="admin-crumb-sep" aria-hidden="true">/</span>
                        <span class="admin-crumb">{!! $crumb !!}</span>
                    @endif
                    <span class="env-badge {{ $environmentClass }}" title="Application environment">{{ ucfirst($environment) }}</span>
                </div>
                <div class="admin-topbar-tools">
                    @yield('admin_toolbar')
                    @hasSection('admin_actions')
                        @yield('admin_actions')
                    @endif
                    <span class="vr" aria-hidden="true"></span>
                    <span class="admin-avatar" title="{{ $adminUser?->email }}">{{ $initials ?: 'SA' }}</span>
                </div>
            </header>

            @if (session('status') || session('error') || session('jobs_import_error'))
                <div class="toast-stack" aria-live="polite">
                    @if (session('status'))
                        <section class="toast toast-success" data-toast><p>{{ session('status') }}</p></section>
                    @endif
                    @if (session('error'))
                        <section class="toast toast-error" data-toast role="alert"><p>{{ session('error') }}</p></section>
                    @endif
                    @if (session('jobs_import_error'))
                        <section class="toast toast-error" data-toast role="alert"><p>{{ session('jobs_import_error') }}</p></section>
                    @endif
                </div>
            @endif

            <main class="admin-content" id="admin-content">
                @if (session('jobs_import_errors') && count(session('jobs_import_errors')) > 0)
                    <section class="flash-panel">
                        <h3>Job Import Warnings</h3>
                        <ul>
                            @foreach (session('jobs_import_errors') as $error)
                                <li>Row {{ $error['row'] ?? '?' }}: {{ $error['message'] ?? '' }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @hasSection('admin_description')
                    <div class="admin-intro"><p>@yield('admin_description')</p></div>
                @endif

                @yield('admin_content')
            </main>
        </div>
    </div>
@endsection
