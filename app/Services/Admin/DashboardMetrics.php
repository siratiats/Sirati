<?php

namespace App\Services\Admin;

use App\Enums\AiStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Aggregate metrics for the admin overview and health pages.
 *
 * Windows: the current window is [start, end] where end = now and start is
 * the start of today (today), or of the day 6 / 29 days ago (7d / 30d). The
 * previous window has the same length and ends where the current one starts:
 * [start - length, start). Deltas compare the two.
 *
 * Active users = distinct users who created a CV analysis, a generated CV or a
 * job match inside the window (guest rows without a user_id are not counted).
 *
 * Every query is an aggregate (count / group by / one ordered row), so no
 * page ever loads all rows of a table.
 */
class DashboardMetrics
{
    public const RANGES = ['today', '7d', '30d'];

    public const CACHE_SECONDS = 60;

    /** Tables whose rows carry the AI pipeline status of one user request. */
    private const AI_PIPELINE_TABLES = [
        'cv_analyses' => 'CV analysis',
        'generated_cvs' => 'Generated CV',
        'job_matches' => 'Job match',
    ];

    /** @var array<string, bool> */
    private array $tables = [];

    public function __construct(private readonly QueueInspector $queue)
    {
    }

    public static function normalizeRange(?string $range): string
    {
        return in_array($range, self::RANGES, true) ? $range : '7d';
    }

    /**
     * @return array{range: string, start: Carbon, end: Carbon, previous_start: Carbon, unit: string, buckets: list<array{key: string, label: string}>}
     */
    public function window(string $range, ?Carbon $now = null): array
    {
        $range = self::normalizeRange($range);
        $end = ($now ?? now())->copy();
        $days = ['today' => 1, '7d' => 7, '30d' => 30][$range];
        $start = $end->copy()->startOfDay()->subDays($days - 1);
        $length = $start->diffInSeconds($end, true);
        $previousStart = $start->copy()->subSeconds((int) round($length));

        $buckets = [];
        if ($range === 'today') {
            for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addHour()) {
                $buckets[] = ['key' => $cursor->format('Y-m-d H'), 'label' => $cursor->format('H:00')];
            }
        } else {
            for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
                $buckets[] = ['key' => $cursor->format('Y-m-d'), 'label' => $cursor->format('M j')];
            }
        }

        return [
            'range' => $range,
            'start' => $start,
            'end' => $end,
            'previous_start' => $previousStart,
            'unit' => $range === 'today' ? 'hour' : 'day',
            'buckets' => $buckets,
        ];
    }

    /**
     * Cached for 60 seconds per range.
     */
    public function overview(string $range): array
    {
        $range = self::normalizeRange($range);

        return Cache::remember("admin:dashboard-metrics:{$range}", self::CACHE_SECONDS, fn () => $this->compute($range));
    }

    /**
     * Uncached: tests and the cache callback use this.
     */
    public function compute(string $range, ?Carbon $now = null): array
    {
        $window = $this->window($range, $now);

        return [
            'window' => $window,
            'generated_at' => $window['end']->copy(),
            'kpis' => $this->attempt(fn () => $this->kpis($window), []),
            'activity' => $this->attempt(fn () => $this->activity($window), ['labels' => [], 'series' => []]),
            'pipeline' => $this->attempt(fn () => $this->pipelineSummary($window['start'], $window['end']), ['completed' => 0, 'failed' => 0, 'failure_rate' => null, 'available' => false]),
            'landing_leads' => $this->attempt(fn () => $this->countBetween('landing_leads', $window['start'], $window['end']), null),
            'average_analysis_score' => $this->attempt(fn () => $this->averageScore('cv_analyses', $window['start'], $window['end']), null),
            'ai' => $this->attempt(fn () => $this->aiUsage($window['start'], $window['end']), ['available' => false]),
            'queue' => $this->queue->summary(),
            'failures' => $this->attempt(fn () => $this->recentFailures(6), null),
            'localization' => $this->localization(),
        ];
    }

    /**
     * Run one dashboard section; a failing query degrades that section to
     * "unavailable" instead of failing the page.
     */
    public function attempt(callable $section, mixed $fallback): mixed
    {
        try {
            return $section();
        } catch (Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }

    /**
     * @return array<string, array{label: string, value: ?int, previous: ?int, delta: ?float, series: list<int>, note: ?string}>
     */
    public function kpis(array $window): array
    {
        $kpis = [];

        $kpis['new_users'] = $this->kpi('New users', $window, fn (Carbon $from, Carbon $to, bool $inclusive) => $this->countBetween('users', $from, $to, $inclusive), $this->series('users', $window));

        $kpis['active_users'] = $this->kpi(
            'Active users',
            $window,
            fn (Carbon $from, Carbon $to, bool $inclusive) => $this->activeUsers($from, $to, $inclusive),
            $this->activeUserSeries($window),
            'Distinct users with an analysis, generated CV or job match in the range.',
        );

        foreach (['cv_analyses' => 'CV analyses', 'generated_cvs' => 'Generated CVs', 'job_matches' => 'Job matches'] as $table => $label) {
            $kpis[$table] = $this->kpi($label, $window, fn (Carbon $from, Carbon $to, bool $inclusive) => $this->countBetween($table, $from, $to, $inclusive), $this->series($table, $window));
        }

        $kpis['premium_users'] = [
            'label' => 'Premium users',
            'value' => $this->premiumUsers($window['end']),
            'previous' => null,
            'delta' => null,
            'series' => [],
            'note' => 'Current snapshot. Premium history is not stored, so there is no trend.',
        ];

        return $kpis;
    }

    /**
     * Count rows created in [from, to] (inclusive) or [from, to) (exclusive).
     */
    public function countBetween(string $table, Carbon $from, Carbon $to, bool $inclusiveEnd = true): ?int
    {
        if (! $this->tableExists($table)) {
            return null;
        }

        return (int) DB::table($table)
            ->where('created_at', '>=', $from)
            ->where('created_at', $inclusiveEnd ? '<=' : '<', $to)
            ->count();
    }

    public function activeUsers(Carbon $from, Carbon $to, bool $inclusiveEnd = true): ?int
    {
        $union = $this->activityUnion($from, $to, $inclusiveEnd, false);

        if ($union === null) {
            return null;
        }

        return (int) DB::query()->fromSub($union, 'activity')->count();
    }

    public function premiumUsers(Carbon $at): ?int
    {
        if (! $this->tableExists('users')) {
            return null;
        }

        return (int) DB::table('users')
            ->where(function (Builder $query) use ($at): void {
                $query->where('premium_until', '>', $at)
                    ->orWhere(fn (Builder $query) => $query->whereNull('premium_until')->where('is_premium', true));
            })
            ->count();
    }

    /**
     * Per-bucket counts for the activity chart.
     *
     * @return array{labels: list<string>, series: array<string, list<int>>}
     */
    public function activity(array $window): array
    {
        return [
            'labels' => array_column($window['buckets'], 'label'),
            'series' => [
                'cv_analyses' => $this->series('cv_analyses', $window),
                'generated_cvs' => $this->series('generated_cvs', $window),
                'job_matches' => $this->series('job_matches', $window),
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function series(string $table, array $window): array
    {
        if (! $this->tableExists($table)) {
            return [];
        }

        $bucket = $this->bucketExpression($window['unit']);
        $counts = DB::table($table)
            ->selectRaw("{$bucket} as bucket, count(*) as aggregate")
            ->where('created_at', '>=', $window['start'])
            ->where('created_at', '<=', $window['end'])
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket');

        return array_map(fn (array $b) => (int) ($counts[$b['key']] ?? 0), $window['buckets']);
    }

    /**
     * AI pipeline outcome for requests created in the window: completed vs
     * failed rows across analyses, generated CVs and job matches.
     *
     * @return array{completed: int, failed: int, failure_rate: ?float, available: bool}
     */
    public function pipelineSummary(Carbon $from, Carbon $to): array
    {
        $completed = 0;
        $failed = 0;
        $available = false;

        foreach (array_keys(self::AI_PIPELINE_TABLES) as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }
            $available = true;
            $row = DB::table($table)
                ->selectRaw('sum(case when ai_status = ? then 1 else 0 end) as completed', [AiStatus::Completed->value])
                ->selectRaw('sum(case when ai_status = ? then 1 else 0 end) as failed', [AiStatus::Failed->value])
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->first();
            $completed += (int) ($row->completed ?? 0);
            $failed += (int) ($row->failed ?? 0);
        }

        $finished = $completed + $failed;

        return [
            'completed' => $completed,
            'failed' => $failed,
            'failure_rate' => $finished > 0 ? round($failed / $finished * 100, 2) : null,
            'available' => $available,
        ];
    }

    /**
     * Totals from ai_call_logs. That table only records successful provider
     * calls and has no cost column, so error rate comes from the pipeline
     * status and cost is shown only when a cost column exists.
     */
    public function aiUsage(Carbon $from, Carbon $to): array
    {
        if (! $this->tableExists('ai_call_logs')) {
            return ['available' => false];
        }

        $base = fn () => DB::table('ai_call_logs')->where('created_at', '>=', $from)->where('created_at', '<=', $to);
        $totals = $base()
            ->selectRaw('count(*) as calls, avg(duration_ms) as avg_ms, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens')
            ->selectRaw('sum(case when was_response_cache_hit = ? then 1 else 0 end) as cache_hits', [true])
            ->first();
        $calls = (int) ($totals->calls ?? 0);
        $costColumn = $this->costColumn();

        return [
            'available' => true,
            'calls' => $calls,
            'avg_ms' => $calls > 0 ? (int) round((float) $totals->avg_ms) : null,
            'p95_ms' => $this->percentile($base(), 'duration_ms', 95, $calls),
            'input_tokens' => (int) ($totals->input_tokens ?? 0),
            'output_tokens' => (int) ($totals->output_tokens ?? 0),
            'cache_hits' => (int) ($totals->cache_hits ?? 0),
            'cost' => $costColumn ? (float) $base()->sum($costColumn) : null,
            'error_rate' => $this->logErrorRate($base, $calls),
            'pipeline' => $this->pipelineSummary($from, $to),
            'providers' => $this->aiProviders($from, $to),
        ];
    }

    /**
     * @return list<array{provider: string, models: string, calls: int, share: float, avg_ms: ?int, p95_ms: ?int, input_tokens: int, output_tokens: int, cache_hits: int, cost: ?float, error_rate: ?float}>
     */
    public function aiProviders(Carbon $from, Carbon $to): array
    {
        if (! $this->tableExists('ai_call_logs')) {
            return [];
        }

        $base = fn () => DB::table('ai_call_logs')->where('created_at', '>=', $from)->where('created_at', '<=', $to);
        $rows = $base()
            ->selectRaw('provider, count(*) as calls, avg(duration_ms) as avg_ms, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens')
            ->selectRaw('sum(case when was_response_cache_hit = ? then 1 else 0 end) as cache_hits', [true])
            ->groupBy('provider')
            ->orderByDesc('calls')
            ->get();
        $total = max(1, (int) $rows->sum('calls'));
        $costColumn = $this->costColumn();

        return $rows->map(function ($row) use ($base, $total, $costColumn): array {
            $calls = (int) $row->calls;
            $scoped = fn () => $base()->where('provider', $row->provider);
            $models = $scoped()->select('model')->distinct()->orderBy('model')->limit(3)->pluck('model')->implode(', ');

            return [
                'provider' => (string) $row->provider,
                'models' => $models,
                'calls' => $calls,
                'share' => round($calls / $total * 100, 1),
                'avg_ms' => $calls > 0 ? (int) round((float) $row->avg_ms) : null,
                'p95_ms' => $this->percentile($scoped(), 'duration_ms', 95, $calls),
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'cache_hits' => (int) $row->cache_hits,
                'cost' => $costColumn ? (float) $scoped()->sum($costColumn) : null,
                'error_rate' => $this->logErrorRate($scoped, $calls),
            ];
        })->all();
    }

    /**
     * Nearest-rank percentile with one ordered single-row query.
     */
    public function percentile(Builder $query, string $column, int $percent, ?int $count = null): ?int
    {
        $count ??= (clone $query)->count();

        if ($count === 0) {
            return null;
        }

        $rank = max(1, (int) ceil($percent / 100 * $count));
        $value = (clone $query)->orderBy($column)->offset($rank - 1)->limit(1)->value($column);

        return $value === null ? null : (int) $value;
    }

    /**
     * Latest failures across failed queue jobs and failed AI pipeline rows.
     *
     * @return list<array{time: ?Carbon, operation: string, source: string, error: string, link: ?string, kind: string}>
     */
    public function recentFailures(int $limit = 8): array
    {
        $items = [];

        foreach ($this->queue->failedJobs($limit) ?? [] as $job) {
            $items[] = [
                'time' => $job['failed_at'],
                'operation' => class_basename($job['job']),
                'source' => 'Queue · '.$job['queue'],
                'error' => $job['exception'],
                'link' => route('admin.health.index').'#failed-jobs',
                'kind' => 'queue',
            ];
        }

        foreach (self::AI_PIPELINE_TABLES as $table => $label) {
            if (! $this->tableExists($table)) {
                continue;
            }
            $rows = DB::table($table)
                ->where('ai_status', AiStatus::Failed->value)
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get(['id', 'ai_error', 'updated_at']);

            foreach ($rows as $row) {
                $items[] = [
                    'time' => $row->updated_at ? Carbon::parse($row->updated_at) : null,
                    'operation' => "{$label} #{$row->id}",
                    'source' => 'AI pipeline',
                    'error' => QueueInspector::firstLine((string) ($row->ai_error ?: 'No error message recorded.')),
                    'link' => match ($table) {
                        'cv_analyses' => route('admin.analyses.index'),
                        'generated_cvs' => route('admin.generated-cvs.index'),
                        default => null,
                    },
                    'kind' => 'ai',
                ];
            }
        }

        usort($items, fn (array $a, array $b) => ($b['time']?->getTimestamp() ?? 0) <=> ($a['time']?->getTimestamp() ?? 0));

        return array_slice($items, 0, $limit);
    }

    /**
     * Only when the localization feature (AppLanguage model) is installed.
     */
    public function localization(): ?array
    {
        $languageClass = 'App\\Models\\AppLanguage';

        if (! class_exists($languageClass)) {
            return null;
        }

        try {
            $languages = $languageClass::query()->orderBy('sort_order')->orderBy('code')->limit(6)->get();
            $keyClass = 'App\\Models\\AppTranslationKey';
            $translationClass = 'App\\Models\\AppTranslation';
            $totalKeys = class_exists($keyClass) ? (int) $keyClass::query()->count() : null;
            $published = [];

            if ($totalKeys !== null && class_exists($translationClass)) {
                // Same definition as the Languages page: published values of keys still in the catalog.
                $published = $translationClass::query()
                    ->join('app_translation_keys', 'app_translation_keys.key', '=', 'app_translations.key')
                    ->selectRaw('app_translations.language_code as code, count(*) as aggregate')
                    ->whereNotNull('app_translations.value')
                    ->where('app_translations.value', '!=', '')
                    ->groupBy('app_translations.language_code')
                    ->pluck('aggregate', 'code')
                    ->all();
            }

            return [
                'total_keys' => $totalKeys,
                'languages' => $languages->map(fn ($language) => [
                    'code' => (string) $language->code,
                    'name' => (string) ($language->name ?? $language->code),
                    'native_name' => (string) ($language->native_name ?? ''),
                    'direction' => (string) ($language->direction ?? 'ltr'),
                    'enabled' => (bool) ($language->is_enabled ?? true),
                    'version' => $language->version ?? null,
                    'published' => isset($published[$language->code]) ? (int) $published[$language->code] : ($totalKeys === null ? null : 0),
                ])->all(),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function tableExists(string $table): bool
    {
        if (! ($this->tables[$table] ?? false)) {
            try {
                $this->tables[$table] = Schema::hasTable($table);
            } catch (Throwable) {
                return false;
            }
        }

        return $this->tables[$table];
    }

    private function kpi(string $label, array $window, callable $counter, array $series, ?string $note = null): array
    {
        $value = $counter($window['start'], $window['end'], true);
        $previous = $counter($window['previous_start'], $window['start'], false);

        return [
            'label' => $label,
            'value' => $value,
            'previous' => $previous,
            'delta' => self::delta($value, $previous),
            'series' => $series,
            'note' => $note,
        ];
    }

    /**
     * Percent change vs the previous window; null when there is no base
     * (previous = 0) because a percentage of zero is undefined.
     */
    public static function delta(?int $current, ?int $previous): ?float
    {
        if ($current === null || $previous === null || $previous === 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /**
     * @return list<int>
     */
    private function activeUserSeries(array $window): array
    {
        $union = $this->activityUnion($window['start'], $window['end'], true, true, $window['unit']);

        if ($union === null) {
            return [];
        }

        $counts = DB::query()->fromSub($union, 'activity')
            ->selectRaw('bucket, count(*) as aggregate')
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket');

        return array_map(fn (array $b) => (int) ($counts[$b['key']] ?? 0), $window['buckets']);
    }

    /**
     * UNION (distinct) of user ids, optionally paired with their bucket.
     */
    private function activityUnion(Carbon $from, Carbon $to, bool $inclusiveEnd, bool $withBucket, string $unit = 'day'): ?Builder
    {
        $union = null;

        foreach (array_keys(self::AI_PIPELINE_TABLES) as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }

            $query = DB::table($table)
                ->whereNotNull('user_id')
                ->where('created_at', '>=', $from)
                ->where('created_at', $inclusiveEnd ? '<=' : '<', $to);
            $query = $withBucket
                ? $query->selectRaw($this->bucketExpression($unit).' as bucket, user_id')
                : $query->select('user_id');

            $union = $union === null ? $query : $union->union($query);
        }

        return $union;
    }

    private function bucketExpression(string $unit): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $unit === 'hour' ? "DATE_FORMAT(created_at, '%Y-%m-%d %H')" : "DATE_FORMAT(created_at, '%Y-%m-%d')",
            'pgsql' => $unit === 'hour' ? "to_char(created_at, 'YYYY-MM-DD HH24')" : "to_char(created_at, 'YYYY-MM-DD')",
            'sqlsrv' => $unit === 'hour' ? 'FORMAT(created_at, \'yyyy-MM-dd HH\')' : 'FORMAT(created_at, \'yyyy-MM-dd\')',
            default => $unit === 'hour' ? "strftime('%Y-%m-%d %H', created_at)" : "strftime('%Y-%m-%d', created_at)",
        };
    }

    private function averageScore(string $table, Carbon $from, Carbon $to): ?float
    {
        if (! $this->tableExists($table)) {
            return null;
        }

        $average = DB::table($table)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->avg('score_total');

        return $average === null ? null : round((float) $average, 1);
    }

    private function costColumn(): ?string
    {
        foreach (['cost_usd', 'estimated_cost', 'cost'] as $column) {
            if (Schema::hasColumn('ai_call_logs', $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Only when ai_call_logs records failures (a status column); it does not today.
     */
    private function logErrorRate(callable $base, int $calls): ?float
    {
        if ($calls === 0 || ! Schema::hasColumn('ai_call_logs', 'status')) {
            return null;
        }

        $errors = $base()->whereNotIn('status', ['ok', 'success', 'completed'])->count();

        return round($errors / $calls * 100, 2);
    }
}
