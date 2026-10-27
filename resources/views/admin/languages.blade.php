@extends('admin.shell')

@section('admin_title', 'Languages')
@section('admin_active', 'languages')
@section('admin_eyebrow', 'Localization')
@section('admin_heading', 'App languages')
@section('admin_description', 'Choose which languages the app offers, their direction and fallback, and publish or roll back their labels.')

@section('admin_actions')
    <a class="button" href="{{ route('admin.labels.index') }}">Edit labels</a>
    <a class="button button-secondary" href="{{ route('admin.index') }}">Back to overview</a>
@endsection

@section('admin_content')
    @include('admin.partials.i18n-messages')

    <section class="card i18n-languages">
        <div class="admin-section-header">
            <div>
                <h2>Languages</h2>
                <p class="muted">{{ $languages->count() }} languages · {{ $totalKeys }} labels in the catalog. Changes go live only when a language is published.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="i18n-languages__table">
                <thead>
                    <tr><th>Language</th><th>Direction</th><th>Fallback</th><th>Status</th><th>Coverage</th><th>Drafts</th><th>Version</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($languages as $language)
                        @php
                            $stat = $stats[$language->code] ?? ['translated' => 0, 'drafts' => 0, 'coverage' => 0];
                        @endphp
                        <tr class="i18n-language-row" data-language="{{ $language->code }}">
                            <td>
                                <strong>{{ $language->name }}</strong>
                                <span class="muted" dir="{{ $language->direction }}">{{ $language->native_name }}</span><br>
                                <code>{{ $language->code }}</code> · sort {{ $language->sort_order }}
                            </td>
                            <td>{{ strtoupper($language->direction) }}</td>
                            <td>{{ $language->effectiveFallback() ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $language->is_enabled ? 'badge-success' : 'badge-neutral' }}">{{ $language->is_enabled ? 'Enabled' : 'Disabled' }}</span>
                                @if ($language->is_default)
                                    <span class="badge badge-info">Default</span>
                                @endif
                            </td>
                            <td>
                                <span class="i18n-coverage" title="{{ $stat['translated'] }} of {{ $totalKeys }} labels published">{{ $stat['coverage'] }}%</span>
                            </td>
                            <td>
                                @if ($stat['drafts'] > 0)
                                    <span class="badge badge-warning">{{ $stat['drafts'] }} draft{{ $stat['drafts'] === 1 ? '' : 's' }}</span>
                                @else
                                    <span class="muted">0</span>
                                @endif
                            </td>
                            <td>
                                v{{ $language->version }}
                                @if ($language->published_at)
                                    <br><span class="muted">{{ $language->published_at->format('Y-m-d H:i') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a class="button button-secondary" href="{{ route('admin.labels.index', ['lang' => $language->code]) }}">Labels</a>
                                    <button class="button button-secondary" type="button" onclick="document.getElementById('language-edit-{{ $language->code }}').showModal()">Edit</button>
                                    <form method="POST" action="{{ route('admin.languages.publish', $language->code) }}" onsubmit="return confirm('Publish {{ $stat['drafts'] }} draft change(s) for {{ $language->code }}? The app picks them up on its next refresh.')">
                                        @csrf
                                        <button class="button" type="submit" @disabled($stat['drafts'] === 0)>Publish</button>
                                    </form>
                                    @unless ($language->is_default)
                                        <form method="POST" action="{{ route('admin.languages.destroy', $language->code) }}" onsubmit="return confirm('Delete {{ $language->code }} and all of its labels? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button-danger" type="submit">Delete</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No languages yet. Run <code>php artisan i18n:import-arb</code> or add one below.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($languages as $language)
        <dialog class="admin-drawer i18n-language-dialog" id="language-edit-{{ $language->code }}">
            <div class="drawer-body">
                <div class="admin-section-header">
                    <div>
                        <h2>Edit {{ $language->name }} ({{ $language->code }})</h2>
                        <p class="muted">Changing direction or fallback publishes a new version.</p>
                    </div>
                    <button class="drawer-close" type="button" onclick="document.getElementById('language-edit-{{ $language->code }}').close()">Close</button>
                </div>

                <form method="POST" action="{{ route('admin.languages.update', $language->code) }}" class="i18n-language-form">
                    @csrf
                    @method('PATCH')
                    <div class="grid grid-3">
                        <label>Name<input name="name" value="{{ $language->name }}" required maxlength="80"></label>
                        <label>Native name<input name="native_name" value="{{ $language->native_name }}" required maxlength="80" dir="{{ $language->direction }}"></label>
                        <label>Direction
                            <select name="direction">
                                @foreach (\App\Models\AppLanguage::DIRECTIONS as $direction)
                                    <option value="{{ $direction }}" @selected($language->direction === $direction)>{{ strtoupper($direction) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Fallback
                            <select name="fallback_code">
                                <option value="">None (bundled strings only)</option>
                                @foreach ($languages as $other)
                                    @continue($other->code === $language->code)
                                    <option value="{{ $other->code }}" @selected($language->fallback_code === $other->code)>{{ $other->name }} ({{ $other->code }})</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Sort order<input name="sort_order" type="number" min="0" max="10000" value="{{ $language->sort_order }}"></label>
                    </div>
                    <div class="filter-actions">
                        <input type="hidden" name="is_enabled" value="0">
                        <label class="i18n-check"><input class="table-check" type="checkbox" name="is_enabled" value="1" @checked($language->is_enabled) @disabled($language->is_default)> Enabled in app</label>
                        @if ($language->is_default)
                            <input type="hidden" name="is_enabled" value="1">
                            <input type="hidden" name="is_default" value="1">
                            <span class="badge badge-info">Default language</span>
                        @else
                            <input type="hidden" name="is_default" value="0">
                            <label class="i18n-check"><input class="table-check" type="checkbox" name="is_default" value="1"> Make default</label>
                        @endif
                    </div>
                    <button class="button" type="submit">Save language</button>
                </form>

                <div class="i18n-revisions">
                    <h3>Published versions</h3>
                    @php
                        $languageRevisions = $revisions[$language->code] ?? collect();
                    @endphp
                    @if ($languageRevisions->isEmpty())
                        <p class="muted">Nothing published yet.</p>
                    @else
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Version</th><th>Published</th><th>By</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($languageRevisions as $revision)
                                        <tr>
                                            <td>v{{ $revision->version }} @if ($revision->version === $language->version)<span class="badge badge-success">Live</span>@endif</td>
                                            <td>{{ $revision->created_at?->format('Y-m-d H:i') }}</td>
                                            <td>{{ $revision->publisher?->email ?? 'ARB import' }}</td>
                                            <td>
                                                @if ($revision->version !== $language->version)
                                                    <form method="POST" action="{{ route('admin.languages.rollback', $language->code) }}" onsubmit="return confirm('Republish version {{ $revision->version }} of {{ $language->code }} as a new version?')">
                                                        @csrf
                                                        <input type="hidden" name="revision" value="{{ $revision->id }}">
                                                        <button class="button button-secondary" type="submit">Roll back to this</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </dialog>
    @endforeach

    <section class="card i18n-language-create">
        <div class="admin-section-header">
            <div>
                <h2>Add a language</h2>
                <p class="muted">A new language starts empty: the app shows its fallback language until labels are translated and published.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.languages.store') }}">
            @csrf
            <div class="grid grid-3">
                <label>Code<input name="code" value="{{ old('code') }}" required maxlength="16" placeholder="fr" pattern="[a-z]{2,3}([_\-][A-Za-z0-9]{2,8})?"></label>
                <label>Name<input name="name" value="{{ old('name') }}" required maxlength="80" placeholder="French"></label>
                <label>Native name<input name="native_name" value="{{ old('native_name') }}" required maxlength="80" placeholder="Français"></label>
                <label>Direction
                    <select name="direction">
                        <option value="ltr">LTR</option>
                        <option value="rtl">RTL</option>
                    </select>
                </label>
                <label>Fallback
                    <select name="fallback_code">
                        <option value="">None</option>
                        @foreach ($languages as $other)
                            <option value="{{ $other->code }}" @selected($other->code === 'en')>{{ $other->name }} ({{ $other->code }})</option>
                        @endforeach
                    </select>
                </label>
                <label>Sort order<input name="sort_order" type="number" min="0" max="10000" value="{{ old('sort_order', $languages->count()) }}"></label>
            </div>
            <div class="filter-actions">
                <label class="i18n-check"><input class="table-check" type="checkbox" name="is_enabled" value="1"> Enabled in app</label>
            </div>
            <button class="button" type="submit">Add language</button>
        </form>
    </section>

    <style>
        .i18n-messages { margin-bottom: 16px; }
        .i18n-messages__list { margin: 0; padding-inline-start: 18px; }
        .i18n-check { display: flex; gap: 8px; align-items: center; }
        .i18n-revisions { margin-top: 20px; }
        .i18n-language-create { margin-top: 16px; }
        .i18n-coverage { font-variant-numeric: tabular-nums; font-weight: 600; }
    </style>
@endsection
