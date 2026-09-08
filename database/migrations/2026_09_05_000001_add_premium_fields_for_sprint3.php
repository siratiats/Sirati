<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cv_templates') && ! Schema::hasColumn('cv_templates', 'is_premium')) {
            Schema::table('cv_templates', function (Blueprint $table) {
                $table->boolean('is_premium')->default(false)->after('is_default');
                $table->index(['is_premium', 'is_active']);
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'is_premium')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_premium')->default(false)->after('remember_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cv_templates') && Schema::hasColumn('cv_templates', 'is_premium')) {
            Schema::table('cv_templates', function (Blueprint $table) {
                $table->dropIndex(['is_premium', 'is_active']);
                $table->dropColumn('is_premium');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_premium')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_premium');
            });
        }
    }
};
