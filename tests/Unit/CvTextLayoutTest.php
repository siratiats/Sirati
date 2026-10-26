<?php

namespace Tests\Unit;

use App\Cv\CvDocument;
use App\Cv\CvTextLayout;
use App\Cv\LegacySectionParser;
use App\Support\Utf8Sanitizer;
use PHPUnit\Framework\TestCase;

class CvTextLayoutTest extends TestCase
{
    /** Lowercase body words; connectors included so wraps can land after them. */
    private const WORDS = [
        'managed', 'campaigns', 'across', 'multiple', 'product', 'categories', 'improving', 'conversion',
        'retargeting', 'revenue', 'performance', 'budget', 'reports', 'weekly', 'team', 'customer',
        'experience', 'platform', 'checkout', 'listings', 'search', 'data-driven', 'and', 'with', 'for',
        'the', 'of', 'to', 'tracking', 'channels', 'payroll', 'entities',
    ];

    private const TITLES = ['Delivered', 'Reduced', 'Promoted', 'Country', 'Managed', 'Built', 'Led', 'Raised', 'Google', 'Saudi'];

    private const NOUNS = ['Leadership', 'Teamwork', 'Budgeting', 'Reporting', 'Negotiation', 'Analytics', 'Excel', 'Python', 'KSA', 'Grade A'];

    private const ARABIC = ['أدار', 'فريق', 'التحول', 'الرقمي', 'الموارد', 'البشرية', 'لشركة', 'كبرى', 'حملات', 'رقمية', 'لتحسين', 'معدل', 'التحويل', 'المتجر'];

    private const LINE_END_CONNECTORS = ['and', 'or', 'for', 'the', 'of', 'to', 'in', 'with', 'a', 'an', 'في', 'من', 'و'];

    public function test_wraps_that_signal_a_continuation_rejoin_exactly(): void
    {
        mt_srand(20261026);
        for ($run = 0; $run < 300; $run++) {
            $sentences = [];
            for ($i = 0, $count = mt_rand(1, 8); $i < $count; $i++) {
                $sentences[] = $this->sentence(mt_rand(5, 40));
            }
            // Lowercase body words: every wrap starts lowercase or follows a connector.
            $width = mt_rand(55, 130);
            $lines = [];
            foreach ($sentences as $sentence) {
                array_push($lines, ...$this->wrap($sentence, $width));
            }

            $this->assertSame($sentences, CvTextLayout::unwrapLines($lines), "width {$width}, run {$run}");
        }

        // Short wraps join too when the next line is lowercase.
        $this->assertSame(
            ['Managed campaigns across multiple product categories in Saudi Arabia.'],
            CvTextLayout::unwrapLines(['Managed campaigns across multiple', 'product categories in Saudi Arabia.']),
        );
        // A hyphenated word broken at the line end is rejoined without a space.
        $this->assertSame(['Built data-driven reports.'], CvTextLayout::unwrapLines(['Built data-', 'driven reports.']));
    }

    public function test_complete_items_never_join_whatever_their_length_or_script(): void
    {
        mt_srand(7);
        for ($run = 0; $run < 300; $run++) {
            $count = mt_rand(2, 8);
            $lists = [
                // Capitalized items of any length without punctuation (50+ chars included).
                array_map(fn (): string => trim($this->pick(self::TITLES, 1)[0].' '.implode(' ', $this->pick(['payroll', 'platform', 'units', 'close', 'days', 'team', 'KSA'], mt_rand(0, 14)))), range(1, $count)),
                // Full sentences.
                array_map(fn (): string => $this->sentence(mt_rand(3, 30)), range(1, $count)),
                // Arabic items (no letter case).
                array_map(fn (): string => implode(' ', $this->pick(self::ARABIC, mt_rand(2, 12))), range(1, $count)),
                // A heading ending in a colon, then numbered items.
                ['Key achievements across the retail and finance entities:', ...array_map(fn (int $i): string => $i.'. Cut the close for '.$this->pick(self::NOUNS, 1)[0], range(1, $count))],
                // Short typed lowercase items.
                array_map(fn (): string => implode(' ', $this->pick(['led', 'team', 'built', 'api', 'ran', 'reports'], mt_rand(1, 3))), range(1, $count)),
                // Items ending on a capital "A" or an acronym, not a connector.
                array_map(fn (): string => 'Promoted to grade '.$this->pick(['A', 'B+', 'KSA', 'GA4'], 1)[0], range(1, $count)),
            ];
            // Sentences mixed with long unpunctuated items (the old 50-char rule joined these).
            $lists[] = array_merge(...array_map(null, $lists[0], $lists[1]));
            foreach ($lists as $list) {
                // An item that really ends on a connector is a wrap by definition.
                $list = array_values(array_filter($list, fn (string $item): bool => ! in_array((string) preg_replace('/^.*\s/us', '', $item), self::LINE_END_CONNECTORS, true)));
                $this->assertSame($list, CvTextLayout::unwrapLines($list), "run {$run}");
            }
        }
    }

    public function test_unwrapping_is_idempotent(): void
    {
        mt_srand(11);
        for ($run = 0; $run < 200; $run++) {
            $lines = [];
            for ($i = 0, $count = mt_rand(1, 12); $i < $count; $i++) {
                $lines[] = mt_rand(0, 1) ? $this->pick(self::NOUNS, 1)[0] : implode(' ', $this->pick(self::WORDS, mt_rand(1, 20))).(mt_rand(0, 1) ? '.' : '');
            }
            $once = CvTextLayout::unwrapLines($lines);
            $this->assertSame($once, CvTextLayout::unwrapLines($once));
        }
    }

    public function test_narrative_is_dropped_only_when_nothing_but_its_bullets_and_a_header_remain(): void
    {
        mt_srand(3);
        for ($run = 0; $run < 150; $run++) {
            $bullets = array_map(fn (): string => $this->sentence(mt_rand(5, 25)), range(1, mt_rand(1, 6)));
            $raw = implode(' ', array_merge(...array_map(fn (string $b): array => $this->wrap($b, mt_rand(55, 120)), $bullets)));
            $newline = mt_rand(0, 1) === 1;
            $spaced = fn (string $text): string => $newline ? str_replace(' ', "\n", $text) : $text;

            $this->assertTrue(CvTextLayout::narrativeRepeatsBullets($spaced($raw), $bullets));
            $this->assertTrue(CvTextLayout::narrativeRepeatsBullets($spaced('Example Co. | Riyadh | Nov 2025 – Present '.$raw), $bullets));

            // Any leftover sentence keeps the narrative, wherever it sits.
            $extra = $this->pick(['Board approved.', 'Reported to the CFO.', 'تمت الموافقة.'], 1)[0];
            $this->assertFalse(CvTextLayout::narrativeRepeatsBullets($raw.' '.$extra, $bullets));
            $this->assertFalse(CvTextLayout::narrativeRepeatsBullets($extra.' '.$raw, $bullets));

            // A description that merely contains a short bullet keeps.
            $short = implode(' ', array_slice(explode(' ', $bullets[0]), 0, 2));
            $this->assertFalse(CvTextLayout::narrativeRepeatsBullets($this->sentence(20).' '.$short, [$short]));
        }
    }

    public function test_tidy_document_rejoins_bullets_drops_duplicate_narrative_unwraps_summary_and_keeps_bilingual_pairs(): void
    {
        mt_srand(5);
        for ($run = 0; $run < 50; $run++) {
            $sentences = array_map(fn (): string => $this->sentence(mt_rand(10, 30)), range(1, mt_rand(2, 6)));
            $lines = array_merge(...array_map(fn (string $s): array => $this->wrap($s, mt_rand(55, 120)), $sentences));
            $own = $this->sentence(6);

            $document = CvTextLayout::tidyDocument([
                'summary' => ['ar' => '', 'en' => implode("\n", $lines)],
                'experience' => [
                    [
                        'bullets' => array_map(fn (string $line): array => ['ar' => '', 'en' => $line], $lines),
                        'narrative' => ['ar' => '', 'en' => implode("\n", $lines)],
                    ],
                    [
                        'bullets' => [['ar' => 'سطر أول', 'en' => 'first line'], ['ar' => 'سطر ثان', 'en' => 'second line']],
                        'narrative' => ['ar' => '', 'en' => $own],
                    ],
                ],
            ]);

            $this->assertSame(implode("\n", $sentences), $document['summary']['en']);
            $this->assertSame($sentences, array_column($document['experience'][0]['bullets'], 'en'));
            $this->assertSame('', $document['experience'][0]['narrative']['en']);
            $this->assertCount(2, $document['experience'][1]['bullets'], 'Bilingual bullets keep their pairing.');
            $this->assertSame($own, $document['experience'][1]['narrative']['en']);
            $this->assertSame($document, CvTextLayout::tidyDocument($document));
        }
    }

    public function test_any_number_of_pasted_qualifications_split_into_one_entry_each(): void
    {
        $degrees = ['Master of', 'Bachelor of', "Bachelor's degree in", 'Associate of', 'Diploma in', 'PhD in', 'MBA', 'BSc', 'MA in', 'Doctor of', 'High School Diploma', 'ماجستير في', 'الماجستير في', 'بكالوريوس', 'البكالوريوس في', 'دبلوم'];
        $fields = ['Project Management (MPM)', 'Pharmacy (Pharm.D)', 'Computer Science — Programming', 'Marketing', 'إدارة المشاريع', 'الصيدلة'];
        // Detail lines, including ones that start with a degree-like word but are not degrees.
        $details = ['Example University, College of Management | Online | Expected 2027', 'King Saud University | 2018', 'GPA 3.9', 'Associate member of SOCPA', 'Doctor on call rota for the university hospital', 'High school mentoring program ran for four years', 'Massachusetts campus | 2016', 'جامعة المثال | الرياض | 2019'];

        mt_srand(23);
        for ($run = 0; $run < 200; $run++) {
            $expected = [];
            for ($i = 0, $count = mt_rand(1, 5); $i < $count; $i++) {
                $lines = [$this->pick($degrees, 1)[0].' '.$this->pick($fields, 1)[0]];
                for ($d = 0, $n = mt_rand(0, 2); $d < $n; $d++) {
                    $lines[] = $this->pick($details, 1)[0];
                }
                $expected[] = implode("\n", $lines);
            }

            $this->assertSame($expected, CvTextLayout::splitQualifications(implode("\n", $expected)), "run {$run}");
            $this->assertCount($count, LegacySectionParser::education(implode("\n", $expected), 'en'), "run {$run}");

            // An entry with its own degree keeps it; the listed degrees follow.
            $withDegree = CvTextLayout::tidyDocument(['education' => [[
                'degree' => ['ar' => '', 'en' => 'Lead qualification'],
                'narrative' => ['ar' => '', 'en' => implode("\n", $expected)],
            ]]]);
            $this->assertCount($count + 1, $withDegree['education']);

            // An entry with no degree takes the first listed one and keeps its institution and dates.
            $shell = CvTextLayout::tidyDocument(['education' => [[
                'institution' => ['ar' => '', 'en' => 'King Saud University'],
                'degree' => ['ar' => '', 'en' => ''],
                'start_date' => '2014-09',
                'end_date' => '2018-06',
                'narrative' => ['ar' => '', 'en' => implode("\n", $expected)],
            ]]]);
            $this->assertCount($count, $shell['education']);
            $this->assertSame('King Saud University', $shell['education'][0]['institution']['en']);
            $this->assertSame('2014-09', $shell['education'][0]['start_date']);
            $this->assertSame(explode("\n", $expected[0])[0], $shell['education'][0]['degree']['en']);
            $this->assertSame($shell, CvTextLayout::tidyDocument($shell));
        }
    }

    public function test_issuer_moves_out_of_the_name_only_when_the_right_side_is_an_organization(): void
    {
        $names = ['Data Analytics Professional Certificate', 'Foundations of Project Management', 'Comprehensive Marketing Diploma (CMD)', 'Data-Driven Marketing', 'شهادة تحليل البيانات'];
        $issuers = ['Google', 'CPD Certification Service, United Kingdom', 'Coursera', 'Project Management Institute', 'جوجل', 'جامعة الملك سعود'];
        $clauses = ['Level II', 'Quality Management Systems', 'Part 1', 'Advanced Track', 'المستوى الثاني'];

        mt_srand(29);
        for ($run = 0; $run < 100; $run++) {
            $name = $this->pick($names, 1)[0];
            $issuer = $this->pick($issuers, 1)[0];
            $dash = $this->pick(['—', '–'], 1)[0];

            $this->assertSame([$name, $issuer], CvTextLayout::splitIssuer("{$name} {$dash} {$issuer}"));
            $this->assertSame([$name, ''], CvTextLayout::splitIssuer($name), 'A hyphenated or plain name is never split.');
            $titled = $this->pick(['CFA', 'ISO 9001', 'CPA', $name], 1)[0]." {$dash} ".$this->pick($clauses, 1)[0];
            $this->assertSame([$titled, ''], CvTextLayout::splitIssuer($titled), 'A title clause is not an issuer.');

            $parsed = LegacySectionParser::certifications("- {$name} {$dash} {$issuer} | 2025", 'en')[0];
            $this->assertSame($name, $parsed->name->resolve('en'));
            $this->assertSame($issuer, $parsed->issuer->resolve('en'));

            $kept = CvTextLayout::tidyDocument(['certifications' => [[
                'name' => ['ar' => '', 'en' => "{$name} {$dash} Google"],
                'authority' => ['ar' => '', 'en' => $issuer],
            ]]]);
            $this->assertSame("{$name} {$dash} Google", $kept['certifications'][0]['name']['en'], 'An entry that has an issuer keeps its name.');
        }
    }

    public function test_every_latin_ligature_expands_inside_any_word_and_arabic_is_untouched(): void
    {
        mt_srand(13);
        foreach (Utf8Sanitizer::LATIN_LIGATURES as $ligature => $letters) {
            for ($run = 0; $run < 50; $run++) {
                $left = $this->pick(['o', 'tra', 'e', 'Of', 'sta', 'a'], 1)[0];
                $right = $this->pick(['ce', 'c', 'ers', 'ice', 'ort', ''], 1)[0];
                $this->assertSame($left.$letters.$right, Utf8Sanitizer::sanitize($left.$ligature.$right));
            }
        }

        for ($run = 0; $run < 100; $run++) {
            $arabic = implode(' ', $this->pick(self::ARABIC, mt_rand(1, 12)))."\u{200F}";
            $this->assertSame($arabic, Utf8Sanitizer::sanitize($arabic));
            $mixed = $arabic.' o'."\u{FB03}".'ce';
            $this->assertSame(Utf8Sanitizer::sanitize($mixed), Utf8Sanitizer::sanitize(Utf8Sanitizer::sanitize($mixed)));
        }

        $this->assertSame(['a' => ['b' => 'office']], Utf8Sanitizer::sanitizeDeep(['a' => ['b' => "o\u{FB03}ce"]]));
    }

    public function test_stored_dates_print_as_month_and_year_in_both_languages(): void
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        mt_srand(17);
        for ($run = 0; $run < 200; $run++) {
            $year = mt_rand(1970, 2035);
            $month = mt_rand(1, 12);
            $stored = match (mt_rand(0, 2)) {
                0 => sprintf('%04d-%02d', $year, $month),
                1 => sprintf('%04d-%02d-%02d', $year, $month, mt_rand(1, 28)),
                default => sprintf(mt_rand(0, 1) ? '%02d/%04d' : '%d/%04d', $month, $year),
            };

            $this->assertSame($months[$month - 1].' '.$year, CvDocument::displayDate($stored, 'en'));
            $this->assertStringEndsWith(' '.$year, (string) CvDocument::displayDate($stored, 'ar'));
            $this->assertDoesNotMatchRegularExpression('/\d{4}-\d|\d\/\d/', (string) CvDocument::displayDate($stored, 'ar'));
        }

        foreach (['2021', 'Nov 2025', 'Present', 'حتى الآن', '2025-13', '13/2025', 'Expected 2027'] as $authored) {
            $this->assertSame($authored, CvDocument::displayDate($authored, 'en'));
        }
        $this->assertNull(CvDocument::displayDate(null, 'en'));
    }

    private function sentence(int $words): string
    {
        return $this->pick(self::TITLES, 1)[0].' '.implode(' ', $this->pick(self::WORDS, $words)).'.';
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

    /**
     * @param  list<string>  $from
     * @return list<string>
     */
    private function pick(array $from, int $count): array
    {
        return $count < 1 ? [] : array_map(fn (): string => $from[mt_rand(0, count($from) - 1)], range(1, $count));
    }
}
