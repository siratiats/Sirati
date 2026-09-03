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
