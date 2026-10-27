<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 80);
            $table->string('native_name', 80);
            $table->string('direction', 3)->default('ltr');
            $table->string('fallback_code', 16)->nullable()->default('en');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('app_translation_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->unique();
            $table->text('description')->nullable();
            $table->json('placeholders')->nullable();
            $table->boolean('is_icu')->default(false);
            $table->string('group', 64)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('app_translations', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 16);
            $table->string('key', 191);
            $table->text('value')->nullable();
            $table->text('draft_value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['language_code', 'key']);
            $table->index('key');
            $table->foreign('language_code')->references('code')->on('app_languages')->cascadeOnDelete();
        });

        Schema::create('app_translation_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 16);
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['language_code', 'version']);
            $table->foreign('language_code')->references('code')->on('app_languages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_translation_revisions');
        Schema::dropIfExists('app_translations');
        Schema::dropIfExists('app_translation_keys');
        Schema::dropIfExists('app_languages');
    }
};
