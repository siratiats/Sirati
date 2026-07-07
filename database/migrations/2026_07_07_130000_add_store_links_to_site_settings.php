<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, array<string, string|int>>
     */
    private array $links = [
        ['key' => 'download.ios_url', 'group' => 'download', 'type' => 'url', 'label' => 'App Store link', 'sort_order' => 100],
        ['key' => 'download.android_url', 'group' => 'download', 'type' => 'url', 'label' => 'Google Play link', 'sort_order' => 101],
    ];

    public function up(): void
    {
        // Skip gracefully if the base CMS table isn't present yet in this env.
        if (! DB::getSchemaBuilder()->hasTable('site_settings')) {
            return;
        }

        $now = now();

        foreach ($this->links as $link) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => $link['key']],
                [
                    'group' => $link['group'],
                    'type' => $link['type'],
                    'label' => $link['label'],
                    'sort_order' => $link['sort_order'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')
            ->whereIn('key', array_column($this->links, 'key'))
            ->delete();
    }
};
