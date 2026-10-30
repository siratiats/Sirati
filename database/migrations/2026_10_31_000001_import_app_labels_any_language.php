<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        // Imports the labels added by #17 (any server language); admin-published
        // values and drafts are never overwritten.
        Artisan::call('i18n:import-arb');
    }

    public function down(): void
    {
        //
    }
};
