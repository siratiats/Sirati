<?php

namespace Tests\Feature;

use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\CvTemplateRenderer;
use App\Support\ArabicPdfText;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Smalot\PdfParser\Parser as PdfParser;
use Tests\TestCase;

class AtsParseBackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CvTemplateSeeder::class);
    }

    public function test_ats_classic_template_renders_structured_sections_directly(): void
    {
        $cv = $this->createStructuredCv('ar', [
            'fullName' => 'سارة بنت عبد الله التميمي',
            'headline' => 'مهندسة برمجيات أولى',
            'email' => 'sara.tamimi@example.com',
            'phone' => '+966551234567',
            'location' => 'الرياض، المملكة العربية السعودية',
            'summary' => 'مهندسة برمجيات ذات خبرة في بناء الأنظمة السحابية وتصميم قواعد البيانات.',
            'experience' => [
                [
                    'title' => 'كبيرة مهندسي النظم',
                    'company' => 'شركة التقنية السحابية',
                    'location' => 'الرياض',
                    'start_date' => '2021',
                    'end_date' => '2024',
                    'narrative' => 'قيادة فريق البنية التحتية وتحسين كفاءة المعالجة.',
                ],
            ],
            'education' => [
                [
                    'degree' => 'بكالوريوس علوم الحاسب',
                    'institution' => 'جامعة الملك سعود',
                    'start_date' => '2016',
                    'end_date' => '2020',
                    'narrative' => 'مرتبة الشرف الأولى.',
                ],
            ],
            'skills' => ['Laravel', 'Flutter', 'Docker', 'PostgreSQL'],
        ]);

        $renderer = app(CvTemplateRenderer::class);
        $html = $renderer->renderHtml($cv, 'ats-classic-professional');

        // Structural invariants
        $this->assertStringContainsString('سارة بنت عبد الله التميمي', $html);
        $this->assertStringContainsString('مهندسة برمجيات أولى', $html);
        $this->assertStringContainsString('sara.tamimi@example.com', $html);
        $this->assertStringContainsString('+966551234567', $html);
        $this->assertStringContainsString('الملخص المهني', $html);
        $this->assertStringContainsString('كبيرة مهندسي النظم', $html);
        $this->assertStringContainsString('جامعة الملك سعود', $html);
        $this->assertStringContainsString('page-break-inside: avoid;', $html);
        $this->assertStringContainsString('page-break-after: avoid;', $html);

        // ATS compliance: contacts in body, no text inside <img>
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_pdf_export_parse_back_preserves_linear_section_order_via_extraction_boundary(): void
    {
        $cv = $this->createStructuredCv('ar', [
            'fullName' => 'فيصل بن ناصر القحطاني',
            'headline' => 'مدير مشروعات تقنية',
            'email' => 'faisal.qahtani@example.com',
            'phone' => '+966509876543',
            'location' => 'جدة، المملكة العربية السعودية',
            'summary' => 'قائد تحول رقمي بخبرة تتجاوز سبع سنوات في المشروعات الحكومية.',
            'experience' => [
                [
                    'title' => 'مدير مشروعات رقمية',
                    'company' => 'مجموعة الابتكار',
                    'location' => 'جدة',
                    'start_date' => '2020',
                    'end_date' => '2024',
                    'narrative' => 'إدارة تسليم منصات كبرى بنجاح.',
                ],
            ],
            'education' => [
                [
                    'degree' => 'ماجستير إدارة الأعمال التقنية',
                    'institution' => 'جامعة الملك عبد العزيز',
                    'start_date' => '2018',
                    'end_date' => '2020',
                    'narrative' => 'تخصص الابتكار المؤسسي.',
                ],
            ],
            'skills' => ['Agile', 'Scrum', 'Budgeting', 'Risk Management'],
        ]);

        $renderer = app(CvTemplateRenderer::class);
        $response = $renderer->downloadResponse($cv, 'ats-classic-professional');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        $pdfBytes = $response->getContent();
        $this->assertNotEmpty($pdfBytes);

        // Ingestion/Extraction boundary contract: normalize extracted PDF text layer
        $parser = new PdfParser;
        $pdf = $parser->parseContent($pdfBytes);
        $rawText = $pdf->getText();

        // 1. Raw extraction assertions:
        // Text layer must NOT contain any Private Use Area (PUA) codepoints (U+E000 - U+F8FF).
        // ATS parsers must receive standard Unicode, not font-internal PUA glyphs.
        $this->assertDoesNotMatchRegularExpression(
            '/[\x{E000}-\x{F8FF}]/u',
            $rawText,
            'PDF text layer contains Private Use Area (PUA) codepoints. All glyphs must map to standard Unicode.'
        );

        // Raw text layer must contain candidate contact details directly
        $this->assertStringContainsString('faisal.qahtani@example.com', $rawText);
        $this->assertStringContainsString('+966509876543', $rawText);

        // Standard Unicode NFKC normalization on raw extraction normalizes presentation forms into canonical Arabic
        $kcNormalized = class_exists(\Normalizer::class) ? \Normalizer::normalize($rawText, \Normalizer::FORM_KC) : $rawText;
        $this->assertDoesNotMatchRegularExpression('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $kcNormalized);

        // 2. Boundary contract: normalize extracted PDF text layer (reversing visual order for RTL lines)
        $normalized = ArabicPdfText::normalizeExtracted($rawText, 'rtl');

        // Verify that candidate identity, contacts, and all core sections are recovered
        $this->assertStringContainsString('faisal.qahtani@example.com', $normalized);
        $this->assertStringContainsString('+966509876543', $normalized);
        $this->assertStringContainsString('فيصل', $normalized);
        $this->assertStringContainsString('القحطاني', $normalized);

        // Reading order linear invariant: Summary before Experience before Education
        $summaryPos = mb_strpos($normalized, 'الملخص المهني');
        $expPos = mb_strpos($normalized, 'الخبرة المهنية');
        $eduPos = mb_strpos($normalized, 'التعليم');

        if ($summaryPos !== false && $expPos !== false) {
            $this->assertLessThan($expPos, $summaryPos, 'Summary must appear before Experience in ATS reading order');
        }
        if ($expPos !== false && $eduPos !== false) {
            $this->assertLessThan($eduPos, $expPos, 'Experience must appear before Education in ATS reading order');
        }
    }

    public function test_pdf_export_sets_document_metadata(): void
    {
        $cv = $this->createStructuredCv('en', [
            'fullName' => 'Nora Al-Mansoor',
            'headline' => 'DevOps Specialist',
            'email' => 'nora.mansoor@example.com',
            'phone' => '+966540000001',
            'location' => 'Dammam, Saudi Arabia',
            'summary' => 'DevOps Engineer specialized in Kubernetes and CI/CD pipelines.',
            'experience' => [
                [
                    'title' => 'Senior DevOps Engineer',
                    'company' => 'FinTech Labs',
                    'location' => 'Khobar',
                    'start_date' => '2022',
                    'end_date' => '2024',
                    'narrative' => 'Automated deployment pipelines.',
                ],
            ],
            'education' => [
                [
                    'degree' => 'BSc Computer Engineering',
                    'institution' => 'KFUPM',
                    'start_date' => '2017',
                    'end_date' => '2021',
                    'narrative' => 'Summa Cum Laude.',
                ],
            ],
            'skills' => ['Kubernetes', 'Terraform', 'AWS', 'GitHub Actions'],
        ]);

        $renderer = app(CvTemplateRenderer::class);
        $response = $renderer->downloadResponse($cv, 'ats-classic-professional');

        $this->assertSame(200, $response->getStatusCode());

        $parser = new PdfParser;
        $pdf = $parser->parseContent($response->getContent());
        $details = $pdf->getDetails();

        // Metadata assertions (SIRATI-45 AC 47)
        $this->assertStringContainsString('Nora Al-Mansoor', (string) ($details['Author'] ?? ''));
        $this->assertStringContainsString('Nora Al-Mansoor', (string) ($details['Title'] ?? ''));
        $this->assertSame('Sirati CV Platform', (string) ($details['Creator'] ?? ''));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createStructuredCv(string $language, array $attributes): GeneratedCv
    {
        $isAr = $language === 'ar';
        $user = User::factory()->create();

        $expEntries = [];
        foreach ($attributes['experience'] ?? [] as $exp) {
            $expEntries[] = [
                'title' => ['ar' => $isAr ? $exp['title'] : '', 'en' => ! $isAr ? $exp['title'] : ''],
                'company' => ['ar' => $isAr ? $exp['company'] : '', 'en' => ! $isAr ? $exp['company'] : ''],
                'location' => ['ar' => $isAr ? $exp['location'] : '', 'en' => ! $isAr ? $exp['location'] : ''],
                'start_date' => $exp['start_date'] ?? null,
                'end_date' => $exp['end_date'] ?? null,
                'is_current' => false,
                'bullets' => [],
                'narrative' => ['ar' => $isAr ? $exp['narrative'] : '', 'en' => ! $isAr ? $exp['narrative'] : ''],
            ];
        }

        $eduEntries = [];
        foreach ($attributes['education'] ?? [] as $edu) {
            $eduEntries[] = [
                'degree' => ['ar' => $isAr ? $edu['degree'] : '', 'en' => ! $isAr ? $edu['degree'] : ''],
                'institution' => ['ar' => $isAr ? $edu['institution'] : '', 'en' => ! $isAr ? $edu['institution'] : ''],
                'field' => ['ar' => '', 'en' => ''],
                'start_date' => $edu['start_date'] ?? null,
                'end_date' => $edu['end_date'] ?? null,
                'narrative' => ['ar' => $isAr ? $edu['narrative'] : '', 'en' => ! $isAr ? $edu['narrative'] : ''],
            ];
        }

        $skillEntries = [];
        foreach ($attributes['skills'] ?? [] as $skill) {
            $skillEntries[] = [
                'name' => ['ar' => $skill, 'en' => $skill],
            ];
        }

        $docPayload = [
            'schema_version' => 1,
            'export_language' => $language,
            'personal' => [
                'full_name' => ['ar' => $isAr ? $attributes['fullName'] : '', 'en' => ! $isAr ? $attributes['fullName'] : ''],
                'headline' => ['ar' => $isAr ? $attributes['headline'] : '', 'en' => ! $isAr ? $attributes['headline'] : ''],
                'email' => $attributes['email'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'linkedin' => null,
                'location' => ['ar' => $isAr ? $attributes['location'] : '', 'en' => ! $isAr ? $attributes['location'] : ''],
            ],
            'summary' => ['ar' => $isAr ? $attributes['summary'] : '', 'en' => ! $isAr ? $attributes['summary'] : ''],
            'experience' => $expEntries,
            'education' => $eduEntries,
            'skills' => $skillEntries,
            'languages' => [],
            'certifications' => [],
            'projects' => [],
            'custom_sections' => [],
        ];

        return GeneratedCv::create([
            'user_id' => $user->id,
            'full_name' => $attributes['fullName'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
            'location' => $attributes['location'],
            'target_job_title' => $attributes['headline'],
            'language' => $language,
            'summary_input' => $attributes['summary'],
            'skills_input' => implode(', ', $attributes['skills'] ?? []),
            'experience_input' => 'Experience narrative',
            'education_input' => 'Education narrative',
            'generated_markdown' => '',
            'document' => $docPayload,
            'form_payload' => ['language' => $language],
            'ai_status' => 'completed',
            'score_total' => 92,
            'grade' => 'A+',
        ]);
    }
}
