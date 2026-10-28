<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Apple refresh token (encrypted) and the client id it was issued to,
        // so deleting the account can revoke Sign in with Apple.
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->text('refresh_token')->nullable()->after('email');
            $table->string('client_id', 255)->nullable()->after('refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn(['refresh_token', 'client_id']);
        });
    }
};
