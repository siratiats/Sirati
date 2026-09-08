<?php

namespace App\Console\Commands;

use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneGuestAnalysesCommand extends Command
{
    protected $signature = 'analyses:prune-guests {--hours=24 : Retention period in hours}';

    protected $description = 'Prune unauthenticated guest CV analyses and generated CVs older than the retention threshold for Saudi PDPL compliance';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $threshold = Carbon::now()->subHours($hours);

        $prunedAnalyses = CvAnalysis::query()
            ->whereNull('user_id')
            ->where('created_at', '<', $threshold)
            ->delete();

        $prunedGeneratedCvs = GeneratedCv::query()
            ->whereNull('user_id')
            ->where('created_at', '<', $threshold)
            ->delete();

        $this->info("Pruned {$prunedAnalyses} guest analyses and {$prunedGeneratedCvs} guest CVs older than {$hours} hours.");

        return self::SUCCESS;
    }
}
