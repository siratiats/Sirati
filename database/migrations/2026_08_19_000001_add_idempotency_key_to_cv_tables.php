<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cv_analyses', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->nullable()->after('user_id');
            $table->unique(['user_id', 'idempotency_key'], 'cv_analyses_user_idempotency_unique');
        });

        Schema::table('generated_cvs', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->nullable()->after('user_id');
            $table->unique(['user_id', 'idempotency_key'], 'generated_cvs_user_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cv_analyses', function (Blueprint $table) {
            $table->dropUnique('cv_analyses_user_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('generated_cvs', function (Blueprint $table) {
            $table->dropUnique('generated_cvs_user_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
