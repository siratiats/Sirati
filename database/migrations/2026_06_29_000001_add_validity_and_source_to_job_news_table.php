<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_news', function (Blueprint $table): void {
            $table->string('apply_url', 500)->nullable()->after('url');
            $table->date('valid_from')->nullable()->after('published_at');
            $table->date('valid_until')->nullable()->after('valid_from');
            $table->string('source', 30)->default('manual')->after('is_published');
            $table->string('source_row_key', 191)->nullable()->after('source');

            $table->index(['language', 'is_published', 'valid_until'], 'job_news_lang_pub_until_idx');
            $table->index('source_row_key', 'job_news_source_row_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('job_news', function (Blueprint $table): void {
            $table->dropIndex('job_news_lang_pub_until_idx');
            $table->dropIndex('job_news_source_row_key_idx');
            $table->dropColumn([
                'apply_url',
                'valid_from',
                'valid_until',
                'source',
                'source_row_key',
            ]);
        });
    }
};
