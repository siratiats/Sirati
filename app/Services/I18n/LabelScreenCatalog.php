<?php

namespace App\Services\I18n;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Where each label appears in the app: app screenshots plus label boxes, produced by the Flutter
 * capture tool into `manifest.json` (+ `<id>.<locale>.png`) under config('i18n.screens_path').
 *
 * A missing or invalid manifest gives an empty catalog; the Labels page keeps working without it.
 * Individual invalid screens, locales or label boxes are dropped (and logged), the rest is kept.
 */
class LabelScreenCatalog
{
    public const MANIFEST = 'manifest.json';

    public const SUPPORTED_VERSION = 1;

    /** Screen ids double as URL segments and must never collide with the `__none` filter value. */
    public const SCREEN_ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/';

    public const LOCALE_PATTERN = '/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/';

    /** Image names are plain file names inside the screens folder: no separators, no `..`. */
    private const IMAGE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}\.png$/';

    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /** @var array{meta: array<string, mixed>, screens: array<string, array<string, mixed>>}|null */
    private ?array $data = null;

    private ?string $loadedFor = null;

    public function __construct(private readonly ?string $path = null) {}

    public function path(): string
    {
        return rtrim($this->path ?? (string) config('i18n.screens_path', resource_path('i18n/screens')), '\\/');
    }

    public function isEmpty(): bool
    {
        return $this->screens() === [];
    }

    /** @return array{generated_at: ?string, app_commit: ?string, device: array<string, mixed>} */
    public function meta(): array
    {
        return $this->load()['meta'];
    }

    /**
     * Screens in display order, keyed by id.
     *
     * @return array<string, array{id: string, name: string, order: int, locales: array<string, array{image: string, width: float, height: float, labels: list<array{key: string, x: float, y: float, w: float, h: float}>}>}>
     */
    public function screens(): array
    {
        return $this->load()['screens'];
    }

    /** @return array{id: string, name: string, order: int, locales: array<string, array<string, mixed>>}|null */
    public function screen(string $id): ?array
    {
        return $this->screens()[$id] ?? null;
    }

    /** @return list<string> */
    public function screenIds(): array
    {
        return array_keys($this->screens());
    }

    /** @return list<string> */
    public function localesFor(string $id): array
    {
        return array_keys($this->screen($id)['locales'] ?? []);
    }

    /**
     * Distinct keys on a screen across all captured locales, in first-seen order.
     *
     * @return list<string>
     */
    public function keysOnScreen(string $id): array
    {
        $keys = [];
        foreach ($this->screen($id)['locales'] ?? [] as $capture) {
            foreach ($capture['labels'] as $label) {
                $keys[$label['key']] = true;
            }
        }

        return array_keys($keys);
    }

    /** @return list<string> Every key that appears on any captured screen. */
    public function allKeys(): array
    {
        $keys = [];
        foreach ($this->screenIds() as $id) {
            foreach ($this->keysOnScreen($id) as $key) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    /** @return list<string> Ids of the screens showing the key, in screen order. */
    public function screensForKey(string $key): array
    {
        return array_values(array_filter(
            $this->screenIds(),
            fn (string $id) => in_array($key, $this->keysOnScreen($id), true),
        ));
    }

    /**
     * Picks the capture to show for a screen: the first candidate locale that was captured (exact code,
     * then its base language), else the first captured locale. Null when the screen is unknown.
     *
     * @param  list<?string>  $candidates  e.g. [target, target fallback, 'en']
     */
    public function resolveLocale(string $id, array $candidates): ?string
    {
        $available = $this->localesFor($id);
        if ($available === []) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            if (in_array($candidate, $available, true)) {
                return $candidate;
            }
            $baseLanguage = strtolower((string) preg_split('/[_-]/', $candidate)[0]);
            if (in_array($baseLanguage, $available, true)) {
                return $baseLanguage;
            }
        }

        return $available[0];
    }

    /** @return array{image: string, width: float, height: float, labels: list<array{key: string, x: float, y: float, w: float, h: float}>}|null */
    public function capture(string $id, string $locale): ?array
    {
        return $this->screen($id)['locales'][$locale] ?? null;
    }

    /** @return list<array{key: string, x: float, y: float, w: float, h: float}> */
    public function labelsFor(string $id, string $locale): array
    {
        return $this->capture($id, $locale)['labels'] ?? [];
    }

    /**
     * Label boxes as percentages of the image, clamped to the image, so they scale with it.
     *
     * @return list<array{key: string, left: float, top: float, width: float, height: float}>
     */
    public function boxesFor(string $id, string $locale): array
    {
        $capture = $this->capture($id, $locale);
        if ($capture === null) {
            return [];
        }

        $boxes = [];
        foreach ($capture['labels'] as $label) {
            $left = self::clampPercent($label['x'] / $capture['width'] * 100);
            $top = self::clampPercent($label['y'] / $capture['height'] * 100);
            $right = self::clampPercent(($label['x'] + $label['w']) / $capture['width'] * 100);
            $bottom = self::clampPercent(($label['y'] + $label['h']) / $capture['height'] * 100);
            if ($right <= $left || $bottom <= $top) {
                continue;
            }
            $boxes[] = ['key' => $label['key'], 'left' => $left, 'top' => $top, 'width' => $right - $left, 'height' => $bottom - $top];
        }

        return $boxes;
    }

    /**
     * Absolute path of a screenshot, only for a screen/locale listed in the manifest whose file is a
     * real PNG inside the screens folder. Null otherwise (callers answer 404).
     */
    public function imagePath(string $id, string $locale): ?string
    {
        $capture = $this->capture($id, $locale);
        if ($capture === null) {
            return null;
        }

        $root = realpath($this->path());
        $file = realpath($this->path().DIRECTORY_SEPARATOR.$capture['image']);
        if ($root === false || $file === false || ! is_file($file) || dirname($file) !== $root) {
            return null;
        }

        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return null;
        }
        $signature = fread($handle, 8);
        fclose($handle);

        return $signature === self::PNG_SIGNATURE ? $file : null;
    }

    /** @return array{meta: array<string, mixed>, screens: array<string, array<string, mixed>>} */
    private function load(): array
    {
        $file = $this->path().DIRECTORY_SEPARATOR.self::MANIFEST;
        clearstatcache(true, $file);
        $mtime = is_file($file) ? @filemtime($file) : false;
        $size = $mtime === false ? false : @filesize($file);
        $signature = $mtime === false ? 'missing:'.$file : $file.'|'.$mtime.'|'.$size;

        if ($this->data !== null && $this->loadedFor === $signature) {
            return $this->data;
        }

        $this->loadedFor = $signature;
        if ($mtime === false) {
            return $this->data = self::emptyData();
        }

        return $this->data = Cache::remember(
            'i18n.screens.manifest.'.sha1($signature),
            (int) config('i18n.cache_ttl_seconds', 86400),
            fn () => $this->parse((string) @file_get_contents($file), $file),
        );
    }

    /** @return array{meta: array<string, mixed>, screens: array<string, array<string, mixed>>} */
    private function parse(string $json, string $file): array
    {
        try {
            $manifest = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::warning('Label screens manifest is not valid JSON; ignoring it.', ['file' => $file, 'error' => $exception->getMessage()]);

            return self::emptyData();
        }

        if (! is_array($manifest) || ($manifest['version'] ?? null) !== self::SUPPORTED_VERSION || ! is_array($manifest['screens'] ?? null) || ! array_is_list($manifest['screens'])) {
            Log::warning('Label screens manifest has an unsupported shape; ignoring it.', ['file' => $file]);

            return self::emptyData();
        }

        $screens = [];
        $dropped = 0;
        foreach ($manifest['screens'] as $index => $raw) {
            $screen = is_array($raw) ? $this->parseScreen($raw, $index, $dropped) : null;
            if ($screen === null || isset($screens[$screen['id']])) {
                $dropped++;

                continue;
            }
            $screens[$screen['id']] = $screen;
        }

        if ($dropped > 0) {
            Log::warning('Label screens manifest: dropped invalid entries.', ['file' => $file, 'dropped' => $dropped]);
        }

        uasort($screens, fn (array $a, array $b) => [$a['order'], $a['name'], $a['id']] <=> [$b['order'], $b['name'], $b['id']]);

        $device = is_array($manifest['device'] ?? null) ? $manifest['device'] : [];

        return [
            'meta' => [
                'generated_at' => is_string($manifest['generated_at'] ?? null) ? $manifest['generated_at'] : null,
                'app_commit' => is_string($manifest['app_commit'] ?? null) ? $manifest['app_commit'] : null,
                'device' => array_filter($device, fn ($value) => is_int($value) || is_float($value)),
            ],
            'screens' => $screens,
        ];
    }

    /** @return array<string, mixed>|null */
    private function parseScreen(array $raw, int $index, int &$dropped): ?array
    {
        $id = $raw['id'] ?? null;
        if (! is_string($id) || preg_match(self::SCREEN_ID_PATTERN, $id) !== 1 || ! is_array($raw['locales'] ?? null)) {
            return null;
        }

        $locales = [];
        foreach ($raw['locales'] as $locale => $capture) {
            $parsed = is_string($locale) && preg_match(self::LOCALE_PATTERN, $locale) === 1 && is_array($capture)
                ? $this->parseCapture($capture, $dropped)
                : null;
            if ($parsed === null) {
                $dropped++;

                continue;
            }
            $locales[$locale] = $parsed;
        }

        if ($locales === []) {
            return null;
        }

        $name = is_string($raw['name'] ?? null) && trim($raw['name']) !== '' ? trim($raw['name']) : $id;

        return [
            'id' => $id,
            'name' => mb_substr($name, 0, 120),
            'order' => is_int($raw['order'] ?? null) ? $raw['order'] : $index + 1,
            'locales' => $locales,
        ];
    }

    /** @return array<string, mixed>|null */
    private function parseCapture(array $capture, int &$dropped): ?array
    {
        $image = $capture['image'] ?? null;
        $width = $capture['width'] ?? null;
        $height = $capture['height'] ?? null;
        if (! is_string($image) || preg_match(self::IMAGE_PATTERN, $image) !== 1 || str_contains($image, '..')
            || ! self::isPositive($width) || ! self::isPositive($height) || ! is_array($capture['labels'] ?? null)) {
            return null;
        }

        $labels = [];
        foreach ($capture['labels'] as $label) {
            $valid = is_array($label)
                && is_string($label['key'] ?? null) && $label['key'] !== '' && strlen($label['key']) <= 191
                && self::isNumber($label['x'] ?? null) && self::isNumber($label['y'] ?? null)
                && self::isPositive($label['w'] ?? null) && self::isPositive($label['h'] ?? null);
            if (! $valid) {
                $dropped++;

                continue;
            }
            $labels[] = ['key' => $label['key'], 'x' => (float) $label['x'], 'y' => (float) $label['y'], 'w' => (float) $label['w'], 'h' => (float) $label['h']];
        }

        return ['image' => $image, 'width' => (float) $width, 'height' => (float) $height, 'labels' => $labels];
    }

    private static function isNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    private static function isPositive(mixed $value): bool
    {
        return self::isNumber($value) && $value > 0;
    }

    private static function clampPercent(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }

    /** @return array{meta: array<string, mixed>, screens: array<string, array<string, mixed>>} */
    private static function emptyData(): array
    {
        return ['meta' => ['generated_at' => null, 'app_commit' => null, 'device' => []], 'screens' => []];
    }
}
