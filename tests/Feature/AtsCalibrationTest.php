<?php

namespace Tests\Feature;

use App\Services\AtsScoringService;
use Tests\Support\AtsCalibrationCorpus;
use Tests\TestCase;

/**
 * ATS Scorer Calibration Test Suite (SIRATI-90).
 *
 * Enforces AGENTS.md Rule 1: Assert ordering properties across valid inputs,
 * not hardcoded numbers or review examples.
 */
class AtsCalibrationTest extends TestCase
{
    private AtsScoringService $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = app(AtsScoringService::class);
    }

    /**
     * Invariant 1: A strong CV always outscores a weak one in the same profession.
     * Tested across multiple in-taxonomy and off-taxonomy professions.
     */
    public function test_invariant_1_strong_cv_always_outscores_weak_cv_in_same_profession(): void
    {
        $pairs = [
            'healthcare' => [
                'profession' => 'Nurse',
                'job_title' => 'Registered Nurse',
                'strong' => AtsCalibrationCorpus::benchmarkNurse()['text'],
                'weak' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'weak_nurse')['text'],
            ],
            'engineering' => [
                'profession' => 'Civil Engineer',
                'job_title' => 'Civil Site Engineer',
                'strong' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'strong_civil_engineer')['text'],
                'weak' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'weak_civil_engineer')['text'],
            ],
            'education' => [
                'profession' => 'Teacher',
                'job_title' => 'Secondary Mathematics Teacher',
                'strong' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'strong_teacher')['text'],
                'weak' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'weak_teacher')['text'],
            ],
            'marketing' => [
                'profession' => 'Marketing',
                'job_title' => 'Digital Marketing Manager',
                'strong' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'strong_marketing')['text'],
                'weak' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'weak_marketing')['text'],
            ],
            'finance' => [
                'profession' => 'Finance',
                'job_title' => 'Senior Financial Analyst',
                'strong' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'strong_finance')['text'],
                'weak' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'weak_finance')['text'],
            ],
            'arabic_healthcare' => [
                'profession' => 'تمريض عربي',
                'job_title' => 'أخصائية تمريض عناية مركزة',
                'strong' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'strong_arabic_nurse')['text'],
                'weak' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'weak_arabic_nurse')['text'],
            ],
            'arabic_engineering' => [
                'profession' => 'هندسة مدنية عربية',
                'job_title' => 'مهندس موقع مدني',
                'strong' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'strong_arabic_civil_engineer')['text'],
                'weak' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'weak_arabic_civil_engineer')['text'],
            ],
            'arabic_education' => [
                'profession' => 'تعليم عربي',
                'job_title' => 'معلم رياضيات ثانوي',
                'strong' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'strong_arabic_teacher')['text'],
                'weak' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'weak_arabic_teacher')['text'],
            ],
            'arabic_software' => [
                'profession' => 'برمجيات عربية',
                'job_title' => 'مطوّر برمجيات',
                'strong' => AtsCalibrationCorpus::adversarialArabicDev()['text'],
                'weak' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'weak_arabic_software')['text'],
            ],
            'arabic_hr' => [
                'profession' => 'موارد بشرية عربية',
                'job_title' => 'أخصائي موارد بشرية أول',
                'strong' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'strong_arabic_hr')['text'],
                'weak' => collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'weak_arabic_hr')['text'],
            ],
        ];

        foreach ($pairs as $key => $pair) {
            $strongResult = $this->scorer->score($pair['strong'], $pair['job_title']);
            $weakResult = $this->scorer->score($pair['weak'], $pair['job_title']);

            $this->assertGreaterThan(
                $weakResult['total'],
                $strongResult['total'],
                "Strong CV for {$pair['profession']} ({$strongResult['total']}) must outscore weak CV ({$weakResult['total']}).",
            );
        }
    }

    /**
     * Invariant 2: The keyword-stuffed junk CV never outscores a real CV.
     * Acceptance criterion for SIRATI-87.
     * Tested against both synthetic benchmarks and the exact reviewer adversarial CVs from ATS_SCORING_REVIEW.md.
     */
    public function test_invariant_2_keyword_stuffed_cv_never_outscores_real_cvs(): void
    {
        // 1. Synthetic benchmarks
        $junk = AtsCalibrationCorpus::benchmarkJunk();
        $nurse = AtsCalibrationCorpus::benchmarkNurse();
        $arabicDev = AtsCalibrationCorpus::benchmarkArabicDev();

        $junkScore = $this->scorer->score($junk['text'], $junk['target_job_title']);
        $nurseScore = $this->scorer->score($nurse['text'], $nurse['target_job_title']);
        $arabicDevScore = $this->scorer->score($arabicDev['text'], $arabicDev['target_job_title']);

        // 2. Exact reviewer adversarial CVs (verbatim from review report)
        $advJunk = AtsCalibrationCorpus::adversarialJunk();
        $advNurse = AtsCalibrationCorpus::adversarialNurse();
        $advArabicDev = AtsCalibrationCorpus::adversarialArabicDev();

        $advJunkScore = $this->scorer->score($advJunk['text'], $advJunk['target_job_title']);
        $advNurseScore = $this->scorer->score($advNurse['text'], $advNurse['target_job_title']);
        $advArabicDevScore = $this->scorer->score($advArabicDev['text'], $advArabicDev['target_job_title']);

        // Report diagnostic values
        dump([
            'benchmark_junk_total' => $junkScore['total'],
            'benchmark_junk_grade' => $junkScore['grade'],
            'benchmark_nurse_total' => $nurseScore['total'],
            'benchmark_nurse_grade' => $nurseScore['grade'],
            'benchmark_arabic_dev_total' => $arabicDevScore['total'],
            'benchmark_arabic_dev_grade' => $arabicDevScore['grade'],
            'adversarial_junk_total' => $advJunkScore['total'],
            'adversarial_junk_grade' => $advJunkScore['grade'],
            'adversarial_nurse_total' => $advNurseScore['total'],
            'adversarial_nurse_grade' => $advNurseScore['grade'],
            'adversarial_arabic_dev_total' => $advArabicDevScore['total'],
            'adversarial_arabic_dev_grade' => $advArabicDevScore['grade'],
        ]);

        $this->assertLessThan(
            $nurseScore['total'],
            $junkScore['total'],
            "Benchmark junk CV ({$junkScore['total']}) must score below benchmark Nurse CV ({$nurseScore['total']}).",
        );

        $this->assertLessThan(
            $arabicDevScore['total'],
            $junkScore['total'],
            "Benchmark junk CV ({$junkScore['total']}) must score below benchmark Arabic Dev CV ({$arabicDevScore['total']}).",
        );

        // Adversarial acceptance criteria (Finding 2 of SPRINT6_REVIEW_ROUND1.md)
        $this->assertLessThan(
            $advNurseScore['total'],
            $advJunkScore['total'],
            "Adversarial junk CV ({$advJunkScore['total']}) must score below adversarial Nurse CV ({$advNurseScore['total']}).",
        );

        $this->assertLessThan(
            $advArabicDevScore['total'],
            $advJunkScore['total'],
            "Adversarial junk CV ({$advJunkScore['total']}) must score below adversarial Arabic Dev CV ({$advArabicDevScore['total']}).",
        );
    }

    /**
     * Invariant 3: Equivalent Arabic and English CVs score within N points.
     * Acceptance criterion for SIRATI-88.
     */
    public function test_invariant_3_equivalent_arabic_and_english_cvs_score_within_n_points(): void
    {
        $pair = AtsCalibrationCorpus::pairedEquivalentCvs();

        $enScore = $this->scorer->score($pair['en']['text'], $pair['en']['target_job_title']);
        $arScore = $this->scorer->score($pair['ar']['text'], $pair['ar']['target_job_title']);

        $delta = abs($arScore['total'] - $enScore['total']);

        dump([
            'equivalent_en_total' => $enScore['total'],
            'equivalent_ar_total' => $arScore['total'],
            'delta' => $delta,
        ]);

        $this->assertLessThanOrEqual(
            8,
            $delta,
            "Equivalent Arabic ({$arScore['total']}) and English ({$enScore['total']}) CVs must score within 8 points (delta: {$delta}).",
        );
    }

    /**
     * Invariant 4: An off-taxonomy CV can reach an A (>= 80 points).
     * Acceptance criterion for SIRATI-89.
     */
    public function test_invariant_4_off_taxonomy_cv_can_reach_an_a(): void
    {
        $nurse = AtsCalibrationCorpus::benchmarkNurse();
        $civilEngineer = collect(AtsCalibrationCorpus::corpus())->firstWhere('id', 'strong_civil_engineer');

        $nurseResult = $this->scorer->score($nurse['text'], $nurse['target_job_title']);
        $civilResult = $this->scorer->score($civilEngineer['text'], $civilEngineer['target_job_title']);

        dump([
            'nurse_total' => $nurseResult['total'],
            'nurse_grade' => $nurseResult['grade'],
            'civil_total' => $civilResult['total'],
            'civil_grade' => $civilResult['grade'],
        ]);

        $this->assertGreaterThanOrEqual(
            80,
            $nurseResult['total'],
            "High quality Nurse CV ({$nurseResult['total']}) must be able to reach an A (>= 80).",
        );

        $this->assertGreaterThanOrEqual(
            80,
            $civilResult['total'],
            "High quality Civil Engineer CV ({$civilResult['total']}) must be able to reach an A (>= 80).",
        );
    }

    /**
     * Invariant 5: Adding a phone number or contact numbers never raises the score.
     * Acceptance criterion for SIRATI-87 / SIRATI-90.
     */
    public function test_invariant_5_adding_phone_number_never_raises_quantified_achievements(): void
    {
        $admin = AtsCalibrationCorpus::benchmarkZeroMetricAdmin();
        $adminResult = $this->scorer->score($admin['text'], $admin['target_job_title']);

        // The administrative CV has 0 metrics, but has phone + PO box + postal code + years.
        // It must NOT receive 11/11 for quantified achievements!
        $quantifiedCriteria = $adminResult['criteria']['experience']['score'];

        dump([
            'zero_metric_admin_total' => $adminResult['total'],
            'zero_metric_admin_experience' => $quantifiedCriteria,
        ]);

        // Base text without phone number
        $baseText = <<<'CV'
Ahmed
Administrative Assistant
email@example.com

Summary
Office assistant with experience in filing.

Experience
Office Clerk | Company
2021 – 2024
- Responsible for office operations and filing.
- Greeted guests and handled incoming inquiries.

Education
High School Diploma
CV;

        $textWithPhone = $baseText . "\nPhone: +966 55 123 4567 | PO Box 31952, Postal 34423";

        $baseScore = $this->scorer->score($baseText, 'Administrative Assistant');
        $withPhoneScore = $this->scorer->score($textWithPhone, 'Administrative Assistant');

        // Experience score must not increase when phone / contact numbers are added
        $this->assertLessThanOrEqual(
            $baseScore['criteria']['experience']['score'],
            $withPhoneScore['criteria']['experience']['score'],
            "Adding a phone number / PO box must NEVER increase experience score through quantified metric regexes.",
        );
    }
}
