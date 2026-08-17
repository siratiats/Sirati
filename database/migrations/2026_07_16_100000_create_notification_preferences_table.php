<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('language', 2)->default('ar');
            $table->smallInteger('timezone_offset_minutes')->default(180);
            $table->string('preferred_time', 5)->default('18:30');
            $table->string('quiet_hours_start', 5)->default('21:00');
            $table->string('quiet_hours_end', 5)->default('09:00');
            $table->unsignedTinyInteger('max_per_week')->default(4);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'last_active_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
