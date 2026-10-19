<?php

namespace Tests\Unit;

use App\Services\AtsScoringService;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class AtsJobTermCatalogTest extends TestCase
{
    private function catalog(): array
    {
        return require __DIR__.'/../../resources/ats/job_terms.php';
    }

    public function test_every_stopword_is_excluded_in_isolation_and_random_mixed_contexts(): void
    {
        $scorer = new AtsScoringService;
        $random = new Randomizer(new Mt19937(20261019));
        $normalize = fn (string $s): string => AtsScoringService::normalizeArabic(mb_strtolower($s));
        $stopwords = $this->catalog()['stopwords'];
        foreach ($stopwords as $word) {
            $this->assertSame([], $scorer->extractJobTerms($word), $word);
            $this->assertSame([], $scorer->extractJobTerms(mb_strtoupper($word).'; '.$word), $word);
        }
        for ($sample = 0; $sample < 30; $sample++) {
            $noise = $random->shuffleArray($stopwords);
            $sentinel = 'signal'.bin2hex($random->getBytes(4));
            $jd = implode('; ', [...$noise, $sentinel]);
            $this->assertSame([$sentinel], $scorer->extractJobTerms($jd));
            $score = $scorer->score($sentinel, 'Engineer', 'software', $jd);
            $this->assertSame([$sentinel], $score['keywords_found']);
            $this->assertSame([], $score['keywords_missing']);
            $this->assertSame(100, $score['job_match']);
            $this->assertSame([], array_intersect(array_map($normalize, $stopwords), $score['keywords_found']));
        }
        foreach (['senior', 'junior', 'principal', 'staff', 'lead'] as $seniority) {
            $this->assertSame([$seniority], $scorer->extractJobTerms($seniority));
        }
    }

    public function test_all_curated_phrases_and_aliases_are_atomic_only_at_literal_boundaries(): void
    {
        $scorer = new AtsScoringService;
        $normalize = fn (string $s): string => AtsScoringService::normalizeArabic(mb_strtolower($s));
        foreach ($this->catalog()['phrases'] as $canonical => $aliases) {
            $expected = $normalize($canonical);
            foreach ([$canonical, ...$aliases] as $alias) {
                $present = "(\n".str_replace(' ', "\t\n", mb_strtoupper($alias))."\n)";
                $this->assertSame([$expected], $scorer->extractJobTerms($present), $alias);
                $this->assertSame([$expected], $scorer->extractJobTerms($alias.'; '.$alias), $alias);
                $score = $scorer->score($present, 'Engineer', 'software', $canonical);
                $this->assertSame([$expected], $score['keywords_found'], $alias);
                $this->assertSame(100, $score['job_match'], $alias);
                foreach (['prefix'.$alias, $alias.'suffix', str_replace([' ', '/'], '; ', $alias),
                    str_replace([' ', '/'], ' and ', $alias),
                    str_replace([' ', '/'], ' https://jobs.example/ ', $alias),
                    str_replace([' ', '/'], ' hiring@example.com ', $alias),
                    str_replace([' ', '/'], ' • ', $alias)] as $absent) {
                    $this->assertNotContains($expected, $scorer->extractJobTerms($absent), $absent);
                    $score = $scorer->score($absent, 'Engineer', 'software', $canonical);
                    $this->assertSame([], $score['keywords_found'], $absent);
                    $this->assertSame(0, $score['job_match'], $absent);
                }
            }
        }
    }

    public function test_generated_phrase_subsets_have_exact_deterministic_bounded_coverage(): void
    {
        $scorer = new AtsScoringService;
        $random = new Randomizer(new Mt19937(20261020));
        $phrases = array_keys($this->catalog()['phrases']);
        for ($sample = 0; $sample < 50; $sample++) {
            $selected = array_slice($random->shuffleArray($phrases), 0, $random->getInt(1, 10));
            $covered = array_slice($random->shuffleArray($selected), 0, $random->getInt(0, count($selected)));
            $jd = implode('; ', $selected);
            $resume = 'Skills: '.implode('; ', $covered);
            $terms = $scorer->extractJobTerms($jd);
            $this->assertCount(count($selected), $terms);
            $this->assertEqualsCanonicalizing($terms, $scorer->extractJobTerms(implode('; ', $terms)));
            $score = $scorer->score($resume, 'Engineer', 'software', $jd);
            $this->assertSame((int) round(count($covered) / count($selected) * 100), $score['job_match']);
            $this->assertSame($score, $scorer->score($resume, 'Engineer', 'software', $jd));
            foreach (['total', 'job_match'] as $field) {
                $this->assertGreaterThanOrEqual(0, $score[$field]);
                $this->assertLessThanOrEqual(100, $score[$field]);
            }
            $this->assertCount(count(array_unique($terms)), $terms);
        }
        $many = implode('; ', array_map(fn (int $i): string => 'term'.$i, range(1, 100)));
        $this->assertCount(25, $scorer->extractJobTerms($many));
    }
}
