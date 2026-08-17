<?php

namespace App\Services\Ai;

use App\Support\ArabicIndicDigits;

final class EnhanceCvFieldResultGuard
{
    /** @param array<string, mixed> $result
     *  @return array{
     *      enhanced_text: string,
     *      changes_made: list<string>,
     *      missing_facts: list<string>,
     *      ats_keywords_added: list<string>,
     *      unverified_claims: list<array{text: string, kind: 'date'|'employer'}>
     *  }
     */
    public function enforce(array $result, string $draft, string $language): array
    {
        $enhanced = (string) ($result['enhanced_text'] ?? '');
        $missing = $this->strings($result['missing_facts'] ?? []);
        $unsupported = [];
        $claims = $this->claims($result['unverified_claims'] ?? []);
        $normalizedDraft = ArabicIndicDigits::normalize($draft);

        preg_match_all('/(?<!\d)(?:19|20)\d{2}(?!\d)|(?<!\d)\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}(?!\d)/u', $enhanced, $dateMatches);
        foreach (array_unique($dateMatches[0] ?? []) as $date) {
            if (! str_contains($normalizedDraft, ArabicIndicDigits::normalize($date))) {
                $unsupported[] = $language === 'en'
                    ? "Confirm and add the date {$date}; it was not in your draft."
                    : "أكد وأضف التاريخ {$date}؛ فهو غير موجود في مسودتك.";
                $claims[] = ['text' => $date, 'kind' => 'date'];
            }
        }

        foreach ($this->employers($enhanced) as $employer) {
            if ($employer !== '' && ! $this->containsFolded($draft, $employer)) {
                $unsupported[] = $language === 'en'
                    ? "Supply the employer name ({$employer}) before adding it."
                    : "أضف اسم جهة العمل ({$employer}) بنفسك قبل إدراجه.";
                $claims[] = ['text' => $employer, 'kind' => 'employer'];
            }
        }

        return [
            'enhanced_text' => $enhanced,
            'changes_made' => $this->strings($result['changes_made'] ?? []),
            'missing_facts' => array_values(array_unique([...$missing, ...$unsupported])),
            'ats_keywords_added' => $this->strings($result['ats_keywords_added'] ?? []),
            'unverified_claims' => $this->uniqueClaims($claims),
        ];
    }

    /** @return list<string> */
    private function strings(mixed $values): array
    {
        return is_array($values)
            ? array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                $values,
            )))
            : [];
    }

    /** @return list<array{text: string, kind: 'date'|'employer'}> */
    private function claims(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $claims = [];
        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }

            $text = trim((string) ($value['text'] ?? ''));
            $kind = (string) ($value['kind'] ?? '');
            if ($text !== '' && in_array($kind, ['date', 'employer'], true)) {
                $claims[] = ['text' => $text, 'kind' => $kind];
            }
        }

        return $claims;
    }

    /** @return list<string> */
    private function employers(string $text): array
    {
        preg_match_all(
            '/(?:\bat|\bfor)\s+((?:\p{Lu}[\p{L}&.\-]*)(?:\s+\p{Lu}[\p{L}&.\-]*){0,3})/u',
            $text,
            $englishMatches,
        );
        preg_match_all(
            '/(?:في\s+شركة|لدى\s+شركة)\s+([^\s،,.؛;\r\n]+(?:\s+[^\s،,.؛;\r\n]+){0,3})/u',
            $text,
            $arabicMatches,
        );

        return array_values(array_unique(array_filter(array_map(
            static fn (string $employer): string => trim($employer),
            [...($englishMatches[1] ?? []), ...($arabicMatches[1] ?? [])],
        ))));
    }

    /**
     * @param  list<array{text: string, kind: 'date'|'employer'}>  $claims
     * @return list<array{text: string, kind: 'date'|'employer'}>
     */
    private function uniqueClaims(array $claims): array
    {
        $unique = [];
        foreach ($claims as $claim) {
            $unique[$claim['kind']."\0".$claim['text']] = $claim;
        }

        return array_values($unique);
    }

    private function containsFolded(string $haystack, string $needle): bool
    {
        return str_contains(
            mb_strtolower($haystack, 'UTF-8'),
            mb_strtolower($needle, 'UTF-8'),
        );
    }
}
