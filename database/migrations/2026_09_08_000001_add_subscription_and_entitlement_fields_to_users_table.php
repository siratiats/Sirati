<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('premium_until')->nullable()->after('is_premium')->index();
            $table->string('subscription_plan_id', 100)->nullable()->after('premium_until');
            $table->string('subscription_provider', 50)->nullable()->after('subscription_plan_id');
            $table->string('subscription_external_id', 191)->nullable()->after('subscription_provider');
        });

        // Backfill legacy users whose is_premium flag was manually set
        DB::table('users')->where('is_premium', true)->update([
            'premium_until' => '2099-12-31 23:59:59',
            'subscription_plan_id' => 'legacy_admin',
            'subscription_provider' => 'admin_override',
        ]);

        Schema::create('subscription_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 191)->unique();
            $table->string('event_type', 100)->index();
            $table->string('app_user_id', 191)->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50)->index();
            $table->string('reason', 255)->nullable();
            $table->timestamp('previous_premium_until')->nullable();
            $table->timestamp('new_premium_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_audit_logs');
        Schema::dropIfExists('subscription_webhook_events');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['premium_until']);
            $table->dropColumn([
                'premium_until',
                'subscription_plan_id',
                'subscription_provider',
                'subscription_external_id',
            ]);
        });
    }
};
