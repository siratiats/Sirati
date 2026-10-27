<?php

namespace App\Console\Commands;

use App\Services\I18n\ArbImporter;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportArbCommand extends Command
{
    protected $signature = 'i18n:import-arb
        {--path= : Directory with app_*.arb files (default: resources/i18n)}
        {--force : Overwrite admin-published values and drafts with the bundled values}';

    protected $description = 'Sync the app label catalog and base translations from the bundled ARB files.';

    public function handle(ArbImporter $importer): int
    {
        $path = $this->option('path') ?: config('i18n.arb_path', resource_path('i18n'));

        if (! is_dir($path)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        try {
            $report = $importer->importDirectory($path, (bool) $this->option('force'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Catalog: {$report['keys_created']} keys added, {$report['keys_updated']} updated.");
        if ($report['languages_created'] !== []) {
            $this->info('Languages created: '.implode(', ', $report['languages_created']).'.');
        }

        $invalidTotal = 0;
        foreach ($report['languages'] as $code => $result) {
            $version = $result['version'] === null ? 'unchanged' : 'now v'.$result['version'];
            $this->line("{$code}: {$result['inserted']} inserted, {$result['updated']} refreshed, {$result['kept']} admin edits kept, ".count($result['invalid'])." invalid ({$version}).");
            foreach ($result['invalid'] as $key => $errors) {
                $invalidTotal++;
                $this->warn("  {$code}.{$key}: ".implode(' ', $errors));
            }
        }

        return $invalidTotal > 0 ? self::FAILURE : self::SUCCESS;
    }
}
