<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per CV ever created (new, duplicate or Job Match copy). The free
 * daily CV quota counts these instead of the CVs that still exist, so
 * deleting a CV never gives a slot back. Rows go with the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_creations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });

        // The quota only looks at today; carry over the CVs that still exist
        // from yesterday on so the switch-over day is counted too.
        DB::table('cv_creations')->insertUsing(
            ['user_id', 'created_at'],
            DB::table('generated_cvs')
                ->select('user_id', 'created_at')
                ->whereNotNull('user_id')
                ->whereNotNull('created_at')
                ->where('created_at', '>=', now()->subDay()->startOfDay()),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_creations');
    }
};
