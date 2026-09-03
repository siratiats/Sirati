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

    public function test_restores_a_visually_reversed_arabic_word(): void
    {
        $this->assertSame('كتب', trim(ArabicPdfText::normalizeExtracted('بتك')));
    }
}
