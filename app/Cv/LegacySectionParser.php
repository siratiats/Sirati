<?php

namespace App\Cv;

/**
 * Turns generator free-text (and generated markdown sections) into the
 * structured entries the existing Blade renderer already knows how to draw.
 */
final class LegacySectionParser
{
    /** A year, optionally preceded by a month name ("Jan 2020", "يناير 2020") or number ("01/2020"). */
    private const DATE_POINT = '(?:\b(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\.?\s+|(?<!\p{L})(?:يناير|فبراير|مارس|أبريل|ابريل|مايو|يونيو|يوليو|أغسطس|اغسطس|سبتمبر|أكتوبر|اكتوبر|نوفمبر|ديسمبر)\s+|(?:0?[1-9]|1[0-2])\/)?(?:19|20)\d{2}';

    /**
     * @return list<ExperienceEntry>
     */
    public static function experience(string $blob, string $language): array
    {
        $blob = self::stripEditorial($blob);
        if (trim($blob) === '') {
            return [];
        }

        $entries = [];
        foreach (self::blocks($blob) as $block) {
            $entry = self::experienceBlock($block, $language);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return list<EducationEntry>
     */
    public static function education(string $blob, string $language): array
    {
        $blob = self::stripEditorial($blob);
        if (trim($blob) === '') {
            return [];
        }

        $entries = [];
        foreach (self::blocks($blob) as $block) {
            $entry = self::educationBlock($block, $language);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return list<Certification>
     */
    public static function certifications(string $blob, string $language): array
    {
        $blob = self::stripEditorial($blob);
        if (trim($blob) === '') {
            return [];
        }

        $entries = [];
        foreach (self::blocks($blob) as $block) {
            $lines = self::nonEmptyLines($block);
            if ($lines === []) {
                continue;
            }
            // A block of "- Name | Issuer | Year" lines holds one certification per line.
            $rows = count(array_filter($lines, fn (string $line): bool => preg_match('/^[-*•]\s+|\s\|\s/u', $line) === 1)) === count($lines)
                ? array_map(fn (string $line): array => [$line], $lines)
                : [$lines];

            foreach ($rows as $row) {
                $headerLine = trim(preg_replace('/^[-*•]\s+/u', '', $row[0]) ?? $row[0]);
                $header = self::splitHeader($headerLine);
                $date = $header['end'] ?? $header['start'];
                // "Name | Issuer | 2021": a lone year lands in the location slot.
                if ($date === null && preg_match('/^'.self::DATE_POINT.'$/iu', $header['location']) === 1) {
                    $date = $header['location'];
                } elseif ($date === null && $header['location'] === '' && preg_match('/^'.self::DATE_POINT.'$/iu', $header['subtitle']) === 1) {
                    $date = $header['subtitle'];
                    $header['subtitle'] = '';
                }
                $entries[] = new Certification(
                    name: LocalizedText::forLanguage($language, $header['title']),
                    issuer: LocalizedText::forLanguage($language, $header['subtitle']),
                    date: $date,
                    narrative: LocalizedText::forLanguage($language, implode("\n", array_slice($row, 1))),
                );
            }
        }

        return $entries;
    }

    /**
     * "Arabic: Native | English: Fluent" (or one language per line).
     *
     * @return list<LanguageSkill>
     */
    public static function languages(string $blob, string $language): array
    {
        $blob = self::stripEditorial($blob);
        $items = preg_split('/\R|\s+\|\s+|\s*[;؛]\s*/u', $blob) ?: [];
        $entries = [];

        foreach ($items as $item) {
            $item = trim(preg_replace('/^[-*•]\s+/u', '', trim($item)) ?? $item);
            if ($item === '') {
                continue;
            }
            $parts = preg_split('/\s*[:：]\s*|\s+[–—-]\s+|\s*\(\s*/u', $item, 2) ?: [$item];
            $name = trim($parts[0]);
            $level = trim(rtrim($parts[1] ?? '', ') '));
            // "Language: Arabic native" — the label is generic, the language leads the value.
            if (preg_match('/^(?:language|lang|اللغة|لغة)$/iu', $name) === 1) {
                $words = preg_split('/\s+/u', $level, 2) ?: [$level];
                $name = trim($words[0]);
                $level = trim($words[1] ?? '', " \t-–—,،");
            }
            if ($name === '') {
                continue;
            }
            $entries[] = new LanguageSkill(
                name: LocalizedText::forLanguage($language, $name),
                level: LocalizedText::forLanguage($language, $level),
            );
        }

        return $entries;
    }

    /**
     * @return list<Project>
     */
    public static function projects(string $blob, string $language): array
    {
        $blob = self::stripEditorial($blob);
        if (trim($blob) === '') {
            return [];
        }

        $entries = [];
        foreach (self::blocks($blob) as $block) {
            [$headerLine, $bullets, $narrative] = self::splitBody($block);
            if ($headerLine === '' && $bullets === [] && $narrative === '') {
                continue;
            }
            $header = self::splitHeader($headerLine);
            $entries[] = new Project(
                name: LocalizedText::forLanguage($language, $header['title']),
                role: LocalizedText::forLanguage($language, $header['subtitle']),
                description: LocalizedText::forLanguage($language, $narrative),
                bullets: array_map(
                    fn (string $bullet): LocalizedText => LocalizedText::forLanguage($language, $bullet),
                    $bullets,
                ),
            );
        }

        return $entries;
    }

    /**
     * Pull named sections out of generated markdown so overlay can replace
     * the matching document fields without inventing a second renderer.
     * Headings without a canonical field (Professional Development, Awards…)
     * are kept under "custom" as title/body pairs instead of being dropped.
     *
     * @return array<string, mixed>
     */
    public static function markdownSections(string $markdown): array
    {
        $markdown = trim($markdown);
        if ($markdown === '') {
            return [];
        }

        $parts = preg_split('/^##\s+/um', $markdown) ?: [];
        $sections = [];

        foreach ($parts as $index => $part) {
            // Text before the first "##" is the name/contact header, never a custom section.
            $isPreamble = $index === 0 && preg_match('/^##\s+/u', $markdown) !== 1;
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $newline = strpos($part, "\n");
            $heading = trim($newline === false ? $part : substr($part, 0, $newline));
            $body = $newline === false ? '' : trim(substr($part, $newline + 1));
            $key = self::sectionKey($heading);
            if ($key === 'custom') {
                if ($isPreamble) {
                    continue;
                }
                $body = self::stripEditorial($body);
                if ($body !== '' && ! self::isEditorialLine($heading)) {
                    $sections['custom'][] = ['title' => trim($heading, " \t:#*"), 'body' => $body];
                }

                continue;
            }
            if ($key === null) {
                continue;
            }
            // e.g. "Professional Experience" + "Volunteer Experience" both feed experience.
            $sections[$key] = isset($sections[$key]) && $body !== '' ? $sections[$key]."\n\n".$body : $body;
        }

        return $sections;
    }

    public static function stripEditorial(string $text): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $kept = [];

        foreach ($lines as $line) {
            if (self::isEditorialLine($line)) {
                continue;
            }
            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }

    public static function isEditorialLine(string $line): bool
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return false;
        }

        return (bool) preg_match(
            '/^(?:تحسينات\s+مطلوبة|ملاحظات(?:\s+(?:المحرر|داخلية))?|تنبيه(?:\s+داخلى|\s+داخلي)?|required\s+improvements?|editor(?:ial)?\s+notes?|internal\s+notes?|todo:|fixme:)/iu',
            $trimmed,
        );
    }

    /**
     * @return list<string>
     */
    private static function blocks(string $blob): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $blob);
        $chunks = preg_split("/\n{2,}/u", $normalized) ?: [];
        $blocks = [];
        $buffer = '';

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $looksLikeHeader = self::looksLikeEntryHeader($chunk);
            if ($buffer !== '' && $looksLikeHeader) {
                $blocks[] = $buffer;
                $buffer = $chunk;

                continue;
            }

            $buffer = $buffer === '' ? $chunk : $buffer."\n\n".$chunk;
        }

        if ($buffer !== '') {
            $blocks[] = $buffer;
        }

        return $blocks === [] ? [trim($blob)] : $blocks;
    }

    private static function experienceBlock(string $block, string $language): ?ExperienceEntry
    {
        [$headerLine, $bullets, $narrative] = self::splitBody($block);
        if ($headerLine === '' && $bullets === [] && $narrative === '') {
            return null;
        }

        $header = self::splitHeader($headerLine);

        return new ExperienceEntry(
            company: LocalizedText::forLanguage($language, $header['subtitle']),
            title: LocalizedText::forLanguage($language, $header['title']),
            location: LocalizedText::forLanguage($language, $header['location']),
            startDate: $header['start'],
            endDate: $header['is_current'] ? null : $header['end'],
            isCurrent: $header['is_current'],
            bullets: array_map(
                fn (string $bullet): LocalizedText => LocalizedText::forLanguage($language, $bullet),
                $bullets,
            ),
            narrative: LocalizedText::forLanguage($language, $narrative),
        );
    }

    private static function educationBlock(string $block, string $language): ?EducationEntry
    {
        [$headerLine, $bullets, $narrative] = self::splitBody($block);
        if ($headerLine === '' && $bullets === [] && $narrative === '') {
            return null;
        }

        $header = self::splitHeader($headerLine);
        $extra = trim(implode("\n", array_filter([$narrative, ...$bullets])));

        return new EducationEntry(
            institution: LocalizedText::forLanguage($language, $header['subtitle']),
            degree: LocalizedText::forLanguage($language, $header['title']),
            startDate: $header['start'],
            endDate: $header['end'],
            narrative: LocalizedText::forLanguage($language, $extra),
        );
    }

    /**
     * @return array{0: string, 1: list<string>, 2: string}
     */
    private static function splitBody(string $block): array
    {
        $lines = preg_split('/\R/u', trim($block)) ?: [];
        $header = '';
        $bullets = [];
        $narrative = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if ($header === '') {
                $header = preg_replace('/^#+\s*/u', '', $trimmed) ?? $trimmed;

                continue;
            }
            if (preg_match('/^[-*•–—]\s+/u', $trimmed) === 1) {
                $bullets[] = trim(preg_replace('/^[-*•–—]\s+/u', '', $trimmed) ?? $trimmed);

                continue;
            }
            $narrative[] = $trimmed;
        }

        return [$header, $bullets, trim(implode("\n", $narrative))];
    }

    /**
     * @return array{title: string, subtitle: string, location: string, start: ?string, end: ?string, is_current: bool}
     */
    private static function splitHeader(string $line): array
    {
        $line = trim($line);
        $dates = self::extractDates($line);
        $withoutDates = trim($dates['remainder']);
        $withoutDates = preg_replace('/^[\s\-–—|,;،]+|[\s\-–—|,;،]+$/u', '', $withoutDates) ?? $withoutDates;

        $title = $withoutDates;
        $subtitle = '';
        $location = '';

        foreach ([' | ', ' — ', ' – ', '، ', ', ', ' لدى '] as $separator) {
            if (! str_contains($withoutDates, $separator)) {
                continue;
            }
            $parts = array_values(array_filter(array_map('trim', explode($separator, $withoutDates, 3)), fn (string $part): bool => $part !== ''));
            $title = $parts[0] ?? '';
            $subtitle = $parts[1] ?? '';
            $location = $parts[2] ?? '';
            break;
        }

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'location' => $location,
            'start' => $dates['start'],
            'end' => $dates['end'],
            'is_current' => $dates['is_current'],
        ];
    }

    /**
     * @return array{start: ?string, end: ?string, is_current: bool, remainder: string}
     */
    private static function extractDates(string $line): array
    {
        $current = '(?:Present|Current|Now|حتى الآن|حالياً?)';
        $year = self::DATE_POINT;
        $pattern = '/('.$year.')\s*(?:[-–—]|to|إلى)\s*('.$year.'|'.$current.')/iu';

        if (preg_match($pattern, $line, $match) !== 1) {
            return ['start' => null, 'end' => null, 'is_current' => false, 'remainder' => $line];
        }

        $isCurrent = (bool) preg_match('/^'.$current.'$/iu', $match[2]);

        return [
            'start' => $match[1],
            'end' => $isCurrent ? null : $match[2],
            'is_current' => $isCurrent,
            'remainder' => trim(str_replace($match[0], '', $line)),
        ];
    }

    private static function looksLikeEntryHeader(string $chunk): bool
    {
        $first = trim(explode("\n", $chunk)[0]);

        return self::extractDates($first)['start'] !== null
            || preg_match('/^#{1,4}\s+\S/u', $first) === 1;
    }

    /**
     * @return list<string>
     */
    private static function nonEmptyLines(string $block): array
    {
        $lines = preg_split('/\R/u', trim($block)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== ''));
    }

    private static function sectionKey(string $heading): ?string
    {
        $normalized = mb_strtolower(trim($heading));
        $normalized = trim($normalized, " \t:#");

        $has = static function (string ...$needles) use ($normalized): bool {
            foreach ($needles as $needle) {
                if (str_contains($normalized, $needle)) {
                    return true;
                }
            }

            return false;
        };

        return match (true) {
            // Languages first so "Language Skills" / "المهارات اللغوية" are not read as skills.
            $has('language', 'لغات', 'لغوي') => 'languages',
            $has('experience', 'employment', 'work history', 'خبر') => 'experience',
            $has('education', 'academic', 'تعليم', 'مؤهلات') => 'education',
            $has('summary', 'profile', 'objective', 'ملخص', 'نبذة') => 'summary',
            $has('skill', 'competenc', 'expertise', 'مهارات', 'كفاءات', 'جدارات') => 'skills',
            $has('certif', 'licens', 'شهاد', 'رخص') => 'certifications',
            $has('project', 'مشاريع', 'مشروعات') => 'projects',
            // The header already carries contact details.
            $has('contact', 'personal information', 'personal details', 'تواصل', 'بيانات شخصية', 'معلومات شخصية') => null,
            $normalized === '' => null,
            default => 'custom',
        };
    }
}
