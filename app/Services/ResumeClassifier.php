<?php

namespace App\Services;

use App\Contracts\CvAiProvider;
use App\Enums\ResumeClassification;

/**
 * Classifies extracted document text as resume vs non-resume content.
 *
 * Uses a two-tier verification architecture:
 * 1. Fast signal-based heuristics evaluating positive (resume structure, sections,
 *    dated experience, contact details) and negative (banking, transfer slips, invoices,
 *    purchase orders, legal terms, attendance certificates) indicators.
 * 2. An AI-assisted audit gate (CvAiProvider::classifyDocument) for borderline or
 *    uncertain documents to ensure non-resume files are never mistakenly scored as CVs.
 */
class ResumeClassifier
{
    /**
     * Non-resume indicator patterns.
     *
     * @var array<string, int>
     */
    private const NON_RESUME_PATTERNS = [
        // Banking / financial transfer signals
        '/\bIBAN\b/iu' => 4,
        '/\b(?:account\s*(?:number|no\.?|#)|رقم\s*(?:ال)?حساب)/iu' => 3,
        '/\b(?:bank\s*(?:transfer|statement|receipt)|(?:إيصال|حوالة|كشف)\s*(?:ال)?(?:بنك|بنكي|مصرف))/iu' => 4,
        '/\b(?:تحويل\s*(?:بنكي|مصرفي|مالي))/iu' => 4,
        '/\b(?:عملية\s*(?:تحويل|دفع|سداد)|إشعار\s*(?:تحويل|سداد|إيداع)|إيصال\s*(?:إيداع|سداد|دفع|استلام)|حوالة\s*مالية)/iu' => 4,
        '/\b(?:transaction\s*(?:id|number|#|reference)|رقم\s*(?:ال)?عملية|رقم\s*(?:ال)?مرجع|الرقم\s*(?:ال)?مرجعي|مرجع\s*(?:ال)?عملية)/iu' => 4,
        '/\b(?:transfer\s*(?:date|amount|type)|تاريخ\s*(?:ال)?تحويل|مبلغ\s*(?:ال)?تحويل|قيمة\s*(?:ال)?حوالة)/iu' => 4,
        '/\b(?:beneficiary|المستفيد|اسم\s*المستفيد)/iu' => 3,
        '/\b(?:swift|bic)\s*(?:code)?/iu' => 3,
        '/\b(?:routing\s*number|رقم\s*(?:ال)?توجيه)/iu' => 3,
        '/\b(?:wire\s*transfer|حوالة\s*دولية)/iu' => 3,
        '/\b(?:debit|credit|مدين|دائن)\b/iu' => 2,
        '/\b(?:current\s*balance|الرصيد\s*الحالي|رصيد\s*(?:ال)?حساب)/iu' => 3,

        // Specific Saudi banks, wallets, and payment gateways
        '/\b(?:Al\s*Rajhi|الراجحي|Alinma|الإنماء|Albilad|البلاد|Aljazira|الجزيرة|SNB|الأهلي\s*(?:السعودي|التجاري)?|Riyad\s*Bank|بنك\s*الرياض|ANB|العربي\s*الوطني|BSF|البنك\s*الفرنسي|SABB|ساب|الأول|meem|ميم|D360|Urpay|Tiqmo|STC\s*Pay|stcpay|SADAD|سداد)\b/iu' => 3,

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

        // Certificate of completion / attendance (when solitary without CV structure)
        '/\b(?:شهادة\s*(?:إتمام|حضور|إنجاز|شكر\s*وتقدير)|نشهد\s*بأن|اجتاز\s*بنجاح|certificate\s*of\s*(?:completion|attendance|appreciation)|this\s*is\s*to\s*certify\s*that)\b/iu' => 3,

        // Government / ID document signals (not a CV)
        '/\b(?:national\s*(?:ID|identity)|الهوية\s*الوطنية|رقم\s*(?:ال)?هوية)\b/iu' => 2,
        '/\b(?:passport\s*(?:number|no\.?)|رقم\s*(?:ال)?جواز)\b/iu' => 2,
        '/\b(?:iqama|إقامة|رقم\s*(?:ال)?إقامة)\b/iu' => 2,
        '/\b(?:تقرير\s*طبي|وصفة\s*طبية|رخصة\s*(?:قيادة|سياقة)|استمارة\s*سيارة|عقد\s*(?:إيجار|بيع)|فاتورة\s*(?:كهرباء|مياه|اتصالات))/iu' => 3,

        // Contract / legal signals
        '/\b(?:terms\s*(?:and|&)\s*conditions|الشروط\s*والأحكام)/iu' => 3,
        '/\b(?:party\s*(?:of|to)|الطرف\s*(?:الأول|الثاني))/iu' => 2,
        '/\b(?:whereas|حيث\s*أن)\b/iu' => 2,
        '/\b(?:hereinafter|المشار\s*إليه)/iu' => 2,
    ];

    /**
     * Core section headings that every valid resume typically contains.
     *
     * @var list<string>
     */
    private const CORE_RESUME_SECTIONS = [
        '/\b(?:experience|work\s*history|employment|career)\b/iu',
        '/(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:خبر(?:ه|ات)|تاريخ\s*مهني|مسيره\s*مهنيه)\b/iu',
        '/\b(?:education|university|college|degrees?|bachelors?|masters?)\b/iu',
        '/(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:تعليم|م[ؤو]هل(?:ات)?|جامع(?:ه|ات))\b/iu',
        '/\b(?:skills?|competenc\w*|expertise)\b/iu',
        '/(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?(?:مهار(?:ه|ات)|كفاء(?:ه|ات))\b/iu',
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
     * Classify extracted text as resume, not-resume, or uncertain.
     */
    public function classify(string $text, ?CvAiProvider $aiProvider = null): ResumeClassification
    {
        if (mb_strlen(trim($text)) < 80) {
            return ResumeClassification::Uncertain;
        }

        $resumeScore = $this->computeSignalScore($text, self::RESUME_PATTERNS);
        $nonResumeScore = $this->computeSignalScore($text, self::NON_RESUME_PATTERNS);
        $hasCoreSection = $this->hasAnyMatch($text, self::CORE_RESUME_SECTIONS);

        // 1. Definite Non-Resume (fast rule match)
        // High non-resume signals:
        if ($nonResumeScore >= 6 && $resumeScore < $nonResumeScore) {
            return ResumeClassification::NotResume;
        }

        // Non-resume signals present without any core CV sections:
        if ($nonResumeScore >= 3 && ! $hasCoreSection && $resumeScore <= 3) {
            return ResumeClassification::NotResume;
        }

        // 2. Definite Resume (fast rule match)
        // Strong resume signals, core section present, zero non-resume signals:
        if ($resumeScore >= 6 && $nonResumeScore === 0 && $hasCoreSection) {
            return ResumeClassification::Resume;
        }

        // 3. Borderline / Ambiguous — Consult AI if configured
        if ($aiProvider !== null && $aiProvider->isConfigured()) {
            try {
                $aiResult = $aiProvider->classifyDocument($text);
                if (isset($aiResult['is_resume'])) {
                    return $aiResult['is_resume']
                        ? ResumeClassification::Resume
                        : ResumeClassification::NotResume;
                }
            } catch (\Throwable) {
                // AI audit call failed or timed out — fall through to fail-closed heuristic.
            }
        }

        // 4. Strict heuristic fallbacks (when AI is not configured or failed)
        // A genuine CV must have at least one core section or dated experience.
        if (! $hasCoreSection && $resumeScore < 4) {
            return ResumeClassification::NotResume;
        }

        if ($nonResumeScore >= 3) {
            return ResumeClassification::NotResume;
        }

        if ($resumeScore >= 5) {
            return ResumeClassification::Resume;
        }

        return ResumeClassification::Uncertain;
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

    /**
     * @param  list<string>  $patterns
     */
    private function hasAnyMatch(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }
}
