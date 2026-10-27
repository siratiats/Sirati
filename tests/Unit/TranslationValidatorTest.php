<?php

namespace Tests\Unit;

use App\Services\I18n\IcuMessageParser;
use App\Services\I18n\TranslationValidator;
use Tests\TestCase;

/**
 * Invariants of the ICU value validator, checked over generated messages rather than fixed examples:
 * every well-formed message with exactly the catalog's placeholders passes; removing any single brace,
 * dropping `other`, or changing the placeholder set makes it fail.
 */
class TranslationValidatorTest extends TestCase
{
    private const TEXT = [
        'Hello', 'مرحباً', 'سيرة ذاتية', "don't", "l'été", '100%', 'a, b', '#', '= 1', 'one', 'other', '—', '؟',
        'CV', 'ATS', '🙂', '  ', "\n", 'تم حفظ {', 'x', ':', '"quoted"', '(', ')', '[1]', 'plural', 'select',
    ];

    private const NAMES = ['count', 'name', 'score', 'days', 'section', 'user_name', 'n2', '_x', 'cvTitle'];

    private function validator(bool $escaping = false): TranslationValidator
    {
        return new TranslationValidator(new IcuMessageParser($escaping));
    }

    public function test_generated_well_formed_messages_with_exact_placeholders_are_valid(): void
    {
        mt_srand(20261027);
        $validator = $this->validator();

        for ($i = 0; $i < 400; $i++) {
            $names = $this->pickNames();
            $message = $this->generateMessage($names, 0);
            $catalog = $this->catalogFor($names);

            $this->assertSame([], $validator->validate($message, $catalog), "Expected valid: {$message}");
        }
    }

    public function test_removing_any_single_brace_from_a_valid_message_makes_it_invalid(): void
    {
        mt_srand(77);
        $validator = $this->validator();

        for ($i = 0; $i < 120; $i++) {
            $names = $this->pickNames();
            $message = $this->generateMessage($names, 0);
            $catalog = $this->catalogFor($names);
            $chars = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY);

            foreach ($chars as $index => $char) {
                if ($char !== '{' && $char !== '}') {
                    continue;
                }
                $mutated = $chars;
                unset($mutated[$index]);
                $broken = implode('', $mutated);

                $this->assertNotSame([], $validator->validate($broken, $catalog), "Expected invalid: {$broken}");
            }
        }
    }

    public function test_placeholder_set_must_equal_the_catalog_exactly(): void
    {
        mt_srand(4242);
        $validator = $this->validator();

        for ($i = 0; $i < 150; $i++) {
            $names = $this->pickNames();
            $message = $this->generateMessage($names, 0);
            if (TranslationValidator::isBlank($message)) {
                // Blank clears the translation; placeholder rules do not apply.
                continue;
            }

            $extra = 'extra'.$i;
            $withExtra = $validator->validate($message, $this->catalogFor([...$names, $extra]));
            $this->assertNotSame([], $withExtra, "Missing {$extra} should fail: {$message} ".json_encode($this->catalogFor([...$names, $extra])));
            $this->assertStringContainsString('{'.$extra.'}', implode(' ', $withExtra));

            if ($names !== []) {
                $dropped = $names[array_rand($names)];
                $withoutOne = $validator->validate($message, $this->catalogFor(array_values(array_diff($names, [$dropped]))));
                $this->assertNotSame([], $withoutOne, "Dropping {$dropped} should fail: {$message}");
                $this->assertStringContainsString('{'.$dropped.'}', implode(' ', $withoutOne));
            }
        }
    }

    public function test_plural_and_select_without_other_are_rejected_at_any_depth(): void
    {
        mt_srand(99);
        $validator = $this->validator();

        for ($i = 0; $i < 100; $i++) {
            $kind = mt_rand(0, 1) === 0 ? 'plural' : 'select';
            $inner = $this->branches($kind, ['count'], 1, false);
            $message = $this->text().'{count, '.$kind.', '.$inner.'}';
            if (mt_rand(0, 1) === 1) {
                // Bury the broken one inside a valid select.
                $message = '{section, select, a{'.$message.'} other{{count} {section}}}';
                $names = ['count', 'section'];
            } else {
                $names = ['count'];
            }

            $errors = $validator->validate($message, $this->catalogFor($names));
            $this->assertNotSame([], $errors, $message);
            $this->assertStringContainsString("'other'", implode(' ', $errors));
        }
    }

    public function test_blank_values_are_valid_and_mean_no_translation(): void
    {
        $validator = $this->validator();
        foreach (['', ' ', "\n\t", "\u{200F}", "\u{200B}\u{FEFF}"] as $blank) {
            $this->assertSame([], $validator->validate($blank, ['name' => ['type' => 'String']]));
            $this->assertTrue(TranslationValidator::isBlank($blank));
        }
        $this->assertFalse(TranslationValidator::isBlank('مرحباً'));
    }

    public function test_adversarial_inputs_are_rejected_without_crashing(): void
    {
        $validator = $this->validator();
        $count = ['count' => ['type' => 'int']];

        $invalid = [
            ['{', []],
            ['}', []],
            ['{}', []],
            ['{ }', []],
            ['{1}', []],
            ['{-x}', []],
            ['{name', ['name' => []]],
            ['{name,}', ['name' => []]],
            ['{name, number}', ['name' => []]],
            ['{name, date, yMd}', ['name' => []]],
            ['{count, plural}', $count],
            ['{count, plural,}', $count],
            ['{count, plural, other}', $count],
            ['{count, plural, other{x}', $count],
            ['{count, plural, =3{x} other{y}}', $count],
            ['{count, plural, several{x} other{y}}', $count],
            ['{count, plural, one{x} one{y} other{z}}', $count],
            ['{count, plural, =1{x} one{y} other{z}}', $count],
            ['{count, select, =1{x} other{y}}', $count],
            ['{count, plural, other{x}} }', $count],
            ['{count, plural, other{x}}{', $count],
            ["{name}\u{0000}{", ['name' => []]],
            ["\xC3\x28", []],
            ['{count, plural, other{{count, select, other{x}}}}', $count],
        ];

        foreach ($invalid as [$value, $catalog]) {
            $this->assertNotSame([], $validator->validate($value, $catalog), 'Expected invalid: '.bin2hex($value));
        }

        $deep = str_repeat('{count, plural, other{', 40).'x'.str_repeat('}}', 40);
        $this->assertNotSame([], $validator->validate($deep, $count));

        $huge = str_repeat('{count}', 5000);
        $this->assertNotSame([], $validator->validate($huge, $count));
    }

    public function test_valid_edge_cases(): void
    {
        $validator = $this->validator();
        $valid = [
            ['{ name }', ['name' => []]],
            ["{\n count ,\n plural ,\n other { {count} } }", ['count' => ['type' => 'int']]],
            ['{count, plural, other{}}', ['count' => ['type' => 'num']]],
            ['{count, plural, =0{لا شيء} =1{واحد} =2{اثنان} few{{count} سير} many{{count} سيرة} other{{count} سيرة}}', ['count' => ['type' => 'int']]],
            ['{section, select, summary-1{x} other_2{y} other{z}}', ['section' => ['type' => 'String']]],
            ['مرحباً {name}، لديك {count, plural, one{رسالة} other{{count} رسائل}}', ['name' => ['type' => 'String'], 'count' => ['type' => 'int']]],
            ['{count, plural, one{{name}} other{{name} ×{count}}}', ['name' => [], 'count' => []]],
        ];

        foreach ($valid as [$value, $catalog]) {
            $this->assertSame([], $validator->validate($value, $catalog), $value);
        }
    }

    public function test_plural_requires_a_numeric_placeholder(): void
    {
        $validator = $this->validator();
        $message = '{name, plural, one{a} other{b}}';

        $this->assertNotSame([], $validator->validate($message, ['name' => ['type' => 'String']]));
        foreach (['int', 'num', 'double'] as $type) {
            $this->assertSame([], $validator->validate($message, ['name' => ['type' => $type]]));
        }
        // Undeclared type: Flutter infers num for a plural argument.
        $this->assertSame([], $validator->validate($message, ['name' => []]));
    }

    public function test_one_name_cannot_be_both_plural_and_select(): void
    {
        $errors = $this->validator()->validate('{n, plural, other{x}} {n, select, other{y}}', ['n' => []]);
        $this->assertNotSame([], $errors);
    }

    public function test_apostrophes_are_literal_without_escaping(): void
    {
        $validator = $this->validator(false);

        // An apostrophe never hides a brace: '{' still opens an argument.
        $this->assertNotSame([], $validator->validate("'{'", []));
        $this->assertNotSame([], $validator->validate("Use '{name}' here", []));
        $this->assertSame([], $validator->validate("Use '{name}' here", ['name' => []]));
        $this->assertSame([], $validator->validate("don't stop, {name}'s CV", ['name' => []]));
    }

    public function test_quotes_escape_braces_with_escaping_enabled(): void
    {
        $validator = $this->validator(true);

        $this->assertSame([], $validator->validate("'{'", []));
        $this->assertSame([], $validator->validate("'{}' and '}'", []));
        $this->assertSame([], $validator->validate("it''s {name}", ['name' => []]));
        $this->assertSame([], $validator->validate("قال ''مرحباً'' {name}", ['name' => []]));
        // A quoted placeholder is literal text, so the real placeholder is missing.
        $this->assertNotSame([], $validator->validate("'{name}'", ['name' => []]));
        $this->assertNotSame([], $validator->validate("don't {name}", ['name' => []]));
        $this->assertSame([], $validator->validate("{count, plural, one{'{'one'}'} other{{count} '{'}}", ['count' => ['type' => 'int']]));

        // Generated messages with quoted literal braces stay valid.
        mt_srand(5150);
        for ($i = 0; $i < 100; $i++) {
            $names = $this->pickNames();
            $message = "'{".$this->plainText()."}' ".$this->generateMessage($names, 0, true);
            $this->assertSame([], $validator->validate($message, $this->catalogFor($names)), $message);
        }
    }

    /** @return list<string> */
    private function pickNames(): array
    {
        $names = self::NAMES;
        shuffle($names);

        return array_slice($names, 0, mt_rand(0, 3));
    }

    /** @param list<string> $names */
    private function catalogFor(array $names): array
    {
        $catalog = [];
        foreach ($names as $name) {
            $catalog[$name] = ['type' => 'int'];
        }

        return $catalog;
    }

    /**
     * A random valid message that uses every name in $names at least once.
     *
     * @param  list<string>  $names
     */
    private function generateMessage(array $names, int $depth, bool $escapingSafe = false): string
    {
        $parts = [];
        if ($names !== [] && $depth < 3 && mt_rand(0, 2) === 0) {
            $selector = $names[array_rand($names)];
            // A name is either plural or select within one message, never both.
            $kind = crc32($selector) % 2 === 0 ? 'plural' : 'select';
            $parts[] = '{'.$selector.(mt_rand(0, 1) ? ', ' : ' ,  ').$kind.','.$this->branches($kind, $names, $depth + 1, true, $escapingSafe).'}';
        } else {
            foreach ($names as $name) {
                $parts[] = mt_rand(0, 3) === 0 ? '{ '.$name.' }' : '{'.$name.'}';
            }
        }

        for ($i = mt_rand(0, 3); $i > 0; $i--) {
            $parts[] = $escapingSafe ? $this->plainText() : $this->text();
        }
        shuffle($parts);

        return implode(mt_rand(0, 1) ? ' ' : '', $parts);
    }

    /** @param list<string> $names */
    private function branches(string $kind, array $names, int $depth, bool $withOther, bool $escapingSafe = false): string
    {
        $pool = $kind === 'plural'
            ? ['=0', 'one', '=2', 'few', 'many']
            : ['summary', 'skills', 'a-b', 'x_1', 'male', 'female'];
        shuffle($pool);
        $selectors = array_slice($pool, 0, mt_rand($withOther ? 0 : 1, 3));
        if ($withOther) {
            $selectors[] = 'other';
            shuffle($selectors);
        }

        $out = '';
        foreach ($selectors as $selector) {
            // Each branch independently uses every placeholder, so the set is complete whatever case runs.
            $out .= ' '.$selector.(mt_rand(0, 1) ? '' : ' ').'{'.$this->generateMessage($names, $depth, $escapingSafe).'}';
        }

        return $out.' ';
    }

    private function text(): string
    {
        $fragment = self::TEXT[array_rand(self::TEXT)];

        // Text never contains a brace (except via the Arabic sample, which is replaced).
        return str_replace(['{', '}'], ['(', ')'], $fragment);
    }

    /** Text without apostrophes (which are syntax when escaping is on). */
    private function plainText(): string
    {
        return str_replace("'", '', $this->text());
    }
}
