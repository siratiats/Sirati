@extends('admin.shell')

@section('admin_title', 'Labels')
@section('admin_active', 'labels')
@section('admin_eyebrow', 'Localization')
@section('admin_heading', 'Labels editor')
@section('admin_description', 'Edit the text the app shows. Edits are saved as drafts and go live when you publish the language.')

@section('admin_actions')
    <a class="button button-secondary" href="{{ route('admin.languages.index') }}">@include('admin.partials.icon', ['name' => 'languages'])Languages</a>
@endsection

@section('admin_content')
    @include('admin.partials.i18n-messages')

    @if ($target === null)
        <section class="card">
            <div class="empty-state">
                <h3>No languages yet</h3>
                <p>Run <code>php artisan i18n:import-arb</code> to load the catalog.</p>
            </div>
        </section>
    @else
        @php
            $targetStat = $stats[$target->code] ?? ['translated' => 0, 'drafts' => 0, 'coverage' => 0];
            $missingCount = max(0, $totalKeys - $targetStat['translated']);
            $missingPercent = $totalKeys > 0 ? round($missingCount * 100 / $totalKeys, 1) : 0;
            $baseCode = $base?->code;
            $filterQuery = fn (string $value) => array_filter(['lang' => $target->code, 'base' => request()->query('base') ? $baseCode : null, 'q' => $q !== '' ? $q : null, 'filter' => $value !== '' ? $value : null]);
            $chipLabels = ['' => 'All', 'missing' => 'Missing', 'draft' => 'Drafts', 'changed' => 'Edited', 'placeholders' => 'With placeholders'];
            $placeholderPresent = fn (string $value, string $name) => preg_match('/\{\s*'.preg_quote($name, '/').'\s*[,}]/u', $value) === 1;
        @endphp

        {{-- Toolbar: languages, search, filters, import/export, publish --}}
        <section class="card l10n-toolbar" aria-label="Labels toolbar">
            <div class="l10n-toolbar__row">
                <form method="GET" action="{{ route('admin.labels.index') }}" class="l10n-toolbar__form" data-autosubmit>
                    @if ($filter !== '')<input type="hidden" name="filter" value="{{ $filter }}">@endif
                    <label class="l10n-select">Target
                        <select name="lang" data-autosubmit-field>
                            @foreach ($languages as $language)
                                @php $languageStat = $stats[$language->code] ?? ['coverage' => 0, 'drafts' => 0]; @endphp
                                <option value="{{ $language->code }}" @selected($language->code === $target->code)>{{ $language->name }} ({{ $language->code }}) · {{ $languageStat['coverage'] }}%{{ $languageStat['drafts'] > 0 ? ' · '.$languageStat['drafts'].' drafts' : '' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="l10n-select">Base
                        <select name="base" data-autosubmit-field>
                            @foreach ($languages as $language)
                                @continue($language->code === $target->code)
                                <option value="{{ $language->code }}" @selected($language->code === $baseCode)>{{ $language->name }} ({{ $language->code }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="l10n-search">
                        <span class="sr-only">Search labels</span>
                        @include('admin.partials.icon', ['name' => 'search', 'class' => 'icon-sm'])
                        <input type="search" name="q" value="{{ $q }}" placeholder="Search key, text or description">
                    </label>
                    <button class="button button-secondary" type="submit">Search</button>
                </form>

                <div class="l10n-toolbar__actions">
                    <button class="button button-secondary" type="button" data-open-dialog="labels-import">@include('admin.partials.icon', ['name' => 'arrow-down', 'class' => 'icon-sm'])Import ARB/CSV</button>
                    <details class="l10n-menu">
                        <summary class="button button-secondary">@include('admin.partials.icon', ['name' => 'arrow-up', 'class' => 'icon-sm'])Export</summary>
                        <div class="l10n-menu__list">
                            <span class="l10n-menu__hint">Published values</span>
                            <a href="{{ route('admin.labels.export', ['code' => $target->code, 'format' => 'arb']) }}">ARB (app_{{ $target->code }}.arb)</a>
                            <a href="{{ route('admin.labels.export', ['code' => $target->code, 'format' => 'csv']) }}">CSV (labels_{{ $target->code }}.csv)</a>
                        </div>
                    </details>
                    <form method="POST" action="{{ route('admin.languages.publish', $target->code) }}" onsubmit="return confirm('Publish all saved draft changes for {{ $target->code }}? Unsaved edits on this page are not included. The app picks them up on its next refresh.')">
                        @csrf
                        <button class="button button-teal" type="submit" data-publish-button @disabled($targetStat['drafts'] === 0)><span>Publish <span data-draft-count>{{ $targetStat['drafts'] }}</span> change<span data-draft-plural>{{ $targetStat['drafts'] === 1 ? '' : 's' }}</span></span></button>
                    </form>
                </div>
            </div>

            <nav class="l10n-chips" aria-label="Filter labels">
                @foreach ($chipLabels as $value => $chipLabel)
                    <a href="{{ route('admin.labels.index', $filterQuery($value)) }}" @class(['chip', 'chip-active' => $filter === $value, 'l10n-chip--missing' => $value === 'missing', 'l10n-chip--draft' => $value === 'draft']) @if ($filter === $value) aria-current="true" @endif title="{{ $value === '' ? 'All labels' : $filters[$value] }}">
                        {{ $chipLabel }} <span class="num l10n-chip__count">{{ number_format($filterCounts[$value] ?? 0) }}</span>
                    </a>
                @endforeach
                @if ($q !== '' || $filter !== '')
                    <a class="button-link l10n-chips__clear" href="{{ route('admin.labels.index', array_filter(['lang' => $target->code, 'base' => request()->query('base') ? $baseCode : null])) }}">Clear</a>
                @endif
            </nav>
        </section>

        <div class="l10n-editor i18n-labels"
            data-labels-editor
            data-update-url="{{ route('admin.labels.update', $target->code) }}"
            data-bulk-url="{{ route('admin.labels.bulk', $target->code) }}"
            data-discard-url="{{ route('admin.labels.discard', $target->code) }}"
            data-csrf="{{ csrf_token() }}">

            <div class="l10n-editor__main">
                @php $currentGroup = null; @endphp
                @forelse ($labels as $label)
                    @php
                        $group = (string) ($label->group ?? '');
                        $current = $label->target_draft ?? $label->target_value ?? '';
                        $hasDraft = $label->target_draft !== null;
                        $isMissing = $label->target_value === null || $label->target_value === '';
                        $status = $hasDraft ? 'draft' : ($isMissing ? 'missing' : 'published');
                    @endphp
                    @if ($group !== $currentGroup)
                        @if ($currentGroup !== null)
                                </div>
                            </section>
                        @endif
                        @php
                            $currentGroup = $group;
                            $groupStat = $groupStats[$group] ?? null;
                        @endphp
                        <section class="card card-flush l10n-group" aria-labelledby="l10n-group-{{ $loop->index }}">
                            <header class="l10n-group__head">
                                <h2 id="l10n-group-{{ $loop->index }}">@include('admin.partials.icon', ['name' => 'layers'])<span class="mono">{{ $group !== '' ? $group : 'ungrouped' }}</span></h2>
                                @if ($groupStat)
                                    <span class="badge badge-neutral" title="Whole catalog, not just this page">{{ $groupStat['keys'] }} key{{ $groupStat['keys'] === 1 ? '' : 's' }} · {{ $groupStat['drafts'] }} draft{{ $groupStat['drafts'] === 1 ? '' : 's' }}</span>
                                @endif
                            </header>
                            <div class="l10n-group__rows">
                    @endif

                    <article class="l10n-row i18n-label {{ $hasDraft ? 'is-draft' : '' }} {{ $isMissing ? 'is-missing' : '' }}" data-label-key="{{ $label->key }}">
                        <header class="l10n-row__head">
                            <code class="l10n-key">{{ $label->key }}</code>
                            <span @class(['badge', 'badge-square', 'l10n-status', 'badge-warning' => $status === 'draft', 'badge-danger' => $status === 'missing', 'badge-success' => $status === 'published']) data-status-chip>{{ ['draft' => 'Draft', 'missing' => 'Missing', 'published' => 'Live'][$status] }}</span>
                            @if ($label->is_icu)<span class="badge badge-info badge-square">plural/select</span>@endif
                            @if ($label->target_updated_by !== null && ! $isMissing)<span class="badge badge-neutral badge-square">Edited</span>@endif
                            <span class="l10n-row__status" data-status aria-live="polite"></span>
                        </header>
                        @if ($label->description)<p class="l10n-row__desc">{{ $label->description }}</p>@endif

                        <div class="l10n-row__cols">
                            <div class="l10n-col">
                                <div class="l10n-col__label">Base <span class="mono">({{ $base?->code ?? '—' }})</span></div>
                                <div class="l10n-base" dir="{{ $base?->direction ?? 'auto' }}" @if ($base) lang="{{ $base->code }}" @endif>{{ $label->base_value ?? '—' }}</div>
                            </div>
                            <div class="l10n-col">
                                <label class="l10n-col__label" for="label-input-{{ $loop->index }}">Target <span class="mono">({{ $target->code }})</span></label>
                                <textarea id="label-input-{{ $loop->index }}" class="l10n-input i18n-label__input" name="values[{{ $label->key }}]" rows="{{ min(8, max(2, substr_count($current, "\n") + 2)) }}" dir="{{ $target->direction }}" lang="{{ $target->code }}" data-original="{{ $current }}" data-published="{{ $label->target_value ?? '' }}" aria-label="{{ $label->key }} in {{ $target->name }}" @if ($isMissing && ! $hasDraft) placeholder="Missing translation" @endif>{{ $current }}</textarea>

                                @if ($label->placeholders !== [])
                                    <div class="l10n-tokens" aria-label="Placeholders">
                                        <span class="l10n-tokens__label">Placeholders:</span>
                                        @foreach ($label->placeholders as $name => $meta)
                                            @php $present = $placeholderPresent($current, (string) $name); @endphp
                                            <button type="button" @class(['l10n-token', 'is-present' => $present, 'is-missing' => ! $present]) data-insert-placeholder="{{ '{'.$name.'}' }}" data-placeholder-name="{{ $name }}" title="{{ is_array($meta) ? ($meta['type'] ?? 'Object') : 'Object' }} · click to insert">
                                                {{ '{'.$name.'}' }}<span class="l10n-token__mark" aria-hidden="true">{{ $present ? '✓' : '✕' }}</span><span class="sr-only" data-token-state>{{ $present ? 'present' : 'missing' }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="l10n-error" data-error role="alert" hidden>
                                    @include('admin.partials.icon', ['name' => 'alert', 'class' => 'icon-sm'])
                                    <p data-error-text></p>
                                </div>

                                <div class="l10n-row__actions">
                                    <button class="button button-secondary button-sm" type="button" data-save>Save draft</button>
                                    <button class="button button-secondary button-sm" type="button" data-discard @if (! $hasDraft) hidden @endif>Discard draft</button>
                                </div>
                            </div>
                        </div>
                    </article>

                    @if ($loop->last)
                            </div>
                        </section>
                    @endif
                @empty
                    <section class="card">
                        <div class="empty-state">
                            <h3>No labels match</h3>
                            <p>Try another search or filter.</p>
                        </div>
                    </section>
                @endforelse

                @if ($labels->hasPages() || $labels->total() > 0)
                    <section class="card l10n-pager">{{ $labels->links('admin.partials.pagination') }}</section>
                @endif
            </div>

            <aside class="l10n-editor__side" aria-label="{{ $target->name }} status">
                <section class="card l10n-status-card">
                    <div class="l10n-status-card__head">
                        <h2>{{ $target->name }} ({{ $target->code }}) status</h2>
                        <span class="badge badge-neutral badge-square">{{ $target->version > 0 ? 'v'.$target->version.' live' : 'not published' }}</span>
                    </div>
                    <p class="l10n-status-card__native"><span dir="{{ $target->direction }}" lang="{{ $target->code }}">{{ $target->native_name }}</span> · {{ strtoupper($target->direction) }} · edited {{ $target->direction === 'rtl' ? 'right-to-left' : 'left-to-right' }}</p>

                    <div class="l10n-status-card__coverage">
                        <span>Coverage</span>
                        <strong class="i18n-coverage">{{ $targetStat['coverage'] }}%</strong>
                    </div>
                    <div class="bar-track"><span @class(['bar-fill', 'amber' => $targetStat['coverage'] < 90]) style="width: {{ max(0, min(100, $targetStat['coverage'])) }}%"></span></div>
                    <p class="l10n-status-card__meta">{{ number_format($targetStat['translated']) }} / {{ number_format($totalKeys) }} keys published</p>

                    <div class="stat-grid l10n-stat-grid">
                        <div class="stat"><span>Translated</span><strong class="teal">{{ number_format($targetStat['translated']) }}</strong><small>{{ $targetStat['coverage'] }}%</small></div>
                        <div @class(['stat', 'stat-warn' => $targetStat['drafts'] > 0])><span>Drafts</span><strong data-draft-count>{{ $targetStat['drafts'] }}</strong><small>saved, not live</small></div>
                        <div @class(['stat', 'stat-bad' => $missingCount > 0])><span>Missing</span><strong>{{ number_format($missingCount) }}</strong><small>{{ $missingPercent }}%</small></div>
                        <div class="stat"><span>Last published</span><strong class="l10n-stat-small">{{ $target->published_at ? $target->published_at->locale('en')->diffForHumans(null, true, true).' ago' : '—' }}</strong><small>{{ $target->published_at?->format('Y-m-d H:i') ?? 'never' }}</small></div>
                    </div>

                    <div class="l10n-unsaved">
                        <span><strong data-unsaved-count>0</strong> unsaved edit<span data-unsaved-plural>s</span> on this page</span>
                        <button class="button button-sm" type="button" data-save-all disabled>Save all changed</button>
                    </div>

                    <form method="POST" action="{{ route('admin.labels.discard', $target->code) }}" onsubmit="return confirm('Discard every draft for {{ $target->code }}?')" class="l10n-discard-all">
                        @csrf
                        <input type="hidden" name="all" value="1">
                        <button class="button button-danger button-sm" type="submit" @disabled($targetStat['drafts'] === 0)>Discard all drafts</button>
                    </form>
                </section>
            </aside>
        </div>

        <dialog class="admin-drawer l10n-modal" id="labels-import" aria-labelledby="labels-import-title">
            <form method="POST" action="{{ route('admin.labels.import', $target->code) }}" enctype="multipart/form-data" class="l10n-drawer__form">
                @csrf
                <div class="l10n-drawer__head">
                    <div>
                        <h2 id="labels-import-title">Import into {{ $target->name }} ({{ $target->code }}) drafts</h2>
                        <p class="muted">ARB or CSV. Values become drafts; invalid or unknown keys are skipped and listed.</p>
                    </div>
                    <button class="icon-button" type="button" data-close-dialog aria-label="Close">✕</button>
                </div>
                <div class="l10n-drawer__body">
                    <label>File<input type="file" name="file" accept=".arb,.json,.csv,.txt" required></label>
                </div>
                <div class="l10n-drawer__foot">
                    <button class="button button-secondary" type="button" data-close-dialog>Cancel</button>
                    <button class="button button-teal" type="submit">Import</button>
                </div>
            </form>
        </dialog>
    @endif

    <script>
        (() => {
            document.addEventListener('click', (event) => {
                const opener = event.target.closest('[data-open-dialog]');
                if (opener) { document.getElementById(opener.dataset.openDialog)?.showModal(); return; }
                if (event.target.closest('[data-close-dialog]')) { event.target.closest('dialog')?.close(); return; }
                if (event.target.matches('dialog.l10n-modal')) event.target.close();
                document.querySelectorAll('details.l10n-menu[open]').forEach((menu) => { if (!menu.contains(event.target)) menu.removeAttribute('open'); });
            });

            document.querySelectorAll('form[data-autosubmit]').forEach((form) => {
                form.querySelectorAll('[data-autosubmit-field]').forEach((field) => field.addEventListener('change', () => {
                    // A new target picks its own default base unless the admin chose one.
                    if (field.name === 'lang') form.querySelector('select[name="base"]')?.setAttribute('disabled', 'disabled');
                    form.submit();
                }));
            });

            const editor = document.querySelector('[data-labels-editor]');
            if (!editor) return;
            const csrf = editor.dataset.csrf;
            const saveAll = editor.querySelector('[data-save-all]');
            const publishButton = document.querySelector('[data-publish-button]');

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
                errorText: article.querySelector('[data-error-text]'),
                status: article.querySelector('[data-status]'),
                chip: article.querySelector('[data-status-chip]'),
                discard: article.querySelector('[data-discard]'),
            });

            const hasDraft = (article) => article.classList.contains('is-draft');

            const escapeRegExp = (text) => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const refreshTokens = (article) => {
                const value = parts(article).input.value;
                article.querySelectorAll('[data-placeholder-name]').forEach((token) => {
                    const present = new RegExp('\\{\\s*' + escapeRegExp(token.dataset.placeholderName) + '\\s*[,}]', 'u').test(value);
                    token.classList.toggle('is-present', present);
                    token.classList.toggle('is-missing', !present);
                    token.querySelector('.l10n-token__mark').textContent = present ? '✓' : '✕';
                    token.querySelector('[data-token-state]').textContent = present ? 'present' : 'missing';
                });
            };

            const showError = (article, messages) => {
                const { error, errorText, input } = parts(article);
                errorText.textContent = (messages || []).join(' ');
                error.hidden = !errorText.textContent;
                if (errorText.textContent) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
            };

            const setStatusChip = (article, status) => {
                const { chip } = parts(article);
                chip.classList.remove('badge-warning', 'badge-danger', 'badge-success');
                chip.classList.add({ draft: 'badge-warning', missing: 'badge-danger', published: 'badge-success' }[status] || 'badge-success');
                chip.textContent = { draft: 'Draft', missing: 'Missing', published: 'Live' }[status] || 'Live';
            };

            const applyRow = (article, row) => {
                const { input, status, discard } = parts(article);
                const shown = row.draft_value ?? row.value ?? '';
                input.value = shown;
                input.dataset.original = shown;
                input.dataset.published = row.value ?? '';
                discard.hidden = !row.has_draft;
                article.classList.toggle('is-draft', row.has_draft);
                article.classList.toggle('is-missing', row.value === null || row.value === '');
                setStatusChip(article, row.status || (row.has_draft ? 'draft' : 'published'));
                status.textContent = row.has_draft ? 'Draft saved' : 'Matches published';
                showError(article, []);
                refreshTokens(article);
                refreshDirty();
            };

            const dirtyArticles = () => [...editor.querySelectorAll('[data-label-key]')].filter((article) => {
                const input = article.querySelector('textarea');
                return input.value !== input.dataset.original;
            });

            const refreshDirty = () => {
                const count = dirtyArticles().length;
                saveAll.disabled = count === 0;
                document.querySelectorAll('[data-unsaved-count]').forEach((el) => { el.textContent = count; });
                document.querySelectorAll('[data-unsaved-plural]').forEach((el) => { el.textContent = count === 1 ? '' : 's'; });
            };

            const bumpDraftCount = (delta) => {
                const counters = document.querySelectorAll('[data-draft-count]');
                if (counters.length === 0) return;
                const next = Math.max(0, (parseInt(counters[0].textContent, 10) || 0) + delta);
                counters.forEach((el) => { el.textContent = next; });
                document.querySelectorAll('[data-draft-plural]').forEach((el) => { el.textContent = next === 1 ? '' : 's'; });
                if (publishButton) publishButton.disabled = next === 0;
            };

            editor.querySelectorAll('[data-label-key]').forEach(refreshTokens);

            editor.addEventListener('input', (event) => {
                if (event.target.matches('textarea')) {
                    const article = event.target.closest('[data-label-key]');
                    parts(article).status.textContent = event.target.value !== event.target.dataset.original ? 'Unsaved' : '';
                    refreshTokens(article);
                    refreshDirty();
                }
            });

            window.addEventListener('beforeunload', (event) => {
                if (dirtyArticles().length > 0) { event.preventDefault(); event.returnValue = ''; }
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
                    const hadDraft = hasDraft(article);
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
                        const hadDraft = hasDraft(article);
                        const row = result.data.data[article.dataset.labelKey];
                        if (row) { applyRow(article, row); bumpDraftCount((row.has_draft ? 1 : 0) - (hadDraft ? 1 : 0)); }
                    });
                    window.adminToast?.(`Saved ${result.data.saved} label(s) as drafts.`);
                } else {
                    articles.forEach((article) => showError(article, result.data.errors?.[article.dataset.labelKey] || []));
                    window.adminToast?.(result.data.message || 'Nothing was saved.', 'error');
                }
                refreshDirty();
            };
        })();
    </script>
@endsection
