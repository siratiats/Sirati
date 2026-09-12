<?php

namespace App\Support;

final class Utf8Sanitizer
{
    /**
     * Sanitize and normalize arbitrary text into valid UTF-8 (RFC 3629) suitable for MySQL utf8mb4.
     *
     * 1. Strips null bytes (\0).
     * 2. Recombines CESU-8 surrogate pairs (\xED[\xA0-\xAF][\x80-\xBF]\xED[\xB0-\xBF][\x80-\xBF])
     *    into standard 4-byte UTF-8 code points (e.g. \u{1F4CC} 📌).
     * 3. Drops lone/unpaired surrogate bytes (\xED[\xA0-\xBF][\x80-\xBF]) which cannot be mapped
     *    to valid Unicode characters and trigger MySQL 1366 error.
     * 4. Cleans up any remaining invalid UTF-8 byte sequences.
     */
    public static function sanitize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // 1. Strip null bytes
        $text = str_replace("\0", '', $text);

        // 2. Recombine CESU-8 surrogate pairs into valid 4-byte UTF-8
        // Note: Pattern must NOT use /u modifier because CESU-8 is not valid UTF-8 in PCRE.
        $recombined = preg_replace_callback(
            '/\xED([\xA0-\xAF])([\x80-\xBF])\xED([\xB0-\xBF])([\x80-\xBF])/',
            static function (array $m): string {
                $high = 0xD000 | ((ord($m[1]) & 0x3F) << 6) | (ord($m[2]) & 0x3F);
                $low = 0xD000 | ((ord($m[3]) & 0x3F) << 6) | (ord($m[4]) & 0x3F);
                $codePoint = 0x10000 + (($high - 0xD800) << 10) + ($low - 0xDC00);

                $char = mb_chr($codePoint, 'UTF-8');

                return $char !== false ? $char : '';
            },
            $text,
        );

        $text = $recombined ?? $text;

        // 3. Strip any lone/unpaired surrogates (\uD800 - \uDFFF encoded as 3 bytes in UTF-8/CESU-8)
        $stripped = preg_replace('/\xED[\xA0-\xBF][\x80-\xBF]/', '', $text);
        $text = $stripped ?? $text;

        // 4. Scrub any remaining invalid UTF-8 byte sequences
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return $text;
    }
}
