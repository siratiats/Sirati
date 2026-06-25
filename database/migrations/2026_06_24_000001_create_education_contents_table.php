<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_contents', function (Blueprint $table) {
            $table->id();
            $table->string('language', 2)->default('ar');
            $table->string('type')->default('study');
            $table->string('title');
            $table->text('body');
            $table->string('duration_label')->nullable();
            $table->string('target_role')->nullable();
            $table->string('badge')->nullable();
            $table->string('button_label')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_contents');
    }
};
