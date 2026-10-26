<?php

namespace Tests\Feature;

use App\Cv\CvDocument;
use App\Enums\AiStatus;
use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use App\Models\User;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/**
 * The classic English export follows the reference CV layout: Liberation Sans
 * (Arial metrics) and its palette.
 */
class CvReferenceLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();
        config(['monetization.enabled' => false]);
        $this->seed(CvTemplateSeeder::class);
    }

    public function test_english_export_embeds_only_liberation_sans_in_regular_bold_and_italic(): void
    {
        $cv = $this->cv();
        $document = $cv->cvDocument()->toArray();
        $document['experience'] = [[
            'title' => ['ar' => '', 'en' => 'Marketing Specialist'],
            'company' => ['ar' => '', 'en' => 'Example Retail Co'],
            'start_date' => '2021-03',
            'is_current' => true,
            'bullets' => [['ar' => '', 'en' => 'Ran weekly reporting.']],
            'narrative' => ['ar' => '', 'en' => 'Retail group with 20 branches.'],
        ]];
        $cv->update(['document' => $document]);

        Sanctum::actingAs($cv->user);
        $bytes = $this->get('/api/generated-cvs/'.$cv->id.'/download')->assertOk()->getContent();
        $fonts = array_map(
            fn ($font): string => (string) preg_replace('/^[A-Z]{6}\+/', '', (string) $font->getName()),
            (new Parser)->parseContent($bytes)->getFonts(),
        );

        $this->assertNotEmpty($fonts);
        foreach ($fonts as $font) {
            $this->assertStringStartsWith('LiberationSans', $font);
        }
        foreach (['LiberationSans', 'LiberationSans-Bold', 'LiberationSans-Italic'] as $face) {
            $this->assertContains($face, $fonts);
        }
        $this->assertFileExists(resource_path('fonts/liberation-sans/LICENSE'));
    }

    public function test_a_date_range_never_starts_or_ends_with_a_dash_whatever_is_missing(): void
    {
        mt_srand(43);
        for ($run = 0; $run < 200; $run++) {
            $point = fn (): ?string => match (mt_rand(0, 3)) {
                0 => null,
                1 => sprintf('%04d-%02d', mt_rand(1990, 2030), mt_rand(1, 12)),
                2 => sprintf('%02d/%04d', mt_rand(1, 12), mt_rand(1990, 2030)),
                default => 'Expected '.mt_rand(2026, 2030),
            };
            $start = $point();
            $end = $point();
            $current = mt_rand(0, 3) === 0;
            $language = mt_rand(0, 1) ? 'en' : 'ar';

            $document = CvDocument::fromArray([
                'experience' => [['title' => ['ar' => 'دور', 'en' => 'Role'], 'start_date' => $start, 'end_date' => $end, 'is_current' => $current]],
                'education' => [['degree' => ['ar' => 'بكالوريوس', 'en' => 'BSc'], 'start_date' => $start, 'end_date' => $end]],
            ]);
            $sections = collect($document->resolve($language)->toStructuredSections())->keyBy('key');

            foreach (['experience', 'education'] as $key) {
                $range = (string) $sections[$key]['entries'][0]['date_range'];
                $this->assertDoesNotMatchRegularExpression('/^\s*–|–\s*$/u', $range, "{$key} run {$run}: {$range}");
                $expectedEnd = $key === 'experience' && $current ? ($language === 'en' ? 'Present' : 'حتى الآن') : CvDocument::displayDate($end, $language);
                foreach (array_filter([CvDocument::displayDate($start, $language), $expectedEnd]) as $part) {
                    $this->assertStringContainsString($part, $range, "{$key} run {$run}");
                }
            }
        }
    }

    public function test_palette_migration_moves_only_seeded_palettes_to_the_reference_navy(): void
    {
        $migration = require database_path('migrations/2026_10_26_000002_reference_palette_for_classic_cv_template.php');
        $template = CvTemplate::where('slug', 'ats-classic-professional')->sole();
        $reference = ['primary' => '#1f3864', 'accent' => '#1f3864'];

        foreach ([['primary' => '#1f2937', 'accent' => '#2563eb'], ['primary' => '#1f3864', 'accent' => '#2e4a7d']] as $seeded) {
            DB::table('cv_templates')->where('id', $template->id)->update(['color_tokens' => json_encode($seeded)]);
            $migration->up();
            $this->assertSame($reference, $template->fresh()->color_tokens);
            $migration->up();
            $this->assertSame($reference, $template->fresh()->color_tokens, 'A second run changes nothing.');
        }

        $custom = ['primary' => '#223344', 'accent' => '#aa5500'];
        DB::table('cv_templates')->where('id', $template->id)->update(['color_tokens' => json_encode($custom)]);
        $migration->up();
        $this->assertSame($custom, $template->fresh()->color_tokens, 'An admin palette stays.');
    }

    private function cv(): GeneratedCv
    {
        return GeneratedCv::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Maya Noor',
            'email' => 'maya@example.test',
            'phone' => '+966500000000',
            'target_job_title' => 'Marketing Specialist',
            'language' => 'en',
            'summary_input' => 'Marketing specialist focused on measurable growth.',
            'skills_input' => 'Analytics: Google Ads, GA4',
            'experience_input' => '',
            'education_input' => 'BSc Marketing | Example University | 2019',
            'certifications_input' => '- Google Ads Search | Google | 05/2024',
            'form_payload' => [],
            'generated_markdown' => '',
            'ai_status' => AiStatus::Completed,
        ])->fresh();
    }
}
