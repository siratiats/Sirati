<?php

namespace App\Support;

final class CvMarkdownIdentityBlock
{
    /** @param list<string> $contacts */
    public static function strip(string $markdown, string $name, string $targetJobTitle, array $contacts): string
    {
        $lines = preg_split('/\R/u', trim($markdown));

        if ($lines === false || $lines === []) {
            return trim($markdown);
        }

        self::removeLeadingBlankLines($lines);

        if ($lines !== [] && self::matchesMarkdownHeading($lines[0], $name)) {
            array_shift($lines);
            self::removeLeadingBlankLines($lines);
        }

        if ($lines !== [] && self::matchesPlainLine($lines[0], $targetJobTitle)) {
            array_shift($lines);
            self::removeLeadingBlankLines($lines);
        }

        if ($lines !== [] && self::looksLikeContactLine($lines[0], $contacts)) {
            array_shift($lines);
            self::removeLeadingBlankLines($lines);
        }

        return ltrim(implode("\n", $lines));
    }

    /** @param list<string> $lines */
    private static function removeLeadingBlankLines(array &$lines): void
    {
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }
    }

    private static function matchesMarkdownHeading(string $line, string $expected): bool
    {
        $text = preg_replace('/\A#{1,6}\s+/u', '', trim($line));

        return self::matchesPlainLine($text ?? $line, $expected);
    }

    private static function matchesPlainLine(string $line, string $expected): bool
    {
        return mb_strtolower(trim($line), 'UTF-8') === mb_strtolower(trim($expected), 'UTF-8');
    }

    /** @param list<string> $contacts */
    private static function looksLikeContactLine(string $line, array $contacts): bool
    {
        $line = mb_strtolower(trim($line), 'UTF-8');

        foreach ($contacts as $contact) {
            $contact = mb_strtolower(trim($contact), 'UTF-8');

            if (
                $contact !== ''
                && str_contains(self::compactContactText($line), self::compactContactText($contact))
            ) {
                return true;
            }
        }

        return false;
    }

    private static function compactContactText(string $text): string
    {
        return preg_replace('/\s+/u', '', $text) ?? $text;
    }
}