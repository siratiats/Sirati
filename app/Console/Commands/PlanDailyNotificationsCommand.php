<?php

namespace App\Console\Commands;

use App\Services\Notifications\DailyNotificationPlanner;
use Illuminate\Console\Command;

class PlanDailyNotificationsCommand extends Command
{
    protected $signature = 'notifications:plan-daily {--chunk=100 : Users per database chunk}';

    protected $description = 'Evaluate eligible users and queue at most one smart daily notification each';

    public function handle(DailyNotificationPlanner $planner): int
    {
        $chunk = max(10, (int) $this->option('chunk'));
        $result = $planner->run($chunk);

        $this->info(sprintf(
            'Smart notifications: scanned=%d planned=%d skipped=%d',
            $result['scanned'],
            $result['planned'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
