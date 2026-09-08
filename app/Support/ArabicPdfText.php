<?php

namespace App\Support;

use Normalizer;

final class ArabicPdfText
{
    /**
     * Restore logical, machine-readable Arabic from a PDF text layer.
     *
     * PDF engines (including mPDF) paint RTL glyphs in visual order and often
     * store presentation forms. Compatibility-normalize letters, then reverse
     * Arabic runs so ATS parsers and tests see logical Unicode.
     *
     * Contract: Designated for the PDF extraction / ATS ingestion boundary (SIRATI-46)
     * to invert visual PDF text stream glyphs into logical Unicode. Do not call on
     * already-logical strings (e.g. database fields or form inputs).
     */
    public static function normalizeExtracted(string $text, string $baseDirection = 'rtl'): string
    {
        $text = self::unshape($text);
        $text = str_replace(["\u{200E}", "\u{200F}", "\u{202A}", "\u{202B}", "\u{202C}"], '', $text);

        $lines = preg_split("/\R/u", $text) ?: [$text];

        return implode("\n", array_map(
            fn (string $line): string => self::restoreLine($line, $baseDirection),
            $lines,
        ));
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

    private static function restoreLine(string $line, string $baseDirection): string
    {
        if ($line === '' || ! preg_match('/\p{Arabic}/u', $line)) {
            return $line;
        }

        // When base direction is 'ltr', the line is left-to-right (keep segment order).
        // When base direction is 'rtl', visual order was painted right-to-left, so segments are reversed.
        $isLtrLine = ($baseDirection === 'ltr');

        // Match Arabic letter runs excluding digits (\p{Nd} and Arabic-Indic digits 0660-0669, 06F0-06F9)
        // Also capture whitespace tokens separately to preserve spacing boundaries
        $pattern = '/([^\P{Arabic}\p{Nd}\x{0660}-\x{0669}\x{06F0}-\x{06F9}]+)|(\s+)/u';
        $parts = preg_split($pattern, $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return $line;
        }

        $restored = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^[^\P{Arabic}\p{Nd}\x{0660}-\x{0669}\x{06F0}-\x{06F9}]+$/u', $part) === 1) {
                $restored[] = self::reverseCharacters($part);
            } else {
                $restored[] = $part;
            }
        }

        if ($isLtrLine) {
            return implode('', $restored);
        }

        return implode('', array_reverse($restored));
    }

    private static function reverseCharacters(string $text): string
    {
        // Split by extended grapheme cluster (\X) so combining marks (harakat)
        // stay anchored to their base characters when reversed.
        preg_match_all('/\X/u', $text, $matches);

        return implode('', array_reverse($matches[0] ?? []));
    }
}
