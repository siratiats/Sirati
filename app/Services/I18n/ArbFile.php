<?php

namespace App\Services\I18n;

use InvalidArgumentException;
use JsonException;

/** Reads and writes ARB (and CSV) translation files. */
final class ArbFile
{
    /**
     * @return array{locale: ?string, messages: array<string, string>, metadata: array<string, array<string, mixed>>}
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $contents): array
    {
        $contents = self::stripBom($contents);

        try {
            $decoded = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The ARB file is not valid JSON: '.$exception->getMessage());
        }

        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new InvalidArgumentException('The ARB file must be a JSON object.');
        }

        $locale = isset($decoded['@@locale']) && is_string($decoded['@@locale']) ? $decoded['@@locale'] : null;
        $messages = [];
        $metadata = [];

        foreach ($decoded as $key => $value) {
            $key = (string) $key;
            if (str_starts_with($key, '@@')) {
                continue;
            }

            if (str_starts_with($key, '@')) {
                if (is_array($value)) {
                    $metadata[substr($key, 1)] = $value;
                }

                continue;
            }

            if (is_string($value)) {
                $messages[$key] = $value;
            }
        }

        return ['locale' => $locale, 'messages' => $messages, 'metadata' => $metadata];
    }

    /**
     * Reads a CSV with a header row containing `key` and `value` columns (other columns are ignored).
     *
     * @return array<string, string>
     *
     * @throws InvalidArgumentException
     */
    public static function parseCsv(string $contents): array
    {
        $contents = self::stripBom($contents);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle, null, ',', '"', '');
        if (! is_array($header)) {
            fclose($handle);
            throw new InvalidArgumentException('The CSV file is empty.');
        }

        $columns = array_map(fn ($name) => strtolower(trim((string) $name)), $header);
        $keyIndex = array_search('key', $columns, true);
        $valueIndex = array_search('value', $columns, true);
        if ($keyIndex === false || $valueIndex === false) {
            fclose($handle);
            throw new InvalidArgumentException('The CSV header must contain "key" and "value" columns.');
        }

        $values = [];
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if ($row === [null]) {
                continue;
            }
            $key = trim((string) ($row[$keyIndex] ?? ''));
            if ($key === '') {
                continue;
            }
            $values[$key] = (string) ($row[$valueIndex] ?? '');
        }
        fclose($handle);

        return $values;
    }

    /**
     * @param  list<array{key: string, value: string, description?: ?string, placeholders?: array<string, mixed>}>  $rows
     */
    public static function toArb(string $locale, array $rows): string
    {
        $out = ['@@locale' => $locale];
        foreach ($rows as $row) {
            $out[$row['key']] = $row['value'];
            $meta = [];
            if (! empty($row['description'])) {
                $meta['description'] = $row['description'];
            }
            if (! empty($row['placeholders'])) {
                $meta['placeholders'] = $row['placeholders'];
            }
            if ($meta !== []) {
                $out['@'.$row['key']] = $meta;
            }
        }

        return json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @param  list<array{key: string, value: string, description?: ?string}>  $rows
     */
    public static function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['key', 'value', 'description'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, [$row['key'], $row['value'], (string) ($row['description'] ?? '')], ',', '"', '');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private static function stripBom(string $contents): string
    {
        return str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents;
    }
}
