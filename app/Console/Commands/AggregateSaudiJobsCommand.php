<?php

namespace App\Console\Commands;

use App\Services\Jobs\SaudiJobAggregatorService;
use Illuminate\Console\Command;

class AggregateSaudiJobsCommand extends Command
{
    protected $signature = 'jobs:aggregate-saudi {--seed : Seed curated Saudi job opportunities into the database} {--purge-seed : Remove previously seeded dummy jobs before aggregating}';

    protected $description = 'Automatically fetch and aggregate job postings from Saudi job feeds with taxonomy linkage';

    public function handle(SaudiJobAggregatorService $aggregator): int
    {
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
