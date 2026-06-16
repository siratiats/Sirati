<?php

namespace App\Services;

class AtsScoringService
{
    private const JOB_KEYWORDS = [
        'ecommerce' => ['ecommerce', 'e-commerce', 'shopify', 'woocommerce', 'amazon', 'product listing', 'conversion', 'cart', 'marketplace', 'retail', 'campaign'],
        'marketing' => ['marketing', 'campaign', 'brand', 'content', 'social media', 'seo', 'sem', 'ppc', 'google ads', 'meta ads', 'analytics'],
        'software' => ['javascript', 'python', 'php', 'laravel', 'react', 'flutter', 'node', 'sql', 'api', 'git', 'agile', 'scrum', 'backend', 'frontend'],
        'data' => ['sql', 'python', 'tableau', 'power bi', 'excel', 'data analysis', 'machine learning', 'dashboard', 'analytics', 'reporting'],
        'management' => ['leadership', 'team', 'strategy', 'planning', 'budget', 'stakeholder', 'project management', 'roadmap', 'operations'],
        'finance' => ['financial analysis', 'excel', 'sql', 'accounting', 'budget', 'forecast', 'p&l', 'cash flow', 'audit'],
        'hr' => ['recruitment', 'talent acquisition', 'onboarding', 'performance management', 'employee relations', 'hr policies'],
        'sales' => ['sales', 'revenue', 'quota', 'pipeline', 'crm', 'salesforce', 'hubspot', 'prospecting', 'negotiation'],
    ];

    private const SECTION_PATTERNS = [
        'experience' => '/\b(experience|work history|employment|career|الخبرات|خبرة|العمل)\b/iu',
        'education' => '/\b(education|university|college|degree|bachelor|master|phd|diploma|التعليم|جامعة|بكالوريوس|ماجستير|دكتوراه|دبلوم)\b/iu',
        'skills' => '/\b(skills?|competenc|expertise|technical|tools?|technologies|المهارات|مهارات|كفاءات)\b/iu',
        'summary' => '/\b(summary|profile|objective|overview|about|professional|ملخص|نبذة|هدف)\b/iu',
        'certifications' => '/\b(certif|license|accreditat|credential|award|الشهادات|شهادات|اعتماد|جوائز)\b/iu',
    ];

    private const QUANT_PATTERNS = [
        '/\d+%/u',
        '/\$[\d,.]+[km]?/iu',
        '/\d+x\b/iu',
        '/\d+\+?\s*(years?|months?|سنوات|أشهر)/iu',
        '/\b\d{2,}\b/u',
    ];

    private const ACTION_VERBS = [
        'led', 'managed', 'developed', 'built', 'created', 'increased', 'reduced', 'improved', 'launched', 'optimized', 'implemented', 'analyzed',
        'قدت', 'أدرت', 'طورت', 'أنشأت', 'رفعت', 'خفضت', 'حسنت', 'أطلقت', 'نفذت', 'حللت',
    ];

    public function score(string $resumeText, string $jobTitle): array
    {
        $text = mb_strtolower($resumeText);
        $lines = array_values(array_filter(preg_split('/\R/u', $resumeText) ?: [], fn ($line) => trim($line) !== ''));
        $category = $this->jobCategory($jobTitle);
        $keywords = self::JOB_KEYWORDS[$category];

        $formatScore = $this->formatScore($resumeText, $lines);
        [$keywordScore, $foundKeywords] = $this->keywordScore($text, $jobTitle, $keywords);
        [$structureScore, $sections] = $this->structureScore($resumeText, $text);
        [$experienceScore, $verbCount, $quantifiedCount] = $this->experienceScore($resumeText);
        $educationScore = $this->educationScore($resumeText);
        $summaryScore = $this->summaryScore($resumeText);
        $contactScore = $this->contactScore($resumeText);

        $criteria = [
            'format' => ['score' => $formatScore, 'max' => 15, 'label' => 'التنسيق وقابلية القراءة'],
            'keywords' => ['score' => $keywordScore, 'max' => 30, 'label' => 'الكلمات المفتاحية والتطابق'],
            'structure' => ['score' => $structureScore, 'max' => 15, 'label' => 'الهيكل والأقسام'],
            'experience' => ['score' => $experienceScore, 'max' => 20, 'label' => 'جودة الخبرة والإنجازات'],
            'education' => ['score' => $educationScore, 'max' => 10, 'label' => 'التعليم والشهادات'],
            'summary' => ['score' => $summaryScore, 'max' => 5, 'label' => 'الملخص المهني'],
            'contact' => ['score' => $contactScore, 'max' => 5, 'label' => 'معلومات التواصل'],
        ];

        $total = array_sum(array_column($criteria, 'score'));
        $grade = $this->grade($total);
        $missingKeywords = array_values(array_diff($keywords, $foundKeywords));

        return [
            'total' => $total,
            'grade' => $grade,
            'job_match' => (int) round(($keywordScore / 30) * 100),
            'category' => $category,
            'criteria' => $criteria,
            'strengths' => $this->strengths($foundKeywords, $sections, $verbCount, $quantifiedCount, $educationScore, $summaryScore, $resumeText),
            'weaknesses' => $this->weaknesses($keywordScore, $sections, $verbCount, $quantifiedCount, $resumeText),
            'keywords_found' => $foundKeywords,
            'keywords_missing' => array_slice($missingKeywords, 0, 10),
            'quick_wins' => $this->quickWins($keywordScore, $missingKeywords, $sections, $quantifiedCount, $resumeText),
        ];
    }

    private function jobCategory(string $jobTitle): string
    {
        $title = mb_strtolower($jobTitle);

        return match (true) {
            (bool) preg_match('/ecommerce|e-commerce|متجر|تجارة/u', $title) => 'ecommerce',
            (bool) preg_match('/market|brand|content|social|seo|ppc|advertis|تسويق/u', $title) => 'marketing',
            (bool) preg_match('/software|developer|engineer|frontend|backend|laravel|flutter|برمجة|مطور/u', $title) => 'software',
            (bool) preg_match('/data|analyst|scientist|analytics|تحليل|بيانات/u', $title) => 'data',
            (bool) preg_match('/manager|director|head|lead|رئيس|مدير|إدارة/u', $title) => 'management',
            (bool) preg_match('/finance|financial|accountant|محاسب|مالي/u', $title) => 'finance',
            (bool) preg_match('/hr|human resources|talent|recruiter|بشرية|موارد/u', $title) => 'hr',
            (bool) preg_match('/sales|business development|مبيعات/u', $title) => 'sales',
            default => 'marketing',
        };
    }

    private function formatScore(string $resumeText, array $lines): int
    {
        $score = 3;
        $lineCount = count($lines);

        if ($lineCount >= 10) {
            $score += 4;
        } elseif ($lineCount >= 5) {
            $score += 2;
        }

        if (mb_strlen($resumeText) > 300) {
            $score += 3;
        }

        if ($lineCount > 0 && (mb_strlen($resumeText) / $lineCount) < 120) {
            $score += 3;
        }

        if (collect($lines)->contains(fn ($line) => mb_strlen(trim($line)) < 8)) {
            $score += 2;
        }

        return min(15, $score);
    }

    private function keywordScore(string $text, string $jobTitle, array $keywords): array
    {
        $found = array_values(array_filter($keywords, fn ($keyword) => str_contains($text, mb_strtolower($keyword))));
        $score = (int) round((count($found) / count($keywords)) * 18);
        $titleWords = preg_split('/\s+/u', mb_strtolower($jobTitle)) ?: [];
        $titleMatch = collect($titleWords)->contains(fn ($word) => mb_strlen($word) > 3 && str_contains($text, $word));

        if ($titleMatch) {
            $score += 6;
        }

        if (collect($found)->contains(fn ($keyword) => str_contains(mb_substr($text, 0, 500), $keyword))) {
            $score += 3;
        }

        return [min(30, $score), $found];
    }

    private function structureScore(string $resumeText, string $text): array
    {
        $score = 0;
        $sections = [];

        foreach (self::SECTION_PATTERNS as $name => $pattern) {
            if (preg_match($pattern, $resumeText)) {
                $sections[$name] = true;
                $score += 3;
            }
        }

        $top = mb_substr($resumeText, 0, 300);
        if ($this->hasEmail($top)) {
            $score += 2;
        }

        if ($this->hasPhone($top)) {
            $score += 1;
        }

        $experiencePosition = mb_strpos($text, 'experience');
        $educationPosition = mb_strpos($text, 'education');
        if ($experiencePosition !== false && $educationPosition !== false && $experiencePosition < $educationPosition) {
            $score += 2;
        }

        return [min(15, $score), $sections];
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

        $verbCount = collect(self::ACTION_VERBS)
            ->filter(fn ($verb) => preg_match('/\b'.preg_quote($verb, '/').'/iu', $resumeText))
            ->count();

        if ($verbCount >= 5) {
            $score += 5;
        } elseif ($verbCount >= 2) {
            $score += 3;
        } elseif ($verbCount === 1) {
            $score += 1;
        }

        $quantifiedCount = 0;
        foreach (self::QUANT_PATTERNS as $pattern) {
            preg_match_all($pattern, $resumeText, $matches);
            $quantifiedCount += count($matches[0]);
        }

        if ($quantifiedCount >= 5) {
            $score += 11;
        } elseif ($quantifiedCount >= 3) {
            $score += 7;
        } elseif ($quantifiedCount >= 1) {
            $score += 4;
        }

        return [min(20, $score), $verbCount, $quantifiedCount];
    }

    private function educationScore(string $resumeText): int
    {
        $score = 0;

        if (preg_match('/\b(bachelor|master|phd|mba|bsc|msc|pharm|بكالوريوس|ماجستير|دكتوراه)\b/iu', $resumeText)) {
            $score += 4;
        } elseif (preg_match('/\b(diploma|associate|degree|دبلوم)\b/iu', $resumeText)) {
            $score += 2;
        }

        if (preg_match('/\b(19|20)\d{2}\b/u', $resumeText)) {
            $score += 1;
        }

        if (preg_match('/\b(certif|google|aws|pmp|cpa|cfa|cpd|microsoft|salesforce|hubspot|meta|شهادة|اعتماد)\b/iu', $resumeText)) {
            $score += 5;
        }

        return min(10, $score);
    }

    private function summaryScore(string $resumeText): int
    {
        if (! preg_match(self::SECTION_PATTERNS['summary'], $resumeText)) {
            return 0;
        }

        $wordCount = count(preg_split('/\s+/u', trim(mb_substr($resumeText, 0, 700))) ?: []);

        return min(5, 2 + match (true) {
            $wordCount >= 30 && $wordCount <= 150 => 3,
            $wordCount >= 15 => 2,
            default => 1,
        });
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
