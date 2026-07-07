<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A previous deploy created this table but failed before adding its
        // indexes, leaving a partial table that was never recorded as migrated.
        // Drop any such leftover so the create below runs cleanly.
        Schema::dropIfExists('user_fcm_tokens');

        Schema::create('user_fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 512)->unique();
            $table->string('device_id')->nullable()->index();
            $table->string('platform', 20)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['device_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_fcm_tokens');
    }
};
