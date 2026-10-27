<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog entry built from the template ARB (app_ar.arb). `placeholders` maps a placeholder name to its
 * ARB metadata ({type, format, ...}); null means the message takes no arguments.
 */
class AppTranslationKey extends Model
{
    protected $fillable = [
        'key',
        'description',
        'placeholders',
        'is_icu',
        'group',
    ];

    protected function casts(): array
    {
        return [
            'is_icu' => 'boolean',
        ];
    }

    protected function placeholders(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null || $value === '') {
                    return [];
                }
                $decoded = is_array($value) ? $value : json_decode((string) $value, true);

                return is_array($decoded) ? $decoded : [];
            },
            set: function ($value) {
                if (! is_array($value) || $value === []) {
                    return null;
                }

                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            },
        );
    }

    /** @return list<string> */
    public function placeholderNames(): array
    {
        return array_map('strval', array_keys($this->placeholders));
    }

    /** Group derived from the key prefix: "jobMatchTitle" -> "job", "home.title" -> "home". */
    public static function groupFor(string $key): string
    {
        foreach (['.', '_'] as $separator) {
            $position = strpos($key, $separator);
            if ($position !== false && $position > 0) {
                return strtolower(substr($key, 0, $position));
            }
        }

        $length = strlen($key);
        $end = 0;
        while ($end < $length && ! ctype_upper($key[$end]) && ! ctype_digit($key[$end])) {
            $end++;
        }

        return strtolower($end > 0 ? substr($key, 0, $end) : $key);
    }
}
