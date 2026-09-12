<?php

namespace App\Services;

use App\Enums\ResumeClassification;

/**
 * Classifies extracted document text as resume vs non-resume content.
 *
 * Uses signal-based heuristics that count positive (resume-like) and negative
 * (non-resume-like) indicators. The classifier never guesses via orthographic
 * heuristics on Arabic text — it evaluates structural and semantic signals only.
 */
class ResumeClassifier
{
    /**
     * Non-resume indicator patterns.
     *
     * Each pattern carries a weight. Higher weights denote stronger signals.
     *
     * @var array<string, int>
     */
    private const NON_RESUME_PATTERNS = [
        // Banking / financial transfer signals
        '/\bIBAN\b/iu' => 4,
        '/\b(?:account\s*(?:number|no\.?|#)|رقم\s*(?:ال)?حساب)/iu' => 3,
        '/\b(?:bank\s*(?:transfer|statement|receipt)|(?:إيصال|حوالة|كشف)\s*(?:ال)?(?:بنك|بنكي|مصرف))/iu' => 4,
        '/\b(?:تحويل\s*(?:بنكي|مصرفي|مالي))/iu' => 4,
        '/\b(?:beneficiary|المستفيد|اسم\s*المستفيد)/iu' => 3,
        '/\b(?:swift|bic)\s*(?:code)?/iu' => 3,
        '/\b(?:routing\s*number|رقم\s*(?:ال)?توجيه)/iu' => 3,
        '/\b(?:wire\s*transfer|حوالة\s*دولية)/iu' => 3,
        '/\b(?:debit|credit|مدين|دائن)\b/iu' => 2,
        '/\b(?:current\s*balance|الرصيد\s*الحالي|رصيد\s*(?:ال)?حساب)/iu' => 3,

        // Invoice / receipt signals
        '/\b(?:invoice\s*(?:number|no\.?|#|date)|رقم\s*(?:ال)?فاتورة)/iu' => 4,
        '/\b(?:receipt\s*(?:number|no\.?|#)|رقم\s*(?:ال)?إيصال)/iu' => 4,
        '/\b(?:subtotal|المجموع\s*الفرعي)/iu' => 3,
        '/\b(?:grand\s*total|الإجمالي|المجموع\s*الكلي)/iu' => 2,
        '/\b(?:tax\s*(?:amount|rate)|مبلغ\s*(?:ال)?ضريبة|نسبة\s*(?:ال)?ضريبة)/iu' => 3,
        '/\b(?:VAT|ضريبة\s*(?:القيمة\s*المضافة|المضافة))\b/iu' => 2,
        '/\b(?:unit\s*price|سعر\s*(?:ال)?وحدة)/iu' => 3,
        '/\b(?:qty|quantity|الكمية)\b/iu' => 2,
        '/\b(?:bill\s*to|فاتورة\s*إلى|العميل)\b/iu' => 2,
        '/\b(?:due\s*date|تاريخ\s*(?:ال)?استحقاق)/iu' => 2,
        '/\b(?:payment\s*(?:method|terms|due)|طريقة\s*(?:ال)?دفع|شروط\s*(?:ال)?دفع)/iu' => 2,
        '/\b(?:purchase\s*order|أمر\s*(?:ال)?شراء)/iu' => 3,

        // Government / ID document signals (not a CV)
        '/\b(?:national\s*(?:ID|identity)|الهوية\s*الوطنية|رقم\s*(?:ال)?هوية)\b/iu' => 2,
        '/\b(?:passport\s*(?:number|no\.?)|رقم\s*(?:ال)?جواز)\b/iu' => 2,
        '/\b(?:iqama|إقامة|رقم\s*(?:ال)?إقامة)\b/iu' => 2,

        // Contract / legal signals
        '/\b(?:terms\s*(?:and|&)\s*conditions|الشروط\s*والأحكام)/iu' => 3,
        '/\b(?:party\s*(?:of|to)|الطرف\s*(?:الأول|الثاني))/iu' => 2,
        '/\b(?:whereas|حيث\s*أن)\b/iu' => 2,
        '/\b(?:hereinafter|المشار\s*إليه)/iu' => 2,
    ];

    /**
     * Positive resume indicator patterns with weights.
     *
     * @var array<string, int>
     */
    private const RESUME_PATTERNS = [
        // Section headings
        '/\b(?:experience|work\s*history|employment|career)\b/iu' => 3,
        '/(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:خبر(?:ه|ات)|تاريخ\s*مهني|مسيره\s*مهنيه)\b/iu' => 3,
        '/\b(?:education|university|college|degrees?|bachelors?|masters?)\b/iu' => 2,
        '/(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:تعليم|م[ؤو]هل(?:ات)?|جامع(?:ه|ات))\b/iu' => 2,
        '/\b(?:skills?|competenc\w*|expertise)\b/iu' => 2,
        '/(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:مهار(?:ه|ات)|كفاء(?:ه|ات))\b/iu' => 2,
        '/\b(?:summary|profile|objective|about\s*me|professional\s*summary)\b/iu' => 2,
        '/(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:ملخص(?:\s+مهني)?|نبذه(?:\s+(?:مهنيه|عني))?|هدف(?:\s+مهني)?)\b/iu' => 2,
        '/\b(?:certif\w*|license\w*|accreditat\w*)\b/iu' => 2,
        '/(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:شهادات|دورات)\b/iu' => 2,

        // Contact info combo (email + phone together is very resume-like)
        '/[\w.+-]+@[\w-]+\.\w+/u' => 2,
        '/(\+?\d[\d\s\-().]{7,}\d)/u' => 1,
        '/linkedin\.com\/in\//iu' => 3,

        // Action verbs (resume language)
        '/\b(?:led|managed|developed|built|created|increased|reduced|improved|launched|implemented)\b/iu' => 1,
        '/(?:^|[\s،:\-])(?:و|ف)?(?:قدت|أدرت|طورت|أنشأت|رفعت|خفضت|حسنت|أطلقت|نفذت|حللت)\b/iu' => 1,

        // Date ranges (employment periods)
        '/\b(?:19|20)\d{2}\s*[-–—]\s*(?:(?:19|20)\d{2}|present|حتى\s*الآن|الآن)\b/iu' => 3,
    ];

    /**
     * Minimum non-resume score to trigger rejection when resume signals are weak.
     *
     * The threshold is set to require multiple converging non-resume signals
     * (e.g. IBAN + account number + transfer = 4+3+4 = 11) to avoid false
     * positives on CVs that mention banking experience.
     */
    private const NOT_RESUME_THRESHOLD = 8;

    /**
     * Minimum resume score below which a document with moderate non-resume
     * signals is marked uncertain rather than passing clean.
     */
    private const WEAK_RESUME_THRESHOLD = 4;

    /**
     * Classify extracted text as resume, not-resume, or uncertain.
     */
    public function classify(string $text): ResumeClassification
    {
        if (mb_strlen(trim($text)) < 80) {
            // Too short to classify meaningfully — let CvTextExtractor handle this.
            return ResumeClassification::Uncertain;
        }

        $resumeScore = $this->computeSignalScore($text, self::RESUME_PATTERNS);
        $nonResumeScore = $this->computeSignalScore($text, self::NON_RESUME_PATTERNS);

        // Strong non-resume signals with weak resume signals → reject
        if ($nonResumeScore >= self::NOT_RESUME_THRESHOLD && $resumeScore < $nonResumeScore) {
            return ResumeClassification::NotResume;
        }

        // Moderate non-resume signals with very weak resume signals → uncertain
        if ($nonResumeScore >= self::WEAK_RESUME_THRESHOLD && $resumeScore <= self::WEAK_RESUME_THRESHOLD) {
            return ResumeClassification::Uncertain;
        }

        return ResumeClassification::Resume;
    }

    /**
     * @param  array<string, int>  $patterns
     */
    private function computeSignalScore(string $text, array $patterns): int
    {
        $score = 0;
        foreach ($patterns as $pattern => $weight) {
            if (preg_match($pattern, $text)) {
                $score += $weight;
            }
        }

        return $score;
    }
}
