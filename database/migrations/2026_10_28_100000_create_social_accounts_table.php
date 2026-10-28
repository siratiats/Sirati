<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_subject', 255);
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_subject']);
            $table->index(['user_id', 'provider']);
        });

        // Accounts created through Google/Apple have no password until the
        // user sets one with the reset-password flow.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
