<?php

namespace App\Cv;

/**
 * On-disk Arabic identifiers for skill/language proficiency, mirrored from
 * Flutter `SkillStorageKeys` / `LanguageLevelStorageKeys`.
 *
 * Used to fill empty `en` on hydrate so English export of documents written
 * before bilingual writes does not fall back to Arabic. Unrecognised `ar`
 * values are left alone.
 */
final class ProficiencyStorageKeys
{
    /** @var array<string, string> */
    public const SKILL = [
        'مبتدئ' => 'Beginner',
        'متوسط' => 'Intermediate',
        'متقدم' => 'Advanced',
        'خبير' => 'Expert',
        'مهارات تقنية' => 'Technical skills',
        'مهارات قيادية وشخصية' => 'Interpersonal skills',
        'أدوات وبرمجيات' => 'Tools and software',
        'إدارة وتخطيط' => 'Management and planning',
        'مهارات عامة' => 'General skills',
    ];

    /** @var array<string, string> */
    public const LANGUAGE_LEVEL = [
        'اللغة الأم (Native)' => 'Native',
        'طليق (C2 / Fluent)' => 'Fluent',
        'مهني متقدم (C1)' => 'Advanced professional (C1)',
        'متوسط (B2)' => 'Intermediate (B2)',
        'أساسي (A2)' => 'Elementary (A2)',
    ];

    /**
     * @param  array<string, string>  $map
     */
    public static function fillEmptyEnglish(LocalizedText $text, array $map): LocalizedText
    {
        if ($text->en !== '' || $text->ar === '') {
            return $text;
        }

        $en = $map[$text->ar] ?? '';

        return $en === '' ? $text : $text->withEn($en);
    }
}
