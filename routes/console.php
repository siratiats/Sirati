<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('fcm:clean-tokens')->weekly();

// Aggregate Saudi jobs every 3 hours with automatic taxonomy matching
Schedule::command('jobs:aggregate-saudi')
    ->everyThreeHours()
    ->withoutOverlapping();

// Dispatch smart daily notifications based on user activity, timezone & job preferences
Schedule::command('notifications:plan-daily')
    ->hourly()
    ->withoutOverlapping();
