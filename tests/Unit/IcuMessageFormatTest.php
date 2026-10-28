<?php

namespace Tests\Unit;

use App\Services\I18n\IcuMessageParser;
use PHPUnit\Framework\TestCase;

/** IcuMessageParser::formatSimple, the rule the Labels phone preview uses (mirrored in the page's JS). */
class IcuMessageFormatTest extends TestCase
{
    private const TEXT_PIECES = ['Hello', ' ', 'مرحباً', ' — ', '%', '<b>', '&amp;', '٣', "\n", 'a.b', '🙂', '=1', 'other', ','];

    /** Random messages of literal text and simple placeholders format to the text with args substituted. */
    public function test_simple_messages_substitute_every_placeholder(): void
    {
        mt_srand(20261028);
        foreach ([false, true] as $escaping) {
            $parser = new IcuMessageParser($escaping);
            for ($run = 0; $run < 300; $run++) {
                $message = '';
                $expected = '';
                $args = [];
                for ($part = 0, $parts = mt_rand(0, 7); $part < $parts; $part++) {
                    if (mt_rand(0, 2) === 0) {
                        $name = ['name', 'count', 'x_1', '_v', 'Score'][mt_rand(0, 4)];
                        $args[$name] ??= self::TEXT_PIECES[mt_rand(0, count(self::TEXT_PIECES) - 1)].mt_rand(0, 99);
                        $pad = [' ', '', '  '][mt_rand(0, 2)];
                        $message .= '{'.$pad.$name.$pad.'}';
                        $expected .= $args[$name];
                    } else {
                        $piece = self::TEXT_PIECES[mt_rand(0, count(self::TEXT_PIECES) - 1)];
                        $message .= $piece;
                        $expected .= $piece;
                    }
                }

                $this->assertSame($expected, $parser->formatSimple($message, $args), $message);
                if ($args !== []) {
                    // Every arg is used by the message: without one of them it cannot be formatted.
                    $fewer = $args;
                    unset($fewer[array_rand($fewer)]);
                    $this->assertNull($parser->formatSimple($message, $fewer), $message);
                }
            }
        }
    }

    public function test_plural_select_missing_args_and_invalid_messages_are_not_formatted(): void
    {
        $parser = new IcuMessageParser(false);
        $args = ['count' => '3', 'name' => 'Ahmed', 'section' => 'skills'];
        foreach ([
            '{count, plural, =1{One} other{{count} CVs}}',
            'Hi {name}: {count, plural, other{# items}}',
            '{section, select, skills{Skills} other{Other}}',
            '{missing}',
            'Hello {name} {missing}',
            '{name',
            'name}',
            '{}',
            '{1abc}',
            '{name, number}',
        ] as $message) {
            $this->assertNull($parser->formatSimple($message, $args), $message);
        }
    }

    public function test_escaping_mode_resolves_quotes_like_the_validator(): void
    {
        $escaping = new IcuMessageParser(true);
        $this->assertSame("It's {name}: Ahmed", $escaping->formatSimple("It''s '{name}': {name}", ['name' => 'Ahmed']));
        $this->assertNull($escaping->formatSimple("Open 'quote {name}", ['name' => 'Ahmed']));

        // Without escaping an apostrophe is plain text and braces stay syntax.
        $plain = new IcuMessageParser(false);
        $this->assertSame("It''s 'Ahmed'", $plain->formatSimple("It''s '{name}'", ['name' => 'Ahmed']));
    }
}
