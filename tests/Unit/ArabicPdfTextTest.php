<?php

namespace Tests\Unit;

use App\Services\Cv\LogicalTextMpdf;
use App\Support\ArabicPdfText;
use LogicException;
use Mpdf\Mpdf;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionClass;
use ReflectionMethod;
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

    public function test_all_lam_alef_presentation_forms_expand_in_logical_order(): void
    {
        foreach (['آ', 'أ', 'إ', 'ا'] as $index => $alef) {
            // Every isolated/final lam-alef pair in Unicode Forms-B.
            foreach ([0, 1] as $final) {
                $form = mb_chr(0xFEF5 + $index * 2 + $final);
                $expected = 'بل'.$alef.'ت';
                $normalized = ArabicPdfText::normalizeExtracted('ب'.$form.'ت');

                $this->assertSame($expected, $normalized);
                $this->assertSame($normalized, ArabicPdfText::normalizeExtracted($normalized));
            }
        }
    }

    public function test_logical_runs_digits_and_combining_marks_are_preserved_idempotently(): void
    {
        $seed = 46;
        $random = new Randomizer(new Mt19937($seed));
        $letters = mb_str_split('ءآأؤإئابتةثجحخدذرزسشصضطظعغفقكلمنهويىٱ');
        foreach (['rtl', 'ltr'] as $direction) {
            for ($sample = 0; $sample < 60; $sample++) {
                $word = '';
                $length = $random->getInt(2, 12);
                for ($i = 0; $i < $length; $i++) {
                    $word .= $letters[$random->getInt(0, count($letters) - 1)];
                }
                $logical = $word.' C++ C# .NET CI/CD TLS-1.3 ٢٠٢٤ 2024 كُتِبَ '.$word;
                $context = 'seed='.$seed.', sample='.$sample.', direction='.$direction;
                $this->assertSame($logical, ArabicPdfText::normalizeExtracted($logical, $direction), $context);
                $this->assertSame($logical, ArabicPdfText::normalizeExtracted(ArabicPdfText::normalizeExtracted($logical, $direction), $direction), $context);
            }
        }
    }

    public function test_only_explicit_display_controls_are_removed_at_extraction(): void
    {
        $logical = 'عمل C++ و C# لإدارة الآلات';
        $extracted = "\u{200F}عمل \u{200E}\u{202A}C++\u{202C} و \u{2066}C#\u{2069} لإدارة الآلات";
        $this->assertSame($logical, ArabicPdfText::normalizeExtracted($extracted));
        $this->assertSame($logical, ArabicPdfText::normalizeExtracted($logical));
    }

    public function test_untagged_text_has_no_guessed_direction_or_word_order(): void
    {
        // Both orders are valid user text; letter patterns cannot establish
        // whether a third-party PDF painted either one in visual order.
        foreach (['ريدم', 'مدير', 'Software Engineer at شركة', 'خبرة Python Django Kubernetes'] as $text) {
            $this->assertSame($text, ArabicPdfText::normalizeExtracted($text));
        }
    }

    public function test_mpdf_constructor_preflight_rejects_each_missing_private_service_clearly(): void
    {
        $guard = new ReflectionMethod(LogicalTextMpdf::class, 'requiredServiceProperties');
        // Installed mPDF must pass the same preflight called by the constructor.
        $this->assertSame(['writer', 'fontCache', 'otl'], array_keys($guard->invoke(null, new ReflectionClass(Mpdf::class))));
        $missing = [
            'writer' => new class
            {
                private $fontCache;

                private $otl;
            },
            'fontCache' => new class
            {
                private $writer;

                private $otl;
            },
            'otl' => new class
            {
                private $writer;

                private $fontCache;
            },
        ];
        foreach ($missing as $name => $engine) {
            try {
                $guard->invoke(null, new ReflectionClass($engine));
                $this->fail('Preflight accepted an engine without $'.$name);
            } catch (LogicException $error) {
                $this->assertStringContainsString('Unsupported mPDF '.Mpdf::VERSION, $error->getMessage());
                $this->assertStringContainsString('service $'.$name.' is missing', $error->getMessage());
                $this->assertStringContainsString('before exporting', $error->getMessage());
            }
        }
        $incompatible = [
            'writer' => new class
            {
                private string $writer;

                private $fontCache;

                private $otl;
            },
            'fontCache' => new class
            {
                private $writer;

                private static $fontCache;

                private $otl;
            },
        ];
        foreach ($incompatible as $name => $engine) {
            try {
                $guard->invoke(null, new ReflectionClass($engine));
                $this->fail('Preflight accepted an incompatible $'.$name);
            } catch (LogicException $error) {
                $this->assertStringContainsString('service $'.$name.' must remain an untyped instance property', $error->getMessage());
            }
        }
    }
}
