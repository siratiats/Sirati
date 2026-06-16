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
        Schema::create('cv_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('target_job_title', 160);
            $table->string('original_filename')->nullable();
            $table->string('input_method', 20)->default('paste');
            $table->longText('resume_text');
            $table->unsignedSmallInteger('score_total');
            $table->string('grade', 3);
            $table->unsignedSmallInteger('job_match');
            $table->json('criteria');
            $table->json('strengths');
            $table->json('weaknesses');
            $table->json('keywords_found');
            $table->json('keywords_missing');
            $table->json('quick_wins');
            $table->string('ai_status', 30)->default('not_configured');
            $table->json('ai_feedback')->nullable();
            $table->text('ai_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cv_analyses');
    }
};
