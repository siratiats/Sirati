<?php

namespace App\Console\Commands;

use App\Services\Jobs\SaudiJobAggregatorService;
use Illuminate\Console\Command;

class AggregateSaudiJobsCommand extends Command
{
    protected $signature = 'jobs:aggregate-saudi';

    protected $description = 'Automatically fetch and aggregate job postings from Saudi job feeds with taxonomy linkage';

    public function handle(SaudiJobAggregatorService $aggregator): int
    {
        $this->info('Starting automated Saudi jobs aggregation...');

        $result = $aggregator->aggregateAll();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Fetched from feeds', $result['fetched']],
                ['New jobs created', $result['created']],
                ['Existing jobs updated', $result['updated']],
                ['Feed errors', count($result['errors'])],
            ]
        );

        if (! empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $this->warn("  - {$error}");
            }
        }

        $this->info('Saudi jobs aggregation finished successfully!');

        return Command::SUCCESS;
    }
}
