<?php

namespace Tests\Feature;

use App\Enums\AiStatus;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\CvTemplateRenderer;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/**
 * Asserts on the downloaded PDF for CV text pasted out of another PDF:
 * hard wraps, Latin ligatures, a narrative that repeats its bullets, ISO dates.
 */
class CvPdfFormattingTest extends TestCase
{
    use RefreshDatabase;

    private const WORDS = ['managed', 'campaigns', 'across', 'product', 'categories', 'improving', 'conversion', 'Google', 'Ads', 'revenue', 'reports', 'customer', 'platform', 'checkout', 'traffic', 'and', 'with', 'for', 'the'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();
        config(['monetization.enabled' => false]);
        $this->seed(CvTemplateSeeder::class);
    }

    public function test_pasted_experience_prints_each_achievement_once_without_ligatures_or_iso_dates(): void
    {
        mt_srand(26);
        for ($run = 0; $run < 4; $run++) {
            $sentences = array_map(fn (): string => $this->sentence(mt_rand(12, 28)), range(1, mt_rand(3, 6)));
            // Pasted text: hard wraps and an "ffi"/"ff" ligature inside words.
            $sentences[0] = 'Raised o'."\u{FB03}".'ce and o'."\u{FB00}".'er quality '.lcfirst($sentences[0]);
            $lines = array_merge(...array_map(fn (string $s): array => $this->wrap($s, mt_rand(70, 110)), $sentences));
            $year = mt_rand(2010, 2024);
            $month = mt_rand(1, 12);

            $cv = $this->cvWithExperience([
                'company' => ['ar' => '', 'en' => 'Example Retail Co'],
                'title' => ['ar' => '', 'en' => 'Marketing Specialist'],
                'location' => ['ar' => '', 'en' => 'Riyadh'],
                'start_date' => sprintf('%04d-%02d-01', $year, $month),
                'end_date' => null,
                'is_current' => true,
                'bullets' => array_map(fn (string $line): array => ['ar' => '', 'en' => $line], $lines),
                'narrative' => ['ar' => '', 'en' => "Example Retail Co | Riyadh | Present\n".implode("\n", $lines)],
            ]);

            $text = $this->downloadText($cv);
            $expected = ['ffi' => 'office', 'ff' => 'offer'];
            foreach ($expected as $word) {
                $this->assertStringContainsString($word, $text);
            }
            $this->assertDoesNotMatchRegularExpression('/[\x{FB00}-\x{FB06}]/u', $text);
            foreach ($sentences as $sentence) {
                $plain = strtr($sentence, ["\u{FB03}" => 'ffi', "\u{FB00}" => 'ff']);
                $this->assertSame(1, substr_count($text, $this->collapse($plain)), "printed once: {$plain}");
            }
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $this->assertStringContainsString($months[$month - 1].' '.$year, $text);
            $this->assertDoesNotMatchRegularExpression('/\b\d{4}-\d{2}-\d{2}\b/', $text);
        }
    }

    public function test_section_heading_stays_with_its_first_entry_and_long_entries_split(): void
    {
        $renderer = app(CvTemplateRenderer::class);

        // A long entry (taller than a page) and a medium one (fits on a page,
        // not in what is left of it); push the heading past every page-end position.
        foreach ([40, 12] as $bulletCount) {
            $bullets = array_map(fn (int $i): string => $this->sentence(14 + $i % 5), range(1, $bulletCount));
            foreach (range(2, 44, 3) as $summarySentences) {
                mt_srand($summarySentences);
                $summary = implode(' ', array_map(fn (): string => $this->sentence(18), range(1, $summarySentences)));
                $cv = $this->cvWithExperience([
                    'company' => ['ar' => '', 'en' => 'Example Retail Co'],
                    'title' => ['ar' => '', 'en' => 'Zeta Role Title'],
                    'start_date' => '2020-01',
                    'end_date' => '2024-06',
                    'is_current' => false,
                    'bullets' => array_map(fn (string $b): array => ['ar' => '', 'en' => $b], $bullets),
                    'narrative' => ['ar' => '', 'en' => ''],
                ], ['summary_input' => $summary]);

                $pages = $this->pageTexts($renderer->renderPdfBlob($cv, $renderer->resolve(null, 'en'), 'en'));
                $headingPage = $this->pageOf($pages, 'PROFESSIONAL EXPERIENCE');
                $titlePage = $this->pageOf($pages, 'Zeta Role Title');
                $firstBulletPage = $this->pageOf($pages, $this->collapse(mb_substr($bullets[0], 0, 40)));

                $this->assertNotNull($headingPage, "summary {$summarySentences}");
                $this->assertSame($headingPage, $titlePage, "heading left alone at summary {$summarySentences}");
                $this->assertSame($titlePage, $firstBulletPage, "entry body pushed away from its header at summary {$summarySentences}");
            }
        }
    }

    public function test_a_long_skills_block_splits_instead_of_jumping_and_keeps_its_heading(): void
    {
        $renderer = app(CvTemplateRenderer::class);
        $skills = array_map(fn (int $i): string => 'Skill'.$i.' '.self::WORDS[$i % count(self::WORDS)], range(1, 120));
        $split = false;

        foreach (range(2, 44, 3) as $summarySentences) {
            mt_srand($summarySentences);
            $summary = implode(' ', array_map(fn (): string => $this->sentence(18), range(1, $summarySentences)));
            $cv = $this->cvWithExperience([
                'company' => ['ar' => '', 'en' => 'Example Retail Co'],
                'title' => ['ar' => '', 'en' => 'Zeta Role Title'],
                'start_date' => '2020-01',
                'is_current' => true,
                'bullets' => [['ar' => '', 'en' => $this->sentence(12)]],
                'narrative' => ['ar' => '', 'en' => ''],
            ], ['summary_input' => $summary, 'skills_input' => implode(', ', $skills)]);

            $pages = $this->pageTexts($renderer->renderPdfBlob($cv, $renderer->resolve(null, 'en'), 'en'));
            $headingPage = $this->pageOf($pages, 'CORE COMPETENCIES');
            $firstPage = $this->pageOf($pages, 'Skill1 ');
            $lastPage = $this->pageOf($pages, 'Skill120 ');

            $this->assertNotNull($headingPage, "summary {$summarySentences}");
            $this->assertSame($headingPage, $firstPage, "skills heading left alone at summary {$summarySentences}");
            $split = $split || $lastPage > $firstPage;
        }

        $this->assertTrue($split, 'A skills block that does not fit the rest of a page continues on the next one.');
    }

    public function test_a_hard_wrapped_summary_is_stored_and_printed_as_one_paragraph(): void
    {
        mt_srand(41);
        $sentences = array_map(fn (): string => strtolower($this->sentence(mt_rand(12, 24))), range(1, 3));
        $sentences[0] = ucfirst($sentences[0]);
        $text = implode(' ', $sentences);
        $cv = $this->cvWithExperience([
            'company' => ['ar' => '', 'en' => 'Example Retail Co'],
            'title' => ['ar' => '', 'en' => 'Marketing Specialist'],
            'bullets' => [['ar' => '', 'en' => 'Ran campaigns.']],
            'narrative' => ['ar' => '', 'en' => ''],
        ], ['summary_input' => implode("\n", $this->wrap($text, 70))]);

        $this->assertSame($text, $cv->summary_input);
        $this->assertStringContainsString($this->collapse($text), $this->downloadText($cv));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $overrides
     */
    private function cvWithExperience(array $entry, array $overrides = []): GeneratedCv
    {
        $cv = GeneratedCv::create(array_replace([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Maya Noor',
            'email' => 'maya@example.test',
            'phone' => '+966500000000',
            'target_job_title' => 'Marketing Specialist',
            'language' => 'en',
            'summary_input' => 'Marketing specialist focused on measurable growth.',
            'skills_input' => 'Google Ads, GA4',
            'experience_input' => "Marketing Specialist | Example Retail Co | 2020 - 2024\n- Ran campaigns.",
            'education_input' => 'BSc Marketing | Example University | 2019',
            'form_payload' => [],
            'generated_markdown' => '',
            'ai_status' => AiStatus::Completed,
        ], $overrides));

        $document = $cv->cvDocument()->toArray();
        $document['experience'] = [$entry];
        $cv->update(['document' => $document]);

        return $cv->fresh();
    }

    private function downloadText(GeneratedCv $cv): string
    {
        Sanctum::actingAs($cv->user);
        $bytes = $this->get('/api/generated-cvs/'.$cv->id.'/download')->assertOk()->getContent();

        return $this->collapse((new Parser)->parseContent($bytes)->getText());
    }

    /**
     * @return list<string>
     */
    private function pageTexts(string $bytes): array
    {
        return array_map(
            fn ($page): string => $this->collapse($page->getText()),
            (new Parser)->parseContent($bytes)->getPages(),
        );
    }

    /**
     * @param  list<string>  $pages
     */
    private function pageOf(array $pages, string $needle): ?int
    {
        foreach ($pages as $index => $page) {
            if (stripos($page, $needle) !== false) {
                return $index;
            }
        }

        return null;
    }

    private function sentence(int $words): string
    {
        $picked = array_map(fn (): string => self::WORDS[mt_rand(0, count(self::WORDS) - 1)], range(1, $words));
        $picked[0] = ucfirst($picked[0]);

        return implode(' ', $picked).'.';
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $width): array
    {
        $lines = [];
        $line = '';
        foreach (explode(' ', $text) as $word) {
            if ($line !== '' && mb_strlen($line.' '.$word) > $width) {
                $lines[] = $line;
                $line = $word;

                continue;
            }
            $line = $line === '' ? $word : $line.' '.$word;
        }
        $lines[] = $line;

        return $lines;
    }

    private function collapse(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
