<?php

namespace App\Console\Commands;

use App\Services\Ai\BakeOff\ArabicCvCorpus;
use App\Services\AtsScoringService;
use App\Services\ClaudeCvService;
use App\Services\OpenAiCvService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Time-boxed Arabic quality bake-off: gpt-4.1-mini vs Claude on analysis_advice.
 *
 * Pre-committed decision rule (do not revise after seeing results):
 *   Switch production to Claude ONLY if Claude wins CLEARLY on factual accuracy
 *   or Arabic fluency. A narrow/ambiguous win does NOT justify ~2.8x cost.
 *   Default remains OpenAI; do not leave an unused second driver if results are close.
 */
class ArabicAiBakeOffCommand extends Command
{
    protected $signature = 'ai:arabic-bake-off
        {--limit=30 : Number of CVs to run (max 30)}
        {--providers=both : openai|claude|both}
        {--out=storage/app/bake-off : Output directory}';

    protected $description = 'Run blind OpenAI vs Claude Arabic CV advice bake-off (analysis_advice)';

    public function handle(AtsScoringService $scorer, OpenAiCvService $openAi, ClaudeCvService $claude): int
    {
        $limit = max(1, min(30, (int) $this->option('limit')));
        $providersOpt = strtolower((string) $this->option('providers'));
        $outDir = base_path(trim((string) $this->option('out'), '/\\'));

        File::ensureDirectoryExists($outDir);
        File::ensureDirectoryExists($outDir.'/blind');
        File::ensureDirectoryExists($outDir.'/raw');

        $runOpenAi = in_array($providersOpt, ['openai', 'both'], true);
        $runClaude = in_array($providersOpt, ['claude', 'both'], true);

        if ($runOpenAi && ! $openAi->isConfigured()) {
            $this->warn('OpenAI is not configured (OPENAI_API_KEY). Skipping OpenAI runs.');
            $runOpenAi = false;
        }
        if ($runClaude && ! $claude->isConfigured()) {
            $this->warn('Claude is not configured (ANTHROPIC_API_KEY). Skipping Claude runs.');
            $runClaude = false;
        }

        if (! $runOpenAi && ! $runClaude) {
            $this->error('No providers available. Set OPENAI_API_KEY and/or ANTHROPIC_API_KEY, then re-run.');
            $this->writeDecisionScaffold($outDir, [], 'no_providers');

            return self::FAILURE;
        }

        $fixtures = array_slice(ArabicCvCorpus::all(), 0, $limit);
        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'decision_rule' => 'Switch to Claude only on a CLEAR win for factual accuracy or Arabic fluency. Narrow/ambiguous → stay on OpenAI and remove Claude driver.',
            'production_default' => 'openai',
            'cost_note' => 'gpt-4.1-mini is ~2.8x cheaper; Claude must win clearly to justify switching.',
            'scoring_dimensions' => [
                'factual_accuracy' => 'Did the model invent employers, metrics, dates, or skills not in the CV? (1–5, 5=perfectly grounded)',
                'arabic_fluency' => 'Formal MSA register, natural Gulf/MENA professional tone (1–5)',
                'actionability' => 'Concrete, prioritised advice a candidate can apply (1–5)',
                'rtl_ltr' => 'Technical terms (Laravel, SQL, API) handled cleanly in mixed text (1–5)',
            ],
            'items' => [],
        ];

        $scoreRows = [
            ['cv_id', 'category', 'target_job_title', 'arm', 'provider_hidden', 'factual_accuracy', 'arabic_fluency', 'actionability', 'rtl_ltr', 'notes'],
        ];

        $this->info("Running bake-off on {$limit} CVs…");
        $bar = $this->output->createProgressBar(count($fixtures));
        $bar->start();

        foreach ($fixtures as $fixture) {
            $score = $scorer->score($fixture['resume_text'], $fixture['target_job_title']);
            $outputs = [];

            if ($runOpenAi) {
                try {
                    $outputs['openai'] = $openAi->analysisAdvice(
                        $score,
                        $fixture['resume_text'],
                        $fixture['target_job_title'],
                    );
                } catch (Throwable $e) {
                    $outputs['openai'] = ['_error' => $e->getMessage()];
                }
            }

            if ($runClaude) {
                try {
                    $outputs['claude'] = $claude->analysisAdvice(
                        $score,
                        $fixture['resume_text'],
                        $fixture['target_job_title'],
                    );
                } catch (Throwable $e) {
                    $outputs['claude'] = ['_error' => $e->getMessage()];
                }
            }

            File::put(
                $outDir.'/raw/'.$fixture['id'].'.json',
                json_encode([
                    'fixture' => $fixture,
                    'score' => $score,
                    'outputs' => $outputs,
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            );

            $arms = $this->blindArms($outputs);
            $blindPacket = [
                'cv_id' => $fixture['id'],
                'category' => $fixture['category'],
                'target_job_title' => $fixture['target_job_title'],
                'resume_text' => $fixture['resume_text'],
                'ats_score_total' => $score['total'],
                'instructions' => 'Score each arm 1–5 on the four dimensions. Do NOT try to guess which vendor is which.',
                'arms' => array_map(static fn (array $arm): array => [
                    'label' => $arm['label'],
                    'output' => $arm['output'],
                ], $arms),
            ];

            File::put(
                $outDir.'/blind/'.$fixture['id'].'.json',
                json_encode($blindPacket, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            );

            // Human score sheet rows (provider column filled only in key file).
            foreach ($arms as $arm) {
                $scoreRows[] = [
                    $fixture['id'],
                    $fixture['category'],
                    $fixture['target_job_title'],
                    $arm['label'],
                    $arm['provider'], // for key file only
                    '',
                    '',
                    '',
                    '',
                    '',
                ];
            }

            $manifest['items'][] = [
                'cv_id' => $fixture['id'],
                'category' => $fixture['category'],
                'target_job_title' => $fixture['target_job_title'],
                'arm_map' => collect($arms)->mapWithKeys(
                    fn (array $arm): array => [$arm['label'] => $arm['provider']]
                )->all(),
                'providers_ok' => collect($outputs)
                    ->map(fn ($out) => is_array($out) && ! isset($out['_error']))
                    ->all(),
            ];

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        File::put($outDir.'/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->writeCsv($outDir.'/score_sheet_KEY.csv', $scoreRows);

        // Blind score sheet without provider names for human raters.
        $blindRows = array_map(static function (array $row): array {
            $row[4] = ''; // hide provider

            return $row;
        }, $scoreRows);
        $this->writeCsv($outDir.'/score_sheet_BLIND.csv', $blindRows);

        $this->writeDecisionScaffold($outDir, $manifest['items'], $runOpenAi && $runClaude ? 'ready' : 'partial');

        $this->info("Wrote bake-off artefacts to: {$outDir}");
        $this->line('- blind/*.json — present to rater (randomised arm order)');
        $this->line('- score_sheet_BLIND.csv — human scores go here');
        $this->line('- score_sheet_KEY.csv — arm→provider key (do not show rater)');
        $this->line('- DECISION.md — pre-committed rule + final call');
        $this->comment('Production default remains CV_AI_PROVIDER=openai until a CLEAR Claude win is recorded.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string, mixed>>  $outputs
     * @return list<array{label: string, provider: string, output: array<string, mixed>}>
     */
    private function blindArms(array $outputs): array
    {
        $arms = [];
        foreach ($outputs as $provider => $output) {
            $arms[] = [
                'label' => '', // filled after shuffle
                'provider' => $provider,
                'output' => $output,
            ];
        }

        shuffle($arms);

        foreach ($arms as $i => $arm) {
            $arms[$i]['label'] = 'Arm '.chr(65 + $i); // Arm A, Arm B
        }

        return $arms;
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            return;
        }
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function writeDecisionScaffold(string $outDir, array $items, string $status): void
    {
        $categories = collect($items)->pluck('category')->unique()->sort()->values()->all();
        $categoryList = $this->formatList($categories);
        $body = <<<MD
# Arabic CV advice bake-off — decision record

## Pre-committed decision rule (locked before results)

1. Score each blind arm on **factual accuracy**, **Arabic fluency**, **actionability**, and **RTL/LTR handling** (1–5).
2. Aggregate mean scores per provider across all CVs.
3. **Switch production to Claude only if Claude wins CLEARLY** on factual accuracy **or** Arabic fluency
   (e.g. ≥0.4 mean points on a 1–5 scale, or a clear majority of pairwise rater preferences with no accuracy regressions).
4. A **narrow or ambiguous** win does **not** justify ~2.8× per-call cost → **stay on OpenAI**.
5. If results are close / Claude does not win clearly: **keep `CV_AI_PROVIDER=openai`** and **delete the Claude driver** rather than leave a second unused vendor alive.
6. **Do not change the production default** until this document records an explicit switch decision.

## Run status

- Status: **{$status}**
- Fixtures prepared: **30** anonymised MENA/Gulf Arabic CVs
- Categories covered: `{$categoryList}`
- Production default: **openai** (unchanged)

## Cost baseline (agreed)

gpt-4.1-mini is ~2.8× cheaper than Claude for comparable volume. Quality must be a clear win, not a tie.

## How to complete human scoring

1. Ensure both `OPENAI_API_KEY` and `ANTHROPIC_API_KEY` are set.
2. Run: `php artisan ai:arabic-bake-off --providers=both`
3. Give a native Arabic speaker familiar with Gulf/MENA hiring the files in `blind/` plus `score_sheet_BLIND.csv` only.
4. Do **not** share `score_sheet_KEY.csv` or `raw/` until scoring is finished.
5. After scores: join KEY + BLIND, compute means, apply the rule above, fill **Final decision** below.

## Final decision

| Field | Value |
|-------|--------|
| Date | _pending human scoring_ |
| OpenAI mean accuracy | — |
| Claude mean accuracy | — |
| OpenAI mean fluency | — |
| Claude mean fluency | — |
| Clear winner? | — |
| **Production provider** | **openai** (default retained) |
| Claude driver retention | Keep only for re-runs until decision; remove if no clear win |

### Rationale

Live API keys were not available in this environment at infrastructure build time
(`OPENAI_API_KEY` / `ANTHROPIC_API_KEY` empty). The harness, corpus, blind packets,
and decision rule are ready. Until a human rater completes the sheet with live
outputs, **production stays on OpenAI** per the pre-committed rule (no clear Claude win recorded).

MD;

        File::put($outDir.'/DECISION.md', $body);
    }

    /**
     * @param  list<string>  $items
     */
    private function formatList(array $items): string
    {
        return $items === [] ? '(none run yet)' : implode(', ', $items);
    }
}
