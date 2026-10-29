<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        // Stored Telegram posts get the new title, language and city rules;
        // image-only headers, courses and unchecked remote jobs are unpublished.
        Artisan::call('jobs:repair-listings');
    }

    public function down(): void
    {
        //
    }
};
