<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\LabelController;
use App\Models\AppTranslationKey;
use App\Models\User;
use App\Services\I18n\LabelScreenPreview;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Phone preview "with edits": the current text of each label (draft, else published, else bundled) is
 * painted over the screenshot from the manifest's text/style fields. Every assertion runs over all
 * fixture screens, locales and labels, with expectations computed from the raw fixture manifest.
 */
class AppLabelLivePreviewAdminTest extends TestCase
{
    use RefreshDatabase;

    /** Appended to messages; outside any braces, so the formatted value is the captured value + this. */
    private const SUFFIX = ' — <b>&"edited"</b>';

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

    /**
     * Every captured screen/locale with its catalog labels (index = position in the manifest capture).
     *
     * @return list<array{screen: string, locale: string, capture: array<string, mixed>, labels: array<int, array<string, mixed>>}>
     */
    private function captures(): array
    {
        $catalog = array_flip(AppTranslationKey::query()->pluck('key')->all());
        $out = [];
        foreach ($this->manifest()['screens'] as $screen) {
            foreach ($screen['locales'] as $locale => $capture) {
                $labels = array_filter($capture['labels'], fn (array $label) => isset($catalog[$label['key']]));
                $out[] = ['screen' => $screen['id'], 'locale' => $locale, 'capture' => $capture, 'labels' => $labels];
            }
        }
        $this->assertNotSame([], $out);

        return $out;
    }

    private static function previewable(array $label): bool
    {
        return isset($label['text'], $label['value'], $label['bg'], $label['style']['font_size']);
    }

    private function isIcu(string $key): bool
    {
        return (bool) AppTranslationKey::query()->where('key', $key)->value('is_icu');
    }

    private function bundled(string $locale, string $key): string
    {
        $arb = json_decode((string) file_get_contents(resource_path('i18n/app_'.$locale.'.arb')), true, 64, JSON_THROW_ON_ERROR);

        return $arb[$key];
    }

    private function saveDraft(string $locale, string $key, string $value): void
    {
        $this->actingAs($this->admin)
            ->putJson(route('admin.labels.update', $locale), ['key' => $key, 'value' => $value])
            ->assertOk()
            ->assertJsonPath('has_draft', true);
    }

    private function page(array $capture, array $query = []): TestResponse
    {
        return $this->actingAs($this->admin)
            ->get(route('admin.labels.index', ['lang' => $capture['locale'], 'screen' => $capture['screen'], 'shot' => $capture['locale']] + $query))
            ->assertOk();
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

    /** @return array<int, DOMElement> elements keyed by their overlay index attribute */
    private function byIndex(DOMXPath $xpath, string $attribute): array
    {
        $out = [];
        foreach ($xpath->query('//*[@'.$attribute.']') as $element) {
            $out[(int) $element->getAttribute($attribute)] = $element;
        }

        return $out;
    }

    /** @return list<string> keys of hotspots marked as edited */
    private function editedHotspots(DOMXPath $xpath): array
    {
        $keys = [];
        foreach ($xpath->query('//a[@data-hotspot-key][contains(concat(" ", @class, " "), " is-edited ")]') as $box) {
            $keys[] = $box->getAttribute('data-hotspot-key');
        }

        return array_values(array_unique($keys));
    }

    private static function replaceFirst(string $text, string $search, string $replace): string
    {
        $at = strpos($text, $search);

        return $at === false ? $replace : substr_replace($text, $replace, $at, strlen($search));
    }

    private static function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /**
     * The patch is the room the text may grow into: as wide as the label's
     * layout width (never narrower than the captured text), inside the
     * screenshot, holding the captured rect, and anchored like the app's text.
     */
    private function assertSlot(string $style, array $label, array $capture, string $where): void
    {
        $this->assertMatchesRegularExpression('/(?:^|; )left: ([\d.]+)%; top: [\d.]+%; width: ([\d.]+)%;/', $style, $where);
        preg_match('/(?:^|; )left: ([\d.]+)%; top: [\d.]+%; width: ([\d.]+)%;/', $style, $m);
        [$left, $width] = [(float) $m[1], (float) $m[2]];
        $shot = $capture['width'];
        $eps = 0.001;
        $room = min($shot, max($label['w'], $label['style']['max_width'] ?? $label['w']));
        $this->assertEqualsWithDelta($room / $shot * 100, $width, $eps, $where);
        $this->assertGreaterThanOrEqual(-$eps, $left, $where);
        $this->assertLessThanOrEqual(100 + $eps, $left + $width, $where);
        $this->assertLessThanOrEqual($label['x'] / $shot * 100 + $eps, $left, $where);
        $this->assertGreaterThanOrEqual(($label['x'] + $label['w']) / $shot * 100 - $eps, $left + $width, $where);

        $rtl = ($label['style']['direction'] ?? 'ltr') === 'rtl';
        $align = $label['style']['align'] ?? 'start';
        $side = match ($align) {
            'center' => 'center',
            'left', 'right' => $align,
            'end' => $rtl ? 'left' : 'right',
            default => $rtl ? 'right' : 'left',
        };
        $unclamped = $room < $shot && $label['x'] + ($label['w'] - $room) / 2 >= 0 && $label['x'] + ($label['w'] + $room) / 2 <= $shot;
        match ($side) {
            'left' => $this->assertEqualsWithDelta(min($label['x'], $shot - $room) / $shot * 100, $left, $eps, $where),
            'right' => $this->assertEqualsWithDelta(max(0, $label['x'] + $label['w'] - $room) / $shot * 100, $left, $eps, $where),
            default => $unclamped
                ? $this->assertEqualsWithDelta(($label['x'] + $label['w'] / 2) / $shot * 100, $left + $width / 2, $eps, $where)
                : null,
        };
    }

    private function assertPatchStyle(DOMElement $patch, array $label, array $capture, string $where): void
    {
        $style = $patch->getAttribute('style');
        $this->assertStringContainsString('--fs: '.self::num($label['style']['font_size']).';', $style, $where);
        $this->assertStringContainsString('--h: '.self::num($label['h']).';', $style, $where);
        $this->assertStringContainsString('--bg: '.strtoupper($label['bg']).';', $style, $where);
        $this->assertStringContainsString('--w: '.self::num($label['w']).';', $style, $where);
        $this->assertSlot($style, $label, $capture, $where);
        if (isset($label['style']['line_height'])) {
            $this->assertStringContainsString('--lh: '.self::num($label['style']['line_height']).';', $style, $where);
        }
        if (isset($label['style']['font_weight'])) {
            $this->assertStringContainsString('font-weight: '.$label['style']['font_weight'].';', $style, $where);
        }
        if (isset($label['style']['font_family'])) {
            $this->assertStringContainsString("font-family: '".$label['style']['font_family']."'", $style, $where);
        }
        $this->assertStringContainsString('text-align: '.($label['style']['align'] ?? 'start').';', $style, $where);
        $this->assertStringContainsString('--max-lines: '.($label['style']['max_lines'] ?? 'none').';', $style, $where);
        if (isset($label['style']['direction'])) {
            $this->assertSame($label['style']['direction'], $patch->getAttribute('dir'), $where);
        }
        $this->assertSame((string) ($label['style']['lines'] ?? 1), $patch->getAttribute('data-lines'), $where);
        // The fit warning is measured by the browser; the server renders it hidden.
        $warn = (new DOMXPath($patch->ownerDocument))->query('.//span[contains(@class, "l10n-patch__warn")]', $patch)->item(0);
        $this->assertInstanceOf(DOMElement::class, $warn, $where);
        $this->assertTrue($warn->hasAttribute('hidden'), $where);
    }

    public function test_unchanged_labels_get_no_overlay_on_any_capture(): void
    {
        foreach ($this->captures() as $capture) {
            $xpath = $this->xpath($this->page($capture));
            $where = $capture['screen'].'.'.$capture['locale'];
            // Holds only if formatting each bundled message with the capture's args gives the captured value.
            $this->assertSame([], $this->byIndex($xpath, 'data-patch-index'), $where);
            $this->assertSame([], $this->byIndex($xpath, 'data-note-index'), $where);
            $this->assertSame([], $this->editedHotspots($xpath), $where);
            $this->assertSame(1, $xpath->query('//*[@data-phone-screen][contains(@style, "--shot-w: '.self::num($this->manifest()['device']['width']).';")]')->length, $where);
        }
    }

    public function test_edited_drafts_are_painted_with_the_new_text_and_capture_style(): void
    {
        foreach ($this->captures() as $capture) {
            $where = $capture['screen'].'.'.$capture['locale'];
            // Edit every other label (by key), so edited and unchanged labels sit side by side.
            $keys = array_values(array_unique(array_column($capture['labels'], 'key')));
            $edited = [];
            foreach ($keys as $position => $key) {
                if ($position % 2 === 0) {
                    $this->saveDraft($capture['locale'], $key, $this->bundled($capture['locale'], $key).self::SUFFIX);
                    $edited[$key] = true;
                }
            }
            $this->assertNotSame([], $edited);

            $xpath = $this->xpath($this->page($capture));
            $patches = $this->byIndex($xpath, 'data-patch-index');
            $notes = $this->byIndex($xpath, 'data-note-index');
            $painted = 0;
            foreach ($capture['labels'] as $index => $label) {
                $at = "{$where}.{$label['key']}#{$index}";
                if (! isset($edited[$label['key']]) || ! self::previewable($label)) {
                    $this->assertArrayNotHasKey($index, $patches, $at);
                    $this->assertArrayNotHasKey($index, $notes, $at);

                    continue;
                }
                if ($this->isIcu($label['key'])) {
                    $this->assertArrayNotHasKey($index, $patches, $at);
                    $this->assertArrayHasKey($index, $notes, $at);

                    continue;
                }

                $this->assertArrayHasKey($index, $patches, $at);
                $this->assertArrayNotHasKey($index, $notes, $at);
                $patch = $patches[$index];
                $this->assertSame($label['key'], $patch->getAttribute('data-patch-key'), $at);
                $text = $xpath->query('.//span[contains(@class, "l10n-patch__text")]', $patch)->item(0)->textContent;
                $this->assertSame(self::replaceFirst($label['text'], $label['value'], $label['value'].self::SUFFIX), $text, $at);
                $this->assertPatchStyle($patch, $label, $capture['capture'], $at);
                $painted++;
            }
            $this->assertGreaterThan(0, $painted, $where);

            // Edited labels carry the dot marker on the phone; unchanged ones do not.
            $onPhone = array_values(array_unique(array_column($capture['labels'], 'key')));
            $this->assertEqualsCanonicalizing(array_values(array_intersect($onPhone, array_keys($edited))), $this->editedHotspots($xpath), $where);

            // Patches are painted below the hotspots, so the boxes stay clickable.
            $children = iterator_to_array($xpath->query('//*[@data-phone-screen]/*'));
            $firstHotspot = array_search(true, array_map(fn (DOMElement $e) => $e->hasAttribute('data-hotspot-key'), $children), true);
            foreach ($children as $position => $child) {
                if ($child->hasAttribute('data-patch-index') || $child->hasAttribute('data-note-index')) {
                    $this->assertLessThan($firstHotspot, $position, $where);
                }
            }

            // Discarding the drafts brings back the bare screenshot.
            $this->actingAs($this->admin)->postJson(route('admin.labels.discard', $capture['locale']), ['all' => 1])->assertOk();
            $xpath = $this->xpath($this->page($capture));
            $this->assertSame([], $this->byIndex($xpath, 'data-patch-index'), $where);
        }
    }

    public function test_published_edits_are_painted_without_a_draft(): void
    {
        foreach ($this->captures() as $capture) {
            foreach ($capture['labels'] as $label) {
                if (self::previewable($label) && ! $this->isIcu($label['key'])) {
                    $this->saveDraft($capture['locale'], $label['key'], $this->bundled($capture['locale'], $label['key']).self::SUFFIX);
                }
            }
        }
        foreach (array_unique(array_column($this->captures(), 'locale')) as $locale) {
            $this->actingAs($this->admin)->post(route('admin.languages.publish', $locale))->assertRedirect();
        }

        foreach ($this->captures() as $capture) {
            $xpath = $this->xpath($this->page($capture));
            $patches = $this->byIndex($xpath, 'data-patch-index');
            foreach ($capture['labels'] as $index => $label) {
                if (self::previewable($label) && ! $this->isIcu($label['key'])) {
                    $text = $xpath->query('.//span[contains(@class, "l10n-patch__text")]', $patches[$index])->item(0)->textContent;
                    $this->assertSame(self::replaceFirst($label['text'], $label['value'], $label['value'].self::SUFFIX), $text);
                }
            }
        }
    }

    public function test_placeholder_messages_substitute_the_capture_args(): void
    {
        $checked = 0;
        foreach ($this->captures() as $capture) {
            foreach ($capture['labels'] as $index => $label) {
                $names = AppTranslationKey::query()->where('key', $label['key'])->first()->placeholderNames();
                if ($names === [] || $this->isIcu($label['key']) || ! self::previewable($label)) {
                    continue;
                }
                // Every placeholder, reordered and repeated, between arbitrary literal text.
                $names = array_reverse($names);
                $message = '«'.implode('» · «', array_map(fn ($name) => '{'.$name.'}', $names)).'» x2: {'.$names[0].'}';
                $expected = '«'.implode('» · «', array_map(fn ($name) => $label['args'][$name], $names)).'» x2: '.$label['args'][$names[0]];
                $this->saveDraft($capture['locale'], $label['key'], $message);

                $xpath = $this->xpath($this->page($capture));
                $patch = $this->byIndex($xpath, 'data-patch-index')[$index] ?? null;
                $this->assertInstanceOf(DOMElement::class, $patch, $label['key']);
                $text = $xpath->query('.//span[contains(@class, "l10n-patch__text")]', $patch)->item(0)->textContent;
                $this->assertSame(self::replaceFirst($label['text'], $label['value'], $expected), $text);
                $this->assertStringNotContainsString('{', $text);
                $checked++;
            }
        }
        $this->assertGreaterThan(0, $checked, 'fixture has placeholder labels with args');
    }

    public function test_placeholder_without_a_capture_arg_shows_the_note_instead(): void
    {
        $dir = $this->tempScreensDir();
        $manifest = $this->manifest();
        $stripped = [];
        foreach ($manifest['screens'] as &$screen) {
            foreach ($screen['locales'] as $locale => &$capture) {
                foreach ($capture['labels'] as &$label) {
                    if (isset($label['args'])) {
                        $label['args'] = [];
                        $stripped[] = [$screen['id'], $locale, $label['key']];
                    }
                }
            }
        }
        unset($screen, $capture, $label);
        File::put($dir.'/manifest.json', json_encode($manifest));
        config(['i18n.screens_path' => $dir]);
        $this->assertNotSame([], $stripped);

        foreach ($stripped as [$screen, $locale, $key]) {
            $names = AppTranslationKey::query()->where('key', $key)->first()->placeholderNames();
            $xpath = $this->xpath($this->page(['screen' => $screen, 'locale' => $locale]));
            if ($names === []) {
                continue;
            }
            // Unedited: nothing to compare against the captured value, so no overlay and no note.
            $this->assertSame(0, $xpath->query('//*[@data-patch-key="'.$key.'"]')->length);

            $this->saveDraft($locale, $key, $this->bundled($locale, $key).self::SUFFIX);
            $xpath = $this->xpath($this->page(['screen' => $screen, 'locale' => $locale]));
            $this->assertSame(0, $xpath->query('//*[@data-patch-key="'.$key.'"]')->length, $key);
            $this->assertSame(LabelScreenPreview::NOTE, trim($xpath->query('//*[@data-note-key="'.$key.'"]')->item(0)?->textContent ?? ''), $key);
        }
    }

    public function test_plural_messages_show_the_note_and_no_overlay(): void
    {
        $checked = 0;
        foreach ($this->captures() as $capture) {
            foreach ($capture['labels'] as $index => $label) {
                if (! $this->isIcu($label['key'])) {
                    continue;
                }
                $this->saveDraft($capture['locale'], $label['key'], $this->bundled($capture['locale'], $label['key']).self::SUFFIX);
                $xpath = $this->xpath($this->page($capture));
                $this->assertArrayNotHasKey($index, $this->byIndex($xpath, 'data-patch-index'));
                $note = $this->byIndex($xpath, 'data-note-index')[$index] ?? null;
                $this->assertInstanceOf(DOMElement::class, $note, $label['key']);
                $this->assertSame(LabelScreenPreview::NOTE, trim($note->textContent));
                $this->assertContains($label['key'], $this->editedHotspots($xpath));
                $checked++;
            }
        }
        $this->assertGreaterThan(0, $checked, 'fixture has plural labels');
    }

    public function test_original_toggle_shows_the_bare_screenshot(): void
    {
        foreach ($this->captures() as $capture) {
            foreach (array_unique(array_column($capture['labels'], 'key')) as $key) {
                $this->saveDraft($capture['locale'], $key, $this->bundled($capture['locale'], $key).self::SUFFIX);
            }

            $withEdits = $this->xpath($this->page($capture));
            $this->assertNotSame([], $this->byIndex($withEdits, 'data-patch-index'));
            $this->assertSame(1, $withEdits->query('//script[@data-phone-labels]')->length);

            $original = $this->xpath($this->page($capture, ['preview' => LabelController::PREVIEW_ORIGINAL]));
            $this->assertSame([], $this->byIndex($original, 'data-patch-index'));
            $this->assertSame([], $this->byIndex($original, 'data-note-index'));
            $this->assertSame(0, $original->query('//script[@data-phone-labels]')->length);
            $active = $original->query('//a[@data-preview-mode][@aria-current="true"]');
            $this->assertSame(1, $active->length);
            $this->assertSame('original', $active->item(0)->getAttribute('data-preview-mode'));
            $this->assertStringNotContainsString('preview=', $original->query('//a[@data-preview-mode="edits"]')->item(0)->getAttribute('href'));
            // Links on the page keep the mode, so moving between screens stays on the original screenshot.
            foreach ($original->query('//a[@data-shot-locale] | //a[@data-hotspot-key]') as $link) {
                $this->assertStringContainsString('preview=original', $link->getAttribute('href'));
            }

            $this->actingAs($this->admin)->postJson(route('admin.labels.discard', $capture['locale']), ['all' => 1])->assertOk();
        }
    }

    public function test_live_data_is_only_sent_when_the_phone_shows_the_target_language(): void
    {
        foreach ($this->captures() as $capture) {
            $xpath = $this->xpath($this->page($capture));
            $data = json_decode($xpath->query('//script[@data-phone-labels]')->item(0)->textContent, true, 64, JSON_THROW_ON_ERROR);
            $this->assertSame(array_keys($capture['labels']), array_column($data, 'index'));
            foreach ($data as $entry) {
                $label = $capture['labels'][$entry['index']];
                $this->assertSame($label['key'], $entry['key']);
                $this->assertSame($label['value'] ?? null, $entry['value']);
                $this->assertSame(self::previewable($label), isset($entry['css']));
            }
            $this->assertSame($capture['locale'], $xpath->query('//*[@data-phone-screen]')->item(0)->getAttribute('data-live-lang'));

            $other = $capture['locale'] === 'ar' ? 'en' : 'ar';
            $xpath = $this->xpath($this->actingAs($this->admin)->get(route('admin.labels.index', ['lang' => $other, 'screen' => $capture['screen'], 'shot' => $capture['locale']]))->assertOk());
            $this->assertSame(0, $xpath->query('//script[@data-phone-labels]')->length);
            $this->assertFalse($xpath->query('//*[@data-phone-screen]')->item(0)->hasAttribute('data-live-lang'));
        }
    }

    public function test_labels_without_text_or_style_and_bad_fields_fall_back_without_errors(): void
    {
        Log::spy();
        $dir = $this->tempScreensDir();
        File::copy($this->fixturePath.'/welcome.en.png', $dir.'/s.en.png');
        $base = ['x' => 20, 'y' => 40, 'w' => 300, 'h' => 30];
        $good = ['text' => 'Retry now', 'value' => 'Retry', 'style' => ['font_size' => 14, 'color' => '#112233', 'lines' => 1], 'bg' => '#FFFFFF'];
        $labels = [
            ['key' => 'retry'] + $base,                                                        // box only
            ['key' => 'retry', 'text' => 'Retry'] + $base,                                     // no style/value/bg
            ['key' => 'retry', 'style' => 'big'] + $good + $base,                              // style not an object
            ['key' => 'retry', 'bg' => 'red'] + $good + $base,                                 // bad bg
            ['key' => 'retry', 'text' => 'Something else'] + $good + $base,                    // value not in text
            ['key' => 'retry', 'style' => ['font_size' => -3] + $good['style']] + $good + $base, // bad font size
            ['key' => 'retry', 'style' => ['font_family' => "x'; } body { color: red"] + $good['style']] + $good + $base,
            ['key' => 'retry', 'style' => ['color' => 'blue', 'align' => 'middle', 'weight' => 9] + $good['style']] + $good + $base,
            ['key' => 'retry', 'args' => ['a', 'b'], 'text' => ['x']] + $good + $base,
        ];
        File::put($dir.'/manifest.json', json_encode(['version' => 1, 'device' => ['width' => 390], 'screens' => [
            ['id' => 's', 'name' => 'S', 'order' => 1, 'locales' => ['en' => ['image' => 's.en.png', 'width' => 390, 'height' => 844, 'labels' => $labels]]],
        ]]));
        config(['i18n.screens_path' => $dir]);

        $this->saveDraft('en', 'retry', 'Try again');
        $xpath = $this->xpath($this->page(['screen' => 's', 'locale' => 'en']));

        // Every label box is kept, whatever its optional fields.
        $this->assertSame(count($labels), $xpath->query('//a[@data-hotspot-key="retry"]')->length);
        $this->assertSame(count($labels), $xpath->query('//a[@data-hotspot-key="retry"][contains(@class, "is-edited")]')->length);
        $patches = $this->byIndex($xpath, 'data-patch-index');
        // Only labels whose required fields survived get a patch: bad color/align/font family are dropped
        // on their own (defaults apply), a bad font size or bg or a value outside the text means no patch.
        $this->assertSame([6, 7], array_keys($patches));
        $this->assertSame([], $this->byIndex($xpath, 'data-note-index'));
        foreach ($patches as $patch) {
            $this->assertSame('Try again now', $xpath->query('.//span[contains(@class, "l10n-patch__text")]', $patch)->item(0)->textContent);
            $this->assertStringNotContainsString('body', $patch->getAttribute('style'));
            $this->assertStringContainsString('--fs: 14;', $patch->getAttribute('style'));
        }
        $this->assertStringContainsString('color: rgb(17 34 51 / 1);', $patches[6]->getAttribute('style'));
        $this->assertStringContainsString('color: rgb(0 0 0 / 1);', $patches[7]->getAttribute('style'));
        $this->assertStringContainsString('text-align: start;', $patches[7]->getAttribute('style'));

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []) => str_contains($message, 'optional label fields')
            && $context['count'] === 9)->once();
    }

    public function test_non_admins_are_still_refused(): void
    {
        $outsider = User::factory()->create(['email' => 'outsider@example.com']);
        foreach ($this->captures() as $capture) {
            foreach ([[], ['preview' => LabelController::PREVIEW_ORIGINAL]] as $query) {
                $this->actingAs($outsider)
                    ->get(route('admin.labels.index', ['lang' => $capture['locale'], 'screen' => $capture['screen']] + $query))
                    ->assertForbidden();
            }
        }
    }

    private function tempScreensDir(): string
    {
        $dir = storage_path('framework/testing/label-preview-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($dir);
        foreach (File::files($this->fixturePath) as $file) {
            if ($file->getExtension() === 'png') {
                File::copy($file->getPathname(), $dir.'/'.$file->getFilename());
            }
        }
        $this->tempDirs[] = $dir;

        return $dir;
    }
}
