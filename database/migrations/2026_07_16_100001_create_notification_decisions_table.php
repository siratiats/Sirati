<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mobile_notification_id')->nullable()
                ->constrained('mobile_notifications')->nullOnDelete();
            $table->string('rule_key', 60);
            $table->string('template_key', 80);
            $table->json('context')->nullable();
            $table->string('idempotency_key', 180)->unique();
            $table->timestamp('scheduled_for')->nullable();
            $table->string('status', 20)->default('planned');
            $table->string('skip_reason', 80)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('conversion_type', 60)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'notification_decisions_user_status_created_idx');
            $table->index(['rule_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_decisions');
    }
};
