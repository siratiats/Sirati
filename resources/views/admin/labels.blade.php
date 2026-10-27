@extends('admin.shell')

@section('admin_title', 'Labels')
@section('admin_active', 'labels')
@section('admin_eyebrow', 'Localization')
@section('admin_heading', 'App labels')
@section('admin_description', 'Edit the text the app shows. Edits are saved as drafts and go live when you publish the language.')

@section('admin_actions')
    <a class="button button-secondary" href="{{ route('admin.languages.index') }}">Languages</a>
@endsection

@section('admin_content')
    @include('admin.partials.i18n-messages')

    @if ($target === null)
        <section class="card">
            <p>No languages yet. Run <code>php artisan i18n:import-arb</code> to load the catalog.</p>
        </section>
    @else
        @php
            $targetStat = $stats[$target->code] ?? ['translated' => 0, 'drafts' => 0, 'coverage' => 0];
        @endphp
        <section class="card i18n-labels-summary">
            <div class="admin-section-header">
                <div>
                    <h2>{{ $target->name }} <span class="muted" dir="{{ $target->direction }}">{{ $target->native_name }}</span></h2>
                    <p class="muted">
                        <span class="i18n-coverage">{{ $targetStat['coverage'] }}%</span> published ({{ $targetStat['translated'] }} / {{ $totalKeys }}) ·
                        <span data-draft-count>{{ $targetStat['drafts'] }}</span> draft(s) · live version v{{ $target->version }}
                        @if ($base) · compared with {{ $base->name }} ({{ $base->code }}) @endif
                    </p>
                </div>
                <div class="table-actions">
                    <form method="POST" action="{{ route('admin.languages.publish', $target->code) }}" onsubmit="return confirm('Publish all draft changes for {{ $target->code }}? The app picks them up on its next refresh.')">
                        @csrf
                        <button class="button" type="submit" data-publish-button>Publish {{ $target->code }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.labels.discard', $target->code) }}" onsubmit="return confirm('Discard every draft for {{ $target->code }}?')">
                        @csrf
                        <input type="hidden" name="all" value="1">
                        <button class="button button-secondary" type="submit">Discard all drafts</button>
                    </form>
                </div>
            </div>

            <div class="i18n-language-tabs" role="navigation" aria-label="Target language">
                @foreach ($languages as $language)
                    @php
                        $languageStat = $stats[$language->code] ?? ['coverage' => 0, 'drafts' => 0];
                    @endphp
                    <a class="pill {{ $language->code === $target->code ? 'chip-active' : '' }}" href="{{ route('admin.labels.index', ['lang' => $language->code]) }}">
                        {{ $language->code }} · {{ $languageStat['coverage'] }}%@if ($languageStat['drafts'] > 0) · {{ $languageStat['drafts'] }} draft(s)@endif
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.labels.index') }}" class="filter-panel">
                <input type="hidden" name="lang" value="{{ $target->code }}">
                <div class="filter-row">
                    <label>Search<input name="q" value="{{ $q }}" placeholder="Key or text"></label>
                    <label>Show
                        <select name="filter">
                            <option value="">All labels</option>
                            @foreach ($filters as $value => $label)
                                <option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="filter-actions">
                    <button class="button" type="submit">Apply</button>
                    <a class="button button-secondary" href="{{ route('admin.labels.index', ['lang' => $target->code]) }}">Clear</a>
                </div>
            </form>

            <div class="i18n-transfer">
                <form method="POST" action="{{ route('admin.labels.import', $target->code) }}" enctype="multipart/form-data" class="i18n-transfer__import">
                    @csrf
                    <label>Import ARB or CSV into drafts<input type="file" name="file" accept=".arb,.json,.csv,.txt" required></label>
                    <button class="button button-secondary" type="submit">Import</button>
                </form>
                <div class="i18n-transfer__export">
                    <span class="muted">Export published:</span>
                    <a class="button button-secondary" href="{{ route('admin.labels.export', ['code' => $target->code, 'format' => 'arb']) }}">ARB</a>
                    <a class="button button-secondary" href="{{ route('admin.labels.export', ['code' => $target->code, 'format' => 'csv']) }}">CSV</a>
                </div>
            </div>
        </section>

        <section class="card i18n-labels"
            data-labels-editor
            data-update-url="{{ route('admin.labels.update', $target->code) }}"
            data-bulk-url="{{ route('admin.labels.bulk', $target->code) }}"
            data-discard-url="{{ route('admin.labels.discard', $target->code) }}"
            data-csrf="{{ csrf_token() }}">
            <div class="admin-section-header">
                <div>
                    <h2>Labels</h2>
                    <p class="muted">{{ $labels->total() }} label(s) shown.</p>
                </div>
                <button class="button" type="button" data-save-all disabled>Save all changed</button>
            </div>

            <div class="i18n-labels__head" aria-hidden="true">
                <span>{{ $base ? $base->name.' ('.$base->code.')' : 'Base' }}</span>
                <span>{{ $target->name }} ({{ $target->code }})</span>
            </div>

            @forelse ($labels as $label)
                @php
                    $current = $label->target_draft ?? $label->target_value ?? '';
                    $hasDraft = $label->target_draft !== null;
                    $isMissing = $label->target_value === null || $label->target_value === '';
                @endphp
                <article class="i18n-label {{ $hasDraft ? 'is-draft' : '' }} {{ $isMissing ? 'is-missing' : '' }}" data-label-key="{{ $label->key }}">
                    <header class="i18n-label__meta">
                        <code class="i18n-label__key">{{ $label->key }}</code>
                        <span class="badge badge-neutral i18n-label__group">{{ $label->group }}</span>
                        @if ($label->is_icu)<span class="badge badge-info">plural/select</span>@endif
                        <span class="badge badge-warning i18n-label__draft-badge" data-draft-badge @if (! $hasDraft) hidden @endif>Draft</span>
                        @if ($isMissing)<span class="badge badge-danger">Missing</span>@endif
                        @if ($label->target_updated_by !== null && ! $isMissing)<span class="badge badge-success">Edited</span>@endif
                        @if ($label->description)<p class="muted i18n-label__description">{{ $label->description }}</p>@endif
                        @if ($label->placeholders !== [])
                            <div class="i18n-label__placeholders" aria-label="Placeholders">
                                @foreach ($label->placeholders as $name => $meta)
                                    <button class="pill i18n-placeholder-chip" type="button" data-insert-placeholder="{{ '{'.$name.'}' }}" title="{{ is_array($meta) ? ($meta['type'] ?? 'Object') : 'Object' }}">{{ '{'.$name.'}' }}</button>
                                @endforeach
                            </div>
                        @endif
                    </header>
                    <div class="i18n-label__columns">
                        <div class="i18n-label__base" dir="{{ $base?->direction ?? 'auto' }}">{{ $label->base_value ?? '—' }}</div>
                        <div class="i18n-label__target">
                            <textarea class="i18n-label__input" name="values[{{ $label->key }}]" rows="{{ min(8, max(2, substr_count($current, "\n") + 2)) }}" dir="{{ $target->direction }}" data-original="{{ $current }}" data-published="{{ $label->target_value ?? '' }}" aria-label="{{ $label->key }} in {{ $target->name }}">{{ $current }}</textarea>
                            <p class="field-error i18n-label__error" data-error hidden></p>
                            <div class="i18n-label__actions">
                                <button class="button button-secondary" type="button" data-save>Save draft</button>
                                <button class="button button-secondary" type="button" data-discard @if (! $hasDraft) hidden @endif>Discard draft</button>
                                <span class="muted i18n-label__status" data-status aria-live="polite"></span>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <p class="muted">No labels match.</p>
            @endforelse

            <div class="pagination-wrap">{{ $labels->links() }}</div>
        </section>
    @endif

    <style>
        .i18n-language-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0; }
        .i18n-transfer { display: flex; flex-wrap: wrap; gap: 16px; align-items: end; justify-content: space-between; margin-top: 12px; }
        .i18n-transfer__import, .i18n-transfer__export { display: flex; gap: 8px; align-items: end; flex-wrap: wrap; }
        .i18n-labels { margin-top: 16px; }
        .i18n-labels__head { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; font-weight: 600; padding: 8px 0; }
        .i18n-label { border-top: 1px solid rgba(148,163,184,.3); padding: 14px 0; }
        .i18n-label.is-draft { box-shadow: inset 3px 0 0 #f59e0b; padding-inline-start: 10px; }
        .i18n-label__meta { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 8px; }
        .i18n-label__description { flex-basis: 100%; margin: 2px 0 0; }
        .i18n-label__placeholders { display: flex; flex-wrap: wrap; gap: 6px; flex-basis: 100%; }
        .i18n-placeholder-chip { cursor: pointer; font-family: ui-monospace, monospace; }
        .i18n-label__columns { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .i18n-label__base { white-space: pre-wrap; padding: 8px; border-radius: 8px; background: rgba(148,163,184,.12); }
        .i18n-label__input { width: 100%; }
        .i18n-label__actions { display: flex; gap: 8px; align-items: center; margin-top: 6px; }
        .i18n-label__error { margin: 4px 0 0; }
        @media (max-width: 760px) { .i18n-label__columns, .i18n-labels__head { grid-template-columns: 1fr; } }
    </style>

    <script>
        (() => {
            const editor = document.querySelector('[data-labels-editor]');
            if (!editor) return;
            const csrf = editor.dataset.csrf;
            const saveAll = editor.querySelector('[data-save-all]');
            const draftCount = document.querySelector('[data-draft-count]');

            const request = async (url, body) => {
                const response = await fetch(url, {
                    method: body._method || 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(body),
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));
                return { ok: response.ok, status: response.status, data };
            };

            const parts = (article) => ({
                input: article.querySelector('textarea'),
                error: article.querySelector('[data-error]'),
                status: article.querySelector('[data-status]'),
                badge: article.querySelector('[data-draft-badge]'),
                discard: article.querySelector('[data-discard]'),
            });

            const showError = (article, messages) => {
                const { error } = parts(article);
                error.textContent = (messages || []).join(' ');
                error.hidden = !error.textContent;
            };

            const applyRow = (article, row) => {
                const { input, status, badge, discard } = parts(article);
                const shown = row.draft_value ?? row.value ?? '';
                input.value = shown;
                input.dataset.original = shown;
                input.dataset.published = row.value ?? '';
                badge.hidden = !row.has_draft;
                discard.hidden = !row.has_draft;
                article.classList.toggle('is-draft', row.has_draft);
                status.textContent = row.has_draft ? 'Draft saved' : 'Matches published';
                showError(article, []);
                refreshDirty();
            };

            const dirtyArticles = () => [...editor.querySelectorAll('[data-label-key]')].filter((article) => {
                const input = article.querySelector('textarea');
                return input.value !== input.dataset.original;
            });

            const refreshDirty = () => { saveAll.disabled = dirtyArticles().length === 0; };

            const bumpDraftCount = (delta) => {
                if (!draftCount) return;
                draftCount.textContent = Math.max(0, (parseInt(draftCount.textContent, 10) || 0) + delta);
            };

            editor.addEventListener('input', (event) => {
                if (event.target.matches('textarea')) {
                    const article = event.target.closest('[data-label-key]');
                    parts(article).status.textContent = event.target.value !== event.target.dataset.original ? 'Unsaved' : '';
                    refreshDirty();
                }
            });

            editor.addEventListener('click', async (event) => {
                const article = event.target.closest('[data-label-key]');
                if (!article) {
                    if (event.target.closest('[data-save-all]')) await saveAllChanged();
                    return;
                }
                const key = article.dataset.labelKey;
                const { input, status } = parts(article);

                const chip = event.target.closest('[data-insert-placeholder]');
                if (chip) {
                    const text = chip.dataset.insertPlaceholder;
                    const start = input.selectionStart ?? input.value.length;
                    const end = input.selectionEnd ?? input.value.length;
                    input.setRangeText(text, start, end, 'end');
                    input.focus();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    return;
                }

                if (event.target.closest('[data-save]')) {
                    const hadDraft = !parts(article).badge.hidden;
                    status.textContent = 'Saving…';
                    const result = await request(editor.dataset.updateUrl, { _method: 'PUT', key, value: input.value });
                    if (result.ok) {
                        applyRow(article, result.data);
                        bumpDraftCount((result.data.has_draft ? 1 : 0) - (hadDraft ? 1 : 0));
                    } else {
                        status.textContent = 'Not saved';
                        showError(article, result.data.errors?.value || result.data.errors?.key || [result.data.message || 'Could not save.']);
                    }
                    return;
                }

                if (event.target.closest('[data-discard]')) {
                    status.textContent = 'Discarding…';
                    const result = await request(editor.dataset.discardUrl, { key });
                    if (result.ok && result.data.data) {
                        applyRow(article, result.data.data);
                        bumpDraftCount(-1);
                    } else {
                        status.textContent = 'Could not discard';
                    }
                }
            });

            const saveAllChanged = async () => {
                const articles = dirtyArticles();
                if (articles.length === 0) return;
                const values = {};
                articles.forEach((article) => { values[article.dataset.labelKey] = article.querySelector('textarea').value; });
                saveAll.disabled = true;
                const result = await request(editor.dataset.bulkUrl, { values });
                if (result.ok) {
                    articles.forEach((article) => {
                        const hadDraft = !parts(article).badge.hidden;
                        const row = result.data.data[article.dataset.labelKey];
                        if (row) { applyRow(article, row); bumpDraftCount((row.has_draft ? 1 : 0) - (hadDraft ? 1 : 0)); }
                    });
                } else {
                    articles.forEach((article) => showError(article, result.data.errors?.[article.dataset.labelKey] || []));
                    alert(result.data.message || 'Nothing was saved.');
                }
                refreshDirty();
            };
        })();
    </script>
@endsection
