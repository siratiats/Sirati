<?php

namespace Database\Seeders;

use App\Services\I18n\ArbImporter;
use Illuminate\Database\Seeder;

/** Loads the label catalog and the ar/en base strings from resources/i18n (same as i18n:import-arb). */
class AppLocalizationSeeder extends Seeder
{
    public function run(): void
    {
        app(ArbImporter::class)->importDirectory(config('i18n.arb_path', resource_path('i18n')));
    }
}
