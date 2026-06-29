<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->string('slug', 140)->unique();
            $table->string('renderer_key', 120);
            $table->string('preview_image_path')->nullable();
            $table->string('language_direction', 10)->default('rtl');
            $table->json('supported_languages')->nullable();
            $table->json('supported_sections')->nullable();
            $table->json('color_tokens')->nullable();
            $table->json('config_json')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
            $table->index(['is_default', 'is_active']);
            $table->index('renderer_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_templates');
    }
};
