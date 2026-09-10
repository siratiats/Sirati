<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cv_analyses', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->after('job_match');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cv_analyses', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
