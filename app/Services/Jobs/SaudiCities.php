<?php

namespace App\Services\Jobs;

/**
 * The one city table for job ingestion and the jobs API: stored city code,
 * Arabic label, region (as used by the app's region chips) and the names a
 * post may use for it (matched as whole words after normalisation).
 */
final class SaudiCities
{
    public const REGIONS = ['central', 'eastern', 'western', 'northern', 'southern'];

    /** @var array<string, array{0: string, 1: string, 2: list<string>}> */
    public const CITIES = [
        'riyadh' => ['الرياض', 'central', ['الرياض', 'riyadh']],
        'kharj' => ['الخرج', 'central', ['الخرج', 'kharj', 'al kharj']],
        'majmaah' => ['المجمعة', 'central', ['المجمعة', 'majmaah']],
        'dawadmi' => ['الدوادمي', 'central', ['الدوادمي', 'dawadmi']],
        'zulfi' => ['الزلفي', 'central', ['الزلفي', 'zulfi']],
        'qassim' => ['القصيم', 'central', ['القصيم', 'بريدة', 'عنيزة', 'qassim', 'buraidah', 'buraydah', 'unaizah']],
        'jeddah' => ['جدة', 'western', ['جدة', 'jeddah', 'jiddah']],
        'mecca' => ['مكة المكرمة', 'western', ['مكة', 'مكة المكرمة', 'mecca', 'makkah']],
        'medina' => ['المدينة المنورة', 'western', ['المدينة المنورة', 'medina', 'madinah']],
        'taif' => ['الطائف', 'western', ['الطائف', 'taif']],
        'yanbu' => ['ينبع', 'western', ['ينبع', 'yanbu']],
        'rabigh' => ['رابغ', 'western', ['رابغ', 'rabigh']],
        'thuwal' => ['ثول', 'western', ['ثول', 'thuwal']],
        'qunfudhah' => ['القنفذة', 'western', ['القنفذة', 'qunfudhah']],
        'dammam' => ['الدمام', 'eastern', ['الدمام', 'dammam']],
        'khobar' => ['الخبر', 'eastern', ['الخبر', 'khobar', 'al khobar', 'al-khobar']],
        'dhahran' => ['الظهران', 'eastern', ['الظهران', 'dhahran', 'dharan']],
        'jubail' => ['الجبيل', 'eastern', ['الجبيل', 'jubail']],
        'qatif' => ['القطيف', 'eastern', ['القطيف', 'qatif']],
        'ahsa' => ['الأحساء', 'eastern', ['الأحساء', 'الهفوف', 'المبرز', 'ahsa', 'alahsa', 'al ahsa', 'al-ahsa', 'hofuf', 'hafuf']],
        'hafar_albatin' => ['حفر الباطن', 'eastern', ['حفر الباطن', 'hafar al batin', 'hafr al batin']],
        'khafji' => ['الخفجي', 'eastern', ['الخفجي', 'khafji']],
        'ras_tanura' => ['رأس تنورة', 'eastern', ['رأس تنورة', 'ras tanura']],
        'abqaiq' => ['بقيق', 'eastern', ['بقيق', 'abqaiq']],
        'tabuk' => ['تبوك', 'northern', ['تبوك', 'tabuk']],
        'hail' => ['حائل', 'northern', ['حائل', 'hail', "ha'il"]],
        'arar' => ['عرعر', 'northern', ['عرعر', 'arar']],
        'jouf' => ['الجوف', 'northern', ['الجوف', 'سكاكا', 'jouf', 'al jouf', 'sakaka']],
        'qurayyat' => ['القريات', 'northern', ['القريات', 'qurayyat']],
        'neom' => ['نيوم', 'northern', ['نيوم', 'neom']],
        'abha' => ['أبها', 'southern', ['أبها', 'abha', 'عسير', 'asir']],
        'khamis_mushait' => ['خميس مشيط', 'southern', ['خميس مشيط', 'khamis mushait']],
        'jazan' => ['جازان', 'southern', ['جازان', 'جيزان', 'jazan', 'jizan', 'gizan']],
        'najran' => ['نجران', 'southern', ['نجران', 'najran']],
        'baha' => ['الباحة', 'southern', ['الباحة', 'al baha', 'albaha']],
        'bisha' => ['بيشة', 'southern', ['بيشة', 'bisha']],
    ];

    /**
     * A province named without a city ("المنطقة الشرقية"). Used only when no
     * city is named, so the region filter still finds the post.
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public const PROVINCES = [
        'eastern_province' => ['المنطقة الشرقية', 'eastern', ['المنطقة الشرقية', 'الشرقية', 'eastern province']],
        'central_province' => ['المنطقة الوسطى', 'central', ['المنطقة الوسطى', 'منطقة الرياض']],
        'western_province' => ['المنطقة الغربية', 'western', ['المنطقة الغربية', 'منطقة مكة المكرمة']],
        'northern_province' => ['المنطقة الشمالية', 'northern', ['المنطقة الشمالية', 'الحدود الشمالية']],
        'southern_province' => ['المنطقة الجنوبية', 'southern', ['المنطقة الجنوبية']],
    ];

    public static function label(string $code): ?string
    {
        return (self::CITIES[$code] ?? self::PROVINCES[$code] ?? null)[0] ?? null;
    }

    public static function isRegion(?string $region): bool
    {
        return in_array($region, self::REGIONS, true);
    }

    /** @return list<string> stored city codes (cities and the province) in [$region] */
    public static function codesInRegion(string $region): array
    {
        $codes = [];
        foreach (self::CITIES + self::PROVINCES as $code => [, $cityRegion]) {
            if ($cityRegion === $region) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /** @return list<string> every name any city or province is known by */
    public static function allNames(): array
    {
        $names = [];
        foreach (self::CITIES + self::PROVINCES as [, , $synonyms]) {
            array_push($names, ...$synonyms);
        }

        return $names;
    }
}
