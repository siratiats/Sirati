<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\LabelController;
use App\Models\AppLanguage;
use App\Models\AppTranslationKey;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Labels page "where does this label appear" preview: app screenshots + label boxes from
 * tests/Fixtures/i18n-screens/manifest.json. Every assertion runs over all fixture screens,
 * locales and labels, computed from the raw manifest rather than from the service under test.
 */
class AppLabelScreensAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $fixturePath;

    /** @var list<string> */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('i18n:import-arb')->assertSuccessful();
        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        config(['services.admin.emails' => ['admin@example.com']]);
        $this->fixturePath = base_path('tests/Fixtures/i18n-screens');
        config(['i18n.screens_path' => $this->fixturePath]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            File::deleteDirectory($dir);
        }
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        return json_decode((string) file_get_contents($this->fixturePath.'/manifest.json'), true, 64, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array<string, mixed>> screens keyed by id, in display order */
    private function fixtureScreens(): array
    {
        $screens = $this->manifest()['screens'];
        usort($screens, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_column($screens, null, 'id');
    }

    /** @return list<string> */
    private function catalogKeys(): array
    {
        return AppTranslationKey::query()->orderBy('id')->pluck('key')->all();
    }

    /** @return list<string> catalog keys on the screen in any locale */
    private function catalogKeysOnScreen(array $screen): array
    {
        $keys = [];
        foreach ($screen['locales'] as $capture) {
            foreach ($capture['labels'] as $label) {
                $keys[] = $label['key'];
            }
        }

        return array_values(array_intersect($this->catalogKeys(), $keys));
    }

    private function tempScreensDir(): string
    {
        $dir = storage_path('framework/testing/label-screens-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($dir);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    /** @return list<string> */
    private function rowKeys(TestResponse $response): array
    {
        $keys = [];
        foreach ($this->xpath($response)->query('//article[@data-label-key]') as $article) {
            $keys[] = $article->getAttribute('data-label-key');
        }

        return $keys;
    }

    /** @return array<string, array{left: float, top: float, width: float, height: float, element: DOMElement}> */
    private function hotspots(TestResponse $response): array
    {
        $boxes = [];
        foreach ($this->xpath($response)->query('//a[@data-hotspot-key]') as $element) {
            preg_match_all('/(left|top|width|height):\s*([0-9.]+)%/', $element->getAttribute('style'), $matches, PREG_SET_ORDER);
            $box = ['element' => $element];
            foreach ($matches as [, $name, $value]) {
                $box[$name] = (float) $value;
            }
            $boxes[$element->getAttribute('data-hotspot-key')] = $box;
        }

        return $boxes;
    }

    /** @return array{screen: string, locale: string}|null */
    private function phoneImage(TestResponse $response): ?array
    {
        $image = $this->xpath($response)->query('//img[contains(@class, "l10n-phone__image")]')->item(0);
        if (! $image instanceof DOMElement) {
            return null;
        }
        $this->assertSame(1, preg_match('#/admin/labels/screens/([^/?]+)/([^/?]+)#', $image->getAttribute('src'), $match));

        return ['screen' => $match[1], 'locale' => $match[2]];
    }

    /** All keys the list shows for a query, across every page. */
    private function allRowKeys(array $query): array
    {
        $keys = [];
        for ($page = 1; $page < 100; $page++) {
            $response = $this->actingAs($this->admin)->get(route('admin.labels.index', $query + ['page' => $page]))->assertOk();
            $pageKeys = $this->rowKeys($response);
            if ($pageKeys === []) {
                break;
            }
            array_push($keys, ...$pageKeys);
        }

        return $keys;
    }

    private function clampPercent(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }

    public function test_page_and_every_screenshot_reject_non_admins_and_fail_closed(): void
    {
        $outsider = User::factory()->create(['email' => 'outsider@example.com']);
        $urls = [route('admin.labels.index', ['screen' => 'welcome'])];
        foreach ($this->fixtureScreens() as $id => $screen) {
            foreach (array_keys($screen['locales']) as $locale) {
                $urls[] = route('admin.labels.screens.image', ['screen' => $id, 'locale' => $locale]);
            }
        }

        foreach ($urls as $url) {
            $this->actingAs($outsider)->get($url)->assertForbidden();
        }

        config(['services.admin.emails' => []]);
        $this->app['env'] = 'production';
        foreach ($urls as $url) {
            $this->actingAs($this->admin)->get($url)->assertStatus(503);
        }
        $this->app['env'] = 'testing';

        auth()->logout();
        foreach ($urls as $url) {
            $this->assertContains($this->get($url)->status(), [401, 302]);
        }
    }

    public function test_image_route_serves_exactly_the_manifest_files_with_cache_headers(): void
    {
        foreach ($this->fixtureScreens() as $id => $screen) {
            foreach ($screen['locales'] as $locale => $capture) {
                $response = $this->actingAs($this->admin)->get(route('admin.labels.screens.image', ['screen' => $id, 'locale' => $locale]));
                $response->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
                $this->assertSame(realpath($this->fixturePath.'/'.$capture['image']), realpath($response->baseResponse->getFile()->getPathname()));

                $etag = (string) $response->headers->get('ETag');
                $this->assertNotSame('', $etag);
                $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
                $this->assertStringContainsString('max-age=', (string) $response->headers->get('Cache-Control'));

                $this->actingAs($this->admin)
                    ->get(route('admin.labels.screens.image', ['screen' => $id, 'locale' => $locale]), ['If-None-Match' => $etag])
                    ->assertStatus(304);
            }

            // Any locale the manifest does not list for this screen is a 404, even if a file could exist.
            foreach (['fr', 'de', 'xx', 'ar_SA', 'en-US'] as $locale) {
                if (! array_key_exists($locale, $screen['locales'])) {
                    $this->actingAs($this->admin)->get("/admin/labels/screens/{$id}/{$locale}")->assertNotFound();
                }
            }
        }

        foreach ([
            '/admin/labels/screens/unknown/en',
            '/admin/labels/screens/manifest/json',
            '/admin/labels/screens/welcome.en.png/en',
            '/admin/labels/screens/..%2F..%2F.env/en',
            '/admin/labels/screens/welcome/..%2Fmanifest.json',
            '/admin/labels/screens/%2E%2E/en',
            '/admin/labels/screens/welcome/en%00',
            '/admin/labels/screens/__none/en',
        ] as $url) {
            $this->assertSame(404, $this->actingAs($this->admin)->get($url)->status(), $url);
        }
    }

    public function test_image_route_refuses_manifest_entries_that_escape_the_folder_or_are_not_png(): void
    {
        $dir = $this->tempScreensDir();
        File::put(dirname($dir).'/outside.png', (string) file_get_contents($this->fixturePath.'/welcome.en.png'));
        File::put($dir.'/fake.png', 'not a png');
        File::copy($this->fixturePath.'/welcome.en.png', $dir.'/good.png');
        $capture = fn (string $image) => ['image' => $image, 'width' => 390, 'height' => 844, 'labels' => [['key' => 'retry', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 10]]];
        File::put($dir.'/manifest.json', json_encode(['version' => 1, 'screens' => [
            ['id' => 'escape', 'name' => 'Escape', 'order' => 1, 'locales' => ['en' => $capture('../outside.png'), 'ar' => $capture('..\\outside.png')]],
            ['id' => 'fake', 'name' => 'Fake', 'order' => 2, 'locales' => ['en' => $capture('fake.png'), 'ar' => $capture('missing.png')]],
            ['id' => 'good', 'name' => 'Good', 'order' => 3, 'locales' => ['en' => $capture('good.png')]],
        ]]));
        config(['i18n.screens_path' => $dir]);

        foreach (['escape/en', 'escape/ar', 'fake/en', 'fake/ar'] as $path) {
            $this->actingAs($this->admin)->get('/admin/labels/screens/'.$path)->assertNotFound();
        }
        $this->actingAs($this->admin)->get('/admin/labels/screens/good/en')->assertOk();
        File::delete(dirname($dir).'/outside.png');
    }

    public function test_screen_filter_lists_exactly_the_catalog_keys_of_that_screen(): void
    {
        $captured = [];
        foreach ($this->fixtureScreens() as $id => $screen) {
            $expected = $this->catalogKeysOnScreen($screen);
            $this->assertNotSame([], $expected, "fixture screen {$id} has catalog keys");
            $captured = array_merge($captured, $expected);

            foreach (['ar', 'en'] as $lang) {
                $response = $this->actingAs($this->admin)->get(route('admin.labels.index', ['lang' => $lang, 'screen' => $id]))->assertOk();
                $this->assertSame($expected, $this->rowKeys($response), "screen {$id} ({$lang})");

                $xpath = $this->xpath($response);
                $rail = $xpath->query('//a[@data-screen-link="'.$id.'"]')->item(0);
                $this->assertSame((string) count($expected), trim($xpath->query('.//span[contains(@class, "l10n-screens__count")]', $rail)->item(0)->textContent));
            }
        }

        $none = array_values(array_diff($this->catalogKeys(), $captured));
        $this->assertSame($none, $this->allRowKeys(['lang' => 'en', 'screen' => LabelController::SCREEN_NONE]));
        $this->assertSame($this->catalogKeys(), $this->allRowKeys(['lang' => 'en']));

        // The rail count and the "All" chip (which applies the screen filter) both equal what the list shows.
        $response = $this->actingAs($this->admin)->get(route('admin.labels.index', ['lang' => 'en', 'screen' => LabelController::SCREEN_NONE]))->assertOk();
        $xpath = $this->xpath($response);
        $this->assertSame(number_format(count($none)), trim($xpath->query('//a[contains(@class, "l10n-screens__item--none")]/span[contains(@class, "l10n-screens__count")]')->item(0)->textContent));
        $this->assertSame(number_format(count($none)), trim($xpath->query('//span[contains(@class, "l10n-chip__count")]')->item(0)->textContent));
    }

    public function test_page_still_works_without_a_usable_manifest(): void
    {
        $missing = $this->tempScreensDir();
        $invalidJson = $this->tempScreensDir();
        File::put($invalidJson.'/manifest.json', '{"version": 1, "screens": [');
        $wrongVersion = $this->tempScreensDir();
        File::put($wrongVersion.'/manifest.json', json_encode(['version' => 2] + $this->manifest()));
        $noScreens = $this->tempScreensDir();
        File::put($noScreens.'/manifest.json', json_encode(['version' => 1, 'screens' => 'welcome']));

        $firstPage = array_slice($this->catalogKeys(), 0, LabelController::PER_PAGE);
        foreach ([$missing, $invalidJson, $wrongVersion, $noScreens] as $dir) {
            config(['i18n.screens_path' => $dir]);
            foreach ([[], ['screen' => 'welcome'], ['screen' => LabelController::SCREEN_NONE], ['key' => 'retry']] as $query) {
                $response = $this->actingAs($this->admin)->get(route('admin.labels.index', ['lang' => 'en'] + $query))->assertOk();
                $response->assertSee('No app screenshots yet');
                $this->assertSame(0, $this->xpath($response)->query('//a[@data-hotspot-key]')->length);
                $this->assertNull($this->phoneImage($response));
                if (! isset($query['key'])) {
                    $this->assertSame($firstPage, $this->rowKeys($response));
                }
            }
            $this->actingAs($this->admin)->get('/admin/labels/screens/welcome/en')->assertNotFound();
        }
    }

    public function test_invalid_manifest_entries_are_dropped_and_valid_ones_kept(): void
    {
        $dir = $this->tempScreensDir();
        File::copy($this->fixturePath.'/welcome.en.png', $dir.'/ok.en.png');
        $valid = ['key' => 'retry', 'x' => 10, 'y' => 20.5, 'w' => 100, 'h' => 30];
        $labels = [$valid, ['key' => 'cancel', 'x' => 10, 'y' => 20, 'w' => -5, 'h' => 30], ['key' => 'save', 'x' => '10', 'y' => 20, 'w' => 5, 'h' => 30], ['x' => 1, 'y' => 1, 'w' => 1, 'h' => 1], 'next'];
        $capture = ['image' => 'ok.en.png', 'width' => 390, 'height' => 844, 'labels' => $labels];
        File::put($dir.'/manifest.json', json_encode(['version' => 1, 'screens' => [
            ['id' => 'ok', 'name' => 'OK', 'order' => 1, 'locales' => ['en' => $capture, 'bad locale' => $capture]],
            ['id' => 'ok', 'name' => 'Duplicate', 'order' => 2, 'locales' => ['en' => $capture]],
            ['id' => '../escape', 'name' => 'Bad id', 'order' => 3, 'locales' => ['en' => $capture]],
            ['id' => '__none', 'name' => 'Reserved', 'order' => 4, 'locales' => ['en' => $capture]],
            ['id' => 'nolocales', 'name' => 'Empty', 'order' => 5, 'locales' => []],
            ['id' => 'badsize', 'name' => 'Bad size', 'order' => 6, 'locales' => ['en' => ['width' => 0] + $capture]],
        ]]));
        config(['i18n.screens_path' => $dir]);

        $response = $this->actingAs($this->admin)->get(route('admin.labels.index', ['lang' => 'en']))->assertOk();
        $xpath = $this->xpath($response);
        $this->assertSame(['ok'], array_map(fn (DOMElement $a) => $a->getAttribute('data-screen-link'), iterator_to_array($xpath->query('//a[@data-screen-link]'))));
        $this->assertSame(['retry'], array_keys($this->hotspots($response)));
        $this->assertSame(['screen' => 'ok', 'locale' => 'en'], $this->phoneImage($response));
    }

    public function test_overlay_boxes_use_manifest_percentages_for_every_label(): void
    {
        $catalog = array_flip($this->catalogKeys());
        foreach ($this->fixtureScreens() as $id => $screen) {
            foreach ($screen['locales'] as $locale => $capture) {
                $response = $this->actingAs($this->admin)
                    ->get(route('admin.labels.index', ['lang' => 'en', 'screen' => $id, 'shot' => $locale]))
                    ->assertOk();
                $this->assertSame(['screen' => $id, 'locale' => $locale], $this->phoneImage($response));

                $boxes = $this->hotspots($response);
                $expected = array_values(array_filter($capture['labels'], fn ($label) => isset($catalog[$label['key']])));
                $this->assertSame(array_column($expected, 'key'), array_keys($boxes), "{$id}.{$locale}: one box per catalog label, none for unknown keys");

                foreach ($expected as $label) {
                    $box = $boxes[$label['key']];
                    $left = $this->clampPercent($label['x'] / $capture['width'] * 100);
                    $top = $this->clampPercent($label['y'] / $capture['height'] * 100);
                    $this->assertEqualsWithDelta($left, $box['left'], 0.0001, "{$id}.{$locale}.{$label['key']} left");
                    $this->assertEqualsWithDelta($top, $box['top'], 0.0001, "{$id}.{$locale}.{$label['key']} top");
                    $this->assertEqualsWithDelta($this->clampPercent(($label['x'] + $label['w']) / $capture['width'] * 100) - $left, $box['width'], 0.0002);
                    $this->assertEqualsWithDelta($this->clampPercent(($label['y'] + $label['h']) / $capture['height'] * 100) - $top, $box['height'], 0.0002);
                    $this->assertStringContainsString('key='.$label['key'], $box['element']->getAttribute('href'));
                    $this->assertStringContainsString('screen='.$id, $box['element']->getAttribute('href'));
                    $this->assertStringNotContainsString('is-selected', $box['element']->getAttribute('class'));
                }

                // Selecting each label marks exactly that box (pulse + key tag) and its row.
                foreach ($expected as $label) {
                    $selected = $this->actingAs($this->admin)
                        ->get(route('admin.labels.index', ['lang' => 'en', 'screen' => $id, 'shot' => $locale, 'key' => $label['key']]))
                        ->assertOk();
                    $xpath = $this->xpath($selected);
                    $marked = $xpath->query('//a[@data-hotspot-key][contains(concat(" ", @class, " "), " is-selected ")]');
                    $this->assertSame(1, $marked->length);
                    $this->assertSame($label['key'], $marked->item(0)->getAttribute('data-hotspot-key'));
                    $this->assertSame('true', $marked->item(0)->getAttribute('aria-current'));
                    $this->assertSame($label['key'], trim($marked->item(0)->textContent));
                    $rows = $xpath->query('//article[contains(concat(" ", @class, " "), " is-selected ")]');
                    $this->assertSame(1, $rows->length);
                    $this->assertSame($label['key'], $rows->item(0)->getAttribute('data-label-key'));
                }
            }
        }
    }

    public function test_selecting_a_label_moves_the_phone_to_its_first_screen_and_the_list_to_its_page(): void
    {
        $screens = $this->fixtureScreens();
        $firstScreen = [];
        foreach ($screens as $id => $screen) {
            foreach ($this->catalogKeysOnScreen($screen) as $key) {
                $firstScreen[$key] ??= $id;
            }
        }
        $this->assertNotSame([], $firstScreen);

        foreach ($firstScreen as $key => $id) {
            $response = $this->actingAs($this->admin)->get(route('admin.labels.index', ['lang' => 'ar', 'key' => $key]))->assertOk();
            $this->assertSame($id, $this->phoneImage($response)['screen'], $key);
            $this->assertContains($key, $this->rowKeys($response), "{$key} is on the page the list opens at");

            $rowLink = $this->xpath($response)->query('//article[@data-label-key="'.$key.'"]//a[contains(@class, "l10n-used-on")]')->item(0);
            $usedOn = count(array_filter($screens, fn ($screen) => in_array($key, $this->catalogKeysOnScreen($screen), true)));
            $this->assertSame((string) $usedOn, $rowLink->getAttribute('data-used-on'));
        }

        // Labels on no captured screen get no "Used on" chip.
        $none = array_values(array_diff($this->catalogKeys(), array_keys($firstScreen)));
        $response = $this->actingAs($this->admin)->get(route('admin.labels.index', ['lang' => 'en', 'screen' => LabelController::SCREEN_NONE]))->assertOk();
        $this->assertSame(0, $this->xpath($response)->query('//a[contains(@class, "l10n-used-on")]')->length);
        $this->assertNotSame([], $none);
    }

    public function test_language_without_capture_uses_its_fallback_then_english(): void
    {
        AppLanguage::query()->create(['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'fallback_code' => 'ar', 'is_enabled' => true]);
        AppLanguage::query()->create(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'direction' => 'ltr', 'is_enabled' => true]);
        $fallbacks = ['fr' => 'ar', 'de' => null, 'ar' => null, 'en' => null];

        foreach ($this->fixtureScreens() as $id => $screen) {
            $captured = array_keys($screen['locales']);
            foreach ($fallbacks as $code => $fallback) {
                $expected = null;
                foreach (array_filter([$code, $fallback, 'en']) as $candidate) {
                    if (in_array($candidate, $captured, true)) {
                        $expected = $candidate;
                        break;
                    }
                }
                $expected ??= $captured[0];

                $response = $this->actingAs($this->admin)->get(route('admin.labels.index', ['lang' => $code, 'screen' => $id]))->assertOk();
                $this->assertSame(['screen' => $id, 'locale' => $expected], $this->phoneImage($response), "{$code} on {$id}");
                if ($expected !== $code) {
                    $response->assertSee('capture for this screen; showing');
                } else {
                    $response->assertDontSee('capture for this screen; showing');
                }
            }
        }
    }
}
