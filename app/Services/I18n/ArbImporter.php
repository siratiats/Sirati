<?php

namespace App\Services\I18n;

use App\Models\AppLanguage;
use App\Models\AppTranslation;
use App\Models\AppTranslationKey;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Imports the bundled ARB files (catalog + published base values) and admin uploads (drafts only).
 */
final class ArbImporter
{
    private const KNOWN_LANGUAGES = [
        'ar' => ['name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'fallback_code' => 'en', 'sort_order' => 0],
        'en' => ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'fallback_code' => null, 'sort_order' => 1],
    ];

    public function __construct(
        private readonly IcuMessageParser $parser,
        private readonly TranslationValidator $validator,
        private readonly TranslationPublisher $publisher,
    ) {
    }

    public static function make(): self
    {
        $parser = IcuMessageParser::fromConfig();
        $validator = new TranslationValidator($parser);

        return new self($parser, $validator, new TranslationPublisher($validator));
    }

    /**
     * Reads every app_*.arb in $path. The template ARB defines the key catalog (always synced from the
     * bundle); values are published only where the language has none yet, or where the current value
     * also came from the bundle (updated_by null). Admin-published values and drafts are left alone
     * unless $force.
     *
     * @return array{keys_created: int, keys_updated: int, languages_created: list<string>, languages: array<string, array{inserted: int, updated: int, kept: int, invalid: array<string, list<string>>, version: ?int}>}
     *
     * @throws InvalidArgumentException
     */
    public function importDirectory(string $path, bool $force = false): array
    {
        $templateLocale = (string) config('i18n.template_locale', 'ar');
        $files = glob(rtrim($path, '/\\').DIRECTORY_SEPARATOR.'app_*.arb') ?: [];
        sort($files);

        $parsed = [];
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                throw new InvalidArgumentException("Cannot read {$file}.");
            }
            $arb = ArbFile::parse($contents);
            $locale = $arb['locale'] ?? substr(basename($file, '.arb'), 4);
            $parsed[$locale] = $arb;
        }

        if (! isset($parsed[$templateLocale])) {
            throw new InvalidArgumentException("The template app_{$templateLocale}.arb was not found in {$path}.");
        }

        return DB::transaction(function () use ($parsed, $templateLocale, $force) {
            [$created, $updated] = $this->syncCatalog($parsed[$templateLocale]);
            $catalog = AppTranslationKey::query()->get()->keyBy('key');

            $report = ['keys_created' => $created, 'keys_updated' => $updated, 'languages_created' => [], 'languages' => []];

            // Template first so it becomes the default language when none exists.
            $locales = array_keys($parsed);
            usort($locales, fn ($a, $b) => [$a !== $templateLocale, $a] <=> [$b !== $templateLocale, $b]);

            foreach ($locales as $locale) {
                [$language, $wasCreated] = $this->ensureLanguage($locale);
                if ($wasCreated) {
                    $report['languages_created'][] = $locale;
                }
                $report['languages'][$locale] = $this->importPublishedValues($language, $parsed[$locale]['messages'], $catalog, $force);
            }

            return $report;
        });
    }

    /**
     * Stores uploaded values as drafts. Unknown keys and invalid values are reported, never saved; a
     * value equal to the published one clears the draft; blank values are ignored.
     *
     * @param  array<string, string>  $values
     * @return array{drafted: int, unchanged: int, unknown: list<string>, invalid: array<string, list<string>>}
     */
    public function importDrafts(AppLanguage $language, array $values, ?int $userId): array
    {
        $catalog = AppTranslationKey::query()->whereIn('key', array_map('strval', array_keys($values)))->get()->keyBy('key');
        $report = ['drafted' => 0, 'unchanged' => 0, 'unknown' => [], 'invalid' => []];

        DB::transaction(function () use ($language, $values, $catalog, $userId, &$report) {
            $rows = AppTranslation::query()->where('language_code', $language->code)->get()->keyBy('key');

            foreach ($values as $key => $value) {
                $key = (string) $key;
                $catalogKey = $catalog->get($key);
                if ($catalogKey === null) {
                    $report['unknown'][] = $key;

                    continue;
                }
                if (TranslationValidator::isBlank($value)) {
                    continue;
                }
                $errors = $this->validator->validateForKey($catalogKey, $value);
                if ($errors !== []) {
                    $report['invalid'][$key] = $errors;

                    continue;
                }

                $row = $rows->get($key);
                if ($row !== null && $row->value === $value) {
                    if ($row->draft_value !== null) {
                        $row->forceFill(['draft_value' => null])->save();
                    }
                    $report['unchanged']++;

                    continue;
                }

                if ($row === null) {
                    AppTranslation::query()->create([
                        'language_code' => $language->code,
                        'key' => $key,
                        'draft_value' => $value,
                    ]);
                } else {
                    $row->forceFill(['draft_value' => $value])->save();
                }
                $report['drafted']++;
            }
        });

        return $report;
    }

    /**
     * @param  array{messages: array<string, string>, metadata: array<string, array<string, mixed>>}  $template
     * @return array{0: int, 1: int}
     */
    private function syncCatalog(array $template): array
    {
        $existing = AppTranslationKey::query()->get()->keyBy('key');
        $created = 0;
        $updated = 0;

        foreach ($template['messages'] as $key => $message) {
            $meta = $template['metadata'][$key] ?? [];
            $placeholders = is_array($meta['placeholders'] ?? null) ? $meta['placeholders'] : [];
            $isIcu = false;

            try {
                $parsed = $this->parser->parse($message);
                $isIcu = $parsed['is_icu'];
                // Undeclared arguments in the template still belong to the key's placeholder set.
                foreach ($parsed['arguments'] as $name => $kinds) {
                    if (! array_key_exists($name, $placeholders)) {
                        $placeholders[$name] = ['type' => in_array('plural', $kinds, true) ? 'num' : 'Object'];
                    }
                }
            } catch (IcuSyntaxException) {
                // Flutter would not compile it either; keep declared metadata only.
            }

            $placeholders = array_map(fn ($value) => is_array($value) ? $value : [], $placeholders);

            $attributes = [
                'description' => is_string($meta['description'] ?? null) ? $meta['description'] : null,
                'placeholders' => $placeholders,
                'is_icu' => $isIcu,
                'group' => AppTranslationKey::groupFor($key),
            ];

            $row = $existing->get($key);
            if ($row === null) {
                AppTranslationKey::query()->create(['key' => $key] + $attributes);
                $created++;

                continue;
            }

            $row->fill($attributes);
            if ($row->isDirty()) {
                $row->save();
                $updated++;
            }
        }

        return [$created, $updated];
    }

    /** @return array{0: AppLanguage, 1: bool} */
    private function ensureLanguage(string $code): array
    {
        $language = AppLanguage::query()->where('code', $code)->first();
        if ($language !== null) {
            return [$language, false];
        }

        $known = self::KNOWN_LANGUAGES[$code] ?? null;
        $hasDefault = AppLanguage::query()->where('is_default', true)->exists();

        $language = AppLanguage::query()->create([
            'code' => $code,
            'name' => $known['name'] ?? $code,
            'native_name' => $known['native_name'] ?? $code,
            'direction' => $known['direction'] ?? 'ltr',
            'fallback_code' => $known === null ? 'en' : $known['fallback_code'],
            // Languages other than the built-in two start disabled until an admin reviews them.
            'is_enabled' => $known !== null,
            'is_default' => $known !== null && ! $hasDefault && $code === config('i18n.template_locale', 'ar'),
            'sort_order' => $known['sort_order'] ?? 100,
            'version' => 0,
        ]);

        return [$language, true];
    }

    /**
     * @param  array<string, string>  $messages
     * @return array{inserted: int, updated: int, kept: int, invalid: array<string, list<string>>, version: ?int}
     */
    private function importPublishedValues(AppLanguage $language, array $messages, $catalog, bool $force): array
    {
        $rows = AppTranslation::query()->where('language_code', $language->code)->get()->keyBy('key');
        $result = ['inserted' => 0, 'updated' => 0, 'kept' => 0, 'invalid' => [], 'version' => null];

        foreach ($messages as $key => $value) {
            $catalogKey = $catalog->get($key);
            if ($catalogKey === null || TranslationValidator::isBlank($value)) {
                continue;
            }

            $errors = $this->validator->validateForKey($catalogKey, $value);
            if ($errors !== []) {
                $result['invalid'][$key] = $errors;

                continue;
            }

            $row = $rows->get($key);
            if ($row === null) {
                AppTranslation::query()->create(['language_code' => $language->code, 'key' => $key, 'value' => $value]);
                $result['inserted']++;

                continue;
            }

            $adminOwned = $row->value !== null && $row->updated_by !== null;
            if ($force) {
                if ($row->value !== $value || $row->draft_value !== null || $row->updated_by !== null) {
                    $row->forceFill(['value' => $value, 'draft_value' => null, 'updated_by' => null])->save();
                    $result['updated']++;
                }

                continue;
            }

            if ($adminOwned) {
                $result['kept']++;

                continue;
            }

            if ($row->value !== $value) {
                $wasMissing = $row->value === null;
                // Drafts are never touched without --force.
                $row->forceFill(['value' => $value, 'updated_by' => null])->save();
                $wasMissing ? $result['inserted']++ : $result['updated']++;
            }
        }

        if ($result['inserted'] > 0 || $result['updated'] > 0) {
            $result['version'] = $this->publisher->bumpVersion($language, null);
        }

        return $result;
    }
}
