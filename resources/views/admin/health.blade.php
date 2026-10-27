@extends('admin.shell')

@php
    use App\Support\AdminCharts;

    $maxAge = (int) config('health.queue.max_age_seconds', 120);
    $heartbeat = $queue['heartbeat_age_seconds'];
    $queueState = ! $queue['available'] ? 'down'
        : (($queue['oldest_age_seconds'] ?? 0) > $maxAge || ($heartbeat !== null && $heartbeat > $maxAge) ? 'degraded' : 'ok');
    $aiAvailable = $ai['available'] ?? false;
    $aiRate = $aiAvailable ? ($ai['error_rate'] ?? $ai['pipeline']['failure_rate']) : null;
    $aiState = ! $aiAvailable ? 'down' : ($aiRate !== null && $aiRate >= 5 ? 'degraded' : 'ok');
    $pdfCount = $pdfFailures === null ? null : count($pdfFailures);
    $pdfState = $pdfCount === null ? 'unknown' : ($pdfCount > 0 ? 'degraded' : 'ok');
    $stateBadge = fn (string $state) => match ($state) {
        'ok' => ['badge-success', 'Operational'],
        'degraded' => ['badge-warning', 'Degraded'],
        'down' => ['badge-danger', 'Unavailable'],
        default => ['badge-neutral', 'Unknown'],
    };
@endphp

@section('admin_title', 'System health')
@section('admin_active', 'health')
@section('admin_eyebrow', 'Infrastructure telemetry')
@section('admin_heading', 'System health')

@section('admin_toolbar')
    <span class="admin-updated">Checked {{ $checkedAt->format('H:i:s') }} · AI window 24h</span>
    <a class="button button-secondary" href="{{ route('admin.health.index') }}">
        @include('admin.partials.icon', ['name' => 'refresh', 'class' => 'icon-sm']) Refresh
    </a>
@endsection

@section('admin_content')
    {{-- Status tiles --}}
    <section class="status-grid" aria-label="Service status">
        @php [$badgeClass, $badgeText] = $stateBadge($database['ok'] ? 'ok' : 'down'); @endphp
        <article @class(['status-tile', 'is-down' => ! $database['ok']])>
            <div class="status-tile-head">
                <div><small>Primary store</small><h3>Database</h3></div>
                <span class="badge badge-dot {{ $badgeClass }}">{{ $badgeText }}</span>
            </div>
            <div class="status-tile-body">
                <div>
                    <div class="big-num">{{ $database['latency_ms'] === null ? '–' : number_format($database['latency_ms'], 1) }}<small>ms</small></div>
                    <span class="status-sub">query round trip</span>
                </div>
                <div class="status-side"><b>{{ $database['driver'] ?? 'unavailable' }}</b>driver</div>
            </div>
        </article>

        @php [$badgeClass, $badgeText] = $stateBadge($queueState); @endphp
        <article @class(['status-tile', 'is-degraded' => $queueState === 'degraded', 'is-down' => $queueState === 'down'])>
            <div class="status-tile-head">
                <div><small>Asynchronous processing</small><h3>Queue workers</h3></div>
                <span class="badge badge-dot {{ $badgeClass }}">{{ $badgeText }}</span>
            </div>
            <div class="status-tile-body">
                <div>
                    <div @class(['big-num', 'warn' => $queueState === 'degraded'])>{{ $queue['available'] ? number_format($queue['pending']) : '–' }}</div>
                    <span class="status-sub">pending jobs (oldest: {{ $queue['available'] ? AdminCharts::age($queue['oldest_age_seconds']) : '–' }})</span>
                </div>
                <div class="status-side"><b>{{ $heartbeat === null ? 'no heartbeat' : AdminCharts::age($heartbeat).' ago' }}</b>worker heartbeat</div>
            </div>
        </article>

        @php [$badgeClass, $badgeText] = $stateBadge($aiState); @endphp
        <article @class(['status-tile', 'is-degraded' => $aiState === 'degraded', 'is-down' => $aiState === 'down'])>
            <div class="status-tile-head">
                <div><small>Cognitive inference</small><h3>AI providers</h3></div>
                <span class="badge badge-dot {{ $badgeClass }}">{{ $badgeText }}</span>
            </div>
            <div class="status-tile-body">
                <div>
                    <div @class(['big-num', 'warn' => $aiState === 'degraded'])>{{ $aiRate === null ? '–' : number_format($aiRate, 2) }}<small>%</small></div>
                    <span class="status-sub">AI request failure rate (24h)</span>
                </div>
                <div class="status-side"><b>{{ $aiAvailable ? count($ai['providers']) : 0 }} active</b>{{ $aiAvailable ? AdminCharts::compact($ai['calls']).' calls' : 'no data' }}</div>
            </div>
        </article>

        @php [$badgeClass, $badgeText] = $stateBadge($pdfState); @endphp
        <article @class(['status-tile', 'is-degraded' => $pdfState === 'degraded'])>
            <div class="status-tile-head">
                <div><small>Document pipeline</small><h3>PDF renderer</h3></div>
                <span class="badge badge-dot {{ $badgeClass }}">{{ $badgeText }}</span>
            </div>
            <div class="status-tile-body">
                <div>
                    <div @class(['big-num', 'warn' => $pdfState === 'degraded'])>{{ $pdfCount === null ? '–' : number_format($pdfCount) }}</div>
                    <span class="status-sub">failed PDF renders (24h)</span>
                </div>
                <div class="status-side"><b>{{ config('cv_pdf.queue', 'default') }}</b>queue</div>
            </div>
        </article>
    </section>

    <div class="health-split">
        {{-- Queues --}}
        <section class="card card-flush" aria-labelledby="queues-title">
            <div class="card-head">
                <h2 id="queues-title">@include('admin.partials.icon', ['name' => 'route'])Queues</h2>
                <span class="badge badge-info badge-square">{{ $queue['connection'] }} · {{ $queue['driver'] ?? 'unknown' }}</span>
            </div>
            @if (! $queue['available'])
                <p class="card-note unavailable">Queue unavailable{{ $queue['error'] ? ': '.$queue['error'] : '' }}.</p>
            @elseif ($queue['inline'])
                <p class="card-note">The {{ $queue['driver'] }} driver runs jobs inline; there is no backlog to show.</p>
            @else
                <div class="table-wrap flush">
                    <table class="table-compact">
                        <thead><tr><th>Queue</th><th>Status</th><th class="num">Pending</th><th class="num">Delayed</th><th class="num">Running</th><th class="num">Oldest</th></tr></thead>
                        <tbody>
                            @foreach ($queue['queues'] as $row)
                                @php $stale = ($row['oldest_age_seconds'] ?? 0) > $maxAge; @endphp
                                <tr>
                                    <td class="td-mono">{{ $row['name'] }}</td>
                                    <td><span @class(['badge badge-square', 'badge-warning' => $stale, 'badge-success' => ! $stale && ($row['pending'] + $row['reserved']) > 0, 'badge-neutral' => ! $stale && ($row['pending'] + $row['reserved']) === 0])>{{ $stale ? 'Backlog' : (($row['pending'] + $row['reserved']) > 0 ? 'Active' : 'Idle') }}</span></td>
                                    <td class="num">{{ number_format($row['pending']) }}</td>
                                    <td class="num">{{ number_format($row['delayed']) }}</td>
                                    <td class="num">{{ number_format($row['reserved']) }}</td>
                                    <td class="num td-mono">{{ AdminCharts::age($row['oldest_age_seconds']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="card-foot"><span>Alert threshold: oldest job &gt; {{ $maxAge }}s</span><span>{{ $queue['failed_24h'] === null ? '' : number_format($queue['failed_24h']).' failed in 24h' }}</span></div>
        </section>

        {{-- Failed jobs --}}
        <section class="card card-flush" id="failed-jobs" aria-labelledby="failed-title">
            <div class="card-head">
                <h2 id="failed-title">@include('admin.partials.icon', ['name' => 'alert'])Failed jobs
                    @if ($queue['failed'])
                        <span class="badge badge-danger">{{ number_format($queue['failed']) }} unhandled</span>
                    @endif
                </h2>
                @if ($queue['failed'])
                    <form method="POST" action="{{ route('admin.health.failed.retry-all') }}" data-confirm="Retry all {{ $queue['failed'] }} failed jobs?">
                        @csrf
                        <button class="button button-danger" type="submit">@include('admin.partials.icon', ['name' => 'retry', 'class' => 'icon-sm']) Retry all ({{ number_format($queue['failed']) }})</button>
                    </form>
                @endif
            </div>
            @if ($failedJobs === null)
                <p class="card-note unavailable">The failed jobs table is unavailable.</p>
            @elseif ($failedJobs === [])
                <p class="card-note">No failed jobs.</p>
            @else
                <div class="table-wrap flush">
                    <table>
                        <thead><tr><th>Job</th><th>Queue</th><th>Exception</th><th>Failed</th><th class="num">Actions</th></tr></thead>
                        <tbody>
                            @foreach ($failedJobs as $job)
                                <tr>
                                    <td><span class="cell-strong">{{ class_basename($job['job']) }}</span><span class="cell-sub td-mono">#{{ $job['numeric_id'] }}</span></td>
                                    <td><span class="badge badge-info badge-square">{{ $job['queue'] }}</span></td>
                                    <td class="td-error">{{ $job['exception'] }}</td>
                                    <td class="td-mono" title="{{ $job['failed_at']?->toDateTimeString() }}">{{ $job['failed_at']?->locale('en')->diffForHumans(null, true, true) }}{{ $job['failed_at'] ? ' ago' : '–' }}</td>
                                    <td class="num">
                                        <div class="table-actions" style="justify-content: flex-end;">
                                            <form method="POST" action="{{ route('admin.health.failed.retry', $job['id']) }}">
                                                @csrf
                                                <button class="button button-secondary button-sm" type="submit">Retry</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.health.failed.forget', $job['id']) }}" data-confirm="Delete this failed job permanently?">
                                                @csrf
                                                <button class="button button-danger button-sm" type="submit">Forget</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    {{-- AI providers --}}
    <section class="stack" aria-labelledby="providers-title" style="gap: 12px;">
        <div class="section-title">
            <h2 id="providers-title">@include('admin.partials.icon', ['name' => 'cpu'])AI providers</h2>
            <span class="card-meta">Aggregation: last 24 hours</span>
        </div>
        @if (! $aiAvailable)
            <p class="card unavailable">AI call logs are unavailable.</p>
        @elseif ($ai['providers'] === [])
            <p class="card muted">No AI calls in the last 24 hours.</p>
        @else
            <div class="provider-cards">
                @foreach ($ai['providers'] as $provider)
                    <article @class(['provider-card', 'is-degraded' => ($provider['error_rate'] ?? 0) >= 5])>
                        <div class="provider-card-head">
                            <h3>{{ $provider['provider'] }}</h3>
                            <span class="badge badge-dot badge-success">{{ number_format($provider['share'], 0) }}% of calls</span>
                        </div>
                        <p class="provider-models">{{ $provider['models'] ?: 'Unknown model' }}</p>
                        <div class="provider-metrics">
                            <div><span>Calls 24h</span><strong>{{ AdminCharts::compact($provider['calls']) }}</strong></div>
                            <div>
                                <span>Error rate</span>
                                @if ($provider['error_rate'] === null)
                                    <strong class="muted" title="ai_call_logs records successful calls only">Not logged</strong>
                                @else
                                    <strong @class(['teal' => $provider['error_rate'] < 1, 'bad' => $provider['error_rate'] >= 5])>{{ number_format($provider['error_rate'], 2) }}%</strong>
                                @endif
                            </div>
                            <div><span>p95 latency</span><strong>{{ AdminCharts::duration($provider['p95_ms']) }}</strong></div>
                            <div><span>Avg latency</span><strong>{{ AdminCharts::duration($provider['avg_ms']) }}</strong></div>
                        </div>
                        <div class="provider-foot">
                            <span>{{ AdminCharts::compact($provider['input_tokens']) }} in / {{ AdminCharts::compact($provider['output_tokens']) }} out tokens</span>
                            <span>{{ $provider['cost'] !== null ? '$'.number_format($provider['cost'], 2).' est.' : number_format($provider['cache_hits']).' cache hits' }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
            <p class="muted" style="margin: 0;">Failed provider calls are not written to ai_call_logs; the failure rate above comes from failed analyses, CVs and job matches ({{ number_format($ai['pipeline']['failed']) }} of {{ number_format($ai['pipeline']['failed'] + $ai['pipeline']['completed']) }} in 24h).</p>
        @endif
    </section>

    {{-- Recent errors --}}
    <section class="card card-flush" aria-labelledby="errors-title">
        <div class="card-head">
            <h2 id="errors-title">@include('admin.partials.icon', ['name' => 'terminal'])Recent errors</h2>
            <span class="card-meta">Failed jobs + failed AI requests</span>
        </div>
        @if ($recentErrors === null)
            <p class="card-note unavailable">Error data is unavailable.</p>
        @elseif ($recentErrors === [])
            <p class="card-note">No recent errors.</p>
        @else
            <div class="table-wrap flush">
                <table>
                    <thead><tr><th>Timestamp</th><th>Severity</th><th>Source</th><th>Diagnostic</th></tr></thead>
                    <tbody>
                        @foreach ($recentErrors as $error)
                            <tr>
                                <td class="td-mono">{{ $error['time']?->format('M j H:i:s') ?? '–' }}</td>
                                <td><span @class(['severity', 'severity-critical' => $error['kind'] === 'queue', 'severity-warning' => $error['kind'] !== 'queue'])>{{ $error['kind'] === 'queue' ? 'Critical' : 'Warning' }}</span></td>
                                <td><span class="source-tag">{{ $error['source'] }}</span></td>
                                <td><span class="error-title">{{ $error['operation'] }}</span><span class="error-trace">{{ $error['error'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <div class="card-foot"><span>Newest first · up to 15 entries</span><span>{{ config('app.name') }} · {{ app()->environment() }}</span></div>
    </section>
@endsection
