<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\MobileNotification;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Services\FirebaseNotificationService;
use App\Services\I18n\ServerText;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\Notifications\NotificationTemplateService;
use App\Support\DailyNotificationCandidate;
use App\Support\NotificationText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Abobker85/Sirati#12: notification text follows the reader's app language,
 * in the in-app list and in the push itself, including rows stored before.
 */
class ServerNotificationTextTest extends TestCase
{
    use RefreshDatabase;

    private const TEMPLATE_KEYS = ['first_analysis', 'low_ats_score', 'analysis_to_cv', 'stale_cv', 'matching_job', 'relevant_education', 'anything_else'];

    public function test_templated_notifications_show_in_the_readers_language_for_any_values(): void
    {
        mt_srand(4);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $templates = app(NotificationTemplateService::class);
        $expected = [];

        foreach (range(1, 30) as $i) {
            $text = $templates->text($this->randomCandidate());
            $sentIn = mt_rand(0, 1) ? 'ar' : 'en';
            $row = $user->mobileNotifications()->create(['type' => 'info', ...$text->attributes($sentIn)]);
            $expected[$row->id] = $text;
        }

        foreach (['ar', 'en'] as $lang) {
            $items = collect($this->getJson('/api/mobile/notifications', ['X-App-Language' => $lang])->assertOk()->json('data.items'));
            $this->assertCount(30, $items);
            foreach ($items as $item) {
                $text = $expected[$item['id']];
                $this->assertSame($text->title($lang), $item['title']);
                $this->assertSame($text->body($lang), $item['body']);
                $this->assertSame($lang === 'en', ! preg_match('/\p{Arabic}/u', $item['title']), $item['title']);
            }
        }
    }

    public function test_a_notification_written_by_hand_is_shown_as_written(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $title = 'عرض خاص '.Str::random(5);
        $user->mobileNotifications()->create(['type' => 'info', 'title' => $title, 'body' => 'نص حر']);

        foreach (['ar', 'en'] as $lang) {
            $this->getJson('/api/mobile/notifications', ['X-App-Language' => $lang])
                ->assertOk()->assertJsonPath('data.items.0.title', $title)->assertJsonPath('data.items.0.body', 'نص حر');
        }
    }

    public function test_the_push_is_sent_in_the_users_notification_language(): void
    {
        foreach (['ar', 'en'] as $lang) {
            $user = User::factory()->create();
            UserFcmToken::query()->create(['user_id' => $user->id, 'token' => 'tok-'.Str::random(12), 'platform' => 'android', 'is_active' => true]);
            app(NotificationPreferenceService::class)->update($user, ['language' => $lang]);

            $sent = [];
            $service = Mockery::mock(FirebaseNotificationService::class)->makePartial();
            $service->shouldReceive('sendToTokens')->andReturnUsing(function ($tokens, $title, $body) use (&$sent) {
                $sent[] = [$title, $body];

                return ['successes' => 1];
            });

            foreach ([['serverNotifyCvReadyTitle', 'serverNotifyCvReadyBody'], ['serverNotifyCvFailedTitle', 'serverNotifyCvFailedBody']] as [$t, $b]) {
                $text = new NotificationText($t, $b);
                // The job was queued with another language's copy: the user's language wins.
                (new SendPushNotificationJob($user->id, 'stale', 'stale', 'cv_ready', 'screen', 'my-cvs/1', [], $text->toArray()))->handle($service);
            }

            $this->assertSame([
                [ServerText::t('serverNotifyCvReadyTitle', [], $lang), ServerText::t('serverNotifyCvReadyBody', [], $lang)],
                [ServerText::t('serverNotifyCvFailedTitle', [], $lang), ServerText::t('serverNotifyCvFailedBody', [], $lang)],
            ], $sent);

            $stored = MobileNotification::query()->where('user_id', $user->id)->orderBy('id')->get();
            $this->assertSame(['serverNotifyCvReadyTitle', 'serverNotifyCvFailedTitle'], $stored->pluck('title_key')->all());
            $this->assertSame($sent[0][0], $stored[0]->title);
        }
    }

    public function test_the_new_job_push_never_invents_a_company_or_place(): void
    {
        mt_srand(8);
        $templates = app(NotificationTemplateService::class);
        foreach (range(1, 20) as $_) {
            $company = mt_rand(0, 1) ? 'Co '.Str::random(4) : null;
            $location = mt_rand(0, 1) ? 'Loc '.Str::random(4) : null;
            $title = 'Role '.Str::random(6);
            $text = $templates->newJob($title, $company, $location);

            foreach (['ar', 'en'] as $lang) {
                $body = $text->body($lang);
                $this->assertStringContainsString($title, $body);
                $company === null ? $this->assertStringNotContainsString(' at ', $body) : $this->assertStringContainsString($company, $body);
                $location === null ? $this->assertStringNotContainsString(' in ', $body) : $this->assertStringContainsString($location, $body);
                $this->assertStringNotContainsString('{', $body);
            }
        }
    }

    public function test_the_migration_gives_rows_stored_before_their_keys_back(): void
    {
        mt_srand(21);
        $migration = require database_path('migrations/2026_10_30_100002_add_text_keys_to_mobile_notifications.php');
        $templates = app(NotificationTemplateService::class);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $migration->down();
        $expected = [];
        foreach (range(1, 40) as $_) {
            $text = $templates->text($this->randomCandidate());
            $lang = mt_rand(0, 1) ? 'ar' : 'en';
            $id = \DB::table('mobile_notifications')->insertGetId([
                'user_id' => $user->id, 'type' => 'info',
                'title' => $text->title($lang), 'body' => $text->body($lang),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $expected[$id] = $text;
        }
        $handWritten = \DB::table('mobile_notifications')->insertGetId([
            'user_id' => $user->id, 'type' => 'info', 'title' => 'Hand written '.Str::random(4), 'body' => 'Free text',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->app->forgetScopedInstances();
        $migration->up();

        foreach ($expected as $id => $text) {
            $row = MobileNotification::query()->findOrFail($id);
            $this->assertSame($text->titleKey, $row->title_key);
            foreach (['ar', 'en'] as $lang) {
                $this->assertSame($text->title($lang), $row->displayTitle($lang));
                $this->assertSame($text->body($lang), $row->displayBody($lang), "{$id} {$lang}");
            }
        }
        $this->assertNull(MobileNotification::query()->findOrFail($handWritten)->title_key);
    }

    private function randomCandidate(): DailyNotificationCandidate
    {
        $key = self::TEMPLATE_KEYS[mt_rand(0, count(self::TEMPLATE_KEYS) - 1)];
        $maybe = fn (string $value) => mt_rand(0, 1) ? $value : null;

        return new DailyNotificationCandidate(
            ruleKey: $key,
            templateKey: $key,
            type: 'info',
            actionType: 'screen',
            actionUrl: 'notifications',
            context: array_filter([
                'score' => mt_rand(0, 1) ? mt_rand(0, 100) : null,
                'tip' => $maybe('Add metrics '.Str::random(5)),
                'job_title' => $maybe(mt_rand(0, 1) ? 'محاسب '.Str::random(3) : 'Engineer '.Str::random(3)),
                'company' => $maybe('Co '.Str::random(4)),
                'title' => $maybe('Read '.Str::random(6)),
            ], fn ($v) => $v !== null),
        );
    }
}
