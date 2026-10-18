<?php

namespace App\Support;

use Normalizer;

final class ArabicPdfText
{
    /**
     * Canonicalize logical Unicode from a PDF extraction boundary.
     *
     * Sirati exports provide logical text using PDF ActualText. Directionality
     * belongs to the PDF renderer; reversing extracted strings loses bidi run
     * boundaries and reverses the letters inside expanded lam-alef ligatures.
     *
     * This operation is idempotent. It never guesses whether Arabic is visual
     * or logical. Untagged visual PDFs require a source-specific extraction
     * adapter, not orthographic guesses here. Never transform stored CV fields.
     * The direction argument remains for callers of the previous boundary API.
     */
    public static function normalizeExtracted(string $text, string $baseDirection = 'rtl'): string
    {
        $text = self::unshape($text);

        return (string) preg_replace('/[\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text);
    }

    /**
     * Only unshape presentation form Arabic characters (Presentation Forms-A & B).
     * Leaving Latin ligatures, trademarks, fractions, etc. untouched.
     */
    public static function unshape(string $text): string
    {

        if (! preg_match('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text)) {
            return $text;
        }

        if (! class_exists(Normalizer::class)) {
            return $text;
        }

        return (string) preg_replace_callback(
            '/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]+/u',
            function (array $match): string {
                $normalized = Normalizer::normalize($match[0], Normalizer::FORM_KC);

                return (is_string($normalized) && $normalized !== '') ? $normalized : $match[0];
            },
            $text,
        );
    }
}
