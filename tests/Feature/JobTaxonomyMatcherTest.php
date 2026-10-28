<?php

namespace Tests\Feature;

use App\Models\JobTitle;
use App\Services\AtsScoringService;
use App\Services\Jobs\JobTaxonomyMatcher;
use Database\Seeders\JobTitleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Production callers: SaudiJobAggregatorService and JobNewsSeeder (job_news.job_title_id/category),
// which JobProfileMatcherService compares with the user's job title.
class JobTaxonomyMatcherTest extends TestCase
{
    use RefreshDatabase;

    private JobTaxonomyMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JobTitleSeeder::class);
        $this->matcher = app(JobTaxonomyMatcher::class);
    }

    /** @return iterable<JobTitle> */
    private function titles(): iterable
    {
        return JobTitle::query()->where('slug', '!=', 'other')->get();
    }

    public function test_every_title_name_resolves_to_itself_in_both_languages_and_with_noise(): void
    {
        foreach ($this->titles() as $title) {
            $postings = [
                $title->name_en,
                mb_strtoupper($title->name_en),
                $title->name_ar,
                'Senior '.$title->name_en,
                $title->name_en.' - Riyadh',
                'مطلوب '.$title->name_ar,
                $title->name_ar.' - جدة',
            ];

            foreach ($postings as $posting) {
                $resolved = $this->matcher->resolveJobTitle($posting);
                $this->assertSame(
                    $title->slug,
                    $resolved?->slug,
                    "Posting [{$posting}] resolved to [".($resolved?->slug ?? 'null').'].',
                );
            }
        }
    }

    public function test_other_is_never_matched_automatically(): void
    {
        $postings = ['Other', 'أخرى', 'Physiotherapist', 'Mother and child nurse', 'Other duties as assigned'];
        foreach ($this->titles() as $title) {
            $postings[] = $title->name_en.' and other duties';
        }

        foreach ($postings as $posting) {
            $this->assertNotSame('other', $this->matcher->resolveJobTitle($posting, 'other tasks')?->slug, $posting);
        }
    }

    public function test_keywords_only_match_whole_words(): void
    {
        foreach ($this->titles() as $title) {
            foreach ($title->keywords ?? [] as $keyword) {
                if (! preg_match('/^[a-z]+$/', $keyword)) {
                    continue;
                }
                // The keyword buried inside one longer word must not count.
                $posting = 'zq'.$keyword.'qz';
                $this->assertNull(
                    $this->matcher->resolveJobTitle($posting, 'prefix'.$keyword.'suffix'),
                    "Keyword [{$keyword}] matched inside [{$posting}].",
                );
            }
        }
    }

    public function test_category_is_always_a_scorer_category(): void
    {
        $keys = array_keys(AtsScoringService::jobKeywords());
        $postings = ['Geotechnical consultant', 'Barista', 'مستشار لوجستي', 'Cloud Architect', 'xyz'];
        foreach ($this->titles() as $title) {
            $postings[] = $title->name_en;
            $postings[] = $title->name_ar;
        }

        foreach ($postings as $posting) {
            $match = $this->matcher->match($posting, '', 'الرياض');
            $this->assertContains($match['category'], $keys, "Posting [{$posting}] got [{$match['category']}].");
            if ($match['job_title_id'] === null) {
                $this->assertSame(AtsScoringService::inferCategory($posting), $match['category']);
            }
        }
    }

    public function test_unknown_posting_falls_back_to_the_category_the_scorer_infers(): void
    {
        foreach ([
            'Senior Geotechnical Engineer (Civil)' => 'engineering',
            'Hospital Nurse Educator' => 'healthcare',
            'Pharmaceutical Warehouse Lead' => 'pharmacy',
            'Kotlin Developer' => 'software',
        ] as $posting => $expected) {
            $match = $this->matcher->match($posting);
            if ($match['job_title_id'] === null) {
                $this->assertSame($expected, $match['category'], $posting);
            } else {
                $this->assertSame($expected, JobTitle::find($match['job_title_id'])->category, $posting);
            }
        }
    }
}
