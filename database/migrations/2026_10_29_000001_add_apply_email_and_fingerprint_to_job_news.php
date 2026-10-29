<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_news', function (Blueprint $table): void {
            if (! Schema::hasColumn('job_news', 'apply_email')) {
                $table->string('apply_email', 190)->nullable()->after('apply_url');
            }
            if (! Schema::hasColumn('job_news', 'fingerprint')) {
                $table->string('fingerprint', 40)->nullable()->after('external_id');
                $table->index('fingerprint');
            }
        });

        // Existing rows carry Telegram "apply" links and placeholder employers.
        Artisan::call('jobs:repair-listings');
    }

    public function down(): void
    {
        Schema::table('job_news', function (Blueprint $table): void {
            if (Schema::hasColumn('job_news', 'fingerprint')) {
                $table->dropIndex(['fingerprint']);
                $table->dropColumn('fingerprint');
            }
            if (Schema::hasColumn('job_news', 'apply_email')) {
                $table->dropColumn('apply_email');
            }
        });
    }
};
