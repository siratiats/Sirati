<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('type', 60)->default('info');
            $table->string('action_type', 60)->nullable();
            $table->string('action_url')->nullable();
            $table->string('audience', 40)->default('all');
            $table->string('sent_by')->nullable(); // admin email for audit
            $table->unsignedInteger('recipients_queued')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('chunks_total')->default(0);
            $table->unsignedInteger('chunks_completed')->default(0);
            $table->string('status', 20)->default('queued'); // queued|sending|completed
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaigns');
    }
};
