<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generated_cv_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 128)->nullable();
            $table->char('request_fingerprint', 64);
            $table->string('target_job_title', 160);
            $table->text('job_description_input');
            $table->string('language', 10);
            $table->unsignedSmallInteger('score_total');
            $table->string('grade', 3);
            $table->unsignedSmallInteger('job_match');
            $table->string('category');
            foreach (['criteria', 'keywords_found', 'keywords_missing', 'strengths', 'weaknesses'] as $column) {
                $table->json($column);
            }
            $table->string('ai_status', 30)->default('queued');
            $table->json('ai_feedback')->nullable();
            $table->text('ai_error')->nullable();
            $table->json('applied_suggestion_ids')->nullable();
            $table->char('cv_snapshot_hash', 64);
            $table->json('cv_snapshot');
            $table->foreignId('applied_generated_cv_id')->nullable()->constrained('generated_cvs')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_matches');
    }
};
