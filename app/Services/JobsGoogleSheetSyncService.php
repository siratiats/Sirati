<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class JobsGoogleSheetSyncService
{
    public function __construct(private readonly JobsImportService $importer)
    {
    }

    /**
     * @return array{created:int, updated:int, skipped:int, errors:array<int, array{row:int, message:string}>}
     */
    public function sync(?string $url = null): array
    {
        $url = $url ?? (string) config('services.jobs_sheet.csv_url');
        if (trim($url) === '') {
            throw new \RuntimeException('JOBS_SHEET_CSV_URL is not configured.');
        }

        $response = Http::timeout(20)->withHeaders(['Accept' => 'text/csv'])->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException(
                "Failed to fetch Google Sheet CSV (status {$response->status()})."
            );
        }

        $body = $response->body();
        if (trim($body) === '') {
            throw new \RuntimeException('Google Sheet CSV response was empty.');
        }

        $result = $this->importer->importCsvString($body, JobsImportService::SOURCE_SHEET);

        Cache::put('jobs_sheet_last_sync', [
            'synced_at' => now()->toIso8601String(),
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'errors' => count($result['errors']),
        ], now()->addDays(30));

        return $result;
    }
}
