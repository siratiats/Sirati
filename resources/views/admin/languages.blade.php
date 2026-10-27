@extends('admin.shell')

@section('admin_title', 'Languages')
@section('admin_active', 'languages')
@section('admin_eyebrow', 'Localization')
@section('admin_heading', 'Languages')
@section('admin_description', 'Languages shown in the app. Label changes go live only when a language is published; no app release is needed.')

@section('admin_actions')
    <a class="button button-secondary" href="{{ route('admin.labels.index') }}">@include('admin.partials.icon', ['name' => 'tag'])Edit labels</a>
    <button class="button button-teal" type="button" data-open-dialog="language-create">+ Add language</button>
@endsection

@section('admin_content')
    @include('admin.partials.i18n-messages')

    @php
        $enabledCount = $languages->where('is_enabled', true)->count();
        $pendingCount = collect($stats)->filter(fn ($stat) => $stat['drafts'] > 0)->count();
        $historyCode = $languages->firstWhere('code', (string) request()->query('history'))?->code
            ?? $languages->firstWhere('is_default', true)?->code
            ?? $languages->first()?->code;
        $ago = fn ($time) => $time ? $time->locale('en')->diffForHumans(null, true, true).' ago' : null;
    @endphp

    <section class="card card-flush l10n-languages" aria-labelledby="languages-title">
        <div class="card-head l10n-card-head">
            <div>
                <h2 id="languages-title">@include('admin.partials.icon', ['name' => 'languages'])Languages <span class="badge badge-neutral">{{ $languages->count() }} configured</span></h2>
                <p>{{ number_format($totalKeys) }} labels in the catalog · {{ $enabledCount }} enabled in the app</p>
            </div>
            @if ($languages->isNotEmpty())
                <div class="segmented l10n-segmented" role="group" aria-label="Show languages" data-language-filter>
                    <button type="button" aria-pressed="true" data-filter-value="all">All</button>
                    <button type="button" aria-pressed="false" data-filter-value="enabled">Enabled <span class="num">{{ $enabledCount }}</span></button>
                    <button type="button" aria-pressed="false" data-filter-value="drafts">Drafts pending <span class="num">{{ $pendingCount }}</span></button>
                </div>
            @endif
        </div>

        <div class="table-wrap flush">
            <table class="l10n-languages-table">
                <thead>
                    <tr>
                        <th class="l10n-star-cell"><span class="sr-only">Default</span></th>
                        <th>Language</th>
                        <th>Direction</th>
                        <th>Fallback</th>
                        <th>Coverage</th>
                        <th>Drafts</th>
                        <th>Live version</th>
                        <th>Enabled</th>
                        <th class="l10n-actions-head">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($languages as $language)
                        @php
                            $stat = $stats[$language->code] ?? ['translated' => 0, 'drafts' => 0, 'coverage' => 0];
                            $fallback = $language->effectiveFallback();
                            $fallbackLanguage = $fallback ? $languages->firstWhere('code', $fallback) : null;
                        @endphp
                        <tr class="i18n-language-row" data-language="{{ $language->code }}" data-enabled="{{ $language->is_enabled ? '1' : '0' }}" data-drafts="{{ $stat['drafts'] }}" @if ($language->is_default) aria-current="true" @endif>
                            <td class="l10n-star-cell">
                                @if ($language->is_default)
                                    <span class="l10n-star is-default" title="Default language" aria-label="Default language">★</span>
                                @else
                                    <form method="POST" action="{{ route('admin.languages.update', $language->code) }}" data-confirm="Make {{ $language->name }} ({{ $language->code }}) the default language?{{ $language->is_enabled ? '' : ' It will also be enabled.' }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_default" value="1">
                                        <input type="hidden" name="is_enabled" value="1">
                                        <button class="l10n-star" type="submit" title="Make default" aria-label="Make {{ $language->name }} the default language">☆</button>
                                    </form>
                                @endif
                            </td>
                            <td>
                                <div class="l10n-lang">
                                    <span class="l10n-code">{{ $language->code }}</span>
                                    <span>
                                        <span class="cell-strong">{{ $language->name }}</span>
                                        @if ($language->is_default)<span class="badge badge-warning badge-square">Default</span>@endif
                                        <span class="cell-sub" dir="{{ $language->direction }}" lang="{{ $language->code }}">{{ $language->native_name }}</span>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-neutral badge-square l10n-dir">{{ $language->direction === 'rtl' ? '← RTL' : '→ LTR' }}</span>
                            </td>
                            <td class="td-mono">
                                @if ($fallbackLanguage)
                                    {{ $fallbackLanguage->name }} ({{ $fallbackLanguage->code }})
                                @else
                                    {{ $fallback ?? '—' }}
                                @endif
                            </td>
                            <td>
                                <div class="l10n-coverage" title="{{ $stat['translated'] }} of {{ $totalKeys }} labels published">
                                    <div class="l10n-coverage__head">
                                        <span class="i18n-coverage">{{ $stat['coverage'] }}%</span>
                                        <span class="muted">{{ number_format($stat['translated']) }} / {{ number_format($totalKeys) }}</span>
                                    </div>
                                    <div class="bar-track"><span @class(['bar-fill', 'amber' => $stat['coverage'] < 90]) style="width: {{ max(0, min(100, $stat['coverage'])) }}%"></span></div>
                                </div>
                            </td>
                            <td>
                                @if ($stat['drafts'] > 0)
                                    <span class="badge badge-warning badge-dot">{{ $stat['drafts'] }} draft{{ $stat['drafts'] === 1 ? '' : 's' }}</span>
                                @else
                                    <span class="badge badge-neutral">0 drafts</span>
                                @endif
                            </td>
                            <td class="l10n-version">
                                @if ($language->version > 0)
                                    <span class="cell-strong">v{{ $language->version }}</span> <span class="badge badge-success">Live</span>
                                    @if ($language->published_at)
                                        <span class="cell-sub" title="{{ $language->published_at->format('Y-m-d H:i') }}">{{ $ago($language->published_at) }}</span>
                                    @endif
                                @else
                                    <span class="muted">Not published</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.languages.update', $language->code) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_enabled" value="{{ $language->is_enabled ? 0 : 1 }}">
                                    <button class="l10n-switch" type="submit" role="switch" aria-checked="{{ $language->is_enabled ? 'true' : 'false' }}" aria-label="{{ $language->name }} enabled in app" @disabled($language->is_default) @if ($language->is_default) title="The default language must stay enabled" @endif></button>
                                </form>
                            </td>
                            <td>
                                <div class="table-actions l10n-row-actions">
                                    <a class="button button-secondary button-sm" href="{{ route('admin.labels.index', ['lang' => $language->code]) }}">Edit labels</a>
                                    <form method="POST" action="{{ route('admin.languages.publish', $language->code) }}" onsubmit="return confirm('Publish {{ $stat['drafts'] }} draft change(s) for {{ $language->code }}? The app picks them up on its next refresh.')">
                                        @csrf
                                        <button class="button button-teal button-sm" type="submit" @disabled($stat['drafts'] === 0)>Publish</button>
                                    </form>
                                    <a class="button button-secondary button-sm" href="{{ route('admin.languages.index', ['history' => $language->code]) }}#publish-history" @if ($historyCode === $language->code) aria-current="true" @endif>History</a>
                                    <button class="icon-button" type="button" data-open-dialog="language-edit-{{ $language->code }}" title="Language settings" aria-label="{{ $language->name }} settings">@include('admin.partials.icon', ['name' => 'sliders'])</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9">No languages yet. Run <code>php artisan i18n:import-arb</code> or add one.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="l10n-filter-empty muted" data-filter-empty hidden>No languages match this filter.</p>
    </section>

    @if ($historyCode !== null)
        @php
            $historyLanguage = $languages->firstWhere('code', $historyCode);
            $languageRevisions = $revisions[$historyCode] ?? collect();
        @endphp
        <section class="card card-flush l10n-history" id="publish-history" aria-labelledby="history-title">
            <div class="card-head">
                <div>
                    <h2 id="history-title">@include('admin.partials.icon', ['name' => 'refresh'])Publish history — {{ $historyLanguage->name }} ({{ $historyLanguage->code }})
                        @if ($historyLanguage->version > 0)<span class="badge badge-success">v{{ $historyLanguage->version }} Live</span>@endif
                    </h2>
                    <p>Rolling back republishes an older snapshot as a new version.</p>
                </div>
                <span class="card-meta">Showing {{ $languageRevisions->count() }} recorded publish{{ $languageRevisions->count() === 1 ? '' : 'es' }}</span>
            </div>
            @if ($languageRevisions->isEmpty())
                <p class="card-note">Nothing published yet for {{ $historyLanguage->code }}.</p>
            @else
                <div class="table-wrap flush">
                    <table>
                        <thead><tr><th>Version</th><th>Published</th><th>By</th><th class="num">Status / action</th></tr></thead>
                        <tbody>
                            @foreach ($languageRevisions as $revision)
                                <tr>
                                    <td class="cell-strong l10n-version-cell">v{{ $revision->version }}</td>
                                    <td class="td-mono" title="{{ $revision->created_at?->format('Y-m-d H:i') }}">{{ $revision->created_at?->format('Y-m-d H:i') }} · {{ $ago($revision->created_at) }}</td>
                                    <td class="td-mono">{{ $revision->publisher?->email ?? 'ARB import' }}</td>
                                    <td class="num">
                                        @if ($revision->version === $historyLanguage->version)
                                            <span class="badge badge-success badge-dot">Current live</span>
                                        @else
                                            <form method="POST" action="{{ route('admin.languages.rollback', $historyLanguage->code) }}" onsubmit="return confirm('Republish version {{ $revision->version }} of {{ $historyLanguage->code }} as a new version?')">
                                                @csrf
                                                <input type="hidden" name="revision" value="{{ $revision->id }}">
                                                <button class="button button-secondary button-sm" type="submit">Rollback</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- Edit drawers --}}
    @foreach ($languages as $language)
        <dialog class="admin-drawer l10n-drawer i18n-language-dialog" id="language-edit-{{ $language->code }}" aria-labelledby="language-edit-{{ $language->code }}-title">
            <form method="POST" action="{{ route('admin.languages.update', $language->code) }}" class="l10n-drawer__form i18n-language-form">
                @csrf
                @method('PATCH')
                <div class="l10n-drawer__head">
                    <div>
                        <h2 id="language-edit-{{ $language->code }}-title">Edit {{ $language->name }} <span class="l10n-code">{{ $language->code }}</span></h2>
                        <p class="muted">Changing direction or fallback publishes a new version.</p>
                    </div>
                    <button class="icon-button" type="button" data-close-dialog aria-label="Close">✕</button>
                </div>
                <div class="l10n-drawer__body">
                    <label>English name<input name="name" value="{{ $language->name }}" required maxlength="80"></label>
                    <label>Native name<input name="native_name" value="{{ $language->native_name }}" required maxlength="80" dir="{{ $language->direction }}" lang="{{ $language->code }}"></label>
                    <fieldset class="l10n-fieldset">
                        <legend>Text direction</legend>
                        <div class="l10n-seg">
                            @foreach (\App\Models\AppLanguage::DIRECTIONS as $direction)
                                <label><input type="radio" name="direction" value="{{ $direction }}" @checked($language->direction === $direction)><span>{{ $direction === 'rtl' ? '← RTL (right-to-left)' : '→ LTR (left-to-right)' }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label>Fallback language
                        <select name="fallback_code">
                            <option value="">None (bundled strings only)</option>
                            @foreach ($languages as $other)
                                @continue($other->code === $language->code)
                                <option value="{{ $other->code }}" @selected($language->fallback_code === $other->code)>{{ $other->name }} ({{ $other->code }})</option>
                            @endforeach
                        </select>
                        <small class="muted l10n-help">Labels without a published translation fall back to this language.</small>
                    </label>
                    <label>Sort order<input name="sort_order" type="number" min="0" max="10000" value="{{ $language->sort_order }}"></label>
                    <input type="hidden" name="is_enabled" value="0">
                    <label class="l10n-switch-row">
                        <span><strong>Enabled in app</strong><small class="muted">@if ($language->is_default)The default language must stay enabled.@else Users can pick it once enabled.@endif</small></span>
                        <input class="l10n-switch-input" type="checkbox" name="is_enabled" value="1" role="switch" @checked($language->is_enabled) @disabled($language->is_default)>
                    </label>
                    @if ($language->is_default)
                        <input type="hidden" name="is_enabled" value="1">
                        <input type="hidden" name="is_default" value="1">
                        <p><span class="badge badge-warning badge-square">Default language</span></p>
                    @else
                        <input type="hidden" name="is_default" value="0">
                        <label class="l10n-switch-row">
                            <span><strong>Make default</strong><small class="muted">The app starts in this language.</small></span>
                            <input class="l10n-switch-input" type="checkbox" name="is_default" value="1" role="switch">
                        </label>
                    @endif
                </div>
                <div class="l10n-drawer__foot">
                    @unless ($language->is_default)
                        <button class="button button-danger l10n-drawer__danger" type="submit" form="language-delete-{{ $language->code }}">Delete language</button>
                    @endunless
                    <button class="button button-secondary" type="button" data-close-dialog>Cancel</button>
                    <button class="button button-teal" type="submit">Save language</button>
                </div>
            </form>
        </dialog>
        @unless ($language->is_default)
            <form id="language-delete-{{ $language->code }}" method="POST" action="{{ route('admin.languages.destroy', $language->code) }}" onsubmit="return confirm('Delete {{ $language->code }} and all of its labels? This cannot be undone.')" hidden>
                @csrf
                @method('DELETE')
            </form>
        @endunless
    @endforeach

    {{-- Add-language drawer --}}
    <dialog class="admin-drawer l10n-drawer i18n-language-create" id="language-create" aria-labelledby="language-create-title" @if (old('code') !== null) data-open-on-load @endif>
        <form method="POST" action="{{ route('admin.languages.store') }}" class="l10n-drawer__form">
            @csrf
            <div class="l10n-drawer__head">
                <div>
                    <h2 id="language-create-title">+ Add language</h2>
                    <p class="muted">Configure a new language for the app.</p>
                </div>
                <button class="icon-button" type="button" data-close-dialog aria-label="Close">✕</button>
            </div>
            <div class="l10n-drawer__body">
                <label>Language code
                    <input name="code" value="{{ old('code') }}" required maxlength="16" placeholder="e.g. fr, de, pt_BR" pattern="[a-z]{2,3}([_\-][A-Za-z0-9]{2,8})?" class="mono" @error('code') aria-invalid="true" @enderror>
                    <small class="muted l10n-help">2–3 lowercase letters, optional region (fr, pt_BR).</small>
                    @error('code')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>English name
                    <input name="name" value="{{ old('name') }}" required maxlength="80" placeholder="e.g. French" @error('name') aria-invalid="true" @enderror>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Native name
                    <input name="native_name" value="{{ old('native_name') }}" required maxlength="80" placeholder="e.g. Français" @error('native_name') aria-invalid="true" @enderror>
                    @error('native_name')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <fieldset class="l10n-fieldset">
                    <legend>Text direction</legend>
                    <div class="l10n-seg">
                        <label><input type="radio" name="direction" value="ltr" @checked(old('direction', 'ltr') === 'ltr')><span>→ LTR (left-to-right)</span></label>
                        <label><input type="radio" name="direction" value="rtl" @checked(old('direction') === 'rtl')><span>← RTL (right-to-left)</span></label>
                    </div>
                </fieldset>
                <label>Fallback language
                    <select name="fallback_code">
                        <option value="">None</option>
                        @foreach ($languages as $other)
                            <option value="{{ $other->code }}" @selected(old('fallback_code', 'en') === $other->code)>{{ $other->name }} ({{ $other->code }})</option>
                        @endforeach
                    </select>
                    <small class="muted l10n-help">Labels without a translation fall back to this language.</small>
                    @error('fallback_code')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Sort order<input name="sort_order" type="number" min="0" max="10000" value="{{ old('sort_order', $languages->count()) }}"></label>
                <label class="l10n-switch-row">
                    <span><strong>Enable language immediately</strong><small class="muted">Users can pick it in the app once saved.</small></span>
                    <input class="l10n-switch-input" type="checkbox" name="is_enabled" value="1" role="switch" @checked(old('is_enabled'))>
                </label>
                <p class="l10n-note">A new language starts empty: the app shows its fallback language until labels are translated and published.</p>
            </div>
            <div class="l10n-drawer__foot">
                <button class="button button-secondary" type="button" data-close-dialog>Cancel</button>
                <button class="button button-teal" type="submit">Save language</button>
            </div>
        </form>
    </dialog>

    <script>
        (() => {
            document.addEventListener('click', (event) => {
                const opener = event.target.closest('[data-open-dialog]');
                if (opener) {
                    opener.closest('details')?.removeAttribute('open');
                    document.getElementById(opener.dataset.openDialog)?.showModal();
                    return;
                }
                if (event.target.closest('[data-close-dialog]')) {
                    event.target.closest('dialog')?.close();
                    return;
                }
                if (event.target.matches('dialog.l10n-drawer')) event.target.close();
                document.querySelectorAll('details.l10n-menu[open]').forEach((menu) => { if (!menu.contains(event.target)) menu.removeAttribute('open'); });
            });
            document.querySelectorAll('dialog[data-open-on-load]').forEach((dialog) => dialog.showModal());

            const filter = document.querySelector('[data-language-filter]');
            const empty = document.querySelector('[data-filter-empty]');
            filter?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-filter-value]');
                if (!button) return;
                filter.querySelectorAll('[data-filter-value]').forEach((b) => b.setAttribute('aria-pressed', b === button ? 'true' : 'false'));
                let shown = 0;
                document.querySelectorAll('.i18n-language-row').forEach((row) => {
                    const value = button.dataset.filterValue;
                    const visible = value === 'all' || (value === 'enabled' && row.dataset.enabled === '1') || (value === 'drafts' && Number(row.dataset.drafts) > 0);
                    row.hidden = !visible;
                    if (visible) shown += 1;
                });
                if (empty) empty.hidden = shown > 0;
            });
        })();
    </script>
@endsection
