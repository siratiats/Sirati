<?php

namespace Tests\Feature;

use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\CvTemplateRenderer;
use App\Services\CvTextExtractor;
use App\Support\ArabicPdfText;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Random\Engine\Mt19937;
use Random\Randomizer;
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

        // 2. ActualText supplies logical order; canonicalize once at extraction.
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

    public function test_generated_mixed_runs_round_trip_exactly_across_pdf_pages_without_mutating_source(): void
    {
        $random = new Randomizer(new Mt19937(46098));
        $letters = mb_str_split('ابتثجحخدذرزسشصضطظعغفقكلمنهوي');
        $word = function () use ($random, $letters): string {
            $result = '';
            $length = $random->getInt(2, 5);
            for ($i = 0; $i < $length; $i++) {
                $result .= $letters[$random->getInt(0, count($letters) - 1)];
            }

            return $result;
        };
        $runs = [];
        // All four alefs, each with isolated/final lam-alef context. Generated
        // surrounding letters prevent calibration to a particular Arabic word.
        foreach (['ا', 'أ', 'إ', 'آ'] as $alef) {
            foreach (['', 'ب'] as $prefix) {
                foreach (['++', '#', '.', '/', '-', '7'] as $symbol) {
                    $arabic = $prefix.'ل'.$alef.$word();
                    $other = $word();
                    $root = 'T'.bin2hex($random->getBytes(2));
                    $term = $root.$symbol.($random->getInt(0, 1) ? (string) $random->getInt(1, 99) : '');
                    array_push($runs,
                        $term.' '.$arabic.' '.$other,
                        $arabic.' '.$term.' '.$other,
                        $arabic.' '.$other.' '.$term,
                        $arabic.' '.$term.' و '.$root.'/v2.4-rc#9+',
                    );
                }
            }
        }
        // Prefix symbols, multi-word Latin runs, font ligatures, and both digit
        // sets exercise boundaries independently of the generated suffix cases.
        foreach (['.NET', 'C++/CLI', 'F#', 'CI/CD', 'TLS-1.3', 'office affinity', 'Risk Management', '+12.5%', '٢٠٢٤'] as $term) {
            $runs[] = $word().' '.$term.' '.$word().' لإدارة الآلات';
        }

        $cv = $this->createStructuredCv('ar', [
            'fullName' => 'مرشح الاختبار', 'headline' => 'مهندس',
            'email' => 'roundtrip@example.com', 'phone' => '+966551234567', 'location' => '',
            'summary' => implode("\n", array_map(fn ($run) => '- '.$run, $runs)),
        ]);
        $stored = $cv->fresh()->getAttributes();

        // Production download consumer: assert the PDF bytes users receive,
        // not HTML or a normalizer-generated proxy of the document.
        $response = app(CvTemplateRenderer::class)->downloadResponse($cv, 'ats-classic-professional');
        $pdf = (new PdfParser)->parseContent($response->getContent());
        $this->assertGreaterThan(1, count($pdf->getPages()));
        $raw = $pdf->getText();
        $normalized = ArabicPdfText::normalizeExtracted($raw);
        $this->assertSame($normalized, ArabicPdfText::normalizeExtracted($normalized));
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $normalized))));
        $heading = array_search('الملخص المهني', $lines, true);
        $this->assertNotFalse($heading);
        $actual = array_slice($lines, $heading + 1);
        $this->assertCount(count($runs), $actual);
        foreach ($runs as $index => $run) {
            $this->assertSame($run, $actual[$index], 'Extract/export invariant at run '.$index);
        }
        $upload = UploadedFile::fake()->createWithContent('roundtrip.pdf', $response->getContent());
        $request = Request::create('/', 'POST', [], [], ['resume_file' => $upload]);
        $ingested = app(CvTextExtractor::class)->extract($request);
        $this->assertSame(trim($normalized), $ingested['text']);
        $this->assertSame($stored, $cv->fresh()->getAttributes());
    }

    public function test_wrapped_mixed_paragraph_preserves_every_run_and_page_reading_order(): void
    {
        $random = new Randomizer(new Mt19937(46100));
        $words = ['عمل', 'مدير', 'مهندس', 'لا', 'لأ', 'لإ', 'لآ', 'الأنظمة', 'الآلات', 'كُتِبَ'];
        $runs = [];
        for ($index = 0; $index < 640; $index++) {
            $runs[] = $words[$random->getInt(0, count($words) - 1)].' T'.$index.'++/#.2-7';
        }
        $summary = implode(' ', $runs);
        $cv = $this->createStructuredCv('ar', [
            'fullName' => 'مرشح الاختبار', 'headline' => 'مهندس',
            'email' => 'wrap@example.com', 'phone' => '+966551234567', 'location' => '',
            'summary' => $summary,
        ]);
        $response = app(CvTemplateRenderer::class)->downloadResponse($cv, 'ats-classic-professional');
        $pdf = (new PdfParser)->parseContent($response->getContent());
        $this->assertGreaterThan(1, count($pdf->getPages()));
        $text = ArabicPdfText::normalizeExtracted($pdf->getText());
        $this->assertSame($text, ArabicPdfText::normalizeExtracted($text));
        $actual = trim(explode('الملخص المهني', $text, 2)[1]);
        // Line wrapping introduces layout whitespace only; all input runs and
        // their order must survive even when a paragraph spans PDF pages.
        $this->assertSame($summary, preg_replace('/\s+/u', ' ', $actual));
    }

    /**
     * @param  array<string, mixed>  $attributes
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
