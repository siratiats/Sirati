<?php

namespace App\Services\I18n;

/**
 * Parser for the ICU message subset Flutter's gen-l10n accepts:
 *
 *   message  := (text | argument)*
 *   argument := "{" name "}"
 *             | "{" name "," ("plural" | "select") "," (selector "{" message "}")+ "}"
 *
 * Plural selectors are limited to Flutter's set (=0 =1 =2 zero one two few many other); select
 * selectors are word characters or "-". Every plural/select needs an `other` branch.
 *
 * Without escaping (Flutter's default, see config/i18n.php) an apostrophe is plain text and every brace
 * is syntax. With escaping, `''` is a literal apostrophe and `'...'` quotes literal text, braces included.
 */
final class IcuMessageParser
{
    public const PLURAL_SELECTORS = ['=0', '=1', '=2', 'zero', 'one', 'two', 'few', 'many', 'other'];

    private const MAX_DEPTH = 12;

    /** @var list<string> */
    private array $chars = [];

    private int $pos = 0;

    private int $length = 0;

    /** @var array<string, array<string, true>> */
    private array $arguments = [];

    private bool $hasIcu = false;

    private readonly bool $useEscaping;

    /** @param bool|null $useEscaping null reads config('i18n.icu_escaping') */
    public function __construct(?bool $useEscaping = null)
    {
        $this->useEscaping = $useEscaping ?? (bool) config('i18n.icu_escaping', false);
    }

    public static function fromConfig(): self
    {
        return new self();
    }

    /**
     * @return array{arguments: array<string, list<string>>, is_icu: bool}
     *               arguments maps each argument name to the kinds it is used as (simple|plural|select).
     *
     * @throws IcuSyntaxException
     */
    public function parse(string $message): array
    {
        if (! mb_check_encoding($message, 'UTF-8')) {
            throw new IcuSyntaxException('The text is not valid UTF-8.');
        }

        $chars = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY);
        $this->chars = $chars === false ? [] : $chars;
        $this->length = count($this->chars);
        $this->pos = 0;
        $this->arguments = [];
        $this->hasIcu = false;

        $this->parseMessage(0, false);

        $arguments = [];
        foreach ($this->arguments as $name => $kinds) {
            $arguments[$name] = array_keys($kinds);
        }

        return ['arguments' => $arguments, 'is_icu' => $this->hasIcu];
    }

    private function parseMessage(int $depth, bool $insideBranch): void
    {
        while ($this->pos < $this->length) {
            $char = $this->chars[$this->pos];

            if ($this->useEscaping && $char === "'") {
                $this->skipQuoted();

                continue;
            }

            if ($char === '{') {
                $this->parseArgument($depth);

                continue;
            }

            if ($char === '}') {
                if ($insideBranch) {
                    return;
                }

                throw new IcuSyntaxException("Unexpected '}' without a matching '{'.", $this->pos + 1);
            }

            $this->pos++;
        }

        if ($insideBranch) {
            throw new IcuSyntaxException("A plural/select branch is missing its closing '}'.");
        }
    }

    private function skipQuoted(): void
    {
        $next = $this->chars[$this->pos + 1] ?? null;
        if ($next === "'") {
            $this->pos += 2;

            return;
        }

        $start = $this->pos;
        $this->pos++;
        while ($this->pos < $this->length && $this->chars[$this->pos] !== "'") {
            $this->pos++;
        }

        if ($this->pos >= $this->length) {
            throw new IcuSyntaxException("Quoted text opened with ' is never closed.", $start + 1);
        }

        $this->pos++;
    }

    private function parseArgument(int $depth): void
    {
        if ($depth >= self::MAX_DEPTH) {
            throw new IcuSyntaxException('Plural/select messages are nested too deeply.', $this->pos + 1);
        }

        $open = $this->pos;
        $this->pos++;
        $this->skipWhitespace();

        $name = $this->readWhile(fn (string $c, bool $first) => $c === '_' || ctype_alpha($c) || (! $first && ctype_digit($c)));
        if ($name === '') {
            throw new IcuSyntaxException("Expected a placeholder name after '{'.", $open + 1);
        }

        $this->skipWhitespace();
        $char = $this->current();

        if ($char === '}') {
            $this->pos++;
            $this->record($name, 'simple');

            return;
        }

        if ($char !== ',') {
            throw $char === null
                ? new IcuSyntaxException("Placeholder {{$name}} is missing its closing '}'.", $open + 1)
                : new IcuSyntaxException("Unexpected character '{$char}' in placeholder {{$name}}.", $this->pos + 1);
        }

        $this->pos++;
        $this->skipWhitespace();
        $type = $this->readWhile(fn (string $c) => ctype_alpha($c));
        if (! in_array($type, ['plural', 'select'], true)) {
            throw new IcuSyntaxException(
                $type === ''
                    ? "Expected 'plural' or 'select' after '{$name},'."
                    : "Unsupported argument type '{$type}' for {$name}; only plural and select are allowed.",
                $this->pos + 1,
            );
        }

        $this->skipWhitespace();
        if ($this->current() !== ',') {
            throw new IcuSyntaxException("Expected ',' after '{$name}, {$type}'.", $this->pos + 1);
        }
        $this->pos++;

        $this->record($name, $type);
        $this->hasIcu = true;

        $selectors = [];
        while (true) {
            $this->skipWhitespace();
            $char = $this->current();

            if ($char === null) {
                throw new IcuSyntaxException("The {$type} for {$name} is missing its closing '}'.", $open + 1);
            }

            if ($char === '}') {
                $this->pos++;
                break;
            }

            $selectorStart = $this->pos;
            $selector = $this->readWhile(fn (string $c) => $c === '=' || $c === '_' || $c === '-' || ctype_alnum($c));
            if ($selector === '') {
                throw new IcuSyntaxException("Expected a {$type} case name for {$name}, found '{$char}'.", $this->pos + 1);
            }

            $this->assertSelector($type, $name, $selector, $selectorStart);

            $canonical = $type === 'plural' ? $this->canonicalPlural($selector) : $selector;
            if (isset($selectors[$canonical])) {
                throw new IcuSyntaxException("The {$type} for {$name} repeats the case '{$selector}'.", $selectorStart + 1);
            }
            $selectors[$canonical] = true;

            $this->skipWhitespace();
            if ($this->current() !== '{') {
                throw new IcuSyntaxException("Expected '{' after the case '{$selector}' of {$name}.", $this->pos + 1);
            }
            $this->pos++;

            $this->parseMessage($depth + 1, true);
            // parseMessage returns on the branch's closing brace.
            $this->pos++;
        }

        if ($selectors === []) {
            throw new IcuSyntaxException("The {$type} for {$name} has no cases.", $open + 1);
        }

        if (! isset($selectors['other'])) {
            throw new IcuSyntaxException("The {$type} for {$name} must have an 'other' case.", $open + 1);
        }
    }

    private function assertSelector(string $type, string $name, string $selector, int $start): void
    {
        if ($type === 'plural') {
            if (! in_array($selector, self::PLURAL_SELECTORS, true)) {
                throw new IcuSyntaxException(
                    "Unsupported plural case '{$selector}' for {$name}; use one of ".implode(' ', self::PLURAL_SELECTORS).'.',
                    $start + 1,
                );
            }

            return;
        }

        if (str_contains($selector, '=')) {
            throw new IcuSyntaxException("Unsupported select case '{$selector}' for {$name}.", $start + 1);
        }
    }

    private function canonicalPlural(string $selector): string
    {
        return match ($selector) {
            '=0' => 'zero',
            '=1' => 'one',
            '=2' => 'two',
            default => $selector,
        };
    }

    private function record(string $name, string $kind): void
    {
        $this->arguments[$name][$kind] = true;
    }

    private function current(): ?string
    {
        return $this->chars[$this->pos] ?? null;
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->length && preg_match('/^\s$/u', $this->chars[$this->pos]) === 1) {
            $this->pos++;
        }
    }

    /** Reads ASCII characters accepted by $accept(char, isFirst). */
    private function readWhile(callable $accept): string
    {
        $out = '';
        while ($this->pos < $this->length) {
            $char = $this->chars[$this->pos];
            if (strlen($char) !== 1 || ! $accept($char, $out === '')) {
                break;
            }
            $out .= $char;
            $this->pos++;
        }

        return $out;
    }
}
