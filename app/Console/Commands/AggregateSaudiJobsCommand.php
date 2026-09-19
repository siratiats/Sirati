<?php

namespace App\Console\Commands;

use App\Services\Jobs\SaudiJobAggregatorService;
use Illuminate\Console\Command;

class AggregateSaudiJobsCommand extends Command
{
    protected $signature = 'jobs:aggregate-saudi
                            {--seed : Seed curated Saudi job opportunities into the database}
                            {--purge-seed : Remove previously seeded dummy jobs before aggregating}
                            {--health : Check feeds health status without running full ingestion}';

    protected $description = 'Automatically fetch and aggregate job postings from Saudi job feeds with taxonomy linkage';

    public function handle(SaudiJobAggregatorService $aggregator): int
    {
        if ($this->option('health')) {
            $this->info('Checking Saudi job feeds health status...');
            $health = $aggregator->getFeedsHealth();

            $rows = array_map(fn ($f) => [
                $f['source'],
                $f['type'],
                $f['healthy'] ? '✅ Healthy' : '❌ Warning/Degraded',
                $f['last_success_at'] ?: 'Never recorded',
                $f['last_fetched_count'],
                $f['last_issue'] ?: 'None',
            ], $health);

            $this->table(['Source', 'Type', 'Status', 'Last Success', 'Last Fetched', 'Last Issue'], $rows);

            return Command::SUCCESS;
        }

        $this->info('Starting automated Saudi jobs aggregation...');

        if ($this->option('purge-seed')) {
            $purged = \App\Models\JobNews::where('external_source', 'saudi_seeder')->delete();
            $this->info("Purged {$purged} previously seeded mock jobs.");
        }

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

        if ($this->option('seed')) {
            $this->info('Seeding authentic curated Saudi job opportunities...');
            $this->call('db:seed', ['--class' => 'JobNewsSeeder']);
            $this->info('Curated Saudi jobs successfully seeded!');
        }

        $this->info('Saudi jobs aggregation finished successfully!');

        return Command::SUCCESS;
    }
}