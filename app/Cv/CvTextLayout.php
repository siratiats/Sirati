<?php

namespace App\Cv;

/**
 * Line structure of CV text at the ingestion boundary.
 *
 * Text pasted from another PDF keeps that PDF's hard line wraps, so one
 * achievement arrives as three "lines". Rejoining happens once, when text
 * enters the document (LegacySectionParser, GeneratedCv::setAttribute),
 * never at render time.
 */
final class CvTextLayout
{
    /**
     * A line that opens a new qualification. A degree word alone is not
     * enough ("Associate member", "Doctor on call", "High school mentoring"):
     * it needs "of/in/degree", a degree abbreviation, or an Arabic degree noun.
     */
    private const QUALIFICATION = '/^(?:[-*•–—]\s+)?(?:'
        .'(?:master|bachelor|associate)(?:\'s|s)?\s+(?:of|in|degree)\b'
        .'|doctor\s+of\b|doctorate\b|ph\.?\s?d\b'
        .'|(?:e?mba|m\.?sc|b\.?sc|m\.?a|b\.?a|m\.?s|b\.?s|b\.?eng|m\.?eng|ll\.?b|ll\.?m|pharm\.?\s?d)(?=[\s(,.:|\-–—]|$)'
        .'|(?:(?:postgraduate|higher|advanced)\s+)?diploma\s+(?:in|of)\b'
        .'|high\s+school\s+(?:diploma|certificate)\b'
        .'|graduate\s+certificate\b'
        .'|(?:ال)?(?:ماجستير|بكالوريوس|دبلوم|دكتوراه)(?=\s|$)'
        .'|(?:شهادة\s+)?الثانوية\s+العامة'
        .')/iu';

    /** Words a line cannot end on when it is a complete item (lowercase only: "grade A" is an end). */
    private const CONNECTORS = [
        'and', 'or', 'of', 'to', 'in', 'for', 'with', 'the', 'a', 'an', 'by', 'on', 'at',
        'from', 'as', 'via', 'into', 'across', 'including',
        'و', 'أو', 'في', 'من', 'إلى', 'على', 'مع', 'عن',
    ];

    /** Right side of "Name — X" that names an issuing organization. */
    private const ORGANIZATION = '/\b(?:inc|ltd|llc|plc|gmbh|services?|institute|university|college|academy|association|council|board|authority|society|foundation|commission|chamber|google|microsoft|amazon|aws|coursera|udemy|udacity|edx|datacamp|linkedin|pmi|cpd|cisco|oracle|ibm|meta|hubspot|salesforce|comptia|isaca|socpa|hrci|shrm|cipd|acca|aicpa|harvard|stanford|mit)\b|جامعة|معهد|أكاديمية|هيئة|الهيئة|جمعية|مجلس|كلية|غرفة|جوجل|مايكروسوفت/iu';

    /**
     * Join lines that continue the previous one.
     *
     * Only an unmistakable continuation joins: the previous line has no
     * sentence end and either breaks a hyphenated word, ends on a comma or a
     * connector word, or is followed by a lowercase line. Anything else, an
     * Arabic wrap without a connector included, stays a separate item.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    public static function unwrapLines(array $lines): array
    {
        $joined = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $index = count($joined) - 1;
            if ($index >= 0 && self::continues($joined[$index], $line)) {
                $joined[$index] = preg_match('/\p{L}-$/u', $joined[$index]) === 1
                    ? $joined[$index].$line
                    : $joined[$index].' '.$line;

                continue;
            }
            $joined[] = $line;
        }

        return $joined;
    }

    public static function unwrapText(string $text): string
    {
        return implode("\n", self::unwrapLines(preg_split('/\R/u', $text) ?: []));
    }

    /**
     * True when the narrative is only the raw text its bullets were cut from
     * (plus, at most, a "Company | City | Dates" header line), so printing
     * both shows every line twice. Any other leftover text keeps it.
     *
     * @param  list<string>  $bullets
     */
    public static function narrativeRepeatsBullets(string $narrative, array $bullets): bool
    {
        $rest = self::collapse($narrative);
        if ($rest === '' || $bullets === []) {
            return false;
        }

        foreach ($bullets as $bullet) {
            $bullet = self::collapse($bullet);
            if ($bullet === '') {
                continue;
            }
            $at = mb_strpos($rest, $bullet);
            if ($at === false) {
                return false;
            }
            $rest = mb_substr($rest, 0, $at).' '.mb_substr($rest, $at + mb_strlen($bullet));
        }

        $rest = trim(self::collapse($rest), " \t|,;.–—-");

        return $rest === '' || (str_contains($rest, '|') && mb_strlen($rest) <= 160);
    }

    /**
     * Cut one education block into one block per qualification. A pasted
     * list keeps every degree in one block (no blank lines between them).
     *
     * @return list<string>
     */
    public static function splitQualifications(string $block): array
    {
        $chunks = [];
        foreach (preg_split('/\R/u', trim($block)) ?: [] as $line) {
            if ($chunks === []) {
                $chunks[] = $line;
            } elseif (self::opensQualification($line)) {
                $chunks[] = $line;
            } else {
                $chunks[count($chunks) - 1] .= "\n".$line;
            }
        }

        return $chunks;
    }

    public static function opensQualification(string $line): bool
    {
        return preg_match(self::QUALIFICATION, trim($line)) === 1;
    }

    /**
     * "Certificate — Issuer" typed into one field. Splits only when the right
     * side names an organization; "CFA — Level II" and "ISO 9001 — Quality
     * Management Systems" are one name.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitIssuer(string $name): array
    {
        $parts = preg_split('/\s+[—–]\s+/u', trim($name), 2) ?: [$name];
        if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
            return [trim($name), ''];
        }
        [$title, $issuer] = [trim($parts[0]), trim($parts[1])];
        $words = count(preg_split('/\s+/u', $issuer) ?: []);

        return $words <= 8 && preg_match(self::ORGANIZATION, $issuer) === 1
            ? [$title, $issuer]
            : [trim($name), ''];
    }

    /**
     * Apply the line rules to a stored document array (GeneratedCv::document).
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function tidyDocument(array $document): array
    {
        $summary = self::localized($document['summary'] ?? null);
        if ($summary !== null && is_array($document['summary'])) {
            $document['summary'] = [
                ...$document['summary'],
                'ar' => self::unwrapText($summary['ar']),
                'en' => self::unwrapText($summary['en']),
            ];
        }

        foreach (['experience', 'education', 'projects'] as $section) {
            if (! is_array($document[$section] ?? null)) {
                continue;
            }
            foreach ($document[$section] as $index => $entry) {
                if (is_array($entry)) {
                    $document[$section][$index] = self::tidyEntry($entry);
                }
            }
        }

        if (is_array($document['education'] ?? null)) {
            $document['education'] = self::splitEducationEntries($document['education']);
        }
        if (is_array($document['certifications'] ?? null)) {
            foreach ($document['certifications'] as $index => $entry) {
                if (is_array($entry)) {
                    $document['certifications'][$index] = self::tidyCertification($entry);
                }
            }
        }

        return $document;
    }

    /**
     * An education entry whose narrative lists degrees becomes one entry per
     * degree (single-language text only, like the bullets). An entry with no
     * degree of its own takes the first listed degree, keeping its
     * institution and dates.
     *
     * @param  array<int|string, mixed>  $entries
     * @return list<mixed>
     */
    private static function splitEducationEntries(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $narrative = is_array($entry) ? self::localized($entry['narrative'] ?? null) : null;
            $language = null;
            if ($narrative !== null && ($narrative['ar'] === '') !== ($narrative['en'] === '')) {
                $language = $narrative['en'] !== '' ? 'en' : 'ar';
            }
            $chunks = $language === null ? [] : self::splitQualifications($narrative[$language]);
            $opensWithDegree = $chunks !== [] && self::opensQualification($chunks[0]);
            if (count($chunks) < 2 && ! $opensWithDegree) {
                $result[] = $entry;

                continue;
            }

            $degree = self::localized($entry['degree'] ?? null) ?? ['ar' => '', 'en' => ''];
            if ($opensWithDegree && $degree['ar'] === '' && $degree['en'] === '') {
                [$entry['degree'], $entry['narrative']] = self::degreeAndDetails($language, (string) array_shift($chunks));
            } else {
                // Text before the first degree line still belongs to this entry.
                $entry['narrative'] = self::pair($language, $opensWithDegree ? '' : (string) array_shift($chunks));
            }
            $result[] = $entry;

            foreach ($chunks as $chunk) {
                [$chunkDegree, $details] = self::degreeAndDetails($language, $chunk);
                $result[] = [
                    'institution' => ['ar' => '', 'en' => ''],
                    'degree' => $chunkDegree,
                    'field_of_study' => ['ar' => '', 'en' => ''],
                    'start_date' => null,
                    'end_date' => null,
                    'graduation_date' => null,
                    'narrative' => $details,
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{0: array{ar: string, en: string}, 1: array{ar: string, en: string}}
     */
    private static function degreeAndDetails(string $language, string $chunk): array
    {
        $lines = preg_split('/\R/u', $chunk) ?: [$chunk];
        $degree = trim((string) preg_replace('/^[-*•–—]\s+/u', '', (string) array_shift($lines)));

        return [self::pair($language, $degree), self::pair($language, trim(implode("\n", $lines)))];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private static function tidyCertification(array $entry): array
    {
        $name = self::localized($entry['name'] ?? null);
        if ($name === null) {
            return $entry;
        }
        // Certification::fromArray reads "authority" before "issuer"; keep both in step.
        $issuer = self::localized($entry['authority'] ?? $entry['issuer'] ?? null) ?? ['ar' => '', 'en' => ''];
        foreach (['ar', 'en'] as $language) {
            if ($issuer[$language] === '' && $name[$language] !== '') {
                [$name[$language], $issuer[$language]] = self::splitIssuer($name[$language]);
            }
        }
        $entry['name'] = $name;
        $entry['authority'] = $issuer;
        $entry['issuer'] = $issuer;

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private static function tidyEntry(array $entry): array
    {
        $bullets = is_array($entry['bullets'] ?? null) ? array_values($entry['bullets']) : null;
        $narrative = self::localized($entry['narrative'] ?? null);

        foreach (['ar', 'en'] as $language) {
            $other = $language === 'ar' ? 'en' : 'ar';
            $texts = [];

            if ($bullets !== null) {
                $texts = array_map(fn (mixed $bullet): string => self::localized($bullet)[$language], $bullets);
                $otherEmpty = array_filter($bullets, fn (mixed $bullet): bool => self::localized($bullet)[$other] !== '') === [];
                // Unwrapping changes the count; only safe while the other
                // language has no bullets to stay aligned with.
                if ($otherEmpty && array_filter($texts, fn (string $text): bool => trim($text) !== '') !== []) {
                    $texts = self::unwrapLines($texts);
                    $bullets = array_map(fn (string $text): array => self::pair($language, $text), $texts);
                }
            }

            if ($narrative !== null && $narrative[$language] !== '') {
                $narrative[$language] = self::unwrapText($narrative[$language]);
                if (self::narrativeRepeatsBullets($narrative[$language], array_values(array_filter($texts, fn (string $text): bool => trim($text) !== '')))) {
                    $narrative[$language] = '';
                }
            }
        }

        if ($bullets !== null) {
            $entry['bullets'] = $bullets;
        }
        if ($narrative !== null) {
            $entry['narrative'] = $narrative;
        }

        return $entry;
    }

    /**
     * @return array{ar: string, en: string}
     */
    private static function pair(string $language, string $text): array
    {
        return ['ar' => $language === 'ar' ? $text : '', 'en' => $language === 'en' ? $text : ''];
    }

    /**
     * @return array{ar: string, en: string}|null
     */
    private static function localized(mixed $value): ?array
    {
        if (is_string($value)) {
            return ['ar' => $value, 'en' => ''];
        }
        if (! is_array($value)) {
            return null;
        }

        return [
            'ar' => is_string($value['ar'] ?? null) ? $value['ar'] : '',
            'en' => is_string($value['en'] ?? null) ? $value['en'] : '',
        ];
    }

    private static function continues(string $previous, string $line): bool
    {
        if (self::endsSentence($previous) || self::opensItem($line)) {
            return false;
        }
        if (preg_match('/\p{L}-$/u', $previous) === 1 || preg_match('/[,،&\/]$/u', $previous) === 1) {
            return true;
        }
        $lastWord = (string) preg_replace('/^.*\s/us', '', $previous);
        if (in_array($lastWord, self::CONNECTORS, true)) {
            return true;
        }
        // A lowercase line continues, unless both are short typed items
        // ("led team" / "built api").
        if (preg_match('/^\p{Ll}/u', $line) !== 1) {
            return false;
        }

        return count(preg_split('/\s+/u', $previous) ?: []) > 3;
    }

    /** A line that starts its own item: a marker or "2." / "2)" numbering. */
    private static function opensItem(string $line): bool
    {
        return preg_match('/^(?:[-*•–—]\s|\d{1,2}[.)]\s)/u', $line) === 1;
    }

    private static function endsSentence(string $line): bool
    {
        return preg_match('/[.!?؟…:]["”’)\]]*$/u', $line) === 1;
    }

    private static function collapse(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
