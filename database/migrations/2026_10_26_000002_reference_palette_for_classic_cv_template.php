<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Job titles and skill categories use the heading navy, as in the reference
 * CV layout. Only rows still on a seeded palette change; a palette an admin
 * picked stays.
 */
return new class extends Migration
{
    private const SEEDED = [
        ['primary' => '#1f2937', 'accent' => '#2563eb'],
        ['primary' => '#1f3864', 'accent' => '#2e4a7d'],
    ];

    public function up(): void
    {
        DB::table('cv_templates')->where('slug', 'ats-classic-professional')->get(['id', 'color_tokens'])
            ->each(function (object $row): void {
                $tokens = json_decode((string) $row->color_tokens, true);
                if (! is_array($tokens) || ! in_array(array_change_key_case($tokens), self::SEEDED, false)) {
                    return;
                }
                DB::table('cv_templates')->where('id', $row->id)->update([
                    'color_tokens' => json_encode(['primary' => '#1f3864', 'accent' => '#1f3864']),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('cv_templates')->where('slug', 'ats-classic-professional')
            ->where('color_tokens', json_encode(['primary' => '#1f3864', 'accent' => '#1f3864']))
            ->update(['color_tokens' => json_encode(['primary' => '#1f3864', 'accent' => '#2e4a7d'])]);
    }
};
