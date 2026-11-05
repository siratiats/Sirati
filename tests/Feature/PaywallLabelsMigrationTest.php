<?php

namespace Tests\Feature;

use App\Models\AppTranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class PaywallLabelsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/arb-'.uniqid());
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_11_04_000001_import_paywall_labels.php');
        $migration->up();
    }

    /** Every paywall label key in the bundled template. */
    private function paywallKeys(): array
    {
        $template = json_decode(File::get(resource_path('i18n/app_ar.arb')), true);

        return array_values(array_filter(
            array_keys($template),
            fn (string $key) => str_starts_with($key, 'premiumGate'),
        ));
    }

    public function test_failed_imports_fail_the_migration_and_a_retry_imports_every_paywall_label(): void
    {
        $keys = $this->paywallKeys();
        $this->assertNotEmpty($keys);

        $broken = [
            'missing directory' => fn () => $this->dir.'/missing',
            'empty directory' => fn () => $this->dir,
            'invalid translation' => function () {
                $ar = json_decode(File::get(resource_path('i18n/app_ar.arb')), true);
                $en = json_decode(File::get(resource_path('i18n/app_en.arb')), true);
                $en['premiumGateCheckAgain'] = '{unclosed';
                File::put($this->dir.'/app_ar.arb', json_encode($ar, JSON_UNESCAPED_UNICODE));
                File::put($this->dir.'/app_en.arb', json_encode($en, JSON_UNESCAPED_UNICODE));

                return $this->dir;
            },
        ];

        foreach ($broken as $case => $path) {
            config(['i18n.arb_path' => $path()]);
            try {
                $this->runMigration();
                $this->fail("{$case}: the migration must fail");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('i18n:import-arb failed', $e->getMessage(), $case);
            }
        }

        config(['i18n.arb_path' => resource_path('i18n')]);
        $this->runMigration();

        $imported = AppTranslationKey::query()->whereIn('key', $keys)->pluck('key')->all();
        $this->assertEqualsCanonicalizing($keys, $imported);
    }
}
