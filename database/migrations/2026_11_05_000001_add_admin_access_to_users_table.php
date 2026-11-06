<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Premium can come from two sources: the App Store (RevenueCat) and an admin.
 * `premium_until` / `subscription_*` stay the access in effect; the admin
 * grant is also remembered here so that a store event (expiry, refund,
 * transfer) never erases access an admin gave, and a store purchase never
 * shortens it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('admin_premium_until')->nullable()->after('subscription_external_id');
            $table->string('admin_plan_id', 100)->nullable()->after('admin_premium_until');
        });

        // Admin grants in effect today ('admin_override' = legacy manual premium).
        DB::table('users')
            ->whereIn('subscription_provider', ['admin', 'admin_override'])
            ->whereNotNull('premium_until')
            ->update([
                'admin_premium_until' => DB::raw('premium_until'),
                'admin_plan_id' => DB::raw('subscription_plan_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['admin_premium_until', 'admin_plan_id']);
        });
    }
};
