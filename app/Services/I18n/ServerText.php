<?php

namespace App\Services\I18n;

use App\Models\AppLanguage;
use Illuminate\Support\Carbon;
use LogicException;
use Throwable;

/**
 * Text the server sends to the app, read from the same label catalog the app
 * uses, so it follows the app language and an admin edit changes it.
 *
 * A value is the language's published string, else its bundled ARB string,
 * then the same for each language in its fallback chain, ending at the default
 * language. Keys must exist in the bundled template ARB: a missing one is a bug.
 *
 * Scoped: one instance per request or queued job, so a publish is seen at once.
 */
final class ServerText
{
    private ?string $language = null;

    /** @var array<string, AppLanguage>|null enabled languages by code */
    private ?array $languages = null;

    /** @var array<string, array<string, string>> */
    private array $published = [];

    /** @var array<string, array<string, string>> */
    private array $bundled = [];

    public function __construct(private readonly TranslationPublisher $publisher)
    {
    }

    /** The caller's language for this request; null means the default language. */
    public function setLanguage(?string $code): void
    {
        $this->language = self::normalize($code);
    }

    /** The resolved language: the caller's when it is enabled, else the default. */
    public function language(): string
    {
        return $this->chain($this->language)[0];
    }

    /** Shortcut for app(ServerText::class)->get(). */
    public static function t(string $key, array $params = [], ?string $language = null): string
    {
        return app(self::class)->get($key, $params, $language);
    }

    public function get(string $key, array $params = [], ?string $language = null): string
    {
        foreach ($this->chain($language ?? $this->language) as $code) {
            $value = $this->published($code)[$key] ?? $this->bundled($code)[$key] ?? null;
            if ($value !== null) {
                return self::format($value, $params);
            }
        }

        throw new LogicException("Server label {$key} is not in the bundled catalog.");
    }

    /** The bundled content language (ar or en) a language reads content rows in. */
    public function contentLanguage(?string $language = null): string
    {
        foreach ($this->chain($language ?? $this->language) as $code) {
            if ($code === 'ar' || $code === 'en') {
                return $code;
            }
        }

        return $this->templateLocale();
    }

    /** A date as the app shows it: the language's month names, Latin digits. */
    public function date(Carbon $date, ?string $language = null): string
    {
        $code = $this->chain($language ?? $this->language)[0];

        return self::latinDigits($date->copy()->locale($code)->isoFormat('LL'));
    }

    /**
     * The language codes to try, in order: the caller's (when enabled), its
     * fallbacks, the default language and the template locale.
     *
     * @return list<string>
     */
    public function chain(?string $code): array
    {
        $languages = $this->languages();
        $chain = [];
        $code = self::normalize($code);
        // Without a readable catalog, any bundled language still counts.
        $usable = fn (string $c) => $languages !== [] ? isset($languages[$c]) : $this->bundled($c) !== [];
        while ($code !== null && $usable($code) && ! in_array($code, $chain, true)) {
            $chain[] = $code;
            $code = isset($languages[$code]) ? $languages[$code]->effectiveFallback() : null;
        }

        $default = collect($languages)->first(fn (AppLanguage $l) => $l->is_default)?->code;
        foreach ([$default, $this->templateLocale(), 'en'] as $tail) {
            if ($tail !== null && ! in_array($tail, $chain, true)) {
                $chain[] = $tail;
            }
        }

        return $chain;
    }

    /** "en-US", "EN_gb" and "en" are all "en"; anything else that is not a tag is null. */
    public static function normalize(?string $code): ?string
    {
        $code = strtolower(trim((string) $code));
        $code = preg_split('/[-_]/', $code)[0] ?? '';

        return preg_match('/^[a-z]{2,3}$/', $code) === 1 ? $code : null;
    }

    /** ICU placeholders "{name}" take the given values; nothing else in the text changes. */
    public static function format(string $value, array $params): string
    {
        if ($params === []) {
            return $value;
        }

        return preg_replace_callback(
            '/\{(\w+)\}/u',
            fn (array $m) => array_key_exists($m[1], $params) ? (string) $params[$m[1]] : $m[0],
            $value,
        ) ?? $value;
    }

    public static function latinDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    /**
     * Enabled languages. When the catalog cannot be read (no migrations yet, or
     * the database is down while an error is being rendered) the bundled ARB
     * files still answer, so server text never fails on its own.
     *
     * @return array<string, AppLanguage>
     */
    private function languages(): array
    {
        if ($this->languages === null) {
            try {
                $this->languages = AppLanguage::query()->enabled()->get()->keyBy('code')->all();
            } catch (Throwable $exception) {
                report($exception);
                $this->languages = [];
            }
        }

        return $this->languages;
    }

    /** @return array<string, string> */
    private function published(string $code): array
    {
        if (! array_key_exists($code, $this->published)) {
            $language = $this->languages()[$code] ?? null;
            try {
                $this->published[$code] = $language === null ? [] : (array) $this->publisher->payload($language)['strings'];
            } catch (Throwable $exception) {
                report($exception);
                $this->published[$code] = [];
            }
        }

        return $this->published[$code];
    }

    /** @return array<string, string> */
    private function bundled(string $code): array
    {
        if (! array_key_exists($code, $this->bundled)) {
            $path = rtrim((string) config('i18n.arb_path', resource_path('i18n')), '/\\').DIRECTORY_SEPARATOR."app_{$code}.arb";
            $messages = [];
            if (is_file($path)) {
                $messages = ArbFile::parse((string) file_get_contents($path))['messages'];
            }
            $this->bundled[$code] = $messages;
        }

        return $this->bundled[$code];
    }

    private function templateLocale(): string
    {
        return (string) config('i18n.template_locale', 'ar');
    }
}
