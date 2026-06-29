<?php

namespace App\Console\Commands;

use App\Services\JobsGoogleSheetSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncJobsFromGoogleSheet extends Command
{
    protected $signature = 'jobs:sync-sheet';

    protected $description = 'Sync job listings from the configured Google Sheets CSV URL.';

    public function handle(JobsGoogleSheetSyncService $service): int
    {
        $url = config('services.jobs_sheet.csv_url');
        if (! is_string($url) || trim($url) === '') {
            $this->warn('JOBS_SHEET_CSV_URL is not set. Skipping sync.');
            return self::SUCCESS;
        }

        try {
            $result = $service->sync();
        } catch (\Throwable $e) {
            $this->error('Sheet sync failed: ' . $e->getMessage());
            Log::error('jobs:sync-sheet failed', ['exception' => $e]);
            return self::FAILURE;
        }

        $summary = sprintf(
            'Sheet sync done. created=%d updated=%d skipped=%d errors=%d',
            $result['created'],
            $result['updated'],
            $result['skipped'],
            count($result['errors'])
        );
        $this->info($summary);
        Log::info('jobs:sync-sheet completed', $result);

        return self::SUCCESS;
    }
}
