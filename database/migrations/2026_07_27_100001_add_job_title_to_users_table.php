<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('job_title_id')
                ->nullable()
                ->after('location')
                ->constrained('job_titles')
                ->nullOnDelete();
            $table->string('job_title_other', 120)
                ->nullable()
                ->after('job_title_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_title_id');
            $table->dropColumn('job_title_other');
        });
    }
};
