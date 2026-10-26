<?php

namespace App\Providers;

use App\Listeners\RecordQueueTiming;
use Illuminate\Support\ServiceProvider;

class QueueTimingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RecordQueueTiming::class);
    }
}
