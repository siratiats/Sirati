<?php

namespace Tests\Feature;

use App\Models\AppLanguage;
use App\Models\AppTranslation;
use App\Models\AppTranslationKey;
use App\Models\AppTranslationRevision;
use App\Models\User;
use App\Services\I18n\TranslationPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Tests\TestCase;

class AppLocalizationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('i18n:import-arb')->assertSuccessful();
        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        config(['services.admin.emails' => ['admin@example.com']]);
    }

    /** @return list<Route> */
    private function localizationRoutes(): array
    {
        return array_values(array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn (Route $route) => preg_match('/^admin\.(languages|labels)\./', (string) $route->getName()) === 1,
        ));
    }

    private function callRoute(Route $route): \Illuminate\Testing\TestResponse
    {
        $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
        $uri = '/'.str_replace('{code}', 'en', $route->uri());

        return $this->json($method, $uri, ['key' => 'retry', 'value' => 'x', 'values' => ['retry' => 'x'], 'revision' => 1, 'all' => 1]);
    }

    private function publicStrings(string $code): array
    {
        return $this->getJson("/api/i18n/languages/{$code}/strings")->json('strings');
    }

    public function test_every_localization_admin_route_rejects_non_admins_and_guests(): void
    {
        $routes = $this->localizationRoutes();
        $names = array_map(fn (Route $route) => $route->getName(), $routes);
        sort($names);
        $this->assertSame([
            'admin.labels.bulk', 'admin.labels.discard', 'admin.labels.export', 'admin.labels.import', 'admin.labels.index', 'admin.labels.update',
            'admin.languages.destroy', 'admin.languages.index', 'admin.languages.publish', 'admin.languages.rollback', 'admin.languages.store', 'admin.languages.update',
        ], $names);

        $snapshot = fn () => [
            AppLanguage::query()->orderBy('code')->get(['code', 'version', 'is_enabled'])->toArray(),
            AppTranslation::query()->orderBy('id')->get(['key', 'value', 'draft_value'])->toArray(),
        ];
        $before = $snapshot();

        $outsider = User::factory()->create(['email' => 'outsider@example.com']);
        foreach ($routes as $route) {
            $this->actingAs($outsider);
            $this->callRoute($route)->assertForbidden();
        }

        // Fails closed: an empty ADMIN_EMAILS outside local/testing denies everyone, admins included.
        config(['services.admin.emails' => []]);
        $this->app['env'] = 'production';
        // CSRF is only skipped under the testing env; this check is about the admin gate.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        foreach ($routes as $route) {
            $this->actingAs($this->admin);
            $this->callRoute($route)->assertForbidden();
        }
        $this->app['env'] = 'testing';

        auth()->logout();
        foreach ($routes as $route) {
            $this->assertContains($this->callRoute($route)->status(), [401, 302]);
        }

        $this->assertSame($before, $snapshot());
    }

    public function test_pages_render_with_coverage_and_drafts(): void
    {
        $this->actingAs($this->admin)->get(route('admin.languages.index'))->assertOk()->assertSee('100%')->assertSee('العربية');

        AppTranslation::query()->where('language_code', 'en')->where('key', 'retry')->update(['draft_value' => 'Retry now']);
        $this->actingAs($this->admin)->get(route('admin.labels.index', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('data-label-key="retry"', false)
            ->assertSee('Retry now')
            ->assertSee(route('admin.labels.update', 'en'), false);
    }

    public function test_label_filters_and_search_match_their_definitions(): void
    {
        $keys = AppTranslationKey::query()->orderBy('id')->pluck('key')->all();
        AppTranslation::query()->where('language_code', 'en')->whereIn('key', array_slice($keys, 0, 7))->update(['value' => null]);
        AppTranslation::query()->where('language_code', 'en')->whereIn('key', array_slice($keys, 10, 4))->update(['draft_value' => 'draft']);
        AppTranslation::query()->where('language_code', 'en')->whereIn('key', array_slice($keys, 20, 3))->update(['updated_by' => $this->admin->id]);

        $listed = function (array $query): array {
            $html = $this->actingAs($this->admin)->get(route('admin.labels.index', ['lang' => 'en'] + $query))->assertOk()->getContent();
            preg_match_all('/data-label-key="([^"]+)"/', $html, $matches);

            return $matches[1];
        };

        $rows = AppTranslation::query()->where('language_code', 'en')->get()->keyBy('key');
        $expect = fn (callable $predicate) => array_values(array_slice(array_filter($keys, fn ($key) => $predicate($rows->get($key), AppTranslationKey::query()->where('key', $key)->first())), 0, 50));

        $this->assertSame($expect(fn ($row) => $row === null || $row->value === null), $listed(['filter' => 'missing']));
        $this->assertSame($expect(fn ($row) => $row?->draft_value !== null), $listed(['filter' => 'draft']));
        $this->assertSame($expect(fn ($row) => $row?->value !== null && $row->updated_by !== null), $listed(['filter' => 'changed']));
        $this->assertSame($expect(fn ($row, $key) => $key->placeholders !== []), $listed(['filter' => 'placeholders']));

        // Search covers key, base (ar) text and target text, case-insensitively for the key.
        $arabic = AppTranslation::query()->where('language_code', 'ar')->where('key', 'retry')->value('value');
        $this->assertContains('retry', $listed(['q' => 'RETRY']));
        $this->assertContains('retry', $listed(['q' => $arabic]));
        $this->assertContains('retry', $listed(['q' => $rows['retry']->value]));
        // LIKE wildcards are literal.
        $this->assertSame([], $listed(['q' => '%_%_%_%_%_%_%_%_%_%_%']));
    }

    public function test_draft_update_validates_placeholders_and_answers_json(): void
    {
        $key = AppTranslationKey::query()->where('key', 'greetingName')->firstOrFail();
        $this->assertSame(['name'], $key->placeholderNames());

        foreach (['Hi {nmae}', 'Hi', 'Hi {name} {extra}', 'Hi {name', '{name, plural, other{x}}'] as $bad) {
            $this->actingAs($this->admin)
                ->putJson(route('admin.labels.update', 'en'), ['key' => 'greetingName', 'value' => $bad])
                ->assertStatus(422)
                ->assertJsonStructure(['message', 'errors' => ['value']]);
        }
        $this->assertNull(AppTranslation::query()->where('language_code', 'en')->where('key', 'greetingName')->value('draft_value'));

        $this->actingAs($this->admin)->putJson(route('admin.labels.update', 'en'), ['key' => 'noSuchKey', 'value' => 'x'])->assertStatus(422);

        $this->actingAs($this->admin)
            ->putJson(route('admin.labels.update', 'en'), ['key' => 'greetingName', 'value' => 'Welcome back, {name}'])
            ->assertOk()
            ->assertJson(['key' => 'greetingName', 'draft_value' => 'Welcome back, {name}', 'has_draft' => true, 'status' => 'draft']);

        // Saving the published text again clears the draft.
        $published = AppTranslation::query()->where('language_code', 'en')->where('key', 'greetingName')->value('value');
        $this->actingAs($this->admin)
            ->putJson(route('admin.labels.update', 'en'), ['key' => 'greetingName', 'value' => $published])
            ->assertOk()
            ->assertJson(['has_draft' => false, 'draft_value' => null]);
    }

    public function test_bulk_save_is_all_or_nothing_and_discard_removes_drafts(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.labels.bulk', 'en'), ['values' => [
            'retry' => 'Retry!',
            'greetingName' => 'Hello {wrong}',
        ]])->assertStatus(422)->assertJsonStructure(['errors' => ['greetingName']]);
        $this->assertSame(0, AppTranslation::query()->whereNotNull('draft_value')->count());

        $this->actingAs($this->admin)->postJson(route('admin.labels.bulk', 'en'), ['values' => [
            'retry' => 'Retry!',
            'greetingName' => 'Hello {name}!',
        ]])->assertOk()->assertJson(['saved' => 2]);
        $this->assertSame(2, AppTranslation::query()->where('language_code', 'en')->whereNotNull('draft_value')->count());

        $this->actingAs($this->admin)->postJson(route('admin.labels.discard', 'en'), ['key' => 'retry'])
            ->assertOk()->assertJson(['discarded' => 1, 'data' => ['has_draft' => false]]);
        $this->actingAs($this->admin)->post(route('admin.labels.discard', 'en'), ['all' => 1])->assertRedirect();
        $this->assertSame(0, AppTranslation::query()->whereNotNull('draft_value')->count());
    }

    public function test_publish_copies_drafts_and_refuses_invalid_ones(): void
    {
        $en = AppLanguage::query()->where('code', 'en')->firstOrFail();

        // Nothing to publish.
        $this->actingAs($this->admin)->post(route('admin.languages.publish', 'en'))->assertSessionHas('i18n_error');
        $this->assertSame($en->version, $en->fresh()->version);

        // A draft that bypassed validation (or predates a catalog change) blocks the publish.
        AppTranslation::query()->where('language_code', 'en')->where('key', 'retry')->update(['draft_value' => 'Retry']);
        AppTranslation::query()->where('language_code', 'en')->where('key', 'greetingName')->update(['draft_value' => 'Hi {user}']);
        $before = $this->publicStrings('en');
        $this->actingAs($this->admin)->post(route('admin.languages.publish', 'en'))->assertSessionHas('i18n_errors');
        $this->assertSame($en->version, $en->fresh()->version);
        $this->assertSame($before, $this->publicStrings('en'));

        AppTranslation::query()->where('language_code', 'en')->where('key', 'greetingName')->update(['draft_value' => '']);
        $this->actingAs($this->admin)->post(route('admin.languages.publish', 'en'))->assertSessionHas('status');

        $strings = $this->publicStrings('en');
        $this->assertSame($en->version + 1, $en->fresh()->version);
        $this->assertSame('Retry', $strings['retry']);
        // A blank draft unpublishes the key so the app falls back.
        $this->assertArrayNotHasKey('greetingName', $strings);
        $this->assertSame(0, AppTranslation::query()->where('language_code', 'en')->whereNotNull('draft_value')->count());
        $this->assertSame($strings, AppTranslationRevision::query()->where('language_code', 'en')->where('version', $en->version + 1)->value('snapshot'));
    }

    public function test_rollback_republishes_an_old_snapshot_as_a_new_version(): void
    {
        $original = $this->publicStrings('en');
        $firstRevision = AppTranslationRevision::query()->where('language_code', 'en')->orderBy('version')->firstOrFail();

        $this->actingAs($this->admin)->putJson(route('admin.labels.update', 'en'), ['key' => 'retry', 'value' => 'Again'])->assertOk();
        $this->actingAs($this->admin)->post(route('admin.languages.publish', 'en'));
        $this->assertSame('Again', $this->publicStrings('en')['retry']);
        $versionAfterPublish = AppLanguage::query()->where('code', 'en')->value('version');

        // Pending draft survives the rollback.
        $this->actingAs($this->admin)->putJson(route('admin.labels.update', 'en'), ['key' => 'cancel', 'value' => 'Stop'])->assertOk();

        $this->actingAs($this->admin)->post(route('admin.languages.rollback', 'en'), ['revision' => $firstRevision->id])->assertSessionHas('status');

        $this->assertSame($versionAfterPublish + 1, AppLanguage::query()->where('code', 'en')->value('version'));
        $this->assertSame($original, $this->publicStrings('en'));
        $this->assertSame('Stop', AppTranslation::query()->where('language_code', 'en')->where('key', 'cancel')->value('draft_value'));

        // A revision of another language is refused.
        $arRevision = AppTranslationRevision::query()->where('language_code', 'ar')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.languages.rollback', 'en'), ['revision' => $arRevision->id])->assertSessionHasErrors('revision');
        $this->assertSame($versionAfterPublish + 1, AppLanguage::query()->where('code', 'en')->value('version'));
    }

    public function test_export_round_trips_through_import_for_arb_and_csv(): void
    {
        foreach (['arb' => 'app_ar.arb', 'csv' => 'labels_ar.csv'] as $format => $filename) {
            $export = $this->actingAs($this->admin)->get(route('admin.labels.export', ['code' => 'ar', 'format' => $format]))->assertOk();
            $this->assertStringContainsString($filename, (string) $export->headers->get('Content-Disposition'));
            $contents = $export->getContent();

            // Re-importing into the same language changes nothing.
            $this->actingAs($this->admin)->post(route('admin.labels.import', 'ar'), [
                'file' => UploadedFile::fake()->createWithContent($filename, $contents),
            ])->assertRedirect();
            $this->assertSame(0, AppTranslation::query()->where('language_code', 'ar')->whereNotNull('draft_value')->count(), $format);

            // Importing into a fresh language reproduces every published string after publish.
            $code = 'x'.$format;
            AppLanguage::query()->create(['code' => $code, 'name' => $code, 'native_name' => $code, 'direction' => 'rtl', 'is_enabled' => true]);
            $this->actingAs($this->admin)->post(route('admin.labels.import', $code), [
                'file' => UploadedFile::fake()->createWithContent($filename, $contents),
            ])->assertRedirect()->assertSessionMissing('i18n_errors');
            $this->actingAs($this->admin)->post(route('admin.languages.publish', $code))->assertSessionHas('status');

            $this->assertSame($this->publicStrings('ar'), $this->publicStrings($code), $format);
        }
    }

    public function test_csv_round_trip_preserves_quotes_commas_newlines_and_arabic(): void
    {
        $values = [
            'retry' => "قال \"مرحباً\", ثم\nسطر جديد",
            'cancel' => '=SUM(A1), "x"',
            'greetingName' => "{name}, it's \"you\"\r\nok",
        ];
        $this->actingAs($this->admin)->postJson(route('admin.labels.bulk', 'en'), ['values' => $values])->assertOk();
        $this->actingAs($this->admin)->post(route('admin.languages.publish', 'en'))->assertSessionHas('status');

        $csv = $this->actingAs($this->admin)->get(route('admin.labels.export', ['code' => 'en', 'format' => 'csv']))->getContent();
        AppLanguage::query()->create(['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_enabled' => true]);
        $this->actingAs($this->admin)->post(route('admin.labels.import', 'fr'), ['file' => UploadedFile::fake()->createWithContent('fr.csv', $csv)]);

        $drafts = AppTranslation::query()->where('language_code', 'fr')->pluck('draft_value', 'key');
        foreach ($values as $key => $value) {
            $this->assertSame($value, $drafts[$key], $key);
        }
    }

    public function test_import_upload_skips_invalid_and_unknown_values(): void
    {
        $arb = json_encode(['@@locale' => 'en', 'retry' => 'Retry please', 'greetingName' => 'Hi {nobody}', 'notAKey' => 'x', '@retry' => ['description' => 'ignored']]);
        $this->actingAs($this->admin)->post(route('admin.labels.import', 'en'), [
            'file' => UploadedFile::fake()->createWithContent('en.arb', $arb),
        ])->assertSessionHas('i18n_errors', fn ($errors) => array_keys($errors) === ['greetingName', 'notAKey']);

        $this->assertSame('Retry please', AppTranslation::query()->where('language_code', 'en')->where('key', 'retry')->value('draft_value'));
        $this->assertNull(AppTranslation::query()->where('language_code', 'en')->where('key', 'greetingName')->value('draft_value'));
        $this->assertFalse(AppTranslation::query()->where('key', 'notAKey')->exists());

        $this->actingAs($this->admin)->post(route('admin.labels.import', 'en'), [
            'file' => UploadedFile::fake()->createWithContent('en.arb', '[1,2'),
        ])->assertSessionHas('i18n_error');
    }

    public function test_language_management_rules(): void
    {
        $this->actingAs($this->admin)->post(route('admin.languages.store'), [
            'code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'fallback_code' => 'en', 'is_enabled' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(['fr'], AppLanguage::query()->where('code', 'fr')->pluck('code')->all());

        foreach ([
            ['code' => 'fr'],
            ['code' => 'FR!'],
            ['code' => '../x'],
            ['code' => 'de', 'direction' => 'up'],
            ['code' => 'de', 'fallback_code' => 'zz'],
            ['code' => 'de', 'fallback_code' => 'de'],
        ] as $overrides) {
            $this->actingAs($this->admin)->post(route('admin.languages.store'), $overrides + ['name' => 'X', 'native_name' => 'X', 'direction' => 'ltr'])->assertSessionHasErrors();
        }

        // The default language cannot be disabled, unset or deleted.
        $this->actingAs($this->admin)->patch(route('admin.languages.update', 'ar'), ['is_enabled' => 0])->assertSessionHasErrors('is_enabled');
        $this->actingAs($this->admin)->patch(route('admin.languages.update', 'ar'), ['is_default' => 0])->assertSessionHasErrors('is_default');
        $this->actingAs($this->admin)->delete(route('admin.languages.destroy', 'ar'))->assertSessionHas('i18n_error');
        $this->assertTrue(AppLanguage::query()->where('code', 'ar')->value('is_default'));

        // A language cannot fall back to itself.
        $this->actingAs($this->admin)->patch(route('admin.languages.update', 'fr'), ['fallback_code' => 'fr'])->assertSessionHasErrors('fallback_code');

        // Moving the default keeps exactly one default.
        $this->actingAs($this->admin)->patch(route('admin.languages.update', 'en'), ['is_default' => 1, 'is_enabled' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['en'], AppLanguage::query()->where('is_default', true)->pluck('code')->all());
        $this->assertSame('en', $this->getJson('/api/i18n/languages')->json('default'));

        // Direction and fallback live in the strings payload, so changing them publishes a new version.
        $ar = AppLanguage::query()->where('code', 'ar')->firstOrFail();
        $this->get('/api/i18n/languages/ar/strings')->assertOk();
        $this->actingAs($this->admin)->patch(route('admin.languages.update', 'ar'), ['direction' => 'ltr'])->assertSessionHasNoErrors();
        $response = $this->get('/api/i18n/languages/ar/strings', ['If-None-Match' => $ar->etag()])->assertOk();
        $this->assertSame('ltr', $response->json('direction'));
        $this->assertSame($ar->version + 1, $response->json('version'));

        // Renaming alone does not.
        $this->actingAs($this->admin)->patch(route('admin.languages.update', 'ar'), ['name' => 'Arabic (SA)'])->assertSessionHasNoErrors();
        $this->assertSame($ar->version + 1, AppLanguage::query()->where('code', 'ar')->value('version'));

        // Deleting a non-default language removes its rows and detaches dependants' fallback.
        $this->actingAs($this->admin)->patch(route('admin.languages.update', 'fr'), ['fallback_code' => 'ar'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete(route('admin.languages.destroy', 'ar'))->assertRedirect();
        $this->assertFalse(AppLanguage::query()->where('code', 'ar')->exists());
        $this->assertSame(0, AppTranslation::query()->where('language_code', 'ar')->count());
        $this->assertSame(0, AppTranslationRevision::query()->where('language_code', 'ar')->count());
        $this->assertNull($this->getJson('/api/i18n/languages/fr/strings')->json('fallback'));
        $this->getJson('/api/i18n/languages/ar/strings')->assertNotFound();
    }

    public function test_cache_keys_are_scoped_to_language_identity_and_version(): void
    {
        $ar = AppLanguage::query()->where('code', 'ar')->firstOrFail();
        $this->assertNotSame(TranslationPublisher::cacheKey($ar, 1), TranslationPublisher::cacheKey($ar, 2));

        $clone = new AppLanguage(['code' => 'ar']);
        $clone->id = $ar->id + 1000;
        $this->assertNotSame(TranslationPublisher::cacheKey($ar, 1), TranslationPublisher::cacheKey($clone, 1));
    }
}
