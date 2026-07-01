@extends('admin.shell')

@section('admin_title', 'Jobs')
@section('admin_active', 'jobs')
@section('admin_eyebrow', 'Job operations')
@section('admin_heading', 'Jobs')
@section('admin_description', 'Add jobs manually, upload Excel files, sync Google Sheets, and manage what appears in the Flutter jobs tab.')

@section('admin_actions')
    <a class="button button-secondary" href="{{ route('admin.job-news.template') }}">Download template</a>
@endsection

@section('admin_content')
    @php
        $jobFilters = $jobNewsFilters ?? [];
        $jobNewsSummary = $jobNewsSummary ?? ['total' => 0, 'active' => 0, 'expired' => 0, 'future' => 0, 'draft' => 0];
        $jobsSheetLastSync = $jobsSheetLastSync ?? null;
        $jobSourceLabels = [
            'manual' => 'Manual',
            'excel_upload' => 'Excel',
            'google_sheet' => 'Google Sheets',
        ];
        $jobStatusLabels = [
            'active' => 'Active',
            'expired' => 'Expired',
            'future' => 'Future',
            'draft' => 'Draft',
        ];
        $jobFilterUrl = fn (array $overrides = []) => route('admin.jobs.index', array_filter(array_merge($jobFilters, $overrides), fn ($value) => filled($value)));
    @endphp

    <section class="card">
        <h2>Add job</h2>
        <p class="muted">Create a single job listing that appears in the mobile app when it is published and within its validity window.</p>

        <form method="POST" action="{{ route('admin.job-news.store') }}" style="margin-top: 16px;">
            @csrf
            <div class="grid grid-3">
                <label>Language
                    <select name="language" required>
                        <option value="ar">Arabic</option>
                        <option value="en">English</option>
                    </select>
                </label>
                <label>Company<input name="company" placeholder="Company name"></label>
                <label>Location<input name="location" placeholder="Riyadh or Remote"></label>
                <label>Published at<input name="published_at" type="datetime-local"></label>
                <label>Valid from<input name="valid_from" type="date"></label>
                <label>Valid until<input name="valid_until" type="date"></label>
                <label>Sort order<input name="sort_order" type="number" value="0" min="0"></label>
                <label>Source URL<input name="url" type="url" placeholder="https://..."></label>
                <label>Apply URL<input name="apply_url" type="url" placeholder="https://..."></label>
            </div>
            <label>Title<input name="title" required></label>
            <label>Description<textarea name="body" required placeholder="Write the job description, requirements, and application details."></textarea></label>
            <label style="display: flex; gap: 8px; align-items: center;"><input type="checkbox" name="is_published" value="1" checked style="width: auto;"> Published in app</label>
            <button class="button" type="submit">Save job</button>
        </form>
    </section>

    <section class="card">
        <div class="admin-section-header">
            <div>
                <h2>Import jobs</h2>
                <p class="muted">Upload an Excel or CSV file, or sync the configured Google Sheet CSV. Rows with the same job_id update existing jobs instead of creating duplicates.</p>
            </div>
            <a class="button button-secondary" href="{{ route('admin.job-news.template') }}">Download CSV template</a>
        </div>

        <div class="grid grid-2" style="margin-top: 16px;">
            <form method="POST" action="{{ route('admin.job-news.import') }}" enctype="multipart/form-data" data-loading-form>
                @csrf
                <label>Jobs file
                    <input type="file" name="jobs_file" accept=".xlsx,.xls,.csv,.ods" required>
                </label>
                <button class="button" type="submit" data-loading-button><span data-loading-label>Upload jobs</span></button>
            </form>

            <div class="sync-card">
                <p class="muted" style="margin: 0 0 8px;">
                    Current sheet URL:
                    <code>{{ config('services.jobs_sheet.csv_url') ?: 'Not configured' }}</code>
                </p>
                @if ($jobsSheetLastSync)
                    <p>
                        Last sync: {{ \Illuminate\Support\Carbon::parse($jobsSheetLastSync['synced_at'])->diffForHumans() }} ·
                        {{ $jobsSheetLastSync['created'] }} created, {{ $jobsSheetLastSync['updated'] }} updated, {{ $jobsSheetLastSync['skipped'] }} skipped
                    </p>
                    <p class="muted">{{ $jobsSheetLastSync['errors'] }} import warnings were reported during the last sync.</p>
                @else
                    <p>Last sync: Never</p>
                @endif
                <form method="POST" action="{{ route('admin.job-news.sync') }}" data-loading-form>
                    @csrf
                    <button class="button" type="submit" data-loading-button @disabled(! config('services.jobs_sheet.csv_url'))>
                        <span data-loading-label>Sync now</span>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="admin-section-header">
            <div>
                <h2>Job management</h2>
                <p class="muted">Search and filter jobs before they appear in the Flutter app. Current results: {{ $jobNewsItems->total() }}.</p>
            </div>
        </div>

        <div class="metric-row">
            <div class="metric-card"><strong>{{ $jobNewsSummary['total'] }}</strong><span>Total</span></div>
            <div class="metric-card"><strong>{{ $jobNewsSummary['active'] }}</strong><span>Active</span></div>
            <div class="metric-card"><strong>{{ $jobNewsSummary['expired'] }}</strong><span>Expired</span></div>
            <div class="metric-card"><strong>{{ $jobNewsSummary['future'] }}</strong><span>Future</span></div>
            <div class="metric-card"><strong>{{ $jobNewsSummary['draft'] }}</strong><span>Draft</span></div>
        </div>

        <form method="GET" action="{{ route('admin.jobs.index') }}" class="filter-panel">
            <div class="filter-row">
                <label>Search
                    <input name="job_q" value="{{ $jobFilters['job_q'] ?? '' }}" placeholder="Title, company, city, or job_id">
                </label>
                <label>Source
                    <select name="job_source">
                        <option value="">All sources</option>
                        @foreach ($jobSourceLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($jobFilters['job_source'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Status
                    <select name="job_status">
                        <option value="">All statuses</option>
                        @foreach ($jobStatusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($jobFilters['job_status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Language
                    <select name="job_language">
                        <option value="">All languages</option>
                        <option value="ar" @selected(($jobFilters['job_language'] ?? '') === 'ar')>Arabic</option>
                        <option value="en" @selected(($jobFilters['job_language'] ?? '') === 'en')>English</option>
                    </select>
                </label>
            </div>
            <div class="filter-actions">
                <button class="button" type="submit">Apply filters</button>
                <a class="button button-secondary" href="{{ route('admin.jobs.index') }}">Clear</a>
                <a class="chip {{ ($jobFilters['job_status'] ?? '') === 'active' ? 'chip-active' : '' }}" href="{{ $jobFilterUrl(['job_status' => 'active']) }}">Active</a>
                <a class="chip {{ ($jobFilters['job_status'] ?? '') === 'expired' ? 'chip-active' : '' }}" href="{{ $jobFilterUrl(['job_status' => 'expired']) }}">Expired</a>
                <a class="chip {{ ($jobFilters['job_source'] ?? '') === 'google_sheet' ? 'chip-active' : '' }}" href="{{ $jobFilterUrl(['job_source' => 'google_sheet']) }}">Google Sheets</a>
            </div>
        </form>

        @if ($jobNewsItems->count() > 0)
            <form id="jobs-bulk-form" method="POST" action="{{ route('admin.job-news.bulk') }}" onsubmit="return confirm('Apply this action to the selected jobs?')">
                @csrf
                @foreach ($jobFilters as $key => $value)
                    @if (filled($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
            </form>
            <div class="bulk-bar">
                <div>
                    <label style="display:flex; grid-template-columns: none; align-items:center; gap:8px; margin:0;">
                        <input class="table-check" type="checkbox" data-check-all=".job-row-check">
                        Select all
                    </label>
                </div>
                <div>
                    <select name="action" form="jobs-bulk-form" required>
                        <option value="">Choose action</option>
                        <option value="publish">Publish</option>
                        <option value="unpublish">Unpublish</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button class="button button-secondary" type="submit" form="jobs-bulk-form">Apply</button>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead><tr><th></th><th>Language</th><th>Title</th><th>Company</th><th>Location</th><th>Validity</th><th>Source</th><th>Published</th><th>Actions</th></tr></thead>
                    <tbody>
                        @foreach ($jobNewsItems as $item)
                            @php
                                $today = today();
                                if (! $item->is_published) {
                                    $status = 'draft';
                                    $statusClass = 'badge-neutral';
                                } elseif ($item->valid_from && $item->valid_from->isAfter($today)) {
                                    $status = 'future';
                                    $statusClass = 'badge-warning';
                                } elseif ($item->valid_until && $item->valid_until->isBefore($today)) {
                                    $status = 'expired';
                                    $statusClass = 'badge-danger';
                                } else {
                                    $status = 'active';
                                    $statusClass = 'badge-success';
                                }
                                $sourceClass = match ($item->source) {
                                    'google_sheet' => 'badge-info',
                                    'excel_upload' => 'badge-success',
                                    default => 'badge-neutral',
                                };
                            @endphp
                            <tr>
                                <td><input class="table-check job-row-check" type="checkbox" name="ids[]" value="{{ $item->id }}" form="jobs-bulk-form"></td>
                                <td>{{ strtoupper($item->language) }}</td>
                                <td><strong>{{ $item->title }}</strong><br><span class="muted">{{ $item->source_row_key ?: 'No key' }}</span></td>
                                <td>{{ $item->company ?: '-' }}</td>
                                <td>{{ $item->location ?: '-' }}</td>
                                <td><span class="badge {{ $statusClass }}">{{ $jobStatusLabels[$status] }}</span><br><span class="muted">Until: {{ $item->valid_until?->format('Y-m-d') ?: '-' }}</span></td>
                                <td><span class="badge {{ $sourceClass }}">{{ $jobSourceLabels[$item->source] ?? $item->source ?? 'manual' }}</span></td>
                                <td>{{ $item->is_published ? 'Yes' : 'No' }}</td>
                                <td>
                                    <div class="table-actions">
                                        <button class="button button-secondary" type="button" onclick="document.getElementById('job-edit-{{ $item->id }}').showModal()">Edit</button>
                                        <form method="POST" action="{{ route('admin.job-news.destroy', $item) }}" onsubmit="return confirm('Delete this job?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @foreach ($jobNewsItems as $item)
                <dialog class="admin-drawer" id="job-edit-{{ $item->id }}">
                    <div class="drawer-body">
                        <div class="drawer-header">
                            <div>
                                <h3>Edit job</h3>
                                <p class="muted">{{ $item->title }}</p>
                            </div>
                            <button class="drawer-close" type="button" onclick="document.getElementById('job-edit-{{ $item->id }}').close()">×</button>
                        </div>
                        <form method="POST" action="{{ route('admin.job-news.update', $item) }}">
                            @csrf
                            @method('PATCH')
                            @foreach ($jobFilters as $key => $value)
                                @if (filled($value))
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <input type="hidden" name="jobs_page" value="{{ $jobNewsItems->currentPage() }}">
                            <div class="grid grid-2">
                                <label>Language
                                    <select name="language" required>
                                        <option value="ar" @selected($item->language === 'ar')>Arabic</option>
                                        <option value="en" @selected($item->language === 'en')>English</option>
                                    </select>
                                </label>
                                <label>Company<input name="company" value="{{ $item->company }}"></label>
                                <label>Location<input name="location" value="{{ $item->location }}"></label>
                                <label>Published at<input name="published_at" type="datetime-local" value="{{ $item->published_at?->format('Y-m-d\\TH:i') }}"></label>
                                <label>Valid from<input name="valid_from" type="date" value="{{ $item->valid_from?->format('Y-m-d') }}"></label>
                                <label>Valid until<input name="valid_until" type="date" value="{{ $item->valid_until?->format('Y-m-d') }}"></label>
                                <label>Sort order<input name="sort_order" type="number" value="{{ $item->sort_order }}" min="0"></label>
                                <label>Source URL<input name="url" type="url" value="{{ $item->url }}"></label>
                                <label>Apply URL<input name="apply_url" type="url" value="{{ $item->apply_url }}"></label>
                            </div>
                            <label>Title<input name="title" value="{{ $item->title }}" required></label>
                            <label>Description<textarea name="body" required>{{ $item->body }}</textarea></label>
                            <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;">
                                <input class="table-check" type="checkbox" name="is_published" value="1" @checked($item->is_published)> Published in app
                            </label>
                            <div class="filter-actions">
                                <button class="button" type="submit">Save changes</button>
                                <button class="button button-secondary" type="button" onclick="document.getElementById('job-edit-{{ $item->id }}').close()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </dialog>
            @endforeach

            <div class="pagination-wrap">{{ $jobNewsItems->links() }}</div>
        @else
            <div class="empty-state">
                <div class="empty-state-icon">+</div>
                <h3>No matching jobs</h3>
                <p class="muted">Change the filters or import a new jobs file.</p>
                <a class="button" href="{{ route('admin.jobs.index') }}">Reset filters</a>
            </div>
        @endif
    </section>
@endsection
