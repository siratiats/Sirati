@extends('admin.shell')

@section('admin_title', 'Overview')
@section('admin_active', 'overview')
@section('admin_eyebrow', 'Operations dashboard')
@section('admin_heading', 'Admin overview')
@section('admin_description', 'Track application activity and jump into the section you need without scrolling through one long page.')

@section('admin_actions')
    <a class="button button-secondary" href="{{ route('generated-cvs.create') }}">Create CV</a>
    <a class="button" href="{{ route('admin.jobs.index') }}">Manage jobs</a>
@endsection

@section('admin_content')
    <section class="admin-kpi-grid" aria-label="Dashboard metrics">
        <article class="admin-kpi-card">
            <span>Landing leads</span>
            <strong>{{ $stats['landing_leads'] }}</strong>
            <p class="muted">People who submitted the public landing form.</p>
        </article>
        <article class="admin-kpi-card">
            <span>CV analyses</span>
            <strong>{{ $stats['analyses'] }}</strong>
            <p class="muted">Average score: {{ $stats['average_analysis_score'] }}</p>
        </article>
        <article class="admin-kpi-card">
            <span>Generated CVs</span>
            <strong>{{ $stats['generated_cvs'] }}</strong>
            <p class="muted">Average score: {{ $stats['average_generated_score'] }}</p>
        </article>
    </section>

    <section class="card">
        <div class="admin-section-header">
            <div>
                <h2>Work areas</h2>
                <p class="muted">Each admin workflow now has its own page so the panel is easier to track and maintain.</p>
            </div>
        </div>
        <div class="admin-work-grid">
            <a class="admin-work-card" href="{{ route('admin.cv-templates.index') }}">
                <span class="admin-work-icon">CV</span>
                <h3>CV Templates</h3>
                <p class="muted">Manage mobile export template metadata, preview images, defaults, and active status.</p>
            </a>
            <a class="admin-work-card" href="{{ route('admin.education.index') }}">
                <span class="admin-work-icon">ED</span>
                <h3>Education Content</h3>
                <p class="muted">Create and review learning cards, certificates, and news shown in the Flutter app.</p>
            </a>
            <a class="admin-work-card" href="{{ route('admin.jobs.index') }}">
                <span class="admin-work-icon">JB</span>
                <h3>Jobs</h3>
                <p class="muted">Add jobs, import Excel files, sync Google Sheets, and manage publishing windows.</p>
            </a>
            <a class="admin-work-card" href="{{ route('admin.leads.index') }}">
                <span class="admin-work-icon">LD</span>
                <h3>Leads</h3>
                <p class="muted">Review landing form submissions and user interest signals.</p>
            </a>
            <a class="admin-work-card" href="{{ route('admin.analyses.index') }}">
                <span class="admin-work-icon">AN</span>
                <h3>CV Analyses</h3>
                <p class="muted">Inspect recent ATS analyses, scores, matching percentages, and processing status.</p>
            </a>
            <a class="admin-work-card" href="{{ route('admin.generated-cvs.index') }}">
                <span class="admin-work-icon">GC</span>
                <h3>Generated CVs</h3>
                <p class="muted">Review generated CV records and download their PDF exports.</p>
            </a>
        </div>
    </section>

    <section class="card">
        <h2>Jobs sync status</h2>
        <div class="metric-row">
            <div class="metric-card"><strong>{{ $jobNewsSummary['total'] }}</strong><span>Total jobs</span></div>
            <div class="metric-card"><strong>{{ $jobNewsSummary['active'] }}</strong><span>Active</span></div>
            <div class="metric-card"><strong>{{ $jobNewsSummary['expired'] }}</strong><span>Expired</span></div>
            <div class="metric-card"><strong>{{ $jobNewsSummary['future'] }}</strong><span>Future</span></div>
            <div class="metric-card"><strong>{{ $jobNewsSummary['draft'] }}</strong><span>Draft</span></div>
        </div>
        <p class="muted" style="margin-top: 14px;">
            Last sheet sync:
            @if ($jobsSheetLastSync)
                {{ \Illuminate\Support\Carbon::parse($jobsSheetLastSync['synced_at'])->diffForHumans() }} ·
                {{ $jobsSheetLastSync['created'] }} created, {{ $jobsSheetLastSync['updated'] }} updated, {{ $jobsSheetLastSync['skipped'] }} skipped
            @else
                Never
            @endif
        </p>
    </section>
@endsection
