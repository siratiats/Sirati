<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        // The server* labels (Abobker85/Sirati#12) join the catalog so the
        // admin can edit them; admin-published values are never overwritten.
        Artisan::call('i18n:import-arb');
    }

    public function down(): void
    {
        //
    }
};
