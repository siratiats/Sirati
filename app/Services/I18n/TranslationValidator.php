<?php

namespace App\Services\I18n;

use App\Models\AppTranslationKey;

/**
 * Decides whether a value may be stored for a catalog key. A value is valid when it parses as ICU
 * (balanced braces, plural/select with an `other` case), uses exactly the catalog's placeholder names,
 * and only pluralizes numeric placeholders. A blank value is valid: it clears the translation so the
 * app falls back.
 */
final class TranslationValidator
{
    private const NUMERIC_TYPES = ['int', 'num', 'double', 'number'];

    public function __construct(private readonly IcuMessageParser $parser)
    {
    }

    public static function fromConfig(): self
    {
        return new self(IcuMessageParser::fromConfig());
    }

    /** @return list<string> human readable errors; empty when the value is valid */
    public function validateForKey(AppTranslationKey $key, string $value): array
    {
        return $this->validate($value, $key->placeholders);
    }

    /**
     * @param  array<string, mixed>  $catalogPlaceholders  name => ARB placeholder metadata
     * @return list<string>
     */
    public function validate(string $value, array $catalogPlaceholders): array
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            return ['The text is not valid UTF-8.'];
        }

        if (self::isBlank($value)) {
            return [];
        }

        $maxLength = (int) config('i18n.max_value_length', 10000);
        if (mb_strlen($value) > $maxLength) {
            return ["The text is longer than {$maxLength} characters."];
        }

        try {
            $parsed = $this->parser->parse($value);
        } catch (IcuSyntaxException $exception) {
            return [$exception->getMessage()];
        }

        $errors = [];
        $expected = array_map('strval', array_keys($catalogPlaceholders));
        $used = array_map('strval', array_keys($parsed['arguments']));

        $missing = array_values(array_diff($expected, $used));
        $unknown = array_values(array_diff($used, $expected));
        sort($missing);
        sort($unknown);

        if ($missing !== []) {
            $errors[] = 'Missing placeholder'.(count($missing) > 1 ? 's' : '').': '.self::braced($missing).'.';
        }

        if ($unknown !== []) {
            $allowed = $expected === [] ? 'this label takes no placeholders' : 'allowed: '.self::braced($expected);
            $errors[] = 'Unknown placeholder'.(count($unknown) > 1 ? 's' : '').': '.self::braced($unknown)." ({$allowed}).";
        }

        foreach ($parsed['arguments'] as $name => $kinds) {
            $name = (string) $name;
            if (in_array('plural', $kinds, true) && in_array('select', $kinds, true)) {
                $errors[] = "{{$name}} cannot be used as both plural and select.";
            }

            if (in_array('plural', $kinds, true) && isset($catalogPlaceholders[$name])) {
                $type = is_array($catalogPlaceholders[$name]) ? ($catalogPlaceholders[$name]['type'] ?? null) : null;
                if (is_string($type) && $type !== '' && ! in_array(strtolower($type), self::NUMERIC_TYPES, true)) {
                    $errors[] = "{{$name}} is a {$type}, so it cannot be used in a plural.";
                }
            }
        }

        return $errors;
    }

    public static function isBlank(?string $value): bool
    {
        return $value === null || preg_match('/^[\s\x{200B}-\x{200F}\x{2060}\x{FEFF}]*$/u', $value) === 1;
    }

    /** @param list<string> $names */
    private static function braced(array $names): string
    {
        return implode(', ', array_map(fn (string $name) => '{'.$name.'}', $names));
    }
}
