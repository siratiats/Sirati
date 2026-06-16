<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('landing_leads', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 120);
            $table->string('email')->index();
            $table->string('phone', 40)->nullable();
            $table->string('role_interest', 20)->default('both');
            $table->string('target_job_title', 160)->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 80)->default('landing_page');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_leads');
    }
};
