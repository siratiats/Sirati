<?php

namespace Tests\Unit;

use App\Support\Utf8Sanitizer;
use Tests\TestCase;

class Utf8SanitizerTest extends TestCase
{
    public function test_converts_cesu8_pushpin_surrogate_pair_to_valid_utf8_emoji(): void
    {
        // \xED\xA0\xBD\xED\xB3\x8C is the CESU-8 surrogate pair for U+1F4CC (📌 PUSHPIN)
        $cesu8 = hex2bin('EDA0BDEDB38C');
        $input = "تنبيه مهم: {$cesu8} تسوبلا يظفحا";

        $sanitized = Utf8Sanitizer::sanitize($input);

        $this->assertTrue(mb_check_encoding($sanitized, 'UTF-8'));
        $this->assertSame("تنبيه مهم: 📌 تسوبلا يظفحا", $sanitized);
    }

    public function test_converts_arbitrary_astral_plane_surrogate_pairs(): void
    {
        $codePoints = [
            0x10000,   // Linear B Syllable B008 A (boundary: lowest supplemental plane)
            0x1F600,   // 😀 Grinning Face
            0x1F680,   // 🚀 Rocket
            0x1F916,   // 🤖 Robot Face
            0x10FFFF,  // Highest valid Unicode code point
        ];

        foreach ($codePoints as $cp) {
            // Calculate UTF-16 surrogate pair
            $offset = $cp - 0x10000;
            $highSurrogate = 0xD800 + ($offset >> 10);
            $lowSurrogate = 0xDC00 + ($offset & 0x3FF);

            // Encode each 16-bit surrogate as 3-byte CESU-8
            $cesu8 = pack(
                'C6',
                0xED, 0xA0 | (($highSurrogate >> 6) & 0x0F), 0x80 | ($highSurrogate & 0x3F),
                0xED, 0xB0 | (($lowSurrogate >> 6) & 0x0F), 0x80 | ($lowSurrogate & 0x3F),
            );

            $expectedChar = mb_chr($cp, 'UTF-8');
            $sanitized = Utf8Sanitizer::sanitize("Prefix {$cesu8} Suffix");

            $this->assertTrue(mb_check_encoding($sanitized, 'UTF-8'));
            $this->assertSame("Prefix {$expectedChar} Suffix", $sanitized);
        }
    }

    public function test_strips_lone_unpaired_surrogates(): void
    {
        // Lone high surrogate without low surrogate
        $loneHigh = hex2bin('EDA0BD'); // U+D83D alone
        // Lone low surrogate without high surrogate
        $loneLow = hex2bin('EDB38C');  // U+DCCC alone

        $input = "Start {$loneHigh} middle {$loneLow} end";
        $sanitized = Utf8Sanitizer::sanitize($input);

        $this->assertTrue(mb_check_encoding($sanitized, 'UTF-8'));
        $this->assertSame('Start  middle  end', $sanitized);
    }

    public function test_strips_null_bytes(): void
    {
        $input = "Line 1\0\0 with null\0 bytes";
        $sanitized = Utf8Sanitizer::sanitize($input);

        $this->assertSame('Line 1 with null bytes', $sanitized);
    }

    public function test_preserves_valid_arabic_latin_and_multilingual_text(): void
    {
        $valid = "مهندس برمجيات Senior Flutter Developer 2026 — خبرة 5 سنوات في الرياض! 😀 🚀";
        $sanitized = Utf8Sanitizer::sanitize($valid);

        $this->assertSame($valid, $sanitized);
    }

    public function test_sanitizer_invariant_holds_across_arbitrary_binary_noise(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $bytes = random_bytes(256);
            $sanitized = Utf8Sanitizer::sanitize($bytes);

            $this->assertTrue(
                mb_check_encoding($sanitized, 'UTF-8'),
                'Sanitizer must always return valid UTF-8 for arbitrary byte sequences.',
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\xED[\xA0-\xBF][\x80-\xBF]/',
                $sanitized,
                'Sanitized output must contain no UTF-16 surrogate bytes.',
            );
        }
    }
}
