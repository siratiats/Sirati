<?php

namespace App\Services;

use App\Cv\CvDocument;

class AtsScoringService
{
    /**
     * Shared criteria metadata (max scores + Arabic labels).
     * Used by scoring and by AI prompt builders — do not duplicate elsewhere.
     *
     * @var array<string, array{max: int, label: string}>
     */
    public const CRITERIA_META = [
        'format' => ['max' => 15, 'label' => 'التنسيق وقابلية القراءة'],
        'keywords' => ['max' => 30, 'label' => 'الكلمات المفتاحية والتطابق'],
        'structure' => ['max' => 15, 'label' => 'الهيكل والأقسام'],
        'experience' => ['max' => 20, 'label' => 'جودة الخبرة والإنجازات'],
        'education' => ['max' => 10, 'label' => 'التعليم والشهادات'],
        'summary' => ['max' => 5, 'label' => 'الملخص المهني'],
        'contact' => ['max' => 5, 'label' => 'معلومات التواصل'],
    ];

    private const JOB_KEYWORDS = [
        'ecommerce' => ['ecommerce', 'e-commerce', 'shopify', 'woocommerce', 'amazon', 'product listing', 'conversion', 'cart', 'marketplace', 'retail', 'campaign'],
        'marketing' => ['marketing', 'campaign', 'brand', 'content', 'social media', 'seo', 'sem', 'ppc', 'google ads', 'meta ads', 'analytics'],
        'software' => ['javascript', 'python', 'php', 'laravel', 'react', 'flutter', 'node', 'sql', 'api', 'git', 'agile', 'scrum', 'backend', 'frontend'],
        'data' => ['sql', 'python', 'tableau', 'power bi', 'excel', 'data analysis', 'machine learning', 'dashboard', 'analytics', 'reporting'],
        'management' => ['leadership', 'team', 'strategy', 'planning', 'budget', 'stakeholder', 'project management', 'roadmap', 'operations'],
        'finance' => ['financial analysis', 'excel', 'sql', 'accounting', 'budget', 'forecast', 'p&l', 'cash flow', 'audit'],
        'hr' => ['recruitment', 'talent acquisition', 'onboarding', 'performance management', 'employee relations', 'hr policies'],
        'sales' => ['sales', 'revenue', 'quota', 'pipeline', 'crm', 'salesforce', 'hubspot', 'prospecting', 'negotiation'],
        'general' => [
            'communication', 'coordination', 'management', 'leadership', 'planning', 'quality',
            'safety', 'compliance', 'training', 'reporting', 'documentation', 'collaboration',
            'operations', 'analysis',
            'تواصل', 'تنسيق', 'إدارة', 'قيادة', 'تخطيط', 'جودة',
            'سلامة', 'امتثال', 'تدريب', 'تقارير', 'توثيق', 'تعاون',
            'عمليات', 'تحليل',
        ],
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function jobKeywords(): array
    {
        return self::JOB_KEYWORDS;
    }

    /**
     * @return array<string, array{max: int, label: string}>
     */
    public static function criteriaMeta(): array
    {
        return self::CRITERIA_META;
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'ecommerce' => 'التجارة الإلكترونية',
            'marketing' => 'التسويق',
            'software' => 'تطوير البرمجيات',
            'data' => 'البيانات والتحليلات',
            'management' => 'الإدارة والقيادة',
            'finance' => 'المالية والمحاسبة',
            'hr' => 'الموارد البشرية',
            'sales' => 'المبيعات وتطوير الأعمال',
            default => 'عام (كفاءات مهنية مشتركة)',
        };
    }

    /**
     * Normalize Arabic text by stripping tatweel, diacritics, and unifying alef/teh marbuta (SIRATI-88).
     */
    public static function normalizeArabic(string $text): string
    {
        // Strip tatweel (kashida)
        $text = preg_replace('/\x{0640}/u', '', $text) ?? $text;
        // Strip diacritics / tashkeel
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text) ?? $text;
        // Normalize alef variants [إأآا] -> ا
        $text = preg_replace('/[إأآ]/u', 'ا', $text) ?? $text;
        // Normalize teh marbuta [ة] -> ه
        $text = preg_replace('/ة/u', 'ه', $text) ?? $text;

        return $text;
    }

    private const SECTION_PATTERNS = [
        'experience' => '/\b(experience\w*|work history|employment|career)\b|(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:خبر(?:ه|ات)|عمل|مسيره مهنيه|تاريخ مهني)\b/iu',
        'education' => '/\b(education\w*|university|college|degrees?|bachelors?|masters?|phd|diplomas?)\b|(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:م[ؤو]هل(?:ات)?(?:\s+(?:علمي[هة]|تعليمي[هة]))?|تعليم|جامع(?:ه|ات)|بكالوريوس|ماجستير|دكتوراه|دبلوم|درجات علميه)\b/iu',
        'skills' => '/\b(skills?|competenc\w*|expertises?|technical|tools?|technologies)\b|(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:مهار(?:ه|ات)|كفاء(?:ه|ات)|قدرات|ادوات العمل|مهاراتي)\b/iu',
        'summary' => '/\b(summary|profile|objective|overview|about me|about|professional summary)\b|(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:ملخص(?:\s+مهني)?|نبذه(?:\s+(?:مهنيه|عني))?|هدف(?:\s+مهني)?|عني|سيره ذاتيه)\b/iu',
        'certifications' => '/\b(certif\w*|license\w*|licence\w*|accreditat\w*|credential\w*|awards?)\b|(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:شهادات|دورات(?:\s+تدريبيه)?|اعتماد(?:ات)?|رخص|جوائز)\b/iu',
    ];

    private const ACTION_VERBS = [
        'led', 'managed', 'developed', 'built', 'created', 'increased', 'reduced', 'improved', 'launched', 'optimized', 'implemented', 'analyzed',
        'trained', 'supervised', 'directed', 'designed', 'automated', 'saved', 'negotiated', 'delivered', 'administered', 'resolved', 'instructed', 'coordinated', 'monitored',
        'قدت', 'أدرت', 'طورت', 'أنشأت', 'رفعت', 'خفضت', 'حسنت', 'أطلقت', 'نفذت', 'حللت',
        'دربت', 'أشرفت', 'وجهت', 'صممت', 'أتمتت', 'وفرت', 'فاوضت', 'سلمت', 'أعددت', 'نسقت', 'راقبت', 'حققت', 'ساعدت', 'نظمت',
    ];

    /**
     * Extract sections and their contained lines from resume text.
     *
     * @return array<string, array{heading: string, lines: list<string>, content: string}>
     */
    public function extractSections(string $resumeText): array
    {
        $lines = preg_split('/\R/u', $resumeText) ?: [];
        $sections = [];
        $currentSection = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $normalizedLine = self::normalizeArabic($trimmed);

            // Check if this line is a section heading:
            // Must be relatively short (<= 45 chars, <= 5 words), without commas or bullet markers
            $matched = null;
            if (mb_strlen($trimmed) <= 45 && count(preg_split('/\s+/u', $trimmed) ?: []) <= 5 && ! str_contains($trimmed, '،') && ! str_contains($trimmed, ',')) {
                foreach (self::SECTION_PATTERNS as $name => $pattern) {
                    if (preg_match($pattern, $trimmed) || preg_match($pattern, $normalizedLine)) {
                        $matched = $name;
                        break;
                    }
                }
            }

            if ($matched !== null && $matched !== $currentSection) {
                $currentSection = $matched;
                if (! isset($sections[$currentSection])) {
                    $sections[$currentSection] = [
                        'heading' => $trimmed,
                        'lines' => [],
                    ];
                }
                continue;
            }

            if ($currentSection !== null) {
                $sections[$currentSection]['lines'][] = $trimmed;
            }
        }

        foreach ($sections as $name => $data) {
            $sections[$name]['content'] = implode("\n", $data['lines']);
        }

        return $sections;
    }

    /**
     * @param  string|null  $categoryHint  Authoritative category (e.g. from users.job_title.category).
     *                                     When set and valid, skips free-text inference.
     */
    public function scoreDocument(CvDocument $document, ?string $categoryHint = null): array
    {
        $resolved = $document->resolve();
        $jobTitle = $resolved->headline !== '' ? $resolved->headline : $resolved->fullName;
        $header = array_filter([
            $resolved->fullName,
            $resolved->headline,
            implode(' | ', array_filter([$resolved->email, $resolved->phone, $resolved->linkedin, $resolved->location])),
        ]);
        $text = implode("\n\n", array_merge($header, [$resolved->toMarkdown()]));

        return $this->score($text, $jobTitle, $categoryHint);
    }

    public function score(string $resumeText, string $jobTitle, ?string $categoryHint = null): array
    {
        $text = mb_strtolower($resumeText);
        $lines = array_values(array_filter(preg_split('/\R/u', $resumeText) ?: [], fn ($line) => trim($line) !== ''));
        $category = $this->jobCategory($jobTitle, $categoryHint);
        $keywords = self::JOB_KEYWORDS[$category];

        $extractedSections = $this->extractSections($resumeText);

        $formatScore = $this->formatScore($resumeText, $lines);
        [$structureScore, $sections] = $this->structureScore($resumeText, $text, $extractedSections);
        [$experienceScore, $verbCount, $quantifiedCount] = $this->experienceScore($resumeText);
        $educationScore = $this->educationScore($resumeText);
        $summaryScore = $this->summaryScore($resumeText, $extractedSections);
        $contactScore = $this->contactScore($resumeText);

        [$keywordScore, $foundKeywords, $missingKeywords] = $this->keywordScore(
            $resumeText,
            $text,
            $jobTitle,
            $keywords,
            $category,
            $sections,
            $quantifiedCount,
            $lines,
        );

        $scoresByKey = [
            'format' => $formatScore,
            'keywords' => $keywordScore,
            'structure' => $structureScore,
            'experience' => $experienceScore,
            'education' => $educationScore,
            'summary' => $summaryScore,
            'contact' => $contactScore,
        ];

        $criteria = [];
        foreach (self::CRITERIA_META as $key => $meta) {
            $criteria[$key] = [
                'score' => $scoresByKey[$key],
                'max' => $meta['max'],
                'label' => $meta['label'],
            ];
        }

        $total = array_sum(array_column($criteria, 'score'));
        $grade = $this->grade($total);
        $keywordsMax = self::CRITERIA_META['keywords']['max'];

        return [
            'total' => $total,
            'grade' => $grade,
            'job_match' => (int) round(($keywordScore / $keywordsMax) * 100),
            'category' => $category,
            'category_label' => self::categoryLabel($category),
            'criteria' => $criteria,
            'strengths' => $this->strengths($foundKeywords, $sections, $verbCount, $quantifiedCount, $educationScore, $summaryScore, $resumeText),
            'weaknesses' => $this->weaknesses($keywordScore, $sections, $verbCount, $quantifiedCount, $resumeText),
            'keywords_found' => $foundKeywords,
            'keywords_missing' => array_slice($missingKeywords, 0, 10),
            'quick_wins' => $this->quickWins($keywordScore, $missingKeywords, $sections, $quantifiedCount, $resumeText),
        ];
    }

    /**
     * Infer (or accept) a job category key from AtsScoringService::JOB_KEYWORDS.
     * Defaults to 'general' instead of 'marketing' (SIRATI-89).
     *
     * @param  string|null  $categoryHint  When provided and valid, wins over free-text inference.
     */
    private function jobCategory(string $jobTitle, ?string $categoryHint = null): string
    {
        if ($categoryHint !== null && array_key_exists($categoryHint, self::JOB_KEYWORDS)) {
            return $categoryHint;
        }

        $normalizedTitle = self::normalizeArabic($jobTitle);
        $title = mb_strtolower($jobTitle).' '.$normalizedTitle;

        return match (true) {
            (bool) preg_match('/ecommerce|e-commerce|متجر|تجارة/u', $title) => 'ecommerce',
            (bool) preg_match('/market|brand|content|social|seo|ppc|advertis|تسويق/u', $title) => 'marketing',
            (bool) preg_match('/software|developer|programmer|frontend|backend|fullstack|laravel|flutter|devops|برمجة|مبرمج|مطور|مهندس\s+برمجيات/u', $title) => 'software',
            (bool) preg_match('/data|analyst|analytics|تحليل|بيانات/u', $title) => 'data',
            (bool) preg_match('/manager|director|head|lead|رئيس|مدير|إدارة/u', $title) => 'management',
            (bool) preg_match('/finance|financial|accountant|محاسب|مالي/u', $title) => 'finance',
            (bool) preg_match('/hr|human resources|talent|recruiter|بشرية|موارد/u', $title) => 'hr',
            (bool) preg_match('/sales|business development|مبيعات/u', $title) => 'sales',
            default => 'general',
        };
    }

    private function formatScore(string $resumeText, array $lines): int
    {
        $words = preg_split('/\s+/u', trim($resumeText)) ?: [];
        $wordCount = count($words);
        $lineCount = count($lines);

        $score = 0;

        // Substantive content volume (not a bare outline or keyword paragraph)
        if ($wordCount >= 200) {
            $score += 5;
        } elseif ($wordCount >= 100) {
            $score += 3;
        } elseif ($wordCount >= 40) {
            $score += 1;
        }

        // Structural line distribution
        if ($lineCount >= 12) {
            $score += 3;
        } elseif ($lineCount >= 6) {
            $score += 2;
        }

        // Pacing & formatting (healthy avg line between 25 and 110 chars)
        $charCount = mb_strlen($resumeText);
        $avgLineLength = $lineCount > 0 ? ($charCount / $lineCount) : 0;
        if ($avgLineLength >= 25 && $avgLineLength <= 110) {
            $score += 4;
        } elseif ($avgLineLength > 0 && $avgLineLength < 140) {
            $score += 2;
        }

        // List / bullet formatting presence
        $hasBullets = false;
        foreach ($lines as $line) {
            $t = trim($line);
            if (preg_match('/^[\-\•\*\–\—\d+\.]\s+/u', $t)) {
                $hasBullets = true;
                break;
            }
        }
        if ($hasBullets) {
            $score += 3;
        }

        // Anti-duplication penalty: penalize identical long sentences repeated across sections
        $longLines = array_values(array_filter(array_map('trim', $lines), fn ($l) => mb_strlen($l) > 35));
        $uniqueLongLines = array_unique($longLines);
        if (count($longLines) > 0 && count($uniqueLongLines) < count($longLines)) {
            $score = max(0, $score - 4);
        }

        return min(15, max(0, $score));
    }

    private function keywordScore(
        string $resumeText,
        string $text,
        string $jobTitle,
        array $keywords,
        string $category,
        array $sections = [],
        int $quantifiedCount = 0,
        array $lines = [],
    ): array {
        $normalizedText = self::normalizeArabic($resumeText);
        $hasArabic = (bool) preg_match('/\p{Arabic}/u', $resumeText);
        $hasLatin = (bool) preg_match('/[a-z]/i', $resumeText);

        // For 'general', filter keywords by language so English CVs are not scored against Arabic tokens denominator
        $effectiveKeywords = $keywords;
        if ($category === 'general') {
            if ($hasLatin && ! $hasArabic) {
                $effectiveKeywords = array_values(array_filter($keywords, fn ($kw) => ! preg_match('/\p{Arabic}/u', $kw)));
            } elseif ($hasArabic && ! $hasLatin) {
                $effectiveKeywords = array_values(array_filter($keywords, fn ($kw) => (bool) preg_match('/\p{Arabic}/u', $kw)));
            }
        }

        $found = [];
        foreach ($effectiveKeywords as $kw) {
            if ($this->matchKeyword($resumeText, $text, $normalizedText, $kw)) {
                $found[] = $kw;
            }
        }
        $found = array_values(array_unique($found));

        $missing = array_values(array_diff($effectiveKeywords, $found));

        $ratio = count($effectiveKeywords) > 0 ? (count($found) / count($effectiveKeywords)) : 0;
        $score = (int) round($ratio * 18);

        $titleWords = preg_split('/\s+/u', mb_strtolower($jobTitle)) ?: [];
        $titleMatch = collect($titleWords)->contains(fn ($word) => mb_strlen($word) > 3 && str_contains($text, $word));

        if ($titleMatch) {
            $score += 6;
        }

        // Early mention: check if any found keyword or title word appears in the first 500 chars
        $top500 = mb_substr($text, 0, 500);
        $top500Normalized = self::normalizeArabic(mb_substr($resumeText, 0, 500));
        $earlyFound = collect($found)->contains(fn ($keyword) => $this->matchKeyword(mb_substr($resumeText, 0, 500), $top500, $top500Normalized, $keyword));

        if ($earlyFound || ($titleMatch && collect($titleWords)->contains(fn ($w) => mb_strlen($w) > 3 && str_contains($top500, $w)))) {
            $score += 3;
        }

        $words = preg_split('/\s+/u', trim($resumeText)) ?: [];
        $totalWords = count($words);
        $foundCount = count($found);
        $density = $totalWords > 0 ? ($foundCount / $totalWords) : 0;

        // Anti-stuffing & ungrounded keywords guard (SIRATI-87):
        // 1. If candidate has ZERO quantified achievements in experience:
        if ($quantifiedCount === 0) {
            // Unproven keywords without measurable metrics cannot earn top marks (max 8)
            $score = min(8, (int) round($score * 0.45));

            // 2. High keyword density with absent achievements (blatant stuffing):
            if ($density > 0.055 || ($totalWords < 130 && $foundCount >= 7) || count($lines) <= 5) {
                $score = min(4, $score);
            }
        }

        return [min(30, max(0, $score)), $found, $missing];
    }

    private function matchKeyword(string $rawText, string $lowerText, string $normalizedText, string $keyword): bool
    {
        $kw = mb_strtolower(trim($keyword));
        if ($kw === '') {
            return false;
        }

        // Common English keyword stems
        $stemPattern = match ($kw) {
            'communication' => 'communicat\w*',
            'coordination' => 'coordinat\w*',
            'management' => 'manag\w*',
            'leadership' => 'lead\w*',
            'planning' => 'plan\w*',
            'quality' => 'quality|qa',
            'safety' => 'safety|safe',
            'compliance' => 'complian\w*',
            'training' => 'train\w*',
            'reporting' => 'report\w*',
            'documentation' => 'document\w*',
            'collaboration' => 'collaborat\w*',
            'operations' => 'operat\w*',
            'analysis' => 'analy\w*',
            'marketing' => 'market\w*',
            'campaign' => 'campaign\w*',
            'development' => 'develop\w*',
            'testing' => 'test\w*',
            'analytics' => 'analytic\w*',
            'accounting' => 'account\w*',
            'recruitment' => 'recruit\w*',
            'api' => 'apis?|rest(?:ful)?',
            'sql' => 'mysql|postgresql|sqlite|nosql|sql',
            'backend' => 'backend|back-end|خلفي\w*',
            'frontend' => 'frontend|front-end|أمامي\w*',
            default => null,
        };

        if ($stemPattern !== null && preg_match('/(?:\b|^)'.$stemPattern.'(?:\b|$)/iu', $rawText)) {
            return true;
        }

        if (preg_match('/(?:\b|^|[\s،:\-\/\\(])'.preg_quote($kw, '/').'(?:\b|$|[\s،:\-\/\\)])/iu', $lowerText)) {
            return true;
        }

        // Check Arabic keywords with clitics and normalization
        $normalizedKw = self::normalizeArabic($kw);
        if (preg_match('/\p{Arabic}/u', $normalizedKw)) {
            $arStem = match ($normalizedKw) {
                'تواصل' => 'تواصل',
                'تنسيق' => 'تنسيق|نسق',
                'اداره' => 'ادار\w*|ادرت|مدير',
                'قياده' => 'قياد\w*|قدت',
                'تخطيط' => 'تخطيط|خطط',
                'جوده' => 'جود[هة]',
                'سلامه' => 'سلام[هة]',
                'تحليل' => 'تحليل|حلل',
                'امتثال' => 'امتثال|التزام',
                'تدريب' => 'تدريب|درب',
                'تقارير' => 'تقارير|تقرير',
                'توثيق' => 'توثيق|وثق',
                'تعاون' => 'تعاون|تعاونت',
                'عمليات' => 'عمليات|تشغيل',
                default => preg_quote($normalizedKw, '/'),
            };

            $arPattern = '/(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:'.$arStem.')(?:[\s،:\-]|$)/iu';
            if (preg_match($arPattern, $normalizedText) || preg_match($arPattern, $rawText)) {
                return true;
            }
        }

        return false;
    }

    private function structureScore(string $resumeText, string $text, array $extractedSections = []): array
    {
        $score = 0;
        $sections = [];

        $expContent = trim($extractedSections['experience']['content'] ?? '');
        $summaryContent = trim($extractedSections['summary']['content'] ?? '');
        $eduContent = trim($extractedSections['education']['content'] ?? '');
        $skillsContent = trim($extractedSections['skills']['content'] ?? '');

        // Check if experience is an identical copy of summary (sham section)
        $isExpDuplicate = ($summaryContent !== '' && $expContent !== '' && str_contains($expContent, $summaryContent));

        if (isset($extractedSections['experience']) && mb_strlen($expContent) >= 20 && ! $isExpDuplicate) {
            $sections['experience'] = true;
            $score += 4;
        }

        if (isset($extractedSections['education']) && mb_strlen($eduContent) >= 15) {
            $sections['education'] = true;
            $score += 3;
        }

        if (isset($extractedSections['skills']) && mb_strlen($skillsContent) >= 15) {
            $sections['skills'] = true;
            $score += 3;
        }

        // Check if summary is substantive and not a run-on keyword dump
        if (isset($extractedSections['summary']) && mb_strlen($summaryContent) >= 15) {
            preg_match_all('/\b(and|و)\b/iu', $summaryContent, $conjMatches);
            $conjunctionCount = count($conjMatches[0] ?? []);
            $sentenceCount = max(1, count(preg_split('/[.!?؛\n]+/u', $summaryContent) ?: []));
            $wordCount = max(1, count(preg_split('/\s+/u', $summaryContent) ?: []));

            if (! ($conjunctionCount >= 4 && ($conjunctionCount / $sentenceCount >= 3 || ($conjunctionCount / $wordCount) > 0.12))) {
                $sections['summary'] = true;
                $score += 2;
            }
        }

        if (isset($extractedSections['certifications']) && mb_strlen(trim($extractedSections['certifications']['content'] ?? '')) >= 10) {
            $sections['certifications'] = true;
            $score += 1;
        }

        // Header / intro presence in top 3 lines
        $firstLines = array_slice(array_filter(preg_split('/\R/u', $resumeText) ?: [], fn ($l) => trim($l) !== ''), 0, 3);
        $hasTopHeader = collect($firstLines)->contains(fn ($l) => $this->hasEmail($l) || $this->hasPhone($l) || preg_match('/linkedin/i', $l));
        if ($hasTopHeader) {
            $score += 1;
        }

        // Logical ordering: experience before education
        $normalizedResume = self::normalizeArabic($resumeText);
        $expPos = mb_strpos($text, 'experience') !== false ? mb_strpos($text, 'experience') : (mb_strpos($normalizedResume, 'خبر') ?: false);
        $eduPos = mb_strpos($text, 'education') !== false ? mb_strpos($text, 'education') : (mb_strpos($normalizedResume, 'م[ؤو]هل') ?: (mb_strpos($normalizedResume, 'تعليم') ?: false));

        if (isset($sections['experience']) && isset($sections['education']) && $expPos !== false && $eduPos !== false && $expPos < $eduPos) {
            $score += 2;
        } elseif (isset($sections['education']) || isset($sections['experience'])) {
            $score += 1;
        }

        // Penalty if primary section (experience) is missing or a sham duplicate
        if (! isset($sections['experience'])) {
            $score = max(0, $score - 2);
        }

        return [min(15, max(0, $score)), $sections];
    }

    private function experienceScore(string $resumeText): array
    {
        $score = 0;
        preg_match_all('/\b(19|20)\d{2}\b/u', $resumeText, $dates);
        $dateCount = count($dates[0]);

        if ($dateCount >= 2) {
            $score += 4;
        } elseif ($dateCount === 1) {
            $score += 2;
        }

        $verbCount = $this->countActionVerbs($resumeText);

        if ($verbCount >= 5) {
            $score += 5;
        } elseif ($verbCount >= 2) {
            $score += 4;
        } elseif ($verbCount === 1) {
            $score += 2;
        }

        // Distinct bullets containing real metrics (SIRATI-87)
        $quantifiedCount = $this->countQuantifiedAchievements($resumeText);

        if ($quantifiedCount >= 5) {
            $score += 11;
        } elseif ($quantifiedCount >= 4) {
            $score += 10;
        } elseif ($quantifiedCount >= 3) {
            $score += 9;
        } elseif ($quantifiedCount >= 1) {
            $score += 4;
        }

        return [min(20, $score), $verbCount, $quantifiedCount];
    }

    private function countActionVerbs(string $resumeText): int
    {
        $normalized = self::normalizeArabic($resumeText);
        $count = 0;

        foreach (self::ACTION_VERBS as $verb) {
            $patternEn = '/\b'.preg_quote($verb, '/').'\b/iu';
            // In Arabic, support optional و/ف prefix and normalized forms
            $normalizedVerb = self::normalizeArabic($verb);
            $patternAr = '/(?:^|[\s،:\-])(?:و|ف)?'.preg_quote($normalizedVerb, '/').'(?:[\s،:\-]|$)/iu';

            if (preg_match($patternEn, $resumeText) || preg_match($patternAr, $normalized)) {
                $count++;
            }
        }

        return $count;
    }

    private function lineHasActionVerb(string $line): bool
    {
        $normalizedLine = self::normalizeArabic($line);

        foreach (self::ACTION_VERBS as $verb) {
            $patternEn = '/\b'.preg_quote($verb, '/').'\b/iu';
            $normalizedVerb = self::normalizeArabic($verb);
            $patternAr = '/(?:^|[\s،:\-])(?:و|ف)?'.preg_quote($normalizedVerb, '/').'(?:[\s،:\-]|$)/iu';

            if (preg_match($patternEn, $line) || preg_match($patternAr, $normalizedLine)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count distinct achievement bullets carrying a genuine metric (SIRATI-87).
     * Excludes phone numbers, PO boxes, postal codes, and year tokens.
     */
    private function countQuantifiedAchievements(string $resumeText): int
    {
        $lines = preg_split('/\R/u', $resumeText) ?: [];
        $counted = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || mb_strlen($trimmed) < 6) {
                continue;
            }

            // Exclude lines with email, phone, URL/linkedin, PO box, postal code
            if ($this->hasEmail($trimmed)
                || $this->hasPhone($trimmed)
                || preg_match('/(?:https?:\/\/|linkedin\.com|github\.com|p\.?o\.?\s*box|ص\.?ب|رمز بريدي|postal code)/iu', $trimmed)) {
                continue;
            }

            // Exclude lines that are purely date headers (e.g. 2021 – Present, 2018 - 2021)
            if (preg_match('/^(?:\d{4}\s*[-—–]\s*(?:\d{4}|present|حتى الآن|الآن)?)$/iu', $trimmed)) {
                continue;
            }

            // A line is a quantified achievement if it contains a metric:
            // 1. Percentage (e.g. 25%, 30.5%, 25 بالمئة)
            $hasPercentage = (bool) preg_match('/\d+(?:\.\d+)?\s*(?:%|بالمئة|بالمائة|في المائة|في المئة)/u', $trimmed);
            // 2. Currency (e.g. $40M, 3,500,000 SAR, 250,000 ريال)
            $hasCurrency = (bool) preg_match('/(?:[\$€£]|SAR|AED|USD|ر\.س|ريال)\s*[\d,.]+[kmbt]?\b|\b[\d,.]+\s*(?:SAR|AED|USD|ر\.س|ريال|دولار|k|m|b|مليون|ألف)\b/iu', $trimmed);
            // 3. Multiplier (e.g. 2x, 3x, 3 أضعاف)
            $hasMultiplier = (bool) preg_match('/\b\d+(?:\.\d+)?x\b|\b\d+(?:\.\d+)?\s*(?:ضعف|أضعاف)\b/iu', $trimmed);
            // 4. Quantified duration with metric context (e.g. 15 hours per week, 50+ emergency resuscitations)
            $hasDuration = (bool) preg_match('/\b\d+\+?\s*(?:hours?|hrs?|ساعات|أسابيع|أيام)\b/iu', $trimmed);

            // 5. Explicit metric units or quantified objects
            $hasUnitOrNoun = (bool) preg_match('/\b(users?|clients?|customers?|patients?|students?|employees?|members?|accounts?|cases?|transactions?|orders?|requests?|calls?|tickets?|leads?|subscribers?|beds?|points?|shifts?|resuscitations?|sessions?|packages?|rfis?|مستخدم|عميل|عملاء|مريض|مرضى|طالب|طلاب|موظف|موظفين|معاملة|معاملات|طلب|طلبات|تذكرة|تذاكر|مشترك|مشتركين|صفقة|صفقات|سرير|نقطة|نقاط|حالة|حالات|مطور|مطورين|جلسة|جلسات)\b/iu', $trimmed);

            // Check for numbers that are NOT 4-digit years (19xx, 20xx)
            $nonYearNumbers = preg_replace('/\b(19|20)\d{2}\b/u', '', $trimmed);
            $hasNumber = (bool) preg_match('/\b\d+(?:\.\d+)?\+?\b/u', $nonYearNumbers);

            $hasActionVerb = $this->lineHasActionVerb($trimmed);

            if ($hasPercentage || $hasCurrency || $hasMultiplier || $hasDuration || ($hasNumber && ($hasUnitOrNoun || $hasActionVerb))) {
                $counted++;
            }
        }

        return $counted;
    }

    private function educationScore(string $resumeText): int
    {
        $score = 0;
        $normalized = self::normalizeArabic($resumeText);

        if (preg_match('/\b(bachelor\w*|master\w*|phd\w*|mba\w*|bsc\w*|msc\w*|bsn\w*|pharm\w*|بكالوريوس|ماجستير|دكتوراه)\b/iu', $resumeText)
            || preg_match('/(?:وال|بال|لل|ال|و|ب|ل)?(?:بكالوريوس|ماجستير|دكتوراه)/u', $normalized)) {
            $score += 4;
        } elseif (preg_match('/\b(diploma\w*|associate\w*|degrees?|دبلوم)\b/iu', $resumeText)
            || preg_match('/(?:وال|بال|لل|ال|و|ب|ل)?دبلوم/u', $normalized)) {
            $score += 2;
        }

        if (preg_match('/\b(19|20)\d{2}\b/u', $resumeText)) {
            $score += 1;
        }

        if (preg_match('/\b(certif\w*|license\w*|licence\w*|accreditat\w*|credential\w*|awards?|google|aws|pmp|cpa|cfa|cma|cpd|microsoft|salesforce|hubspot|meta|scfhs|bls|acls|cpr|osha|sce|etec|شهادة|اعتماد)\b/iu', $resumeText)
            || preg_match('/(?:وال|بال|لل|ال|و|ب|ل)?(?:شهاده|شهادات|اعتماد|رخصه|ترخيص)/u', $normalized)) {
            $score += 5;
        }

        return min(10, $score);
    }

    private function summaryScore(string $resumeText, array $extractedSections = []): int
    {
        $normalized = self::normalizeArabic($resumeText);
        $pattern = self::SECTION_PATTERNS['summary'];

        if (! isset($extractedSections['summary']) && ! preg_match($pattern, $resumeText) && ! preg_match($pattern, $normalized)) {
            return 0;
        }

        $summaryContent = trim($extractedSections['summary']['content'] ?? '');
        if ($summaryContent === '') {
            return 1; // Heading present but empty or stub
        }

        $words = preg_split('/\s+/u', $summaryContent) ?: [];
        $wordCount = count($words);

        if ($wordCount < 8) {
            return 1;
        }

        // Check for keyword-stuffed run-on sentences in summary (SIRATI-87)
        preg_match_all('/\b(and|و)\b/iu', $summaryContent, $conjMatches);
        $conjunctionCount = count($conjMatches[0] ?? []);
        $sentenceCount = max(1, count(preg_split('/[.!?؛\n]+/u', $summaryContent) ?: []));

        if ($conjunctionCount >= 4 && ($conjunctionCount / $sentenceCount >= 3 || ($conjunctionCount / $wordCount) > 0.12)) {
            return 1; // Stuffed keyword list masquerading as a summary
        }

        if ($wordCount >= 20 && $wordCount <= 140) {
            return 5;
        } elseif ($wordCount >= 10) {
            return 3;
        }

        return 2;
    }

    private function contactScore(string $resumeText): int
    {
        $score = 0;

        if ($this->hasEmail($resumeText)) {
            $score += 2;
        }

        if ($this->hasPhone($resumeText)) {
            $score += 1;
        }

        if (preg_match('/linkedin\.com\/in\//iu', $resumeText)) {
            $score += 2;
        }

        return min(5, $score);
    }

    private function strengths(array $foundKeywords, array $sections, int $verbCount, int $quantifiedCount, int $educationScore, int $summaryScore, string $resumeText): array
    {
        $strengths = [];

        if (count($foundKeywords) >= 4) {
            $strengths[] = 'تغطية جيدة للكلمات المفتاحية: '.implode('، ', array_slice($foundKeywords, 0, 4));
        }

        if ($verbCount >= 3) {
            $strengths[] = 'استخدام مناسب لأفعال إنجاز قوية في قسم الخبرات.';
        }

        if (($sections['experience'] ?? false) && ($sections['education'] ?? false) && ($sections['skills'] ?? false)) {
            $strengths[] = 'الأقسام الأساسية موجودة: الخبرات، التعليم، والمهارات.';
        }

        if ($quantifiedCount >= 3) {
            $strengths[] = "تم رصد {$quantifiedCount} مؤشرات رقمية، وهذا يرفع قوة السيرة.";
        }

        if ($this->hasEmail($resumeText) && $this->hasPhone($resumeText)) {
            $strengths[] = 'معلومات التواصل الأساسية موجودة.';
        }

        if ($educationScore >= 8) {
            $strengths[] = 'قسم التعليم والشهادات قوي ومكتمل.';
        }

        if ($summaryScore >= 4) {
            $strengths[] = 'الملخص المهني واضح ومناسب للطول المطلوب.';
        }

        return $strengths ?: ['السيرة قابلة للقراءة ويمكن تحسينها بسرعة بإضافة الأقسام والكلمات المفتاحية الناقصة.'];
    }

    private function weaknesses(int $keywordScore, array $sections, int $verbCount, int $quantifiedCount, string $resumeText): array
    {
        $weaknesses = [];

        if ($quantifiedCount < 3) {
            $weaknesses[] = ['priority' => 'high', 'issue' => 'الإنجازات الرقمية قليلة.', 'fix' => 'أضف نسباً وأرقاماً مثل: رفعت المبيعات 25% أو خفضت وقت المعالجة 30%.'];
        }

        if (! preg_match('/linkedin\.com\/in\//iu', $resumeText)) {
            $weaknesses[] = ['priority' => 'high', 'issue' => 'رابط LinkedIn مفقود.', 'fix' => 'أضف رابط LinkedIn في أعلى السيرة بجانب البريد والجوال.'];
        }

        if ($keywordScore < 18) {
            $weaknesses[] = ['priority' => 'high', 'issue' => 'تطابق الكلمات المفتاحية منخفض.', 'fix' => 'ادمج الكلمات المناسبة من إعلان الوظيفة داخل الملخص والخبرات.'];
        }

        if (! ($sections['summary'] ?? false)) {
            $weaknesses[] = ['priority' => 'medium', 'issue' => 'الملخص المهني مفقود.', 'fix' => 'أضف ملخصاً من 3 إلى 4 أسطر يبدأ بالمسمى الوظيفي وسنوات الخبرة.'];
        }

        if ($verbCount < 3) {
            $weaknesses[] = ['priority' => 'medium', 'issue' => 'الأفعال القوية قليلة في الخبرات.', 'fix' => 'ابدأ نقاط الخبرة بأفعال مثل: طورت، نفذت، حسنت، أدرت.'];
        }

        return $weaknesses;
    }

    private function quickWins(int $keywordScore, array $missingKeywords, array $sections, int $quantifiedCount, string $resumeText): array
    {
        return array_values(array_filter([
            ! preg_match('/linkedin\.com\/in\//iu', $resumeText) ? 'أضف رابط LinkedIn في الهيدر مباشرة.' : null,
            $quantifiedCount < 3 ? 'حوّل المهام إلى إنجازات رقمية: حسّنت الأداء 25% بدلاً من حسّنت الأداء.' : null,
            $keywordScore < 18 && $missingKeywords ? 'أضف كلمات مفتاحية مناسبة مثل: '.implode('، ', array_slice($missingKeywords, 0, 3)).'.' : null,
            ! ($sections['summary'] ?? false) ? 'أضف ملخصاً مهنياً قصيراً في بداية السيرة.' : null,
        ]));
    }

    private function grade(int $total): string
    {
        return match (true) {
            $total >= 90 => 'A+',
            $total >= 80 => 'A',
            $total >= 70 => 'B',
            $total >= 60 => 'C',
            $total >= 50 => 'D',
            default => 'F',
        };
    }

    private function hasEmail(string $text): bool
    {
        return (bool) preg_match('/[\w.+-]+@[\w-]+\.\w+/u', $text);
    }

    private function hasPhone(string $text): bool
    {
        return (bool) preg_match('/(\+?\d[\d\s\-().]{7,}\d)/u', $text);
    }
}
