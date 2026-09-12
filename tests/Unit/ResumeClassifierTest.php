<?php

namespace Tests\Unit;

use App\Enums\ResumeClassification;
use App\Services\ResumeClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResumeClassifierTest extends TestCase
{
    private ResumeClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new ResumeClassifier;
    }

    // ── Bank receipt / financial documents → NotResume ──────────────────────

    public function test_bank_transfer_receipt_is_rejected(): void
    {
        $text = <<<'DOC'
        إيصال تحويل بنكي
        بنك الراجحي
        رقم الحساب: SA4420000001234567891234
        IBAN: SA4420000001234567891234
        اسم المستفيد: محمد عبدالله
        المبلغ: 5,000.00 ريال سعودي
        تاريخ التحويل: 2026-09-01
        رقم العملية: TRN-2026-09-01-00123
        الرصيد الحالي: 12,450.75 ريال
        DOC;

        $this->assertSame(
            ResumeClassification::NotResume,
            $this->classifier->classify($text),
            'A bank transfer receipt with IBAN, account number, and beneficiary must be classified as NotResume',
        );
    }

    public function test_english_bank_statement_is_rejected(): void
    {
        $text = <<<'DOC'
        Bank Statement - Al Rajhi Bank
        Account Number: 1234567891234
        IBAN: SA4420000001234567891234
        Statement Period: 01 Sep 2026 - 30 Sep 2026

        Date        Description          Debit     Credit    Balance
        01-Sep      Wire Transfer        5,000.00            12,450.75
        05-Sep      ATM Withdrawal       500.00              11,950.75
        10-Sep      Salary Credit                  15,000.00 26,950.75

        Current Balance: 26,950.75 SAR
        DOC;

        $this->assertSame(
            ResumeClassification::NotResume,
            $this->classifier->classify($text),
        );
    }

    // ── Invoice → NotResume ────────────────────────────────────────────────

    public function test_arabic_invoice_is_rejected(): void
    {
        $text = <<<'DOC'
        فاتورة ضريبية
        رقم الفاتورة: INV-2026-0042
        التاريخ: 2026-09-10
        العميل: شركة الأمل للتقنية
        الرقم الضريبي: 310123456700003

        البند                  الكمية   سعر الوحدة   المجموع
        خدمات تصميم             1        3,500.00     3,500.00
        استضافة سنوية           1        1,200.00     1,200.00

        المجموع الفرعي: 4,700.00 ريال
        ضريبة القيمة المضافة 15%: 705.00 ريال
        الإجمالي: 5,405.00 ريال

        طريقة الدفع: تحويل بنكي
        DOC;

        $this->assertSame(
            ResumeClassification::NotResume,
            $this->classifier->classify($text),
        );
    }

    public function test_english_invoice_is_rejected(): void
    {
        $text = <<<'DOC'
        TAX INVOICE
        Invoice Number: INV-2026-0099
        Invoice Date: September 10, 2026
        Due Date: October 10, 2026
        Bill To: Acme Corp

        Item              Qty   Unit Price   Total
        Web Development   1     $5,000.00    $5,000.00
        Hosting (Annual)  1     $1,200.00    $1,200.00

        Subtotal: $6,200.00
        VAT (15%): $930.00
        Grand Total: $7,130.00

        Payment Method: Wire Transfer
        Payment Terms: Net 30
        DOC;

        $this->assertSame(
            ResumeClassification::NotResume,
            $this->classifier->classify($text),
        );
    }

    // ── Legal / contract → NotResume ───────────────────────────────────────

    public function test_contract_document_is_rejected(): void
    {
        $text = <<<'DOC'
        عقد اتفاقية خدمات
        الطرف الأول: شركة التقنية المتقدمة
        الطرف الثاني: مؤسسة الابتكار
        حيث أن الطرف الأول يرغب في تقديم خدمات تطوير البرمجيات
        والطرف الثاني يوافق على الشروط والأحكام الواردة أدناه
        يتم الدفع على أقساط شهرية بمبلغ 10,000 ريال
        مدة العقد: 12 شهراً من تاريخ التوقيع
        الشروط والأحكام:
        1. يلتزم الطرف الأول بتسليم المشروع في الموعد المحدد
        2. يلتزم الطرف الثاني بسداد المستحقات في مواعيدها
        DOC;

        $this->assertNotSame(
            ResumeClassification::Resume,
            $this->classifier->classify($text),
            'A legal contract must not be classified as a valid resume',
        );
    }

    // ── Valid resumes → Resume ─────────────────────────────────────────────

    public function test_full_english_resume_is_accepted(): void
    {
        $text = <<<'CV'
        Salem Sayer
        Laravel Backend Developer
        salem@example.com | +966 59 189 0300 | linkedin.com/in/salem

        Summary
        Backend Developer with 5+ years of experience building Laravel API platforms, SQL dashboards, and backend integrations for business teams.

        Skills
        PHP, Laravel, API, SQL, Git, Agile, Scrum, Backend, JavaScript, Reporting

        Experience
        Backend Developer, Sirati Labs, 2021 - 2025
        - Developed Laravel APIs used by 25 internal users across operations and support.
        - Improved reporting speed by 35% by optimizing SQL queries and dashboard endpoints.
        - Built API integrations that reduced manual data entry by 20%.
        - Managed release planning with agile workflows and Git version control.

        Education
        Bachelor of Computer Science, 2020

        Certifications
        AWS Certified Cloud Practitioner
        CV;

        $this->assertSame(
            ResumeClassification::Resume,
            $this->classifier->classify($text),
        );
    }

    public function test_full_arabic_resume_is_accepted(): void
    {
        $text = <<<'CV'
        أحمد محمد العتيبي
        مطور برمجيات
        ahmed@example.com | +966 55 123 4567 | linkedin.com/in/ahmed-otaibi

        الملخص المهني
        مطور برمجيات بخبرة 4 سنوات في تطوير تطبيقات الويب باستخدام Laravel و Flutter.

        المهارات
        PHP، Laravel، Flutter، SQL، Git، API، JavaScript

        الخبرات المهنية
        مطور برمجيات، شركة التقنية، 2021 - 2025
        - طورت واجهات برمجية RESTful لتطبيق موبايل يخدم 10,000 مستخدم
        - حسنت أداء قاعدة البيانات بنسبة 40%
        - نفذت نظام إشعارات فورية باستخدام Firebase

        التعليم
        بكالوريوس علوم حاسب، جامعة الملك سعود، 2020

        الشهادات
        AWS Certified Cloud Practitioner
        CV;

        $this->assertSame(
            ResumeClassification::Resume,
            $this->classifier->classify($text),
        );
    }

    public function test_minimal_resume_with_key_sections_is_accepted(): void
    {
        $text = <<<'CV'
        Fatimah Al-Harbi
        fatimah@example.com

        Experience
        Marketing Manager, Digital Agency, 2020 - 2024
        - Led social media campaigns reaching 500,000 users.
        - Managed a team of 5 content creators.

        Education
        Bachelor of Business Administration
        CV;

        $this->assertSame(
            ResumeClassification::Resume,
            $this->classifier->classify($text),
        );
    }

    // ── Finance professional resume must NOT be falsely rejected ───────────

    public function test_finance_professional_resume_is_not_rejected(): void
    {
        $text = <<<'CV'
        Khalid Al-Dossari
        Senior Financial Analyst
        khalid@example.com | +966 50 999 8877 | linkedin.com/in/khalid

        Summary
        Certified financial analyst with 7+ years experience in budgeting, cash flow
        analysis, and P&L reporting. Proven track record of reducing operational costs
        and improving financial compliance across multi-entity organizations.

        Experience
        Senior Financial Analyst, Aramco, 2019 - 2025
        - Managed annual budgets exceeding 50M SAR across 3 business units.
        - Led quarterly financial audits reducing discrepancies by 30%.
        - Developed automated financial dashboards using Excel and Power BI.
        - Improved cash flow forecasting accuracy by 25%.

        Financial Analyst, SABIC, 2017 - 2019
        - Prepared monthly P&L statements and variance analysis.
        - Coordinated with external audit teams for annual compliance reviews.

        Education
        Bachelor of Finance, King Fahd University, 2016

        Certifications
        CFA Level II, CMA, CPA
        CV;

        $result = $this->classifier->classify($text);
        $this->assertNotSame(
            ResumeClassification::NotResume,
            $result,
            'A finance professional resume mentioning financial terms must not be rejected as not-a-resume',
        );
    }

    // ── Edge: very short text → Uncertain ─────────────────────────────────

    public function test_very_short_text_is_uncertain(): void
    {
        $this->assertSame(
            ResumeClassification::Uncertain,
            $this->classifier->classify('Hello world, this is a test document.'),
        );
    }

    // ── Mixed content with strong non-resume signals ──────────────────────

    public function test_purchase_order_is_rejected(): void
    {
        $text = <<<'DOC'
        أمر شراء رقم PO-2026-0015
        التاريخ: 2026-09-12
        المورد: شركة الأجهزة المتقدمة
        العميل: مؤسسة البناء الحديث

        البند          الكمية    سعر الوحدة    المجموع
        لابتوب Dell     10       4,500.00      45,000.00
        شاشة Samsung    10       1,200.00      12,000.00

        المجموع الفرعي: 57,000.00
        ضريبة القيمة المضافة 15%: 8,550.00
        الإجمالي: 65,550.00 ريال

        طريقة الدفع: تحويل بنكي
        تاريخ الاستحقاق: 2026-10-12
        DOC;

        $this->assertSame(
            ResumeClassification::NotResume,
            $this->classifier->classify($text),
        );
    }

    // ── Data provider for varied bank receipts ────────────────────────────

    #[DataProvider('bankReceiptProvider')]
    public function test_various_bank_receipts_are_rejected(string $text): void
    {
        $this->assertSame(
            ResumeClassification::NotResume,
            $this->classifier->classify($text),
        );
    }

    public static function bankReceiptProvider(): iterable
    {
        yield 'STC Pay transfer' => [<<<'DOC'
        STC Pay Transfer Receipt
        Transaction ID: STC-2026-09-12-88721
        From Account: +966 55 000 1234
        To: IBAN SA44 2000 0001 2345 6789 1234
        Beneficiary: Mohammed Ali
        Amount: 2,500.00 SAR
        Date: 12 Sep 2026
        Current Balance: 8,200.00 SAR
        DOC];

        yield 'Al Ahli bank statement (Arabic)' => [<<<'DOC'
        كشف حساب - البنك الأهلي السعودي
        رقم الحساب: 6800012345678
        IBAN: SA8480000068000123456789
        الفترة: 01/09/2026 - 30/09/2026
        الرصيد الافتتاحي: 15,000.00 ريال
        إجمالي المدين: 3,200.00
        إجمالي الدائن: 18,000.00
        الرصيد الحالي: 29,800.00 ريال
        DOC];
    }
}
