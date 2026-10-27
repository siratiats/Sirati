<?php

namespace Tests\Feature;

use App\Models\AppLanguage;
use App\Models\AppTranslation;
use App\Models\AppTranslationKey;
use App\Models\AppTranslationRevision;
use App\Models\User;
use App\Services\I18n\ArbFile;
use App\Services\I18n\IcuMessageParser;
use App\Services\I18n\TranslationValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AppLocalizationImportTest extends TestCase
{
    use RefreshDatabase;

    private function bundled(string $locale): array
    {
        return ArbFile::parse(File::get(resource_path("i18n/app_{$locale}.arb")));
    }

    public function test_import_builds_the_catalog_and_languages_from_the_bundled_arb_files(): void
    {
        $this->artisan('i18n:import-arb')->assertSuccessful();

        $template = $this->bundled('ar');
        $parser = new IcuMessageParser(false);

        $this->assertSame(count($template['messages']), AppTranslationKey::query()->count());
        foreach (AppTranslationKey::query()->get() as $key) {
            $message = $template['messages'][$key->key];
            $meta = $template['metadata'][$key->key] ?? [];
            $parsed = $parser->parse($message);

            $this->assertSame($meta['description'] ?? null, $key->description, $key->key);
            $this->assertSame($parsed['is_icu'], $key->is_icu, $key->key);
            $this->assertEqualsCanonicalizing(array_keys($parsed['arguments']), $key->placeholderNames(), $key->key);
            foreach ($meta['placeholders'] ?? [] as $name => $placeholderMeta) {
                $this->assertSame($placeholderMeta, $key->placeholders[$name], "{$key->key}.{$name}");
            }
            $this->assertNotSame('', (string) $key->group);
        }

        $ar = AppLanguage::query()->where('code', 'ar')->firstOrFail();
        $en = AppLanguage::query()->where('code', 'en')->firstOrFail();
        $this->assertTrue($ar->is_default && $ar->is_enabled);
        $this->assertSame('rtl', $ar->direction);
        $this->assertSame('en', $ar->effectiveFallback());
        $this->assertFalse($en->is_default);
        $this->assertTrue($en->is_enabled);
        $this->assertSame('ltr', $en->direction);
        $this->assertSame(1, $ar->version);
        $this->assertSame(1, $en->version);

        // Every bundled value of every language is published verbatim.
        foreach (['ar' => $template, 'en' => $this->bundled('en')] as $code => $arb) {
            $published = AppTranslation::query()->where('language_code', $code)->pluck('value', 'key')->all();
            $expected = array_intersect_key($arb['messages'], $template['messages']);
            $actual = array_intersect_key($published, $expected);
            ksort($expected);
            ksort($actual);
            $this->assertSame($expected, $actual);
            $this->assertCount(count($expected), $published);
        }
    }

    public function test_every_bundled_value_passes_the_validator(): void
    {
        $this->artisan('i18n:import-arb')->assertSuccessful();
        $validator = new TranslationValidator(new IcuMessageParser(false));
        $catalog = AppTranslationKey::query()->get()->keyBy('key');

        foreach (['ar', 'en'] as $code) {
            foreach ($this->bundled($code)['messages'] as $key => $value) {
                if ($catalog->has($key)) {
                    $this->assertSame([], $validator->validateForKey($catalog[$key], $value), "{$code}.{$key}");
                }
            }
        }
    }

    public function test_reimport_is_idempotent(): void
    {
        $this->artisan('i18n:import-arb')->assertSuccessful();
        $versions = AppLanguage::query()->pluck('version', 'code')->all();
        $revisions = AppTranslationRevision::query()->count();

        $this->artisan('i18n:import-arb')->assertSuccessful();

        $this->assertSame($versions, AppLanguage::query()->pluck('version', 'code')->all());
        $this->assertSame($revisions, AppTranslationRevision::query()->count());
    }

    public function test_import_never_clobbers_admin_edits_or_drafts_unless_forced(): void
    {
        $this->artisan('i18n:import-arb')->assertSuccessful();
        $admin = User::factory()->create();
        $bundled = $this->bundled('ar')['messages'];

        mt_srand(31337);
        $keys = array_keys($bundled);
        shuffle($keys);
        $published = array_slice($keys, 0, 25);
        $drafted = array_slice($keys, 25, 25);

        foreach ($published as $key) {
            // Appending text keeps the placeholder set, so the edit is a valid value.
            AppTranslation::query()->where('language_code', 'ar')->where('key', $key)
                ->update(['value' => $bundled[$key].' (معدل)', 'updated_by' => $admin->id]);
        }
        foreach ($drafted as $key) {
            AppTranslation::query()->where('language_code', 'ar')->where('key', $key)
                ->update(['draft_value' => $bundled[$key].' draft']);
        }
        // A key the admin cleared entirely gets its bundled value back (it was missing).
        $missing = $keys[60];
        AppTranslation::query()->where('language_code', 'ar')->where('key', $missing)->delete();

        $this->artisan('i18n:import-arb')->assertSuccessful();

        $rows = AppTranslation::query()->where('language_code', 'ar')->get()->keyBy('key');
        foreach ($keys as $key) {
            $row = $rows[$key];
            if (in_array($key, $published, true)) {
                $this->assertSame($bundled[$key].' (معدل)', $row->value, $key);
            } else {
                $this->assertSame($bundled[$key], $row->value, $key);
            }
            $this->assertSame(in_array($key, $drafted, true) ? $bundled[$key].' draft' : null, $row->draft_value, $key);
        }

        $this->artisan('i18n:import-arb', ['--force' => true])->assertSuccessful();

        $rows = AppTranslation::query()->where('language_code', 'ar')->get();
        foreach ($rows as $row) {
            $this->assertSame($bundled[$row->key], $row->value, $row->key);
            $this->assertNull($row->draft_value, $row->key);
            $this->assertNull($row->updated_by, $row->key);
        }
    }

    public function test_import_refreshes_bundle_owned_values_when_the_arb_changes_and_bumps_the_version(): void
    {
        $dir = storage_path('framework/testing/arb-'.getmypid());
        File::ensureDirectoryExists($dir);
        File::copy(resource_path('i18n/app_ar.arb'), $dir.'/app_ar.arb');
        File::copy(resource_path('i18n/app_en.arb'), $dir.'/app_en.arb');

        try {
            $this->artisan('i18n:import-arb', ['--path' => $dir])->assertSuccessful();
            $before = AppLanguage::query()->where('code', 'en')->value('version');

            $en = json_decode(File::get($dir.'/app_en.arb'), true);
            $en['retry'] = 'Try again now';
            File::put($dir.'/app_en.arb', json_encode($en, JSON_UNESCAPED_UNICODE));

            $this->artisan('i18n:import-arb', ['--path' => $dir])->assertSuccessful();

            $this->assertSame('Try again now', AppTranslation::query()->where('language_code', 'en')->where('key', 'retry')->value('value'));
            $this->assertSame($before + 1, AppLanguage::query()->where('code', 'en')->value('version'));
            $this->assertSame(1, AppLanguage::query()->where('code', 'ar')->value('version'));
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_import_fails_without_the_template_file(): void
    {
        $dir = storage_path('framework/testing/arb-empty-'.getmypid());
        File::ensureDirectoryExists($dir);

        try {
            $this->artisan('i18n:import-arb', ['--path' => $dir])->assertFailed();
            $this->assertSame(0, AppTranslationKey::query()->count());
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_database_seeder_loads_the_catalog(): void
    {
        $this->seed(\Database\Seeders\AppLocalizationSeeder::class);

        $this->assertSame(count($this->bundled('ar')['messages']), AppTranslationKey::query()->count());
        $this->assertTrue(AppLanguage::query()->where('code', 'ar')->value('is_default'));
    }
}
