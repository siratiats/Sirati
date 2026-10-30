<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * After migrations alone (what a deploy runs), the public strings endpoint
 * the app reads serves every label in the bundled ARB files.
 */
class AppLabelCatalogDeployTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrated_server_serves_every_bundled_label(): void
    {
        foreach (['ar', 'en'] as $code) {
            $arb = json_decode(file_get_contents(resource_path("i18n/app_{$code}.arb")), true);
            $keys = array_values(array_filter(array_keys($arb), fn ($k) => ! str_starts_with($k, '@')));

            $strings = $this->getJson("/api/i18n/languages/{$code}/strings")->assertOk()->json('strings');

            $this->assertSame([], array_values(array_diff($keys, array_keys($strings))), "missing {$code} labels");
            foreach ($keys as $key) {
                $this->assertSame($arb[$key], $strings[$key], "{$code}.{$key}");
            }
        }
    }
}
