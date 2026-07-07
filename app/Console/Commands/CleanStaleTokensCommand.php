<?php

namespace App\Console\Commands;

use App\Models\UserFcmToken;
use Illuminate\Console\Command;

class CleanStaleTokensCommand extends Command
{
    protected $signature = 'fcm:clean-tokens {--days=90 : Deactivate tokens not seen in this many days}';

    protected $description = 'Deactivate FCM tokens that have not been seen recently';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $count = UserFcmToken::where('is_active', true)
            ->where('last_seen_at', '<', now()->subDays($days))
            ->update(['is_active' => false]);

        $this->info("Deactivated {$count} stale FCM tokens (not seen in >{$days} days).");

        return self::SUCCESS;
    }
}
