<?php

use App\Services\I18n\ServerText;
use App\Services\Notifications\NotificationTemplateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifications keep their catalog keys and values, so the in-app list
     * shows them in the reader's app language (Abobker85/Sirati#12). Rows sent
     * before this kept only the text, in the language of the day: text that a
     * template produced gets its keys and values back; anything else (admin
     * broadcasts) stays as written.
     */
    public function up(): void
    {
        Schema::table('mobile_notifications', function (Blueprint $table): void {
            $table->string('title_key', 100)->nullable()->after('body');
            $table->string('body_key', 100)->nullable()->after('title_key');
            $table->json('text_params')->nullable()->after('body_key');
        });

        $templates = app(NotificationTemplateService::class);
        $text = app(ServerText::class);

        DB::table('mobile_notifications')
            ->whereNull('title_key')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($templates, $text): void {
                foreach ($rows as $row) {
                    $found = $templates->recognise((string) $row->title, (string) $row->body, $text);
                    if ($found === null) {
                        continue;
                    }
                    DB::table('mobile_notifications')->where('id', $row->id)->update([
                        'title_key' => $found->titleKey,
                        'body_key' => $found->bodyKey,
                        'text_params' => json_encode($found->params, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('mobile_notifications', function (Blueprint $table): void {
            $table->dropColumn(['title_key', 'body_key', 'text_params']);
        });
    }
};
