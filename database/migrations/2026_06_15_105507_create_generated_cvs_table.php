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
        Schema::create('generated_cvs', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 160);
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('linkedin')->nullable();
            $table->string('location')->nullable();
            $table->string('target_job_title', 160);
            $table->string('language', 10)->default('ar');
            $table->text('summary_input')->nullable();
            $table->text('skills_input');
            $table->longText('experience_input');
            $table->text('education_input');
            $table->text('certifications_input')->nullable();
            $table->longText('generated_markdown');
            $table->json('form_payload');
            $table->string('ai_status', 30)->default('not_configured');
            $table->json('ai_output')->nullable();
            $table->text('ai_error')->nullable();
            $table->unsignedSmallInteger('score_total')->nullable();
            $table->string('grade', 3)->nullable();
            $table->json('criteria')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_cvs');
    }
};
