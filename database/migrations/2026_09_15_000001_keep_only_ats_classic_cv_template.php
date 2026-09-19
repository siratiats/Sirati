<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The product now ships one CV layout. Archive (soft delete) every other
 * template record and make ats-classic-professional the active default.
 */
return new class extends Migration
{
    private const KEEP = 'ats-classic-professional';

    public function up(): void
    {
        $now = now();

        DB::table('cv_templates')
            ->where('slug', '!=', self::KEEP)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'is_default' => false, 'updated_at' => $now]);

        DB::table('cv_templates')
            ->where('slug', self::KEEP)
            ->update([
                'renderer_key' => 'classic_rtl',
                'is_active' => true,
                'is_default' => true,
                'is_premium' => false,
                'deleted_at' => null,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Archived rows can be restored manually; nothing is destroyed here.
    }
};
