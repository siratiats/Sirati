<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_news', function (Blueprint $table): void {
            if (! Schema::hasColumn('job_news', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('is_published');
            }
            if (! Schema::hasColumn('job_news', 'source_type')) {
                $table->string('source_type', 60)->default('general')->after('source');
            }
            if (! Schema::hasColumn('job_news', 'employment_type')) {
                $table->string('employment_type', 60)->default('full_time')->after('source_type');
            }
            if (! Schema::hasColumn('job_news', 'experience_level')) {
                $table->string('experience_level', 60)->nullable()->after('employment_type');
            }
            if (! Schema::hasColumn('job_news', 'salary_min')) {
                $table->unsignedInteger('salary_min')->nullable()->after('experience_level');
            }
            if (! Schema::hasColumn('job_news', 'salary_max')) {
                $table->unsignedInteger('salary_max')->nullable()->after('salary_min');
            }
            if (! Schema::hasColumn('job_news', 'skills_required')) {
                $table->json('skills_required')->nullable()->after('salary_max');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_news', function (Blueprint $table): void {
            $columnsToDrop = [];
            foreach (['is_verified', 'source_type', 'employment_type', 'experience_level', 'salary_min', 'salary_max', 'skills_required'] as $col) {
                if (Schema::hasColumn('job_news', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
