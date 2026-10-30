<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\I18n\ServerText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Abobker85/Sirati#12: each kind of server message reaches the app in the
 * app's language (X-App-Language), as the catalog label for that language.
 */
class ServerMessagesLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_labels_follow_the_app_language(): void
    {
        foreach (['ar', 'en'] as $lang) {
            $h = ['X-App-Language' => $lang];
            $this->getJson('/api/mobile/job-news', $h)->assertOk()
                ->assertJsonPath('data.title', ServerText::t('serverJobsTitle', [], $lang))
                ->assertJsonPath('data.subtitle', ServerText::t('serverJobsSubtitle', [], $lang));
            $this->getJson('/api/mobile/education', $h)->assertOk()
                ->assertJsonPath('data.tabs.2.label', ServerText::t('serverEducationTabStudy', [], $lang));
        }
    }

    public function test_plan_names_and_the_free_plan_follow_the_app_language(): void
    {
        foreach (['ar', 'en'] as $lang) {
            $data = $this->getJson('/api/mobile/subscription-plans', ['X-App-Language' => $lang])->assertOk()->json('data');

            foreach ($data['plans'] as $plan) {
                $this->assertSame(ServerText::t($plan['name_key'], [], $lang), $plan['name']);
                // Older builds pick a pair by language; both come from the catalog too.
                $this->assertSame(ServerText::t($plan['name_key'], [], 'ar'), $plan['name_ar']);
                $this->assertSame(ServerText::t($plan['name_key'], [], 'en'), $plan['name_en']);
            }
            $this->assertSame(ServerText::t('serverPlanFreeTitle', [], $lang), $data['free_plan']['title']);
            $this->assertSame($data['free_plan']["includes_{$lang}"], $data['free_plan']['includes']);
        }
    }

    public function test_api_errors_follow_the_app_language(): void
    {
        $user = User::factory()->create(['email' => 'u'.Str::random(6).'@example.com', 'password' => 'Secret-123456']);

        foreach (['ar', 'en'] as $lang) {
            $h = ['X-App-Language' => $lang];

            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong-'.Str::random(6)], $h)
                ->assertStatus(422)
                ->assertJsonPath('message', ServerText::t('serverAuthInvalidCredentials', [], $lang))
                ->assertJsonPath('errors.email.0', ServerText::t('serverAuthInvalidCredentials', [], $lang));

            // Framework errors: no token, then a missing record.
            $this->getJson('/api/auth/me', $h)->assertStatus(401)
                ->assertJsonPath('message', ServerText::t('serverErrorUnauthenticated', [], $lang));
        }

        Sanctum::actingAs($user);
        foreach (['ar', 'en'] as $lang) {
            $this->getJson('/api/cv-analyses/'.mt_rand(100000, 999999), ['X-App-Language' => $lang])->assertStatus(404)
                ->assertJsonPath('message', ServerText::t('serverErrorNotFound', [], $lang));
            $this->postJson('/api/auth/logout', [], ['X-App-Language' => $lang])->assertOk()
                ->assertJsonPath('message', ServerText::t('serverAuthLoggedOut', [], $lang));
        }
    }

    public function test_validation_messages_follow_the_app_language(): void
    {
        foreach (['ar', 'en'] as $lang) {
            $response = $this->postJson('/api/auth/register', ['email' => 'not-an-email', 'password' => 'x'], ['X-App-Language' => $lang])
                ->assertStatus(422);

            $this->assertSame(
                ServerText::t('serverValidationRequired', ['attribute' => ServerText::t('serverAttributeName', [], $lang)], $lang),
                $response->json('errors.name.0'),
            );
            $this->assertSame(ServerText::t('serverValidationEmail', [], $lang), $response->json('errors.email.0'));
            $this->assertSame(
                ServerText::t('serverValidationMinString', ['attribute' => ServerText::t('serverAttributePassword', [], $lang), 'min' => 8], $lang),
                $response->json('errors.password.0'),
            );

            $count = count(collect($response->json('errors'))->flatten());
            $this->assertSame(
                ServerText::t('serverValidationMoreErrors', ['message' => $response->json('errors.name.0'), 'count' => $count - 1], $lang),
                $response->json('message'),
            );
            if ($lang === 'en') {
                $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', json_encode($response->json(), JSON_UNESCAPED_UNICODE));
            }
        }
    }
}
