<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        // Imports the Premium marker and profile subscription labels so admins can
        // edit them, and refreshes serverPlanSixMonthsName to Latin digits. Admin-
        // published values and drafts are never overwritten. A failed import fails
        // the migration, so it is not recorded as done and the next deploy retries.
        $exitCode = Artisan::call('i18n:import-arb');
        if ($exitCode !== 0) {
            throw new RuntimeException(
                "i18n:import-arb failed with exit code {$exitCode}: ".trim(Artisan::output())
            );
        }
    }

    public function down(): void
    {
        //
    }
};
