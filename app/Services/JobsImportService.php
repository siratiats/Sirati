<?php

namespace App\Services;

use App\Models\JobNews;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class JobsImportService
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_UPLOAD = 'excel_upload';
    public const SOURCE_SHEET = 'google_sheet';

    private const HEADER_ALIASES = [
        'language' => ['language', 'lang', 'اللغة'],
        'title' => ['title', 'job title', 'position', 'العنوان', 'الوظيفة', 'المسمى الوظيفي'],
        'company' => ['company', 'employer', 'الشركة', 'جهة العمل', 'صاحب العمل'],
        'location' => ['location', 'city', 'الموقع', 'المدينة', 'المكان'],
        'body' => ['body', 'description', 'details', 'الوصف', 'التفاصيل', 'الوصف الوظيفي'],
        'url' => ['url', 'link', 'الرابط'],
        'apply_url' => ['apply_url', 'apply url', 'apply', 'apply link', 'application url', 'رابط التقديم', 'تقديم'],
        'published_at' => ['published_at', 'published', 'publish date', 'تاريخ النشر', 'النشر'],
        'valid_from' => ['valid_from', 'valid from', 'start', 'start date', 'from', 'يبدأ', 'صالح من', 'تاريخ البدء'],
        'valid_until' => ['valid_until', 'valid until', 'expires', 'expires on', 'end', 'end date', 'until', 'deadline', 'ينتهي', 'صالح حتى', 'تاريخ الانتهاء', 'آخر موعد'],
        'sort_order' => ['sort_order', 'sort order', 'order', 'الترتيب'],
        'is_published' => ['is_published', 'published?', 'is published', 'منشور', 'نشر'],
        'source_row_key' => ['job_id', 'jobid', 'job id', 'id', 'key', 'row id', 'rowid', 'المعرف', 'رقم الوظيفة'],
    ];

    public function importUploadedFile(UploadedFile $file, string $source = self::SOURCE_UPLOAD): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: ''));
        if (! in_array($extension, ['csv', 'txt', 'xls', 'xlsx', 'ods'], true)) {
            throw new \InvalidArgumentException("Unsupported file extension: {$extension}");
        }

        $rows = $this->parseSpreadsheetFile($file->getRealPath(), $extension);
        return $this->applyRows($rows, $source);
    }

    public function importCsvString(string $csv, string $source = self::SOURCE_SHEET): array
    {
        $temp = tempnam(sys_get_temp_dir(), 'jobs_csv_');
        if ($temp === false) {
            throw new \RuntimeException('Could not allocate temp file for CSV.');
        }

        try {
            file_put_contents($temp, $csv);
            $rows = $this->parseSpreadsheetFile($temp, 'csv');
            return $this->applyRows($rows, $source);
        } finally {
            @unlink($temp);
        }
    }

    private function parseSpreadsheetFile(string $path, string $extension): array
    {
        $reader = match ($extension) {
            'csv', 'txt' => IOFactory::createReader('Csv'),
            'xlsx' => IOFactory::createReader('Xlsx'),
            'xls' => IOFactory::createReader('Xls'),
            'ods' => IOFactory::createReader('Ods'),
            default => IOFactory::createReaderForFile($path),
        };

        $reader->setReadDataOnly(true);

        if ($reader instanceof CsvReader) {
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(',');
            $reader->setEnclosure('"');
        }

        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getActiveSheet();
        $formatData = in_array($extension, ['csv', 'txt'], true);
        $data = $worksheet->toArray(null, true, $formatData, false);
        if (count($data) < 2) {
            return [];
        }

        $headers = array_map(fn ($value) => (string) ($value ?? ''), array_shift($data));
        $fromSpreadsheet = ! in_array($extension, ['csv', 'txt'], true);
        $rows = [];
        $rowNumber = 1;
        foreach ($data as $values) {
            $rowNumber++;
            if (! $this->rowHasValues($values)) {
                continue;
            }
            $rows[] = $this->buildRow($headers, $values, $rowNumber, $fromSpreadsheet);
        }
        return $rows;
    }

    private function rowHasValues(array $values): bool
    {
        foreach ($values as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return true;
            }
        }
        return false;
    }

    private function buildRow(array $headers, array $values, int $rowNumber, bool $fromSpreadsheet): array
    {
        $row = ['__row' => $rowNumber, '__from_spreadsheet' => $fromSpreadsheet];
        foreach ($headers as $i => $header) {
            $field = $this->resolveHeader((string) $header);
            if ($field === null) {
                continue;
            }
            $row[$field] = $values[$i] ?? null;
        }
        return $row;
    }

    private function resolveHeader(string $header): ?string
    {
        $normalized = $this->normalizeHeader($header);
        if ($normalized === '') {
            return null;
        }
        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if ($this->normalizeHeader($alias) === $normalized) {
                    return $field;
                }
            }
        }
        return null;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = mb_strtolower($header, 'UTF-8');
        $header = preg_replace('/[_\-\s]+/u', ' ', $header) ?? $header;
        return trim($header);
    }

    /**
     * @return array{created:int, updated:int, skipped:int, errors:array<int, array{row:int, message:string}>}
     */
    private function applyRows(array $rows, string $source): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        if ($rows === []) {
            return compact('created', 'updated', 'skipped', 'errors');
        }

        DB::transaction(function () use ($rows, $source, &$created, &$updated, &$skipped, &$errors): void {
            foreach ($rows as $row) {
                $rowNumber = (int) ($row['__row'] ?? 0);
                $fromSpreadsheet = (bool) ($row['__from_spreadsheet'] ?? false);

                try {
                    $attributes = $this->prepareAttributes($row, $source, $fromSpreadsheet);
                } catch (\InvalidArgumentException $e) {
                    $skipped++;
                    $errors[] = ['row' => $rowNumber, 'message' => $e->getMessage()];
                    continue;
                }

                $existing = JobNews::query()
                    ->where('source_row_key', $attributes['source_row_key'])
                    ->first();

                if ($existing) {
                    $existing->fill($attributes)->save();
                    $updated++;
                } else {
                    JobNews::create($attributes);
                    $created++;
                }
            }
        });

        return compact('created', 'updated', 'skipped', 'errors');
    }

    private function prepareAttributes(array $row, string $source, bool $fromSpreadsheet): array
    {
        $language = $this->normalizeLanguage($row['language'] ?? null);
        $title = $this->stringValue($row['title'] ?? null, 180);
        $body = $this->stringValue($row['body'] ?? null, 3000);

        if ($title === null) {
            throw new \InvalidArgumentException('Missing required field: title.');
        }
        if ($body === null) {
            throw new \InvalidArgumentException('Missing required field: body/description.');
        }

        $company = $this->stringValue($row['company'] ?? null, 160);
        $sourceRowKey = $this->stringValue($row['source_row_key'] ?? null, 191)
            ?? $this->fallbackRowKey($source, $language, $title, $company);

        return [
            'language' => $language,
            'title' => $title,
            'company' => $company,
            'location' => $this->stringValue($row['location'] ?? null, 160),
            'body' => $body,
            'url' => $this->urlValue($row['url'] ?? null, 255),
            'apply_url' => $this->urlValue($row['apply_url'] ?? null, 500),
            'published_at' => $this->dateValue($row['published_at'] ?? null, $fromSpreadsheet),
            'valid_from' => $this->dateValue($row['valid_from'] ?? null, $fromSpreadsheet),
            'valid_until' => $this->dateValue($row['valid_until'] ?? null, $fromSpreadsheet),
            'sort_order' => $this->intValue($row['sort_order'] ?? null, 0),
            'is_published' => $this->boolValue($row['is_published'] ?? null, true),
            'source' => $source,
            'source_row_key' => $sourceRowKey,
        ];
    }

    private function normalizeLanguage($value): string
    {
        $string = is_string($value) ? trim(mb_strtolower($value, 'UTF-8')) : '';
        return match ($string) {
            'en', 'english', 'إنجليزي', 'انجليزي' => 'en',
            default => 'ar',
        };
    }

    private function stringValue($value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }
        return mb_substr($string, 0, $max);
    }

    private function urlValue($value, int $max): ?string
    {
        $string = $this->stringValue($value, $max);
        if ($string === null) {
            return null;
        }
        if (! preg_match('#^https?://#i', $string)) {
            throw new \InvalidArgumentException("Invalid URL: {$string}");
        }
        return $string;
    }

    private function intValue($value, int $fallback): int
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return $fallback;
    }

    private function boolValue($value, bool $fallback): bool
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        if (is_bool($value)) {
            return $value;
        }
        $normalized = mb_strtolower(trim((string) $value), 'UTF-8');
        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'on', 'نعم', 'منشور' => true,
            '0', 'false', 'no', 'n', 'off', 'لا', 'غير منشور' => false,
            default => $fallback,
        };
    }

    private function dateValue($value, bool $fromSpreadsheet): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($fromSpreadsheet && is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            } catch (\Throwable) {
                // fall through
            }
        }

        $string = trim((string) $value);
        foreach (['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y', 'd/m/Y H:i', 'm/d/Y', \DateTimeInterface::ATOM] as $format) {
            $parsed = Carbon::createFromFormat($format, $string);
            if ($parsed instanceof Carbon) {
                return $parsed;
            }
        }

        try {
            return Carbon::parse($string);
        } catch (\Throwable) {
            throw new \InvalidArgumentException("Invalid date value: {$string}");
        }
    }

    private function fallbackRowKey(string $source, string $language, string $title, ?string $company): string
    {
        $digest = hash('sha1', implode('|', [
            $source,
            $language,
            Str::lower($title),
            Str::lower((string) $company),
        ]));
        return 'auto:' . substr($digest, 0, 24);
    }
}
