<?php

namespace App\Support;

/**
 * Pure geometry for the admin's inline SVG charts (no chart library).
 */
final class AdminCharts
{
    /**
     * @param  list<int|float>  $values
     * @return array{line: string, area: string}|null null when there is nothing to draw
     */
    public static function sparkline(array $values, float $width = 100, float $height = 26, float $pad = 2): ?array
    {
        if (count($values) < 2) {
            return null;
        }

        $points = self::points($values, $width, $height, $pad, max($values));
        $line = self::path($points);

        return [
            'line' => $line,
            'area' => $line.sprintf(' L%s,%s L%s,%s Z', self::n($width), self::n($height), self::n(0), self::n($height)),
        ];
    }

    /**
     * Multi-series line chart geometry inside a plot box.
     *
     * @param  array<string, list<int>>  $series
     * @return array{max: int, ticks: list<array{y: float, label: string}>, lines: array<string, string>, areas: array<string, string>, xs: list<float>, plot: array{left: float, top: float, right: float, bottom: float}}
     */
    public static function lineChart(array $series, float $width, float $height, float $left = 40, float $top = 10, float $right = 10, float $bottom = 28): array
    {
        $count = max(array_map('count', $series ?: [[]]));
        $peak = 0;
        foreach ($series as $values) {
            $peak = max($peak, $values === [] ? 0 : max($values));
        }
        $max = self::niceMax($peak);
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;

        $xs = [];
        for ($i = 0; $i < $count; $i++) {
            $xs[] = $left + ($count > 1 ? $plotWidth * $i / ($count - 1) : $plotWidth / 2);
        }

        $lines = [];
        $areas = [];
        foreach ($series as $name => $values) {
            if ($values === []) {
                continue;
            }
            $points = [];
            foreach ($values as $i => $value) {
                $points[] = [$xs[$i], $top + $plotHeight - ($max > 0 ? $value / $max * $plotHeight : 0)];
            }
            $lines[$name] = self::path($points);
            $last = end($points);
            $areas[$name] = $lines[$name].sprintf(' L%s,%s L%s,%s Z', self::n($last[0]), self::n($top + $plotHeight), self::n($points[0][0]), self::n($top + $plotHeight));
        }

        $ticks = [];
        for ($i = 0; $i <= 4; $i++) {
            $value = $max * $i / 4;
            $ticks[] = ['y' => $top + $plotHeight - ($plotHeight * $i / 4), 'label' => self::compact($value)];
        }

        return [
            'max' => $max,
            'ticks' => $ticks,
            'lines' => $lines,
            'areas' => $areas,
            'xs' => $xs,
            'plot' => ['left' => $left, 'top' => $top, 'right' => $width - $right, 'bottom' => $top + $plotHeight],
        ];
    }

    public static function niceMax(int|float $value): int
    {
        if ($value <= 4) {
            return 4;
        }

        $magnitude = 10 ** floor(log10($value));
        foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $step) {
            if ($step * $magnitude >= $value) {
                return (int) ceil($step * $magnitude);
            }
        }

        return (int) ceil($value);
    }

    public static function compact(int|float|null $value): string
    {
        if ($value === null) {
            return '–';
        }
        if (abs($value) >= 1_000_000) {
            return rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.').'M';
        }
        if (abs($value) >= 10_000) {
            return rtrim(rtrim(number_format($value / 1_000, 1), '0'), '.').'k';
        }

        return number_format($value, fmod((float) $value, 1.0) === 0.0 ? 0 : 1);
    }

    public static function duration(?int $milliseconds): string
    {
        if ($milliseconds === null) {
            return '–';
        }

        return $milliseconds >= 1000 ? number_format($milliseconds / 1000, 2).'s' : $milliseconds.'ms';
    }

    public static function age(?int $seconds): string
    {
        if ($seconds === null) {
            return '–';
        }
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m';
        }
        if ($seconds < 86400) {
            return intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m';
        }

        return intdiv($seconds, 86400).'d';
    }

    /**
     * @param  list<int|float>  $values
     * @return list<array{0: float, 1: float}>
     */
    private static function points(array $values, float $width, float $height, float $pad, int|float $max): array
    {
        $count = count($values);
        $points = [];
        foreach (array_values($values) as $i => $value) {
            $x = $width * $i / ($count - 1);
            $y = $max > 0 ? $height - $pad - ($value / $max) * ($height - 2 * $pad) : $height - $pad;
            $points[] = [$x, $y];
        }

        return $points;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function path(array $points): string
    {
        $parts = [];
        foreach ($points as $i => [$x, $y]) {
            $parts[] = ($i === 0 ? 'M' : 'L').self::n($x).','.self::n($y);
        }

        return implode(' ', $parts);
    }

    private static function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
