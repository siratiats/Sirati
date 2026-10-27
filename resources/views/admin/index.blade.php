@extends('admin.shell')

@php
    use App\Support\AdminCharts;

    $rangeLabels = ['today' => 'Today', '7d' => '7d', '30d' => '30d'];
    $rangeText = ['today' => 'today', '7d' => 'the last 7 days', '30d' => 'the last 30 days'][$range];
    $kpis = $metrics['kpis'];
    $ai = $metrics['ai'];
    $queue = $metrics['queue'];
    $pipeline = $metrics['pipeline'];
    $activity = $metrics['activity'];
    $failures = $metrics['failures'];
    $localization = $metrics['localization'];
@endphp

@section('admin_title', 'Overview')
@section('admin_active', 'overview')
@section('admin_eyebrow', 'Operations dashboard')
@section('admin_heading', 'Overview')

@section('admin_toolbar')
    <nav class="segmented" aria-label="Date range">
        @foreach ($rangeLabels as $key => $label)
            <a href="{{ route('admin.index', ['range' => $key]) }}" @if ($range === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <span class="admin-updated">Updated {{ $metrics['generated_at']->locale('en')->diffForHumans(null, true, true) }} ago</span>
    <a class="button button-secondary" href="{{ route('admin.index', ['range' => $range, 'refresh' => 1]) }}">
        @include('admin.partials.icon', ['name' => 'refresh', 'class' => 'icon-sm']) Refresh
    </a>
@endsection

@section('admin_content')
    {{-- KPI tiles --}}
    <section class="kpi-grid" aria-label="Key metrics for {{ $rangeText }}">
        @forelse ($kpis as $key => $kpi)
            <article class="kpi" @if ($kpi['note']) title="{{ $kpi['note'] }}" @endif>
                <div class="kpi-head">
                    <span class="kpi-label">{{ $kpi['label'] }}</span>
                    @if ($kpi['delta'] !== null)
                        @php $direction = $kpi['delta'] > 0 ? 'up' : ($kpi['delta'] < 0 ? 'down' : 'flat'); @endphp
                        <span class="delta delta-{{ $direction }}" title="vs previous {{ $range === 'today' ? 'period' : $range }}: {{ number_format($kpi['previous']) }}">
                            {{ $kpi['delta'] > 0 ? '+' : '' }}{{ number_format($kpi['delta'], 1) }}%
                            @if ($direction !== 'flat')
                                @include('admin.partials.icon', ['name' => $direction === 'up' ? 'arrow-up' : 'arrow-down', 'class' => 'icon-sm'])
                            @endif
                        </span>
                    @elseif ($kpi['previous'] === 0 && ($kpi['value'] ?? 0) > 0)
                        <span class="delta delta-none" title="Nothing in the previous period">new</span>
                    @elseif ($key === 'premium_users')
                        <span class="delta delta-none">now</span>
                    @endif
                </div>
                <div class="kpi-value">{{ $kpi['value'] === null ? '–' : number_format($kpi['value']) }}</div>
                @php $spark = AdminCharts::sparkline($kpi['series']); @endphp
                @if ($spark)
                    <svg class="spark" viewBox="0 0 100 26" preserveAspectRatio="none" aria-hidden="true">
                        <path d="{{ $spark['area'] }}" fill="#12b3a6" fill-opacity=".12"></path>
                        <path d="{{ $spark['line'] }}" fill="none" stroke="#12b3a6" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>
                    </svg>
                @else
                    <div class="spark-empty">{{ $key === 'premium_users' ? 'Snapshot' : ' ' }}</div>
                @endif
            </article>
        @empty
            <p class="unavailable">Metrics are unavailable right now.</p>
        @endforelse
    </section>

    <div class="overview-grid">
        <div class="stack">
            {{-- Activity chart --}}
            <section class="card card-flush" aria-labelledby="activity-title">
                <div class="card-head">
                    <div>
                        <h2 id="activity-title">Activity trends</h2>
                        <p>CV analyses, generated CVs and job matches created {{ $rangeText }}, per {{ $metrics['window']['unit'] }}.</p>
                    </div>
                </div>
                <div class="legend" aria-hidden="true">
                    <span class="chip chip-active"><span class="legend-swatch" style="background:#12b3a6"></span>CV analyses</span>
                    <span class="chip"><span class="legend-swatch" style="background:#101b33"></span>Generated CVs</span>
                    <span class="chip"><span class="legend-swatch" style="background:#5b6b82"></span>Job matches</span>
                </div>
                @php
                    $chartW = 680; $chartH = 300;
                    $chart = AdminCharts::lineChart($activity['series'], $chartW, $chartH);
                    $labels = $activity['labels'];
                    $labelEvery = max(1, (int) ceil(count($labels) / 6));
                    $chartPoints = [];
                    foreach ($chart['xs'] as $i => $x) {
                        $chartPoints[] = [
                            'x' => round($x, 2),
                            'label' => $labels[$i] ?? '',
                            'rows' => [
                                ['CV analyses', number_format($activity['series']['cv_analyses'][$i] ?? 0)],
                                ['Generated CVs', number_format($activity['series']['generated_cvs'][$i] ?? 0)],
                                ['Job matches', number_format($activity['series']['job_matches'][$i] ?? 0)],
                            ],
                        ];
                    }
                    $seriesTotal = array_sum(array_map('array_sum', $activity['series'] ?: [[]]));
                @endphp
                <div class="chart-wrap" data-chart='@json($chartPoints)'>
                    <svg class="chart" viewBox="0 0 {{ $chartW }} {{ $chartH }}" role="img" aria-label="Activity chart: {{ number_format($seriesTotal) }} items {{ $rangeText }}">
                        <defs>
                            <linearGradient id="area-teal" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#12b3a6" stop-opacity=".28"></stop>
                                <stop offset="100%" stop-color="#12b3a6" stop-opacity="0"></stop>
                            </linearGradient>
                            <linearGradient id="area-navy" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#101b33" stop-opacity=".12"></stop>
                                <stop offset="100%" stop-color="#101b33" stop-opacity="0"></stop>
                            </linearGradient>
                        </defs>
                        @foreach ($chart['ticks'] as $tick)
                            <line class="grid-line" x1="{{ $chart['plot']['left'] }}" x2="{{ $chart['plot']['right'] }}" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}"></line>
                            <text x="{{ $chart['plot']['left'] - 8 }}" y="{{ $tick['y'] + 4 }}" text-anchor="end">{{ $tick['label'] }}</text>
                        @endforeach
                        @foreach ($chart['xs'] as $i => $x)
                            @if ($i % $labelEvery === 0 || $i === count($chart['xs']) - 1)
                                <text x="{{ $x }}" y="{{ $chartH - 6 }}" text-anchor="{{ $i === 0 ? 'start' : ($i === count($chart['xs']) - 1 ? 'end' : 'middle') }}">{{ $labels[$i] ?? '' }}</text>
                            @endif
                        @endforeach
                        @isset($chart['areas']['cv_analyses'])
                            <path d="{{ $chart['areas']['cv_analyses'] }}" fill="url(#area-teal)"></path>
                        @endisset
                        @isset($chart['areas']['generated_cvs'])
                            <path d="{{ $chart['areas']['generated_cvs'] }}" fill="url(#area-navy)"></path>
                        @endisset
                        @isset($chart['lines']['job_matches'])
                            <path d="{{ $chart['lines']['job_matches'] }}" fill="none" stroke="#5b6b82" stroke-width="2" stroke-dasharray="4 4" stroke-linejoin="round"></path>
                        @endisset
                        @isset($chart['lines']['generated_cvs'])
                            <path d="{{ $chart['lines']['generated_cvs'] }}" fill="none" stroke="#101b33" stroke-width="2.25" stroke-linejoin="round"></path>
                        @endisset
                        @isset($chart['lines']['cv_analyses'])
                            <path d="{{ $chart['lines']['cv_analyses'] }}" fill="none" stroke="#12b3a6" stroke-width="2.5" stroke-linejoin="round"></path>
                        @endisset
                        <line class="hover-line" x1="0" x2="0" y1="{{ $chart['plot']['top'] }}" y2="{{ $chart['plot']['bottom'] }}" visibility="hidden"></line>
                    </svg>
                    <div class="chart-tooltip" hidden></div>
                    @if ($seriesTotal === 0)
                        <p class="card-note" style="margin: 0;">No activity {{ $rangeText }} yet.</p>
                    @endif
                </div>
                <div class="chart-stats">
                    <div>
                        <span>Landing leads</span>
                        <strong>{{ $metrics['landing_leads'] === null ? '–' : number_format($metrics['landing_leads']) }}</strong>
                    </div>
                    <div>
                        <span>Avg analysis score</span>
                        <strong>{{ $metrics['average_analysis_score'] === null ? '–' : number_format($metrics['average_analysis_score'], 1) }}</strong>
                    </div>
                    <div>
                        <span>AI pipeline success</span>
                        @php $finished = $pipeline['completed'] + $pipeline['failed']; @endphp
                        <strong @class(['ok' => $finished > 0 && $pipeline['failed'] === 0])>
                            {{ $finished > 0 ? number_format($pipeline['completed'] / $finished * 100, 2).'%' : '–' }}
                        </strong>
                    </div>
                </div>
            </section>

            {{-- Recent failures --}}
            <section class="card card-flush" aria-labelledby="failures-title">
                <div class="card-head">
                    <h2 id="failures-title"><span class="dot-live" aria-hidden="true"></span>Recent failures
                        @if ($failures !== null && count($failures) > 0)
                            <span class="badge badge-danger">{{ count($failures) }} latest</span>
                        @endif
                    </h2>
                </div>
                @if ($failures === null)
                    <p class="card-note unavailable">Failure data is unavailable.</p>
                @elseif ($failures === [])
                    <p class="card-note">No failed queue jobs or AI requests recorded.</p>
                @else
                    <div class="table-wrap flush">
                        <table>
                            <thead><tr><th>Time</th><th>Operation</th><th>Source</th><th>Error</th><th class="num">Action</th></tr></thead>
                            <tbody>
                                @foreach ($failures as $failure)
                                    <tr>
                                        <td class="td-mono">{{ $failure['time']?->format($range === 'today' ? 'H:i:s' : 'M j H:i') ?? '–' }}</td>
                                        <td><span class="failure-op">@include('admin.partials.icon', ['name' => 'alert', 'class' => 'icon-sm']){{ $failure['operation'] }}</span></td>
                                        <td><span @class(['badge badge-square', 'badge-violet' => $failure['kind'] === 'ai', 'badge-info' => $failure['kind'] !== 'ai'])>{{ $failure['source'] }}</span></td>
                                        <td class="td-error">{{ $failure['error'] }}</td>
                                        <td class="num">@if ($failure['link'])<a class="button-link" href="{{ $failure['link'] }}">Investigate</a>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <div class="card-foot">
                    <span>Failed queue jobs and failed AI requests, newest first</span>
                    <a href="{{ route('admin.health.index') }}">System health ›</a>
                </div>
            </section>
        </div>

        <div class="stack">
            {{-- AI usage --}}
            <section class="card card-flush" aria-labelledby="ai-title">
                <div class="card-head">
                    <h2 id="ai-title">@include('admin.partials.icon', ['name' => 'brain'])AI usage</h2>
                    <span class="card-meta">{{ $rangeLabels[$range] }}</span>
                </div>
                @if (! ($ai['available'] ?? false))
                    <p class="card-note unavailable">AI call logs are unavailable.</p>
                @else
                    <div class="stat-grid">
                        <div class="stat"><span>Total calls</span><strong>{{ AdminCharts::compact($ai['calls']) }}</strong></div>
                        <div class="stat" title="Failed analyses, generated CVs and job matches out of all finished ones">
                            <span>{{ $ai['error_rate'] !== null ? 'Error rate' : 'Failure rate' }}</span>
                            @php $rate = $ai['error_rate'] ?? $ai['pipeline']['failure_rate']; @endphp
                            <strong @class(['teal' => $rate !== null && $rate < 1, 'bad' => $rate !== null && $rate >= 5])>{{ $rate === null ? '–' : number_format($rate, 2).'%' }}</strong>
                        </div>
                        <div class="stat"><span>p95 latency</span><strong>{{ AdminCharts::duration($ai['p95_ms']) }}</strong></div>
                        @if ($ai['cost'] !== null)
                            <div class="stat"><span>Est. cost</span><strong>${{ number_format($ai['cost'], 2) }}</strong></div>
                        @else
                            <div class="stat"><span>Avg latency</span><strong>{{ AdminCharts::duration($ai['avg_ms']) }}</strong></div>
                        @endif
                    </div>
                    @if ($ai['providers'] !== [])
                        <div class="providers">
                            @foreach ($ai['providers'] as $i => $provider)
                                <div>
                                    <div class="provider-row-head"><span>{{ ucfirst($provider['provider']) }}{{ $provider['models'] !== '' ? ' ('.$provider['models'].')' : '' }}</span><span class="num">{{ number_format($provider['share'], 0) }}%</span></div>
                                    <div class="bar-track"><span @class(['bar-fill', 'navy' => $i === 1, 'slate' => $i === 2, 'amber' => $i > 2]) style="width: {{ max(1, $provider['share']) }}%"></span></div>
                                    <div class="provider-row-meta"><span>{{ AdminCharts::compact($provider['calls']) }} calls</span><span>avg {{ AdminCharts::duration($provider['avg_ms']) }}</span></div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="card-note" style="margin-top: 14px;">No AI calls {{ $rangeText }}.</p>
                    @endif
                    <p class="card-note">Failure rate counts failed analyses, CVs and job matches; ai_call_logs records successful calls only{{ $ai['cost'] === null ? ' and has no cost column' : '' }}.</p>
                @endif
            </section>

            {{-- Queue --}}
            <section class="card card-flush" aria-labelledby="queue-title">
                <div class="card-head">
                    <h2 id="queue-title">@include('admin.partials.icon', ['name' => 'layers'])Queue</h2>
                    @if ($queue['heartbeat_age_seconds'] !== null)
                        <span @class(['badge badge-dot', 'badge-success' => $queue['heartbeat_age_seconds'] <= (int) config('health.queue.max_age_seconds', 120), 'badge-warning' => $queue['heartbeat_age_seconds'] > (int) config('health.queue.max_age_seconds', 120)])>Worker {{ AdminCharts::age($queue['heartbeat_age_seconds']) }} ago</span>
                    @else
                        <span class="badge badge-neutral">{{ $queue['connection'] }} · {{ $queue['driver'] ?? 'unknown' }}</span>
                    @endif
                </div>
                <div class="queue-stats">
                    <div @class(['stat stat-center', 'stat-warn' => ($queue['pending'] ?? 0) > 0])><span>Pending</span><strong>{{ $queue['available'] ? number_format($queue['pending']) : '–' }}</strong></div>
                    <div class="stat stat-center"><span>Oldest job</span><strong>{{ $queue['available'] ? AdminCharts::age($queue['oldest_age_seconds']) : '–' }}</strong><small>max {{ (int) config('health.queue.max_age_seconds', 120) }}s</small></div>
                    <div @class(['stat stat-center', 'stat-bad' => ($queue['failed'] ?? 0) > 0])><span>Failed</span><strong>{{ $queue['failed'] === null ? '–' : number_format($queue['failed']) }}</strong></div>
                </div>
                <div class="queue-foot">
                    <span>
                        @if (! $queue['available'])
                            Queue unavailable{{ $queue['error'] ? ': '.$queue['error'] : '' }}
                        @elseif ($queue['inline'])
                            {{ $queue['driver'] }} driver: jobs run inline
                        @else
                            {{ $queue['connection'] }} · {{ $queue['driver'] }}
                        @endif
                    </span>
                    @if (($queue['failed'] ?? 0) > 0)
                        <form method="POST" action="{{ route('admin.health.failed.retry-all') }}" data-confirm="Retry all {{ $queue['failed'] }} failed jobs?">
                            @csrf
                            <button class="button button-danger button-sm" type="submit">@include('admin.partials.icon', ['name' => 'retry', 'class' => 'icon-sm']) Retry all ({{ $queue['failed'] }})</button>
                        </form>
                    @endif
                </div>
            </section>

            {{-- Localization (only when the feature is installed) --}}
            @if ($localization !== null)
                <section class="card card-flush" aria-labelledby="l10n-title">
                    <div class="card-head">
                        <h2 id="l10n-title">@include('admin.partials.icon', ['name' => 'languages'])Localization</h2>
                        @if ($localization['total_keys'] !== null)
                            <span class="card-meta">{{ number_format($localization['total_keys']) }} keys</span>
                        @endif
                    </div>
                    <div class="lang-list">
                        @forelse ($localization['languages'] as $language)
                            @php $coverage = ($localization['total_keys'] ?? 0) > 0 && $language['published'] !== null ? min(100, $language['published'] / $localization['total_keys'] * 100) : null; @endphp
                            <div>
                                <div class="lang-row-head">
                                    <span><span class="lang-code">{{ strtoupper($language['code']) }}</span>@if (Route::has('admin.labels.index'))<a class="lang-link" href="{{ route('admin.labels.index', ['lang' => $language['code']]) }}"><b>{{ $language['name'] }}</b></a>@else<b>{{ $language['name'] }}</b>@endif @if (! $language['enabled']) <span class="muted">(disabled)</span>@endif</span>
                                    <span class="num">{{ $coverage === null ? '–' : round($coverage, 1).'%' }}</span>
                                </div>
                                @if ($coverage !== null)
                                    <div class="bar-track"><span @class(['bar-fill', 'amber' => $coverage < 90]) style="width: {{ max(1, $coverage) }}%"></span></div>
                                    <div class="provider-row-meta"><span>{{ number_format($language['published']) }} / {{ number_format($localization['total_keys']) }} strings</span><span>{{ strtoupper($language['direction']) }}{{ $language['version'] !== null ? ' · v'.$language['version'] : '' }}</span></div>
                                @endif
                            </div>
                        @empty
                            <p class="muted">No languages yet.</p>
                        @endforelse
                    </div>
                    @if (Route::has('admin.labels.index'))
                        <a class="card-action" href="{{ route('admin.labels.index') }}">Manage strings &amp; labels @include('admin.partials.icon', ['name' => 'arrow-right'])</a>
                    @else
                        <div style="height: 18px;"></div>
                    @endif
                </section>
            @endif
        </div>
    </div>
@endsection
