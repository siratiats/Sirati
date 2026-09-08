<?php

namespace Tests\Unit;

use App\Support\ArabicPdfText;
use Tests\TestCase;

class ArabicPdfTextTest extends TestCase
{
    public function test_unshapes_presentation_form_alef(): void
    {
        $this->assertSame('ا', ArabicPdfText::unshape("\u{FE8D}"));
    }

    public function test_preserves_latin_symbols_and_fractions(): void
    {
        $text = 'Sirati™ ½ Senior Developer';
        $this->assertSame($text, ArabicPdfText::unshape($text));
    }

    public function test_preserves_arabic_indic_and_western_digits(): void
    {
        // Visually reversed word 'كتب' with Arabic-Indic digits '2024' (H3: بتك ٢٠٢٤ → ٢٠٢٤ كتب)
        $visual = 'بتك ٢٠٢٤';
        $normalized = ArabicPdfText::normalizeExtracted($visual, 'rtl');
        $this->assertSame('٢٠٢٤ كتب', $normalized);

        // Western digits: numbers remain unreversed internally
        $visualWestern = 'بتك 2024';
        $normalizedWestern = ArabicPdfText::normalizeExtracted($visualWestern, 'rtl');
        $this->assertSame('2024 كتب', $normalizedWestern);
    }

    public function test_keeps_harakat_anchored_to_base_characters(): void
    {
        // In visual stream, grapheme clusters are painted right-to-left:
        // [ب + َ] then [ت + ِ] then [ك + ُ]
        $visual = "\u{0628}\u{064E}\u{062A}\u{0650}\u{0643}\u{064F}";
        $normalized = ArabicPdfText::normalizeExtracted($visual, 'rtl');

        // Extended grapheme clusters (\X) reverse cluster-by-cluster:
        // restoring to [ك + ُ] [ت + ِ] [ب + َ] ('كُتِبَ')
        $expected = "\u{0643}\u{064F}\u{062A}\u{0650}\u{0628}\u{064E}";
        $this->assertSame($expected, $normalized);
    }

    public function test_restores_visual_arabic_job_titles_from_pdf_layer(): void
    {
        // Ordinary Arabic CV job titles extracted in visual order from PDF streams
        $this->assertSame('مدير', ArabicPdfText::normalizeExtracted('ريدم', 'rtl'));
        $this->assertSame('مهندس برمجيات', ArabicPdfText::normalizeExtracted('تايجمرب سدنهم', 'rtl'));
        $this->assertSame('تطوير واجهات', ArabicPdfText::normalizeExtracted('تاهجاو ريوطت', 'rtl'));
    }

    public function test_restores_latin_heavy_arabic_skills_line(): void
    {
        $visual = 'Kubernetes Django Python ةربخ';
        $normalized = ArabicPdfText::normalizeExtracted($visual, 'rtl');

        $this->assertSame('خبرة Python Django Kubernetes', $normalized);
    }

    public function test_ltr_base_direction_keeps_segment_order(): void
    {
        $line = 'Software Engineer at ةكرش';
        $normalized = ArabicPdfText::normalizeExtracted($line, 'ltr');

        $this->assertSame('Software Engineer at شركة', $normalized);
    }
}

