<?php

namespace App\Services\Ai\Prompts;

final class ArabicRegisterRules
{
    public static function section(): string
    {
        return implode("\n", [
            '## Arabic register and bilingual handling',
            '- User-facing prose: formal MSA (فصحى معاصرة), not dialect.',
            '- Prefer Arabic HR/ATS terms when natural (ملخص مهني، خبرات، مهارات، إنجازات، كلمات مفتاحية); do not transliterate English when an Arabic term exists.',
            '- Keep technical stack/product terms in English (Laravel, SQL, API, SEO, CRM, P&L).',
            '- Mixed RTL/LTR: leave Latin tokens intact; do not reverse identifiers; separate Arabic clauses cleanly from English tokens.',
            '- Prose fields Arabic-first; keyword lists may keep English bank terms.',
            '- Never invent employers, dates, metrics, certifications, or skills—surface what is missing instead.',
        ]);
    }
}
