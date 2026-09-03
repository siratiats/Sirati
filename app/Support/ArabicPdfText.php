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
     */
    public static function normalizeExtracted(string $text): string
    {
        $text = self::unshape($text);
        $text = str_replace(["\u{200E}", "\u{200F}", "\u{202A}", "\u{202B}", "\u{202C}"], '', $text);

        $lines = preg_split("/\R/u", $text) ?: [$text];

        return implode("\n", array_map(self::restoreLine(...), $lines));
    }

    public static function unshape(string $text): string
    {
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($text, Normalizer::FORM_KC);

            if (is_string($normalized) && $normalized !== '') {
                return $normalized;
            }
        }

        return $text;
    }

    private static function restoreLine(string $line): string
    {
        if ($line === '' || ! preg_match('/\p{Arabic}/u', $line)) {
            return $line;
        }

        $parts = preg_split('/(\p{Arabic}+)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $line;
        }

        $restored = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\p{Arabic}+$/u', $part) === 1) {
                $restored[] = self::reverseCharacters($part);
            } else {
                $restored[] = $part;
            }
        }

        return implode('', array_reverse($restored));
    }

    private static function reverseCharacters(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return implode('', array_reverse($chars ?: []));
    }
}
