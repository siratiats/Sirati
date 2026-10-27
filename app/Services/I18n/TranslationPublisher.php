<?php

namespace App\Services\I18n;

use App\Models\AppLanguage;
use App\Models\AppTranslation;
use App\Models\AppTranslationKey;
use App\Models\AppTranslationRevision;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Every change to published strings goes through here: it bumps the language version, writes a
 * revision snapshot and builds/caches the public payload for exactly that version.
 */
final class TranslationPublisher
{
    public function __construct(private readonly TranslationValidator $validator)
    {
    }

    /**
     * Copies every draft of the language to its published value, clears the drafts and bumps the
     * version. Drafts are re-validated against the current catalog; any invalid draft aborts the publish.
     *
     * @return array{published: int, version: int}
     *
     * @throws InvalidDraftsException|RuntimeException
     */
    public function publish(AppLanguage $language, ?int $userId): array
    {
        return DB::transaction(function () use ($language, $userId) {
            $language = AppLanguage::query()->whereKey($language->getKey())->lockForUpdate()->firstOrFail();

            $drafts = AppTranslation::query()
                ->where('language_code', $language->code)
                ->whereNotNull('draft_value')
                ->get();

            if ($drafts->isEmpty()) {
                throw new RuntimeException('There are no draft changes to publish.');
            }

            $catalog = AppTranslationKey::query()->whereIn('key', $drafts->pluck('key'))->get()->keyBy('key');
            $invalid = [];
            foreach ($drafts as $draft) {
                $key = $catalog->get($draft->key);
                if ($key === null) {
                    $invalid[$draft->key] = ['This key is no longer in the catalog.'];

                    continue;
                }
                $errors = $this->validator->validateForKey($key, (string) $draft->draft_value);
                if ($errors !== []) {
                    $invalid[$draft->key] = $errors;
                }
            }

            if ($invalid !== []) {
                throw new InvalidDraftsException($invalid);
            }

            foreach ($drafts as $draft) {
                $draft->forceFill([
                    'value' => TranslationValidator::isBlank($draft->draft_value) ? null : $draft->draft_value,
                    'draft_value' => null,
                    'updated_by' => $userId,
                ])->save();
            }

            $version = $this->bumpVersion($language, $userId);

            return ['published' => $drafts->count(), 'version' => $version];
        });
    }

    /**
     * Republishes an older snapshot as a new version. Drafts are kept. Snapshot values that no longer
     * pass validation against the current catalog are not restored (those keys fall back in the app).
     *
     * @return array{restored: int, skipped: list<string>, version: int}
     */
    public function rollback(AppLanguage $language, AppTranslationRevision $revision, ?int $userId): array
    {
        if ($revision->language_code !== $language->code) {
            throw new RuntimeException('That revision belongs to another language.');
        }

        return DB::transaction(function () use ($language, $revision, $userId) {
            $language = AppLanguage::query()->whereKey($language->getKey())->lockForUpdate()->firstOrFail();
            $snapshot = is_array($revision->snapshot) ? $revision->snapshot : [];
            $catalog = AppTranslationKey::query()->get()->keyBy('key');

            $restore = [];
            $skipped = [];
            foreach ($snapshot as $key => $value) {
                $key = (string) $key;
                $catalogKey = $catalog->get($key);
                if ($catalogKey === null || ! is_string($value) || $this->validator->validateForKey($catalogKey, $value) !== []) {
                    $skipped[] = $key;

                    continue;
                }
                $restore[$key] = TranslationValidator::isBlank($value) ? null : $value;
            }

            $rows = AppTranslation::query()->where('language_code', $language->code)->get()->keyBy('key');
            foreach ($rows as $key => $row) {
                $target = $restore[$key] ?? null;
                if ($row->value !== $target) {
                    $row->forceFill(['value' => $target, 'updated_by' => $userId])->save();
                }
            }

            foreach ($restore as $key => $value) {
                if ($value !== null && ! $rows->has($key)) {
                    AppTranslation::query()->create([
                        'language_code' => $language->code,
                        'key' => $key,
                        'value' => $value,
                        'updated_by' => $userId,
                    ]);
                }
            }

            $version = $this->bumpVersion($language, $userId);

            return ['restored' => count(array_filter($restore, fn ($value) => $value !== null)), 'skipped' => $skipped, 'version' => $version];
        });
    }

    /**
     * Records the current published strings as a new version. Call inside a transaction after changing
     * published values (publish, rollback, ARB import).
     */
    public function bumpVersion(AppLanguage $language, ?int $userId): int
    {
        $previous = (int) $language->version;
        $version = max($previous, (int) AppTranslationRevision::query()->where('language_code', $language->code)->max('version')) + 1;

        $language->forceFill(['version' => $version, 'published_at' => now()])->save();

        AppTranslationRevision::query()->create([
            'language_code' => $language->code,
            'version' => $version,
            'snapshot' => $this->publishedStrings($language->code),
            'published_by' => $userId,
        ]);

        DB::afterCommit(fn () => Cache::forget(self::cacheKey($language, $previous)));

        return $version;
    }

    /**
     * Non-blank published values of catalog keys, in catalog order.
     *
     * @return array<string, string>
     */
    public function publishedStrings(string $languageCode): array
    {
        $rows = AppTranslation::query()
            ->join('app_translation_keys', 'app_translation_keys.key', '=', 'app_translations.key')
            ->where('app_translations.language_code', $languageCode)
            ->whereNotNull('app_translations.value')
            ->orderBy('app_translation_keys.id')
            ->get(['app_translations.key as k', 'app_translations.value as v']);

        $strings = [];
        foreach ($rows as $row) {
            $value = (string) $row->v;
            if (! TranslationValidator::isBlank($value)) {
                $strings[(string) $row->k] = $value;
            }
        }

        return $strings;
    }

    /**
     * Public payload for one language, cached per language id + version.
     *
     * @return array{code: string, direction: string, fallback: ?string, version: int, strings: object}
     */
    public function payload(AppLanguage $language): array
    {
        $ttl = (int) config('i18n.cache_ttl_seconds', 86400);

        $payload = Cache::remember(self::cacheKey($language, (int) $language->version), $ttl, fn () => [
            'code' => $language->code,
            'direction' => $language->direction,
            'fallback' => $language->effectiveFallback(),
            'version' => (int) $language->version,
            'strings' => $this->publishedStrings($language->code),
        ]);

        // Always a JSON object, even with no strings.
        $payload['strings'] = (object) $payload['strings'];

        return $payload;
    }

    public static function cacheKey(AppLanguage $language, int $version): string
    {
        return 'i18n:strings:'.$language->getKey().':'.$language->code.':v'.$version;
    }
}
