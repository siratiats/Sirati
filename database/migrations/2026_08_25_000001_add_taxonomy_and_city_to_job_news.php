<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_news', function (Blueprint $table): void {
            $table->string('category', 60)->nullable()->after('location');
            $table->foreignId('job_title_id')->nullable()->after('category')->constrained('job_titles')->nullOnDelete();
            $table->string('city', 60)->nullable()->after('location');
            $table->boolean('is_remote')->default(false)->after('city');
            $table->string('external_source', 60)->nullable()->after('source');
            $table->string('external_id', 160)->nullable()->after('external_source');

            $table->index(['job_title_id', 'is_published'], 'job_news_title_pub_idx');
            $table->index(['city', 'is_published'], 'job_news_city_pub_idx');
            $table->index(['is_remote', 'is_published'], 'job_news_remote_pub_idx');
            $table->index(['category', 'is_published'], 'job_news_category_pub_idx');
        });
    }

    public function down(): void
    {
        Schema::table('job_news', function (Blueprint $table): void {
            $table->dropForeign(['job_title_id']);
            $table->dropIndex('job_news_title_pub_idx');
            $table->dropIndex('job_news_city_pub_idx');
            $table->dropIndex('job_news_remote_pub_idx');
            $table->dropIndex('job_news_category_pub_idx');
            $table->dropColumn(['category', 'job_title_id', 'city', 'is_remote', 'external_source', 'external_id']);
        });
    }
};
