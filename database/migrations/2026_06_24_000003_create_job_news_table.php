<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_news', function (Blueprint $table) {
            $table->id();
            $table->string('language', 10)->default('ar');
            $table->string('title', 180);
            $table->string('company', 160)->nullable();
            $table->string('location', 160)->nullable();
            $table->text('body');
            $table->string('url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_news');
    }
};
