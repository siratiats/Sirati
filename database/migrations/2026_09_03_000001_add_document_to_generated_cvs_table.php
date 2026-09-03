<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('generated_cvs', 'document')) {
            return;
        }

        Schema::table('generated_cvs', function (Blueprint $table) {
            $table->json('document')->nullable()->after('form_payload');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('generated_cvs', 'document')) {
            return;
        }

        Schema::table('generated_cvs', function (Blueprint $table) {
            $table->dropColumn('document');
        });
    }
};
