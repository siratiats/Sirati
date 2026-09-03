<?php

namespace Tests\Unit;

use App\Services\Cv\CvMarkdownRenderer;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class CvMarkdownRendererTest extends TestCase
{
    public function test_markdown_headings_and_bullets_become_safe_html(): void
    {
        $html = (new CvMarkdownRenderer)->render("## Experience\n\n- Built APIs\n- Improved tests", 'en');

        $this->assertStringContainsString('<h2>Experience</h2>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Built APIs</li>', $html);
        $this->assertStringNotContainsString('##', $html);
        $this->assertNotSame('', trim($html));
    }

    public function test_raw_html_and_unsafe_links_are_not_emitted(): void
    {
        $html = (new CvMarkdownRenderer)->render(
            '<script>alert(1)</script>'."\n\n".'[unsafe](javascript:alert(1))',
            'en',
        );

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    public function test_arabic_shaping_changes_only_text_nodes_and_keeps_valid_tags(): void
    {
        $html = (new CvMarkdownRenderer)->render("## الخبرة\n\n- طورت واجهات API", 'ar');
        $document = new DOMDocument;

        $this->assertTrue($document->loadHTML('<meta charset="UTF-8">'.$html));
        $this->assertSame(1, $document->getElementsByTagName('h2')->length);
        $this->assertSame(1, $document->getElementsByTagName('ul')->length);
        $this->assertSame(1, $document->getElementsByTagName('li')->length);
        $this->assertStringNotContainsString('<ﻫ', $html);
    }

    public function test_english_is_not_glyph_shaped(): void
    {
        $html = (new CvMarkdownRenderer)->render("## Experience\n\nBuilt APIs.", 'en');

        $this->assertSame("<h2>Experience</h2>\n<p>Built APIs.</p>\n", $html);
    }

    public function test_latin_contacts_are_byte_identical_in_arabic_mode(): void
    {
        $renderer = new CvMarkdownRenderer;

        foreach ([
            'salem@example.com',
            'https://linkedin.com/in/salem',
            '+966500000000',
        ] as $contact) {
            $this->assertSame($contact, $renderer->shapeText($contact, 'ar'));
        }
    }

    public function test_mixed_arabic_latin_location_is_still_shaped(): void
    {
        $renderer = new CvMarkdownRenderer;
        $location = 'الرياض, Saudi Arabia';

        $this->assertNotSame($location, $renderer->shapeText($location, 'ar'));
    }

    public function test_arabic_bidi_fixture_isolates_latin_numerals_without_reordering(): void
    {
        $markdown = file_get_contents(base_path('tests/Fixtures/arabic_cv_bidi.md'));

        $this->assertIsString($markdown);

        $html = (new CvMarkdownRenderer)->render($markdown, 'ar');

        $this->assertStringContainsString("\u{200E}\u{202A}2020\u{202C}", $html);
        $this->assertStringContainsString("\u{200E}\u{202A}2023\u{202C}", $html);
        $this->assertStringContainsString("\u{200E}\u{202A}35%\u{202C}", $html);
        $this->assertStringContainsString('بكالوريوس علوم الحاسب', $html);
        $this->assertStringNotContainsString("\u{2066}", $html);
        $this->assertStringNotContainsString("\u{2069}", $html);
    }

    public function test_mixed_arabic_keeps_latin_tech_terms_in_logical_order(): void
    {
        $html = (new CvMarkdownRenderer)->render("## الخبرة\n\n- طورت واجهات API وLaravel", 'ar');

        $this->assertStringContainsString('طورت واجهات', $html);
        $this->assertStringContainsString("\u{200E}\u{202A}API\u{202C}", $html);
        $this->assertStringContainsString("\u{200E}\u{202A}Laravel\u{202C}", $html);
        $this->assertStringNotContainsString('IPA', $html);
    }

    public function test_http_and_mailto_links_are_rewritten_as_visible_urls(): void
    {
        $html = (new CvMarkdownRenderer)->render(
            "[LinkedIn](https://linkedin.com/in/x)\n\n[Email](mailto:salem@example.com)",
            'en',
        );

        $this->assertStringContainsString('LinkedIn (https://linkedin.com/in/x)', $html);
        $this->assertStringContainsString('Email (mailto:salem@example.com)', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    public function test_unsafe_link_does_not_emit_scheme_or_url(): void
    {
        $html = (new CvMarkdownRenderer)->render('[profile](javascript:alert(1))', 'en');

        $this->assertStringContainsString('profile', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_link_text_equal_to_url_is_not_duplicated(): void
    {
        $url = 'https://example.com/profile';
        $html = (new CvMarkdownRenderer)->render("[{$url}]({$url})", 'en');

        $this->assertSame(1, substr_count($html, $url));
    }

    public function test_wrapper_lookup_falls_back_to_first_div(): void
    {
        $document = new DOMDocument;
        $document->loadHTML('<html><body><div>fallback</div></body></html>');
        $method = new ReflectionMethod(CvMarkdownRenderer::class, 'rootOrFail');

        $root = $method->invoke(new CvMarkdownRenderer, $document, 42, 8);

        $this->assertInstanceOf(DOMElement::class, $root);
        $this->assertSame('fallback', $root->textContent);
    }

    public function test_wrapper_lookup_failure_logs_and_throws(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('CV markdown wrapper lookup failed', [
                'generated_cv_id' => 42,
                'markdown_length' => 8,
            ]);
        $method = new ReflectionMethod(CvMarkdownRenderer::class, 'rootOrFail');

        $this->expectException(RuntimeException::class);
        $method->invoke(new CvMarkdownRenderer, new DOMDocument, 42, 8);
    }
}
