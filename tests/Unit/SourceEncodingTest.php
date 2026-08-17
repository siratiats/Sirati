<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class SourceEncodingTest extends TestCase
{
    public function test_php_and_dart_sources_contain_no_mojibake_signatures(): void
    {
        $roots = [
            app_path(),
            base_path('bootstrap'),
            config_path(),
            database_path(),
            lang_path(),
            resource_path(),
            base_path('routes'),
            base_path('tests'),
            base_path('flutter_app/lib'),
            base_path('flutter_app/test'),
        ];
        $signature = '/[\x{00D8}\x{00D9}\x{00E2}]/u';

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                $extension = strtolower($file->getExtension());
                if (! in_array($extension, ['dart', 'php'], true)) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                $this->assertIsString($contents);
                $this->assertDoesNotMatchRegularExpression(
                    $signature,
                    $contents,
                    $file->getPathname().' contains a mojibake signature.',
                );
            }
        }
    }
}
