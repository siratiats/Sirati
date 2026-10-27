<?php

namespace Tests\Feature;

use App\Models\AppLanguage;
use App\Models\AppTranslation;
use App\Models\AppTranslationRevision;
use App\Models\User;
use App\Services\I18n\TranslationPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppLocalizationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('i18n:import-arb')->assertSuccessful();
    }

    private function publishedFromDb(string $code): array
    {
        return app(TranslationPublisher::class)->publishedStrings($code);
    }

    public function test_languages_lists_enabled_languages_in_sort_order(): void
    {
        AppLanguage::query()->create(['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'fallback_code' => 'en', 'is_enabled' => true, 'sort_order' => 0]);
        AppLanguage::query()->create(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'direction' => 'ltr', 'is_enabled' => false, 'sort_order' => 0]);
        AppLanguage::query()->where('code', 'ar')->update(['sort_order' => 5]);

        $response = $this->getJson('/api/i18n/languages')->assertOk();

        $this->assertSame(['default', 'data'], array_keys($response->json()));
        $this->assertSame('ar', $response->json('default'));

        $expected = AppLanguage::query()->enabled()->ordered()->get();
        $this->assertSame($expected->pluck('code')->all(), array_column($response->json('data'), 'code'));
        $this->assertNotContains('de', array_column($response->json('data'), 'code'));
        $sortOrders = $expected->pluck('sort_order')->all();
        $sorted = $sortOrders;
        sort($sorted);
        $this->assertSame($sorted, $sortOrders);

        foreach ($response->json('data') as $row) {
            $this->assertSame(['code', 'name', 'native_name', 'direction', 'fallback', 'version'], array_keys($row));
            $language = $expected->firstWhere('code', $row['code']);
            $this->assertSame($language->version, $row['version']);
            $this->assertSame($language->direction, $row['direction']);
            $this->assertNotSame($row['code'], $row['fallback']);
        }

        $ar = collect($response->json('data'))->firstWhere('code', 'ar');
        $this->assertSame(['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'fallback' => 'en', 'version' => 1], $ar);
    }

    public function test_strings_payload_shape_headers_and_published_values_only(): void
    {
        foreach (['ar', 'en'] as $code) {
            $language = AppLanguage::query()->where('code', $code)->firstOrFail();
            // A draft and a blank value must never reach the app.
            $draftRow = AppTranslation::query()->where('language_code', $code)->orderBy('id')->firstOrFail();
            $draftRow->update(['draft_value' => 'UNPUBLISHED DRAFT']);
            AppTranslation::query()->where('language_code', $code)->orderBy('id')->skip(1)->firstOrFail()->update(['value' => "  \u{200F} "]);

            $response = $this->get("/api/i18n/languages/{$code}/strings")->assertOk();

            $this->assertSame(['code', 'direction', 'fallback', 'version', 'strings'], array_keys($response->json()));
            $this->assertSame($code, $response->json('code'));
            $this->assertSame($language->direction, $response->json('direction'));
            $this->assertSame($language->effectiveFallback(), $response->json('fallback'));
            $this->assertSame($language->version, $response->json('version'));
            $response->assertHeader('ETag', '"'.$code.'-'.$language->version.'"');
            $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));

            $strings = $response->json('strings');
            $this->assertNotContains('UNPUBLISHED DRAFT', $strings);
            foreach ($strings as $key => $value) {
                $this->assertIsString($value);
                $this->assertNotSame('', trim($value, " \u{200F}"), $key);
            }
            $expected = AppTranslation::query()->where('language_code', $code)->whereNotNull('value')->get()
                ->filter(fn ($row) => trim($row->value, " \u{200F}") !== '')
                ->pluck('value', 'key')->all();
            ksort($expected);
            ksort($strings);
            $this->assertSame($expected, $strings);
        }
    }

    public function test_strings_is_a_json_object_even_when_empty(): void
    {
        AppLanguage::query()->create(['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_enabled' => true]);

        $response = $this->get('/api/i18n/languages/fr/strings')->assertOk();

        $this->assertStringContainsString('"strings":{}', $response->getContent());
        $response->assertHeader('ETag', '"fr-0"');
    }

    public function test_if_none_match_returns_304_for_the_current_etag_only(): void
    {
        $etag = AppLanguage::query()->where('code', 'ar')->firstOrFail()->etag();

        foreach ([$etag, 'W/'.$etag, '"zz-1", '.$etag, '*'] as $header) {
            $response = $this->get('/api/i18n/languages/ar/strings', ['If-None-Match' => $header]);
            $response->assertStatus(304);
            $this->assertSame('', $response->getContent());
            $response->assertHeader('ETag', $etag);
        }

        foreach (['"ar-0"', '"en-1"', '"ar-1 "', 'ar-1x', ''] as $header) {
            $this->get('/api/i18n/languages/ar/strings', ['If-None-Match' => $header])->assertOk();
        }
    }

    public function test_unknown_and_disabled_languages_are_404_even_with_a_matching_etag(): void
    {
        $this->getJson('/api/i18n/languages/xx/strings')->assertNotFound();

        $en = AppLanguage::query()->where('code', 'en')->firstOrFail();
        $etag = $en->etag();
        $en->update(['is_enabled' => false]);

        $this->getJson('/api/i18n/languages/en/strings')->assertNotFound();
        $this->get('/api/i18n/languages/en/strings', ['If-None-Match' => $etag])->assertNotFound();
        $this->assertNotContains('en', array_column($this->getJson('/api/i18n/languages')->json('data'), 'code'));
    }

    public function test_publishing_bumps_version_etag_and_never_serves_a_stale_cached_payload(): void
    {
        $admin = User::factory()->create();
        $ar = AppLanguage::query()->where('code', 'ar')->firstOrFail();
        $before = $this->get('/api/i18n/languages/ar/strings')->assertOk();
        $oldEtag = $before->headers->get('ETag');

        $key = AppTranslation::query()->where('language_code', 'ar')->where('key', 'retry')->firstOrFail();
        $this->actingAs($admin)->putJson(route('admin.labels.update', 'ar'), ['key' => 'retry', 'value' => 'أعد المحاولة الآن'])->assertOk();

        // Draft saved: public payload unchanged.
        $this->assertSame($before->json('strings'), $this->get('/api/i18n/languages/ar/strings')->json('strings'));

        $this->actingAs($admin)->post(route('admin.languages.publish', 'ar'))->assertRedirect();

        $after = $this->get('/api/i18n/languages/ar/strings', ['If-None-Match' => $oldEtag])->assertOk();
        $this->assertSame($ar->version + 1, $after->json('version'));
        $after->assertHeader('ETag', '"ar-'.($ar->version + 1).'"');
        $this->assertSame('أعد المحاولة الآن', $after->json('strings.retry'));
        $this->assertSame($this->publishedFromDb('ar'), $after->json('strings'));
        $this->assertSame($ar->version + 1, collect($this->getJson('/api/i18n/languages')->json('data'))->firstWhere('code', 'ar')['version']);

        $revision = AppTranslationRevision::query()->where('language_code', 'ar')->where('version', $ar->version + 1)->firstOrFail();
        $this->assertSame($after->json('strings'), $revision->snapshot);
        $this->assertSame($admin->id, $revision->published_by);
        $this->assertNull($key->fresh()->draft_value);
    }

    public function test_public_endpoints_are_throttled(): void
    {
        $this->assertContains('throttle:60,1', app('router')->getRoutes()->getByName('api.i18n.strings')->gatherMiddleware());
        $this->assertContains('throttle:60,1', app('router')->getRoutes()->getByName('api.i18n.languages')->gatherMiddleware());
    }
}
