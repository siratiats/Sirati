<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_matches', function (Blueprint $table) {
            $table->json('confirmed_fact_keys')->nullable();
            $table->json('suggestion_application_audit')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_matches', function (Blueprint $table) {
            $table->dropColumn(['confirmed_fact_keys', 'suggestion_application_audit']);
        });
    }
};
