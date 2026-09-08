<?php

namespace Tests\Feature;

use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use App\Services\Cv\CvMarkdownRenderer;
use App\Services\CvTemplateRenderer;
use App\Support\ArabicPdfText;
use Database\Seeders\CvTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Smalot\PdfParser\Parser as PdfParser;
use SplFileInfo;
use Tests\TestCase;

class CvPdfRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_classic_template_renders_contacts_and_omits_stray_separators(): void
    {
        $template = $this->template('classic', 'classic_rtl');
        $cv = $this->cv('en', [
            'email' => 'candidate@example.com',
            'phone' => null,
            'linkedin' => null,
            'location' => null,
        ]);

        $html = app(CvTemplateRenderer::class)->renderHtml($cv, $template->slug);

        $this->assertStringContainsString('candidate@example.com', $html);
        $this->assertMatchesRegularExpression(
            '/<div class="contact">\s*<span class="contact-item">candidate@example\.com<\/span>\s*<\/div>/',
            $html,
        );
        $this->assertStringNotContainsString('<span class="contact-separator">', $html);
    }

    public function test_classic_template_contains_all_present_contact_values(): void
    {
        $template = $this->template('classic', 'classic_rtl');
        $cv = $this->cv('en');

        $html = app(CvTemplateRenderer::class)->renderHtml($cv, $template->slug);

        $this->assertStringContainsString('candidate@example.com', $html);
        $this->assertStringContainsString('+966500000000', $html);
        $this->assertStringContainsString('linkedin.com/in/candidate', $html);
        $this->assertStringContainsString('Riyadh', $html);
        $this->assertStringContainsString('<h2>Experience</h2>', $html);
        $this->assertStringContainsString('<li>Built APIs</li>', $html);
    }

    public function test_renderer_strips_duplicate_identity_block_from_body_markdown(): void
    {
        $template = $this->template('classic', 'classic_rtl');
        $cv = $this->cv('en', [
            'generated_markdown' => "# Ahmed Ali\nSoftware Developer\n"
                ."candidate@example.com | +966500000000 | linkedin.com/in/candidate | Riyadh\n\n"
                ."## Experience\n\n- Built APIs",
        ]);

        $html = app(CvTemplateRenderer::class)->renderHtml($cv, $template->slug);

        $this->assertSame(1, substr_count($html, '<h1>Ahmed Ali</h1>'));
        $this->assertSame(1, substr_count($html, 'candidate@example.com'));
        $this->assertStringContainsString('<div class="content"><h2>Experience</h2>', $html);
        $this->assertStringNotContainsString('<div class="content"><h1>Ahmed Ali</h1>', $html);
    }

    public function test_modern_template_renders_contacts_as_wrappable_items(): void
    {
        $template = $this->template('modern', 'modern_rtl');
        $cv = $this->cv('en', [
            'linkedin' => 'https://linkedin.com/in/candidate-with-a-very-long-profile-slug-for-wrapping',
        ]);

        $html = app(CvTemplateRenderer::class)->renderHtml($cv, $template->slug);

        $this->assertStringContainsString('@page { margin: 26px; }', $html);
        $this->assertStringNotContainsString('margin: -22px -22px 18px;', $html);
        $this->assertSame(4, substr_count($html, 'class="meta-item"'));
        $this->assertSame(3, substr_count($html, 'class="meta-separator"'));
        $this->assertStringContainsString('word-wrap: break-word;', $html);
    }

    public function test_arabic_template_preserves_latin_contacts_and_shapes_mixed_location(): void
    {
        $template = $this->template('classic', 'classic_rtl');
        $cv = $this->cv('ar', [
            'email' => 'salem@example.com',
            'phone' => '+966500000000',
            'linkedin' => 'https://linkedin.com/in/salem',
            'location' => 'الرياض, Saudi Arabia',
        ]);

        $html = app(CvTemplateRenderer::class)->renderHtml($cv, $template->slug);

        $this->assertStringContainsString('salem@example.com', $html);
        $this->assertStringContainsString('+966500000000', $html);
        $this->assertStringContainsString('https://linkedin.com/in/salem', $html);
        $this->assertStringNotContainsString('moc.elpmaxe@melas', $html);
        $this->assertStringNotContainsString('melas/ni/moc.nideknil', $html);
        $this->assertStringContainsString('الرياض', $html);
        $this->assertStringContainsString('Saudi Arabia', $html);
        $this->assertStringNotContainsString('الرياض, Saudi Arabia', $html);
        $this->assertNotSame('', trim((string) data_get(
            app(CvTemplateRenderer::class)->viewModel($cv, $template),
            'sections.generated_markdown',
        )));
    }

    public function test_arabic_ats_label_is_shaped_in_the_view_model(): void
    {
        $template = $this->template('classic', 'classic_rtl');
        $cv = $this->cv('ar');
        $renderer = app(CvTemplateRenderer::class);
        $expected = app(CvMarkdownRenderer::class)->shapeText('نتيجة ATS', 'ar');

        $viewModel = $renderer->viewModel($cv, $template);
        $html = $renderer->renderHtml($cv, $template->slug);

        $this->assertSame($expected, data_get($viewModel, 'labels.ats_score'));
        $this->assertStringContainsString($expected, $html);
        $this->assertStringNotContainsString('نتيجة ATS', $html);
    }

    public function test_generated_cv_blade_templates_contain_no_arabic_literals(): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views/generated-cvs')),
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            $this->assertIsString($contents);
            $this->assertDoesNotMatchRegularExpression(
                '/\p{Arabic}/u',
                $contents,
                $file->getPathname().' contains a hardcoded Arabic literal.',
            );
        }
    }

    public function test_arabic_bidi_fixture_renders_to_a_non_empty_pdf(): void
    {
        $template = $this->template('classic', 'classic_rtl');
        $markdown = file_get_contents(base_path('tests/Fixtures/arabic_cv_bidi.md'));

        $this->assertIsString($markdown);

        $response = app(CvTemplateRenderer::class)->downloadResponse(
            $this->cv('ar', ['generated_markdown' => $markdown]),
            $template->slug,
        );

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertGreaterThan(1000, strlen((string) $response->getContent()));
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_arabic_pdf_extraction_is_logical_and_machine_readable(): void
    {
        $template = $this->template('classic', 'classic_rtl');
        $markdown = file_get_contents(base_path('tests/Fixtures/arabic_cv_bidi.md'));
        $this->assertIsString($markdown);

        $response = app(CvTemplateRenderer::class)->downloadResponse(
            $this->cv('ar', [
                'generated_markdown' => $markdown."\n\n- طورت واجهات API",
                'email' => 'salem@example.com',
            ]),
            $template->slug,
        );

        $binary = (string) $response->getContent();
        $dumpDir = storage_path('app/testing');
        if (! is_dir($dumpDir) && ! mkdir($dumpDir, 0755, true) && ! is_dir($dumpDir)) {
            $this->fail('Unable to write Arabic PDF fixture for inspection.');
        }
        file_put_contents($dumpDir.'/arabic-cv-bidi.pdf', $binary);

        $html = app(CvTemplateRenderer::class)->renderHtml(
            $this->cv('ar', [
                'generated_markdown' => $markdown."\n\n- طورت واجهات API",
                'email' => 'salem@example.com',
            ]),
            $template->slug,
        );
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('text-align: right', $html);

        $extracted = (new PdfParser)->parseContent($binary)->getText();
        $logical = ArabicPdfText::normalizeExtracted($extracted);

        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $extracted);
        $this->assertStringContainsString('API', $extracted);
        $this->assertStringContainsString('salem@example.com', $extracted);
        $this->assertStringNotContainsString('moc.elpmaxe@melas', $extracted);
        $this->assertStringNotContainsString('IPA', $extracted);

        $this->assertStringContainsString('التعليم', $logical);
        $this->assertStringContainsString('جامعة الملك سعود', $logical);
        $this->assertStringContainsString('طورت', $logical);
        $this->assertStringContainsString('واجهات', $logical);
        $this->assertStringContainsString('API', $logical);
        $this->assertStringContainsString('salem@example.com', $logical);
    }

    public function test_download_response_is_non_empty_for_both_templates_and_languages(): void
    {
        $classic = $this->template('classic', 'classic_rtl');
        $modern = $this->template('modern', 'modern_rtl');
        $renderer = app(CvTemplateRenderer::class);

        foreach ([$classic, $modern] as $template) {
            foreach (['ar', 'en'] as $language) {
                $response = $renderer->downloadResponse($this->cv($language), $template->slug);

                $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
                $this->assertGreaterThan(1000, strlen((string) $response->getContent()));
                $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
            }
        }
    }

    public function test_signed_pdf_download_route_accepts_valid_signature_with_template_parameter(): void
    {
        $template = $this->template('modern', 'modern_rtl');
        $cv = $this->cv('en');

        $signedUrl = URL::temporarySignedRoute(
            'api.generated-cvs.pdf',
            now()->addMinutes(30),
            ['generatedCv' => $cv->id]
        );

        $urlWithTemplate = $signedUrl.'&template='.$template->slug;

        $response = $this->get($urlWithTemplate);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_each_of_the_six_seeded_templates_renders_distinct_html_views(): void
    {
        $this->seed(CvTemplateSeeder::class);
        $renderer = app(CvTemplateRenderer::class);
        $cv = $this->cv('ar');

        $slugs = [
            'ats-classic-professional',
            'graduate-launchpad',
            'executive-leadership-brief',
            'sales-impact-performer',
            'bilingual-global-professional',
        ];

        $renderedOutputs = [];
        foreach ($slugs as $slug) {
            $html = $renderer->renderHtml($cv, $slug);
            $this->assertNotEmpty($html);
            $renderedOutputs[$slug] = $html;
        }

        // Assert all rendered HTMLs are unique from one another
        $uniqueCount = count(array_unique($renderedOutputs));
        $this->assertSame(count($slugs), $uniqueCount, 'Expected all template HTML views to be distinctly unique.');
    }

    public function test_english_render_has_no_arabic_outside_candidate_typed_fields(): void
    {
        $template = $this->template('classic-en-export', 'classic_rtl');

        $languageLevels = [
            'اللغة الأم (Native)' => 'Native',
            'طليق (C2 / Fluent)' => 'Fluent',
            'مهني متقدم (C1)' => 'Advanced professional (C1)',
            'متوسط (B2)' => 'Intermediate (B2)',
            'أساسي (A2)' => 'Elementary (A2)',
        ];
        $skillNameAr = 'إدارة المشاريع';
        $candidateArabic = [
            'سارة القحطاني',
            'مطورة برمجيات',
            'ملخص مهني مكتوب بالعربية',
            $skillNameAr,
        ];

        $languages = [];
        $i = 0;
        foreach ($languageLevels as $ar => $en) {
            $languages[] = [
                'name' => ['ar' => '', 'en' => 'Spoken-'.$i],
                // Empty `en` — hydrate must backfill from the known key.
                'level' => ['ar' => $ar, 'en' => ''],
            ];
            $i++;
        }

        $cv = $this->cv('en', [
            'full_name' => 'Sara Al-Qahtani',
            'target_job_title' => 'Software Developer',
            'generated_markdown' => '',
            'skills_input' => '',
            'experience_input' => '',
            'education_input' => '',
            'document' => [
                'schema_version' => 1,
                'export_language' => 'en',
                'personal' => [
                    'full_name' => ['ar' => 'سارة القحطاني', 'en' => 'Sara Al-Qahtani'],
                    'headline' => ['ar' => 'مطورة برمجيات', 'en' => 'Software Developer'],
                    'email' => 'sara@example.com',
                    'phone' => '+966500000000',
                    'linkedin' => 'linkedin.com/in/sara',
                    'location' => ['ar' => 'الرياض', 'en' => 'Riyadh'],
                ],
                'summary' => [
                    'ar' => 'ملخص مهني مكتوب بالعربية',
                    'en' => 'A professional summary written in English.',
                ],
                'skills' => [
                    ['name' => ['ar' => $skillNameAr, 'en' => '']],
                    ['name' => ['ar' => '', 'en' => 'Flutter']],
                ],
                'languages' => $languages,
            ],
        ]);

        $html = app(CvTemplateRenderer::class)->renderHtml($cv, $template->slug);

        foreach ($languageLevels as $ar => $en) {
            $this->assertStringContainsString($en, $html);
            $this->assertStringNotContainsString($ar, $html);
        }

        $remainder = $html;
        foreach ($candidateArabic as $typed) {
            $remainder = str_replace($typed, '', $remainder);
        }

        $this->assertDoesNotMatchRegularExpression(
            '/\p{Arabic}/u',
            $remainder,
            'English HTML leaked Arabic outside candidate-typed fields: '.$remainder,
        );
    }

    public function test_template_renders_directly_from_cv_document_payload(): void
    {
        $template = $this->template('classic-doc', 'classic_rtl');
        $cv = $this->cv('en', [
            'full_name' => 'Legacy Name',
            'target_job_title' => 'Legacy Title',
            'email' => 'legacy@example.com',
            'document' => [
                'schema_version' => 1,
                'export_language' => 'en',
                'personal' => [
                    'full_name' => ['ar' => 'اسم وثيقة', 'en' => 'Document Typed Name'],
                    'headline' => ['ar' => 'عنوان وثيقة', 'en' => 'Document Typed Headline'],
                    'email' => 'typed@example.com',
                    'phone' => '+966511111111',
                    'linkedin' => 'linkedin.com/in/typed',
                    'location' => ['ar' => 'جدة', 'en' => 'Jeddah'],
                ],
                'summary' => ['ar' => 'ملخص', 'en' => 'Typed document summary content.'],
                'experience' => [
                    [
                        'company' => ['ar' => 'شركة', 'en' => 'Acme Corp'],
                        'title' => ['ar' => 'مهندس', 'en' => 'Principal Architect'],
                        'narrative' => ['ar' => 'تفاصيل', 'en' => 'Spearheaded cloud migration.'],
                    ],
                ],
            ],
            'generated_markdown' => '',
        ]);

        $html = app(CvTemplateRenderer::class)->renderHtml($cv, $template->slug);

        $this->assertStringContainsString('Document Typed Name', $html);
        $this->assertStringContainsString('Document Typed Headline', $html);
        $this->assertStringContainsString('typed@example.com', $html);
        $this->assertStringContainsString('+966511111111', $html);
        $this->assertStringContainsString('Jeddah', $html);
        $this->assertStringContainsString('Typed document summary content.', $html);
        $this->assertStringContainsString('Principal Architect', $html);
        $this->assertStringNotContainsString('Legacy Name', $html);
        $this->assertStringNotContainsString('legacy@example.com', $html);
    }

    private function template(string $slug, string $rendererKey): CvTemplate
    {
        return CvTemplate::create([
            'name_ar' => $slug,
            'name_en' => ucfirst($slug),
            'slug' => $slug,
            'renderer_key' => $rendererKey,
            'language_direction' => 'both',
            'supported_languages' => ['ar', 'en'],
            'is_active' => true,
            'is_default' => $slug === 'classic',
        ]);
    }

    public function test_arabic_pdf_footer_isolates_grade_and_score_metrics_without_stranding_plus(): void
    {
        $this->seed(CvTemplateSeeder::class);
        $renderer = app(CvTemplateRenderer::class);

        $testCases = [
            ['ats-classic-professional', '94', 'A+'],
            ['executive-leadership-brief', '88', 'B+'],
            ['sales-impact-performer', '76', 'C-'],
        ];

        $user = \App\Models\User::factory()->create(['is_premium' => true]);

        foreach ($testCases as [$templateSlug, $score, $grade]) {
            $cv = $this->cv('ar', [
                'user_id' => $user->id,
                'score_total' => (int) $score,
                'grade' => $grade,
            ]);

            $response = $renderer->downloadResponse($cv, $templateSlug, $user);
            $pdfContent = (string) $response->getContent();
            $extracted = (new PdfParser)->parseContent($pdfContent)->getText();

            // Scope assertion to the footer line containing the percentage metric
            $footerLines = array_values(array_filter(
                explode("\n", $extracted),
                fn (string $line): bool => str_contains($line, "{$score}%")
            ));

            $this->assertNotEmpty($footerLines, "Expected a footer line containing {$score}% in template {$templateSlug}.");
            $footerLine = trim($footerLines[0]);

            // General invariant (AGENTS.md Rule 1):
            // 1. Grade token with sign must exist intact in the footer line.
            $this->assertStringContainsString($grade, $footerLine, "Grade {$grade} must remain intact in footer line for {$templateSlug}.");

            // 2. Count invariant: the number of '+' and '-' in the footer line must exactly match
            // the count in the grade token, proving zero modifiers were stranded elsewhere in the footer.
            $this->assertSame(
                substr_count($grade, '+'),
                substr_count($footerLine, '+'),
                "Extraneous or stranded '+' appeared outside {$grade} in footer line '{$footerLine}'."
            );
            $this->assertSame(
                substr_count($grade, '-'),
                substr_count($footerLine, '-'),
                "Extraneous or stranded '-' appeared outside {$grade} in footer line '{$footerLine}'."
            );
        }
    }

    /** @param array<string, mixed> $overrides */
    private function cv(string $language, array $overrides = []): GeneratedCv
    {
        $arabic = $language === 'ar';

        return GeneratedCv::create(array_merge([
            'full_name' => $arabic ? 'أحمد علي' : 'Ahmed Ali',
            'email' => 'candidate@example.com',
            'phone' => '+966500000000',
            'linkedin' => 'linkedin.com/in/candidate',
            'location' => $arabic ? 'الرياض' : 'Riyadh',
            'target_job_title' => $arabic ? 'مطور برمجيات' : 'Software Developer',
            'language' => $language,
            'skills_input' => 'PHP, Laravel',
            'experience_input' => 'Built APIs and improved automated test coverage for internal products.',
            'education_input' => 'BSc Computer Science',
            'generated_markdown' => $arabic
                ? "## الخبرة\n\n- طورت واجهات API\n- حسنت الاختبارات"
                : "## Experience\n\n- Built APIs\n- Improved tests",
            'form_payload' => ['language' => $language],
            'ai_status' => 'completed',
            'score_total' => 88,
            'grade' => 'A',
        ], $overrides));
    }
}
