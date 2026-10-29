<?php

namespace App\Services\Jobs;

/**
 * Decides, once at ingestion, what an aggregated post is: whether it is a job
 * worth publishing, its clean title and its language.
 */
class JobPostClassifier
{
    public const REJECT_EMPTY = 'empty';

    public const REJECT_IMAGE_ONLY = 'image_only';

    public const REJECT_NOT_A_JOB = 'not_a_job';

    /** A post that offers work (normalised text). Wins over a non-job cue. */
    private const HIRING_CUE = '/وظيف|وظائف|توظيف|شاغر|شواغر|(?<!\p{L})مطلوب|تمهير|تطوير الخريجين|فرص عمل|فرصه عمل|(?:نبحث|يبحث|تبحث) عن|بحاجه (?:الي|ل)|(?<![a-z])looking for(?![a-z])|(?<![a-z])(?:hiring|vacanc(?:y|ies)|jobs?|positions?|openings?)(?![a-z])/u';

    /**
     * A job actually offered (normalised text). Only this overrides a course
     * cue: "دورة في مهارات التوظيف" is about employment, not a vacancy.
     */
    private const OFFER_CUE = '/(?<!\p{L})(?:[وبل]|ال)?(?:وظائف|وظيفه|شاغر|شاغره|شواغر|تمهير)(?!\p{L})|(?<!\p{L})[وب]?مطلوب(?:ين|ون)?(?!\p{L})|تطوير الخريجين|فرص(?:ه)? عمل|فرص(?:ه)? وظيفيه|(?:منتهي|منتهيه|مبتدئ|مبتدئه|مبتديء)\s+بالتوظيف|فتح (?:باب )?التوظيف|(?<![a-z])(?:hiring|vacanc(?:y|ies))(?![a-z])/u';

    /** Text that points at the attachment for the details (normalised). */
    private const SEE_ATTACHMENT = '/اضغط علي (?:الملف|الصوره)|(?:في|داخل) (?:الملف|الصوره|المرفق)|(?:الملف|الصوره) المرفق|التفاصيل بالصوره|(?<![a-z])(?:see|check) (?:the )?(?:attached|image|pdf|file)(?![a-z])/u';

    /** Courses, admissions and training without a job (normalised text). */
    private const NON_JOB_CUE = '/(?<![\p{L}\p{N}])(?:[وبل]|ال|بال)?(?:دوره|دورات|ورشه|ورش|ندوه|قبول|الدراسات العليا|الدبلوم العالي|التجسير|برامج الدبلوم|برنامج تدريبي|برامج تدريبيه)(?![\p{L}\p{N}])|(?<![a-z])(?:courses?|webinars?|workshops?|admissions?)(?![a-z])/u';

    /** Header words that say "a job" without saying which (normalised). */
    private const GENERIC_WORDS = [
        'فرصه', 'فرص', 'وظيفيه', 'وظيفه', 'وظائف', 'وظيفي', 'اعلان', 'توظيف', 'شاغر', 'شاغره', 'شواغر',
        'جديده', 'جديد', 'عاجل', 'مطلوب', 'في', 'لدى', 'لدي', 'من', 'الخاص', 'رجال', 'ونساء', 'نساء', 'للجنسين',
        'عده', 'مدن', 'مناطق', 'المملكه', 'منطقه', 'انضم', 'الى', 'فريقنا', 'بمختلف', 'job', 'jobs', 'vacancy',
        'opportunity', 'hiring', 'now', 'we’re', "we're", 'were', 'are', 'للسعوديين', 'للسعوديات', 'والسعوديات', 'السعوديين', 'فقط',
    ];

    /** A leading "عاجل |"-style segment that is not part of the title. */
    private const URGENCY_SEGMENT = '/^(?:عاجل|رسميا|قريبا|جديد|هام|مهم|مجانا|حصريا|(?:[^|]{0,30})?(?:بدا|يبدا) التقديم[^|]{0,20})$/u';

    /** Lines that name the role under a generic header ("💼 محاسب", "المسمى: …"). */
    private const ROLE_LINE = '/^\s*(?:[\x{1F4BC}\x{2B05}\x{27A1}\x{2B95}]|\x{1F4CC}?\s*(?:المسمى(?: الوظيفي)?|job title)\s*[:：])/iu';

    public function __construct(private readonly JobTaxonomyMatcher $matcher) {}

    /**
     * Why [$text] should not be published, or null to publish it. A short
     * post with no apply destination and no phone is dropped when it names
     * no role (a generic header such as "وظائف في القطيف") or offers no work.
     * With a photo or file the bar is higher, since the job is then usually
     * in the image; the attachment alone never drops a post that names a
     * role. A course or admission is not a job unless it offers one.
     *
     * @param  array{url: ?string, email: ?string}  $apply
     */
    public function rejectionReason(string $text, array $apply, bool $hasMedia): ?string
    {
        $actionable = $apply['url'] !== null || $apply['email'] !== null || $this->hasPhone($text);

        // Hashtags are channel tags ("#وظائف_الشرقية"), not an offer.
        $prose = $this->matcher->normalizeText((string) preg_replace('/#\S+/u', ' ', $text));
        if (! $actionable && $this->letterCount($text) < ($hasMedia ? 120 : 60)
            && ($this->isGeneric($this->titleFrom($text)) || ! preg_match(self::HIRING_CUE, $prose)
                || ($hasMedia && preg_match(self::SEE_ATTACHMENT, $prose)))) {
            return $hasMedia ? self::REJECT_IMAGE_ONLY : self::REJECT_EMPTY;
        }

        $head = $this->matcher->normalizeText(implode("\n", array_slice($this->lines($text), 0, 2)));
        if (preg_match(self::NON_JOB_CUE, $head) && ! preg_match(self::OFFER_CUE, $head)) {
            return self::REJECT_NOT_A_JOB;
        }

        return null;
    }

    /**
     * The post's title: the first line that is not a hashtag line, without
     * emoji, bidi marks or "عاجل |"-style prefixes. When that line is only a
     * generic header ("فرصة وظيفية | الدمام"), the role line below it is used.
     */
    public function titleFrom(string $text): string
    {
        $lines = array_slice($this->lines($text), 0, 8);

        $candidates = [];
        foreach ($lines as $line) {
            $clean = $this->cleanLine($line);
            if ($clean !== '') {
                $candidates[] = $clean;
            }
        }

        $title = $candidates[0] ?? '';
        if ($title === '' || $this->isGeneric($title)) {
            foreach ($lines as $line) {
                if (preg_match(self::ROLE_LINE, $line)) {
                    $role = $this->cleanLine(preg_replace('/^[^:：]*(?:المسمى(?: الوظيفي)?|job title)\s*[:：]/iu', '', $line));
                    if ($role !== '' && ! $this->isGeneric($role)) {
                        $title = $role;
                        break;
                    }
                }
            }
        }
        if ($title !== '' && $this->isGeneric($title)) {
            foreach (array_slice($candidates, 1, 3) as $candidate) {
                if (! $this->isGeneric($candidate)) {
                    $title = $candidate;
                    break;
                }
            }
        }

        if (mb_strlen($title) < 5 && isset($candidates[1]) && $candidates[1] !== $title) {
            $title = trim($title.' - '.$candidates[1], ' -');
        }

        return mb_strlen($title) > 180 ? mb_substr($title, 0, 177).'...' : $title;
    }

    /**
     * Whether a stored title still carries what [titleFrom] removes (emoji,
     * prefixes, a hashtag line) or is only a generic header.
     */
    public function isMessyTitle(string $title): bool
    {
        return $this->cleanLine($title) !== trim($title) || $this->isGeneric($title);
    }

    /**
     * 'en' when Latin letters clearly outnumber Arabic ones, else 'ar'. Links
     * and email addresses are not prose and do not count.
     */
    public function languageOf(string $text): string
    {
        $text = (string) preg_replace('~(?:https?://|www\.)\S+|[^\s@]+@[^\s@]+~iu', ' ', $text);
        $arabic = preg_match_all('/\p{Arabic}/u', $text);
        $latin = preg_match_all('/[A-Za-z]/', $text);

        return $latin > 2 * $arabic ? 'en' : 'ar';
    }

    /**
     * Whether a remote job's stated candidate location admits someone in Saudi
     * Arabia: one of its listed places must be exactly an open one
     * ("Worldwide", "EMEA", "Saudi Arabia"). An empty or unknown location, any
     * exclusion ("Worldwide except …") and a qualified one ("Anywhere in
     * Europe") do not.
     */
    public function remoteOpenToSaudi(?string $requiredLocation): bool
    {
        $required = mb_strtolower(trim((string) $requiredLocation));
        if ($required === '' || preg_match('/(?<![a-z])(?:except|excluding|exclude|excl|not|outside|without)(?![a-z])/', $required)) {
            return false;
        }

        $open = ['worldwide', 'anywhere', 'anywhere in the world', 'global', 'globally', 'saudi', 'saudi arabia', 'ksa',
            'middle east', 'mena', 'emea', 'gcc', 'gulf', 'gulf region'];
        foreach (preg_split('/\s*(?:[,;\/|]|\band\b|&)\s*/u', $required) ?: [] as $place) {
            if (in_array(trim($place, " \t.()"), $open, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> non-empty trimmed lines */
    private function lines(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $text) ?: []),
            fn (string $line): bool => $line !== '',
        ));
    }

    private function cleanLine(string $line): string
    {
        // Emoji, pictographs, keycaps, skin tones and bidi marks.
        $line = preg_replace('/[\p{So}\p{Sk}\p{Cs}\p{Co}\x{FE0F}\x{FE0E}\x{200D}\x{20E3}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $line);

        // A line of hashtags ("#وظائف_الشرقية #الخبر") is not a title.
        $tokens = preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens !== [] && count(array_filter($tokens, fn (string $t): bool => str_starts_with($t, '#') || str_contains($t, '_'))) === count($tokens)) {
            return '';
        }

        $line = preg_replace('/\(\s*\)|\[\s*\]/u', '', $line);
        // Inline hashtags read as words ("#legal_leader" is "legal leader").
        $line = preg_replace_callback('/#(\p{L}[\p{L}\p{N}_]*)/u', fn (array $m): string => str_replace('_', ' ', $m[1]), $line);
        // Leading bullets and punctuation; ".NET" keeps its dot.
        $line = preg_replace('/^(?:[^\p{L}\p{N}.(]|\.(?![A-Za-z]))+/u', '', $line);
        $line = preg_replace('/[\s:：\-–—|،,؛*]+$/u', '', $line);
        // A label before the role ("Job Title:", "المسمى الوظيفي:").
        $line = preg_replace('/^(?:المسمى(?: الوظيفي)?|job title|position)\s*[:：]\s*/iu', '', $line);

        $segments = array_map('trim', explode('|', $line));
        $segments = array_values(array_filter($segments, fn (string $s): bool => $s !== ''
            && ! preg_match(self::URGENCY_SEGMENT, $this->matcher->normalizeText($s))
            && ! (count($segments) > 1 && $this->isGeneric($s))));
        $line = implode(' | ', $segments);

        $line = trim(preg_replace('/\s{2,}/u', ' ', $line));
        // "عاجل ورسميًا (…)" without a separator: the words before the title.
        $line = preg_replace('/^(?:و?(?:عاجل|رسميًا|رسميا|قريبًا|قريبا|حصريًا|حصريا)\s+)+(?=\S)/u', '', $line);
        $line = preg_replace('/^(?:[^\p{L}\p{N}.(]|\.(?![A-Za-z]))+/u', '', $line);

        // Close a bracket the post left open; drop a stray closing one.
        $open = substr_count($line, '(');
        $close = substr_count($line, ')');
        if ($open > $close) {
            $line .= str_repeat(')', $open - $close);
        } elseif ($close > $open && str_ends_with($line, ')')) {
            $line = rtrim(substr($line, 0, -1));
        }

        return trim($line);
    }

    private function isGeneric(string $line): bool
    {
        $text = ' '.$this->matcher->normalizeText($line).' ';

        $words = array_map(
            fn (string $w): string => $this->matcher->normalizeText($w),
            [...SaudiCities::allNames(), ...self::GENERIC_WORDS],
        );
        usort($words, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        foreach ($words as $word) {
            $text = preg_replace('/(?<!\p{L})(?:[وبل]|ال)?'.preg_quote($word, '/').'(?!\p{L})/u', ' ', $text);
        }

        return $this->letterCount($text) < 3;
    }

    private function letterCount(string $text): int
    {
        $withoutTags = preg_replace('/#\S+/u', '', $text);

        return preg_match_all('/\p{L}/u', (string) $withoutTags);
    }

    private function hasPhone(string $text): bool
    {
        $digits = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $digits = preg_replace('/(?<=\d)[\s\-.]+(?=\d)/u', '', $digits);

        return (bool) preg_match('/(?<!\d)(?:(?:\+|00)?9665\d{8}|05\d{8}|9200\d{5}|800\d{7})(?!\d)/', (string) $digits);
    }
}
