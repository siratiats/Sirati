<?php

namespace App\Console\Commands;

use App\Models\AiCallLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AiCostReportCommand extends Command
{
    private const INPUT_RATE_PER_MTOK = 0.40;

    private const OUTPUT_RATE_PER_MTOK = 1.60;

    private const CACHED_INPUT_RATE_PER_MTOK = 0.10;

    protected $signature = 'ai:cost-report {--days=30 : Number of days to include}';

    protected $description = 'Print AI call cost and latency stats per operation';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = Carbon::now()->subDays($days);

        $logs = AiCallLog::query()
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->get(['operation', 'input_tokens', 'output_tokens', 'cached_tokens', 'duration_ms', 'was_response_cache_hit']);

        if ($logs->isEmpty()) {
            $this->info("No AI call logs in the last {$days} day(s).");

            return self::SUCCESS;
        }

        $rows = $logs
            ->groupBy('operation')
            ->map(function ($group, string $operation): array {
                $count = $group->count();
                $inputTokens = (int) $group->sum('input_tokens');
                $outputTokens = (int) $group->sum('output_tokens');
                $cachedTokens = (int) $group->sum('cached_tokens');
                $durations = $group->pluck('duration_ms')->map(fn ($ms) => (int) $ms)->all();
                $meanDuration = (int) round(array_sum($durations) / max(1, count($durations)));
                $p95Duration = $this->percentile($durations, 95);
                $responseCacheHits = $group->where('was_response_cache_hit', true)->count();
                $responseHitRate = $count > 0 ? ($responseCacheHits / $count) * 100 : 0.0;
                $promptCacheRate = $inputTokens > 0 ? ($cachedTokens / $inputTokens) * 100 : 0.0;
                $promptCacheSaving = $this->estimatePromptCacheSaving($cachedTokens);
                $spend = $this->estimateSpend($inputTokens, $outputTokens, $cachedTokens);

                return [
                    $operation,
                    $count,
                    number_format($inputTokens),
                    number_format($outputTokens),
                    number_format($cachedTokens),
                    number_format($promptCacheRate, 1).'%',
                    '$'.number_format($promptCacheSaving, 4),
                    number_format($meanDuration).' ms',
                    number_format($p95Duration).' ms',
                    number_format($responseHitRate, 1).'%',
                    '$'.number_format($spend, 4),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        $this->info("AI cost report — last {$days} day(s)");
        $this->newLine();
        $this->table(
            [
                'Operation',
                'Calls',
                'Input tokens',
                'Output tokens',
                'Cached tokens',
                'Prompt cache rate',
                'Prompt cache saving',
                'Mean duration',
                'p95 duration',
                'Response cache hit',
                'Est. spend',
            ],
            $rows,
        );

        $totalInput = (int) $logs->sum('input_tokens');
        $totalOutput = (int) $logs->sum('output_tokens');
        $totalCached = (int) $logs->sum('cached_tokens');
        $totalSpend = $this->estimateSpend($totalInput, $totalOutput, $totalCached);
        $totalPromptCacheRate = $totalInput > 0 ? ($totalCached / $totalInput) * 100 : 0.0;
        $totalPromptCacheSaving = $this->estimatePromptCacheSaving($totalCached);

        $this->newLine();
        $this->line(sprintf(
            'Totals: calls=%d input=%s output=%s cached=%s prompt_cache_rate=%s%% prompt_cache_saving=$%s est. spend=$%s',
            $logs->count(),
            number_format($totalInput),
            number_format($totalOutput),
            number_format($totalCached),
            number_format($totalPromptCacheRate, 1),
            number_format($totalPromptCacheSaving, 4),
            number_format($totalSpend, 4),
        ));
        $this->comment(
            'Prompt cache rate = cached_tokens / input_tokens (OpenAI automatic prefix cache). '
            .'Saving = cached_tokens × ($0.40 − $0.10) / 1MTok. '
            .'If cached tokens stay 0 all day, the static prefix is not engaging — check length ≥1024 and no per-request system-prompt leakage.'
        );

        return self::SUCCESS;
    }

    /**
     * OpenAI bills non-cached prompt tokens at the input rate and cached prompt
     * tokens at the cached rate. prompt_tokens already includes cached_tokens.
     */
    private function estimateSpend(int $inputTokens, int $outputTokens, int $cachedTokens): float
    {
        $cached = min($cachedTokens, $inputTokens);
        $uncachedInput = max(0, $inputTokens - $cached);

        return ($uncachedInput / 1_000_000) * self::INPUT_RATE_PER_MTOK
            + ($cached / 1_000_000) * self::CACHED_INPUT_RATE_PER_MTOK
            + ($outputTokens / 1_000_000) * self::OUTPUT_RATE_PER_MTOK;
    }

    /**
     * Dollars saved by paying $0.10/MTok for cached input instead of $0.40/MTok.
     */
    private function estimatePromptCacheSaving(int $cachedTokens): float
    {
        $delta = self::INPUT_RATE_PER_MTOK - self::CACHED_INPUT_RATE_PER_MTOK;

        return ($cachedTokens / 1_000_000) * $delta;
    }

    /**
     * @param  list<int>  $values
     */
    private function percentile(array $values, float $percentile): int
    {
        if ($values === []) {
            return 0;
        }

        sort($values);
        $rank = (int) ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, min($rank, count($values) - 1));

        return (int) $values[$index];
    }
}
