<?php

namespace Tests\Feature;

use App\Models\AppLanguage;
use App\Models\AppTranslation;
use App\Services\I18n\ServerText;
use App\Services\I18n\TranslationPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Abobker85/Sirati#12: server text comes from the label catalog, in the
 * caller's language, and an admin edit is what the server then sends.
 */
class ServerTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_server_label_resolves_to_its_bundled_value_with_any_params(): void
    {
        mt_srand(12);
        $text = app(ServerText::class);

        foreach (['ar', 'en'] as $code) {
            $keys = $this->serverKeys($code);
            $this->assertGreaterThan(100, count($keys));

            foreach ($keys as $key => $template) {
                preg_match_all('/\{(\w+)\}/', $template, $m);
                $params = [];
                foreach (array_unique($m[1]) as $name) {
                    $params[$name] = $this->randomValue();
                }

                $expected = preg_replace_callback('/\{(\w+)\}/', fn ($x) => (string) $params[$x[1]], $template);
                $this->assertSame($expected, $text->get($key, $params, $code), "{$code}.{$key}");
            }
        }
    }

    public function test_english_and_arabic_differ_for_every_server_label_with_letters(): void
    {
        $ar = $this->serverKeys('ar');
        $en = $this->serverKeys('en');
        $this->assertSame(array_keys($ar), array_keys($en));

        foreach ($ar as $key => $value) {
            if (preg_match('/\p{Arabic}/u', $value)) {
                $this->assertNotSame($value, $en[$key], $key);
                $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $en[$key], $key);
            }
        }
    }

    public function test_an_admin_published_edit_is_what_the_server_sends(): void
    {
        mt_srand(99);
        $keys = array_keys($this->serverKeys('en'));
        $picked = array_map(fn () => $keys[mt_rand(0, count($keys) - 1)], range(1, 6));

        foreach (['ar', 'en'] as $code) {
            $language = AppLanguage::query()->where('code', $code)->firstOrFail();
            $edits = [];
            $bundled = $this->serverKeys($code);
            foreach (array_unique($picked) as $key) {
                // An edit keeps the label's placeholders, or it cannot be published.
                preg_match_all('/\{\w+\}/', $bundled[$key], $m);
                $value = 'Edited '.Str::random(8).' '.implode(' ', $m[0]);
                AppTranslation::query()->where('language_code', $code)->where('key', $key)->update(['draft_value' => $value]);
                $edits[$key] = $value;
            }
            app(TranslationPublisher::class)->publish($language, null);

            // A fresh resolver, as the next request gets.
            $this->app->forgetScopedInstances();
            $text = app(ServerText::class);
            foreach ($edits as $key => $value) {
                $this->assertSame($value, $text->get($key, [], $code), "{$code}.{$key}");
            }
        }
    }

    public function test_any_language_resolves_through_its_fallback_chain(): void
    {
        foreach (['xq', 'zz', 'qa'] as $code) {
            AppLanguage::query()->create([
                'code' => $code, 'name' => $code, 'native_name' => $code, 'direction' => 'ltr',
                'fallback_code' => 'en', 'is_enabled' => true, 'is_default' => false,
                'sort_order' => 50, 'version' => 1,
            ]);
            AppTranslation::query()->create(['language_code' => $code, 'key' => 'serverJobsTitle', 'value' => "Jobs {$code}"]);
            $this->app->forgetScopedInstances();
            $text = app(ServerText::class);

            $this->assertSame("Jobs {$code}", $text->get('serverJobsTitle', [], $code));
            $this->assertSame($this->serverKeys('en')['serverMyCvsTitle'], $text->get('serverMyCvsTitle', [], $code));
            $this->assertSame('en', $text->contentLanguage($code));

            // A disabled language is never served: the default language is.
            AppLanguage::query()->where('code', $code)->update(['is_enabled' => false]);
            $this->app->forgetScopedInstances();
            $this->assertSame($this->serverKeys('ar')['serverJobsTitle'], app(ServerText::class)->get('serverJobsTitle', [], $code));
        }
    }

    public function test_the_request_language_comes_from_the_app_header_then_the_lang_query(): void
    {
        $ar = $this->serverKeys('ar')['serverJobsTitle'];
        $en = $this->serverKeys('en')['serverJobsTitle'];

        $cases = [
            [[], '', $ar],
            [[], '?lang=en', $en],
            [[], '?lang=ar', $ar],
            [[], '?lang=nonsense', $ar],
            [['X-App-Language' => 'en'], '', $en],
            [['X-App-Language' => 'en-US'], '', $en],
            [['X-App-Language' => 'EN_gb'], '', $en],
            [['X-App-Language' => 'ar-SA'], '', $ar],
            // The header is the app language; ?lang only picks content rows.
            [['X-App-Language' => 'en'], '?lang=ar', $en],
            [['X-App-Language' => 'ar'], '?lang=en', $ar],
            [['X-App-Language' => '*'], '?lang=en', $en],
            [['X-App-Language' => 'xx-unknown'], '', $ar],
            // The device language is not the app language.
            [['Accept-Language' => 'en-US,en;q=0.9'], '', $ar],
            [['Accept-Language' => 'ar'], '?lang=en', $en],
        ];

        foreach ($cases as [$headers, $query, $expected]) {
            $this->assertSame(
                $expected,
                $this->getJson('/api/mobile/job-news'.$query, $headers)->assertOk()->json('data.title'),
                json_encode([$headers, $query]),
            );
        }
    }

    /** @return array<string, string> */
    private function serverKeys(string $code): array
    {
        $arb = json_decode(file_get_contents(resource_path("i18n/app_{$code}.arb")), true);

        return array_filter($arb, fn ($v, $k) => str_starts_with($k, 'server'), ARRAY_FILTER_USE_BOTH);
    }

    private function randomValue(): string
    {
        return match (mt_rand(0, 3)) {
            0 => (string) mt_rand(0, 100000),
            1 => Str::random(mt_rand(1, 12)),
            2 => 'محمد '.Str::random(3),
            default => '{literal} $1 \\0 '.Str::random(2),
        };
    }
}
