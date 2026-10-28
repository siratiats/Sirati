<?php

namespace App\Services\I18n;

use App\Models\AppTranslation;
use InvalidArgumentException;

/**
 * Paints the CURRENT text of each label over an app screenshot, so the phone preview on the Labels page
 * shows edits instead of the text the screenshot was captured with.
 *
 * Current value = draft, else published value, else the bundled ARB value (first one that is not blank).
 * A label gets a patch when its manifest entry describes the painted paragraph (text, value, style with a
 * font size, bg) and its current value, formatted with the capture's `args`, differs from the captured value.
 * Messages that cannot be formatted by plain substitution (plural/select, a placeholder without an arg,
 * invalid syntax) get a note instead of a patch when they were edited.
 *
 * The same formatting rule runs in the page's JS for live typing; both use the IcuMessageParser grammar.
 */
class LabelScreenPreview
{
    public const NOTE = 'Edited — preview not available for this message';

    /** Fallback line height (in multiples of the font size) when the capture did not record one. */
    private const DEFAULT_LINE_HEIGHT = 1.4;

    /** @var array<string, array<string, string>> bundled ARB messages per locale, for this request */
    private array $bundled = [];

    public function __construct(private readonly IcuMessageParser $parser) {}

    public static function make(): self
    {
        return new self(IcuMessageParser::fromConfig());
    }

    /**
     * Current raw message per key for one language: draft, else published, else bundled.
     *
     * @param  list<string>  $keys
     * @return array<string, ?string>
     */
    public function currentValues(string $locale, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $rows = AppTranslation::query()
            ->where('language_code', $locale)
            ->whereIn('key', $keys)
            ->get(['key', 'value', 'draft_value'])
            ->keyBy('key');
        $bundled = $this->bundledMessages($locale);

        $current = [];
        foreach ($keys as $key) {
            $row = $rows->get($key);
            $current[$key] = null;
            foreach ([$row?->draft_value, $row?->value, $bundled[$key] ?? null] as $candidate) {
                if (! TranslationValidator::isBlank($candidate)) {
                    $current[$key] = $candidate;
                    break;
                }
            }
        }

        return $current;
    }

    /**
     * Bundled ARB messages of a locale (config('i18n.arb_path')/app_<locale>.arb); empty when absent or invalid.
     *
     * @return array<string, string>
     */
    public function bundledMessages(string $locale): array
    {
        if (isset($this->bundled[$locale])) {
            return $this->bundled[$locale];
        }
        if (preg_match(LabelScreenCatalog::LOCALE_PATTERN, $locale) !== 1) {
            return $this->bundled[$locale] = [];
        }

        $file = rtrim((string) config('i18n.arb_path', resource_path('i18n')), '\\/').DIRECTORY_SEPARATOR.'app_'.$locale.'.arb';
        if (! is_file($file)) {
            return $this->bundled[$locale] = [];
        }

        try {
            return $this->bundled[$locale] = ArbFile::parse((string) file_get_contents($file))['messages'];
        } catch (InvalidArgumentException) {
            return $this->bundled[$locale] = [];
        }
    }

    /**
     * Formats a message by substituting the capture's args; null when it cannot be (plural/select,
     * missing arg, invalid syntax).
     *
     * @param  array<string, string>  $args
     */
    public function format(string $message, array $args): ?string
    {
        return $this->parser->formatSimple($message, $args);
    }

    /**
     * One entry per label box of the capture whose key passes `$include`, in manifest order.
     *
     * @param  array{width: float, height: float, labels: list<array<string, mixed>>}  $capture
     * @param  array<string, ?string>  $current  key => current raw message
     * @param  array<string, string>  $bundled  key => bundled raw message of the capture's locale
     * @param  string  $direction  the capture language's direction, used when the style has none
     * @return list<array<string, mixed>>
     */
    public function overlays(array $capture, array $current, array $bundled, string $direction, ?callable $include = null): array
    {
        $out = [];
        foreach ($capture['labels'] as $index => $label) {
            if ($include !== null && ! $include($label['key'])) {
                continue;
            }
            $out[] = $this->overlay($label, $index, $capture, $current[$label['key']] ?? null, $bundled[$label['key']] ?? null, $direction);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function overlay(array $label, int $index, array $capture, ?string $current, ?string $bundled, string $direction): array
    {
        $style = $label['style'] ?? [];
        $previewable = isset($label['text'], $label['value'], $label['bg'], $style['font_size']);
        $args = $label['args'] ?? [];
        $formatted = $current === null ? null : $this->format($current, $args);

        // Edited = what the app would paint now differs from what was captured.
        $edited = match (true) {
            $current === null => false,
            $formatted !== null && isset($label['value']) => $formatted !== $label['value'],
            $bundled !== null => $current !== $bundled,
            default => false,
        };

        $mode = null;
        $patchText = null;
        if ($edited && $previewable) {
            if ($formatted !== null) {
                $mode = 'patch';
                $patchText = self::replaceFirst($label['text'], $label['value'], $formatted);
            } else {
                $mode = 'note';
            }
        }

        $left = self::clampPercent($label['x'] / $capture['width'] * 100);
        $top = self::clampPercent($label['y'] / $capture['height'] * 100);
        $width = max(0.0, self::clampPercent(($label['x'] + $label['w']) / $capture['width'] * 100) - $left);
        $boxCss = 'left: '.self::num($left).'%; top: '.self::num($top).'%; width: '.self::num($width).'%; --h: '.self::num($label['h']).';';

        $css = null;
        $dir = $style['direction'] ?? $direction;
        if ($previewable) {
            $fontSize = $style['font_size'];
            $lineHeight = $style['line_height'] ?? $fontSize * self::DEFAULT_LINE_HEIGHT;
            $css = $boxCss
                .' --fs: '.self::num($fontSize).'; --lh: '.self::num($lineHeight).';'
                .' --max-lines: '.(isset($style['max_lines']) ? $style['max_lines'] : 'none').';'
                .' background: '.$label['bg'].';'
                .' color: '.self::rgba($style['color'] ?? '#000000', $style['opacity'] ?? 1.0).';'
                .' font-family: '.implode(', ', array_map(fn (string $family) => "'{$family}'", array_unique(array_filter([$style['font_family'] ?? null, 'IBM Plex Sans Arabic', 'IBM Plex Sans'])))).', sans-serif;'
                .' font-weight: '.($style['font_weight'] ?? 400).';'
                .' text-align: '.($style['align'] ?? 'start').';';
        }

        return [
            'key' => $label['key'],
            'index' => $index,
            'previewable' => $previewable,
            'edited' => $edited,
            'mode' => $mode,
            'text' => $patchText,
            'css' => $css,
            'boxCss' => $boxCss,
            'dir' => $dir,
            'lines' => $style['lines'] ?? 1,
            // What the page's JS needs to repaint this label while the admin types.
            'live' => $previewable ? [
                'key' => $label['key'],
                'index' => $index,
                'text' => $label['text'],
                'value' => $label['value'],
                'args' => (object) $args,
                'bundled' => $bundled,
                'css' => $css,
                'boxCss' => $boxCss,
                'dir' => $dir,
                'lines' => $style['lines'] ?? 1,
            ] : [
                'key' => $label['key'],
                'index' => $index,
                'value' => $label['value'] ?? null,
                'args' => (object) $args,
                'bundled' => $bundled,
            ],
        ];
    }

    private static function replaceFirst(string $haystack, string $needle, string $replacement): string
    {
        $position = strpos($haystack, $needle);

        return $position === false ? $replacement : substr_replace($haystack, $replacement, $position, strlen($needle));
    }

    private static function rgba(string $hex, float $opacity): string
    {
        [$r, $g, $b] = array_map('hexdec', str_split(substr($hex, 1), 2));

        return "rgb({$r} {$g} {$b} / ".self::num($opacity).')';
    }

    private static function clampPercent(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }

    private static function num(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }
}
