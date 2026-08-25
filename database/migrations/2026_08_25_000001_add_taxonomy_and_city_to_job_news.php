<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_news', function (Blueprint $table): void {
            if (! Schema::hasColumn('job_news', 'category')) {
                $table->string('category', 60)->nullable()->after('location');
            }
            if (! Schema::hasColumn('job_news', 'job_title_id')) {
                $table->foreignId('job_title_id')->nullable()->after('category')->constrained('job_titles')->nullOnDelete();
            }
            if (! Schema::hasColumn('job_news', 'city')) {
                $table->string('city', 60)->nullable()->after('location');
            }
            if (! Schema::hasColumn('job_news', 'is_remote')) {
                $table->boolean('is_remote')->default(false)->after('city');
            }
            if (! Schema::hasColumn('job_news', 'external_source')) {
                $table->string('external_source', 60)->nullable()->after('source');
            }
            if (! Schema::hasColumn('job_news', 'external_id')) {
                $table->string('external_id', 160)->nullable()->after('external_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_news', function (Blueprint $table): void {
            $columnsToDrop = [];
            foreach (['category', 'job_title_id', 'city', 'is_remote', 'external_source', 'external_id'] as $col) {
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
