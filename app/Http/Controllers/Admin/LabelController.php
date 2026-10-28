<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesAdmin;
use App\Http\Controllers\Controller;
use App\Models\AppLanguage;
use App\Models\AppTranslation;
use App\Models\AppTranslationKey;
use App\Services\I18n\ArbFile;
use App\Services\I18n\ArbImporter;
use App\Services\I18n\LabelScreenCatalog;
use App\Services\I18n\LabelScreenPreview;
use App\Services\I18n\TranslationPublisher;
use App\Services\I18n\TranslationValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LabelController extends Controller
{
    use AuthorizesAdmin;

    public const FILTERS = [
        'missing' => 'Missing translation',
        'draft' => 'Has draft changes',
        'changed' => 'Edited by an admin',
        'placeholders' => 'Has placeholders',
    ];

    /** `?screen=` value for labels that are on no captured app screen. */
    public const SCREEN_NONE = '__none';

    public const PER_PAGE = 50;

    /** `?preview=` value that shows the bare screenshot, without the current text painted over it. */
    public const PREVIEW_ORIGINAL = 'original';

    public function __construct(
        private readonly TranslationValidator $validator,
        private readonly TranslationPublisher $publisher,
        private readonly ArbImporter $importer,
        private readonly LabelScreenCatalog $screens,
        private readonly LabelScreenPreview $preview,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $languages = AppLanguage::query()->ordered()->get();
        $target = $languages->firstWhere('code', (string) $request->query('lang', ''))
            ?? $languages->firstWhere('is_default', true)
            ?? $languages->first();

        $requestedBase = (string) $request->query('base', '');
        $base = $target === null ? null : (
            $requestedBase !== '' && $requestedBase !== $target->code
                ? ($languages->firstWhere('code', $requestedBase) ?? $this->baseLanguageFor($target, $languages))
                : $this->baseLanguageFor($target, $languages)
        );
        $q = trim((string) $request->query('q', ''));
        $filter = array_key_exists((string) $request->query('filter'), self::FILTERS) ? (string) $request->query('filter') : '';

        $labels = null;
        $filterCounts = [];
        $groupStats = [];
        $screenView = null;
        if ($target !== null) {
            $baseCode = $base?->code ?? $target->code;
            $screenView = $this->screenView($request, $target);
            $scope = $screenView['scope'];

            $page = null;
            if ($screenView['selectedKey'] !== null && ! $request->has('page')) {
                $page = $this->pageOfKey($this->labelQuery($target->code, $baseCode, $q, $filter, $scope), $screenView['selectedKey']);
            }

            $labels = $this->labelQuery($target->code, $baseCode, $q, $filter, $scope)
                ->paginate(self::PER_PAGE, ['*'], 'page', $page)
                ->withQueryString();

            // Chip counts use the same query as the list, so a chip's number is what clicking it shows.
            foreach (['' => null] + self::FILTERS as $value => $unused) {
                $filterCounts[$value] = $this->labelQuery($target->code, $baseCode, $q, (string) $value, $scope)->count();
            }
            $groupStats = $this->groupStats($target->code);
        }

        $stats = LanguageController::languageStats($languages->pluck('code')->all());

        return view('admin.labels', [
            'languages' => $languages,
            'target' => $target,
            'base' => $base,
            'labels' => $labels,
            'q' => $q,
            'filter' => $filter,
            'filters' => self::FILTERS,
            'filterCounts' => $filterCounts,
            'groupStats' => $groupStats,
            'stats' => $stats,
            'totalKeys' => AppTranslationKey::query()->count(),
            'screenView' => $screenView,
        ]);
    }

    /** Serves one app screenshot listed in the screens manifest; anything else is a 404. */
    public function screenImage(Request $request, string $screen, string $locale): BinaryFileResponse
    {
        $this->authorizeAdmin($request);

        $path = $this->screens->imagePath($screen, $locale);
        abort_if($path === null, 404);

        $response = new BinaryFileResponse($path, 200, ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff']);
        $response->setEtag(sha1($screen.'|'.$locale.'|'.(string) sha1_file($path)));
        $response->setLastModified((new \DateTimeImmutable)->setTimestamp((int) filemtime($path)));
        $response->setPrivate();
        $response->setMaxAge(300);
        $response->isNotModified($request);

        return $response;
    }

    /**
     * Screen filter, selected label and the phone preview (screenshot + label boxes) for the page.
     *
     * @return array<string, mixed>
     */
    private function screenView(Request $request, AppLanguage $target): array
    {
        $catalog = $this->screens;
        $screens = $catalog->screens();
        $known = $screens === [] ? [] : array_flip(AppTranslationKey::query()->pluck('key')->all());
        $knownOnly = fn (array $keys) => array_values(array_filter($keys, fn (string $key) => isset($known[$key])));

        $counts = [];
        foreach ($screens as $id => $screen) {
            $counts[$id] = count($knownOnly($catalog->keysOnScreen($id)));
        }
        $capturedKeys = $knownOnly($catalog->allKeys());

        $requested = (string) $request->query('screen', '');
        $screenFilter = $screens === [] ? '' : ($requested === self::SCREEN_NONE || isset($screens[$requested]) ? $requested : '');
        $scope = match (true) {
            $screenFilter === self::SCREEN_NONE => ['except' => $capturedKeys],
            $screenFilter !== '' => ['only' => $knownOnly($catalog->keysOnScreen($screenFilter))],
            default => null,
        };

        $requestedKey = (string) $request->query('key', '');
        $selectedKey = $requestedKey !== '' && AppTranslationKey::query()->where('key', $requestedKey)->exists() ? $requestedKey : null;
        $keyScreens = $selectedKey === null ? [] : $catalog->screensForKey($selectedKey);

        // The phone follows the selected label: its first screen, unless the filtered screen shows it too.
        $phoneScreen = match (true) {
            $keyScreens !== [] => in_array($screenFilter, $keyScreens, true) ? $screenFilter : $keyScreens[0],
            $screenFilter !== '' && $screenFilter !== self::SCREEN_NONE => $screenFilter,
            default => array_key_first($screens),
        };

        $requestedShot = (string) $request->query('shot', '');
        $shot = preg_match(LabelScreenCatalog::LOCALE_PATTERN, $requestedShot) === 1 ? $requestedShot : null;
        $meta = $catalog->meta();
        $previewMode = $request->query('preview') === self::PREVIEW_ORIGINAL ? self::PREVIEW_ORIGINAL : null;
        $phone = null;
        if ($phoneScreen !== null) {
            $phoneLocale = (string) $catalog->resolveLocale($phoneScreen, [$shot, $target->code, $target->effectiveFallback(), 'en']);
            $ids = $catalog->screenIds();
            $position = (int) array_search($phoneScreen, $ids, true);
            $capture = $catalog->capture($phoneScreen, $phoneLocale);
            $phoneKeys = array_values(array_unique(array_filter(array_column($capture['labels'], 'key'), fn (string $key) => isset($known[$key]))));
            $phoneLanguage = AppLanguage::query()->where('code', $phoneLocale)->first();
            $overlays = $this->preview->overlays(
                $capture,
                $this->preview->currentValues($phoneLocale, $phoneKeys),
                $this->preview->bundledMessages($phoneLocale),
                $phoneLanguage?->direction ?? 'ltr',
                fn (string $key) => isset($known[$key]),
            );
            $phone = [
                'screen' => $screens[$phoneScreen],
                'locale' => $phoneLocale,
                'locales' => $catalog->localesFor($phoneScreen),
                'capture' => $capture,
                // Logical width the manifest coordinates and font sizes are measured in.
                'unitWidth' => (float) ($meta['device']['width'] ?? $capture['width']),
                'overlays' => $overlays,
                'editedKeys' => array_values(array_unique(array_column(array_filter($overlays, fn (array $o) => $o['edited']), 'key'))),
                // Typing in a row repaints the phone only when the phone shows the language being edited.
                'live' => $previewMode === null && $phoneLocale === $target->code,
                'imageUrl' => route('admin.labels.screens.image', [
                    'screen' => $phoneScreen,
                    'locale' => $phoneLocale,
                    'v' => substr(sha1(($meta['generated_at'] ?? '').'|'.($meta['app_commit'] ?? '')), 0, 10),
                ]),
                'boxes' => array_values(array_filter($catalog->boxesFor($phoneScreen, $phoneLocale), fn (array $box) => isset($known[$box['key']]))),
                'position' => $position + 1,
                'count' => count($ids),
                'previous' => $ids[$position - 1] ?? null,
                'next' => $ids[$position + 1] ?? null,
                'selectedOnPhone' => $selectedKey !== null && in_array($selectedKey, array_column($catalog->labelsFor($phoneScreen, $phoneLocale), 'key'), true),
            ];
        }

        return [
            'hasScreens' => $screens !== [],
            'screens' => $screens,
            'counts' => $counts,
            'noneCount' => max(0, AppTranslationKey::query()->count() - count($capturedKeys)),
            'filter' => $screenFilter,
            'scope' => $scope,
            'shot' => $shot,
            'preview' => $previewMode,
            'selectedKey' => $selectedKey,
            'keyScreens' => $keyScreens,
            'phone' => $phone,
            'meta' => $meta,
            'screensForKey' => fn (string $key) => $catalog->screensForKey($key),
        ];
    }

    /** Page of the (already filtered) list that holds the key, or null when the list does not hold it. */
    private function pageOfKey(Builder $query, string $key): ?int
    {
        $id = AppTranslationKey::query()->where('key', $key)->value('id');
        if ($id === null || ! (clone $query)->where('app_translation_keys.key', $key)->exists()) {
            return null;
        }

        return intdiv((clone $query)->where('app_translation_keys.id', '<', $id)->count(), self::PER_PAGE) + 1;
    }

    /** Saves the draft of one key. Always answers JSON; invalid values get 422 with errors.value. */
    public function update(Request $request, string $code): JsonResponse
    {
        $this->authorizeAdmin($request);
        $language = AppLanguage::query()->where('code', $code)->firstOrFail();

        $input = Validator::make($request->all(), [
            'key' => ['required', 'string', 'max:191'],
            'value' => ['present', 'nullable', 'string'],
        ]);
        if ($input->fails()) {
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $input->errors()->toArray()], 422);
        }

        $catalogKey = AppTranslationKey::query()->where('key', (string) $request->input('key'))->first();
        if ($catalogKey === null) {
            return response()->json(['message' => 'Unknown label key.', 'errors' => ['key' => ['This key is not in the catalog.']]], 422);
        }

        $value = (string) $request->input('value', '');
        $errors = $this->validator->validateForKey($catalogKey, $value);
        if ($errors !== []) {
            return response()->json(['message' => $errors[0], 'errors' => ['value' => $errors]], 422);
        }

        $row = DB::transaction(fn () => $this->saveDraft($language, $catalogKey->key, $value));

        return response()->json($this->rowPayload($catalogKey->key, $row));
    }

    /** Saves several drafts at once; nothing is saved when any value is invalid. */
    public function bulk(Request $request, string $code): JsonResponse
    {
        $this->authorizeAdmin($request);
        $language = AppLanguage::query()->where('code', $code)->firstOrFail();

        $values = $request->input('values');
        if (! is_array($values) || $values === []) {
            return response()->json(['message' => 'Send at least one value.', 'errors' => ['values' => ['Send at least one value.']]], 422);
        }

        $catalog = AppTranslationKey::query()->whereIn('key', array_map('strval', array_keys($values)))->get()->keyBy('key');
        $errors = [];
        foreach ($values as $key => $value) {
            $key = (string) $key;
            if (! $catalog->has($key)) {
                $errors[$key] = ['This key is not in the catalog.'];

                continue;
            }
            if ($value !== null && ! is_string($value)) {
                $errors[$key] = ['The value must be text.'];

                continue;
            }
            $keyErrors = $this->validator->validateForKey($catalog->get($key), (string) $value);
            if ($keyErrors !== []) {
                $errors[$key] = $keyErrors;
            }
        }

        if ($errors !== []) {
            return response()->json(['message' => count($errors).' value(s) are invalid; nothing was saved.', 'errors' => $errors], 422);
        }

        $rows = DB::transaction(function () use ($language, $values) {
            $saved = [];
            foreach ($values as $key => $value) {
                $saved[(string) $key] = $this->rowPayload((string) $key, $this->saveDraft($language, (string) $key, (string) $value));
            }

            return $saved;
        });

        return response()->json(['saved' => count($rows), 'data' => $rows]);
    }

    public function discard(Request $request, string $code): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin($request);
        $language = AppLanguage::query()->where('code', $code)->firstOrFail();

        $query = AppTranslation::query()->where('language_code', $language->code)->whereNotNull('draft_value');
        $key = $request->input('key');
        if (is_string($key) && $key !== '') {
            $query->where('key', $key);
        } elseif (! $request->boolean('all')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Send a key, or all=1.', 'errors' => ['key' => ['Send a key, or all=1.']]], 422)
                : back()->with('i18n_error', 'Choose a label, or discard all drafts.');
        }

        $count = $query->update(['draft_value' => null, 'updated_at' => now()]);

        if ($request->expectsJson()) {
            $payload = ['discarded' => $count];
            if (is_string($key) && $key !== '') {
                $payload['data'] = $this->rowPayload($key, AppTranslation::query()->where('language_code', $language->code)->where('key', $key)->first());
            }

            return response()->json($payload);
        }

        return back()->with('status', "Discarded {$count} draft(s) for {$language->code}.");
    }

    /** Uploads an ARB or CSV file into drafts of the language. */
    public function import(Request $request, string $code): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $language = AppLanguage::query()->where('code', $code)->firstOrFail();

        $request->validate([
            'file' => ['required', 'file', 'max:2048'],
            'format' => ['nullable', 'in:arb,csv'],
        ]);

        $file = $request->file('file');
        $format = $request->input('format') ?: (in_array(strtolower((string) $file->getClientOriginalExtension()), ['csv', 'txt'], true) ? 'csv' : 'arb');
        $contents = (string) file_get_contents($file->getRealPath());

        try {
            $values = $format === 'csv' ? ArbFile::parseCsv($contents) : ArbFile::parse($contents)['messages'];
        } catch (InvalidArgumentException $exception) {
            return back()->with('i18n_error', $exception->getMessage());
        }

        $report = $this->importer->importDrafts($language, $values, $request->user()?->id);

        $problems = [];
        foreach ($report['invalid'] as $key => $errors) {
            $problems[$key] = $errors;
        }
        foreach ($report['unknown'] as $key) {
            $problems[$key] = ['Not in the catalog; skipped.'];
        }

        $redirect = redirect()->route('admin.labels.index', ['lang' => $language->code])
            ->with('status', "Imported {$report['drafted']} draft(s) for {$language->code}; {$report['unchanged']} already published, ".count($problems).' skipped.');

        return $problems === [] ? $redirect : $redirect->with('i18n_errors', $problems);
    }

    /** Downloads the published values as ARB (default) or CSV. */
    public function export(Request $request, string $code): Response
    {
        $this->authorizeAdmin($request);
        $language = AppLanguage::query()->where('code', $code)->firstOrFail();
        $format = $request->query('format') === 'csv' ? 'csv' : 'arb';

        $published = AppTranslation::query()
            ->where('language_code', $language->code)
            ->whereNotNull('value')
            ->pluck('value', 'key');

        $rows = [];
        foreach (AppTranslationKey::query()->orderBy('id')->get() as $catalogKey) {
            $value = (string) ($published[$catalogKey->key] ?? '');
            if ($format === 'arb' && TranslationValidator::isBlank($value)) {
                continue;
            }
            $rows[] = [
                'key' => $catalogKey->key,
                'value' => $value,
                'description' => $catalogKey->description,
                'placeholders' => $catalogKey->placeholders,
            ];
        }

        if ($format === 'csv') {
            return response(ArbFile::toCsv($rows), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="labels_'.$language->code.'.csv"',
            ]);
        }

        return response(ArbFile::toArb($language->code, $rows), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="app_'.$language->code.'.arb"',
        ]);
    }

    private function saveDraft(AppLanguage $language, string $key, string $value): ?AppTranslation
    {
        $row = AppTranslation::query()->where('language_code', $language->code)->where('key', $key)->first();
        $published = $row?->value;
        $sameAsPublished = $published === null ? TranslationValidator::isBlank($value) : $value === $published;

        if ($sameAsPublished) {
            if ($row !== null && $row->draft_value !== null) {
                $row->forceFill(['draft_value' => null])->save();
            }

            return $row;
        }

        if ($row === null) {
            return AppTranslation::query()->create([
                'language_code' => $language->code,
                'key' => $key,
                'draft_value' => $value,
            ]);
        }

        $row->forceFill(['draft_value' => $value])->save();

        return $row;
    }

    /** @return array{key: string, value: ?string, draft_value: ?string, has_draft: bool, status: string} */
    private function rowPayload(string $key, ?AppTranslation $row): array
    {
        $value = $row?->value;
        $draft = $row?->draft_value;

        return [
            'key' => $key,
            'value' => $value,
            'draft_value' => $draft,
            'has_draft' => $draft !== null,
            'status' => $draft !== null ? 'draft' : (TranslationValidator::isBlank($value) ? 'missing' : 'published'),
        ];
    }

    private function baseLanguageFor(AppLanguage $target, $languages): ?AppLanguage
    {
        foreach ([$target->effectiveFallback(), 'en'] as $candidate) {
            if ($candidate !== null && $candidate !== $target->code && ($found = $languages->firstWhere('code', $candidate))) {
                return $found;
            }
        }

        return $languages->first(fn (AppLanguage $language) => $language->is_default && $language->code !== $target->code)
            ?? $languages->first(fn (AppLanguage $language) => $language->code !== $target->code);
    }

    /**
     * Catalog-wide key and draft counts per key group for one language (for the group headers).
     *
     * @return array<string, array{keys: int, drafts: int}>
     */
    private function groupStats(string $targetCode): array
    {
        $query = AppTranslationKey::query()
            ->leftJoin('app_translations as t', function ($join) use ($targetCode) {
                $join->on('t.key', '=', 'app_translation_keys.key')->where('t.language_code', '=', $targetCode);
            });
        $group = $query->getQuery()->getGrammar()->wrap('app_translation_keys.group');

        return $query
            ->groupBy('app_translation_keys.group')
            ->selectRaw($group.' as label_group, count(*) as keys_count, sum(case when t.draft_value is not null then 1 else 0 end) as drafts_count')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) ($row->label_group ?? '') => ['keys' => (int) $row->keys_count, 'drafts' => (int) $row->drafts_count]])
            ->all();
    }

    /** @param  array{only?: list<string>, except?: list<string>}|null  $scope  keys allowed by the screen filter */
    private function labelQuery(string $targetCode, string $baseCode, string $q, string $filter, ?array $scope = null): Builder
    {
        $query = AppTranslationKey::query()
            ->leftJoin('app_translations as t', function ($join) use ($targetCode) {
                $join->on('t.key', '=', 'app_translation_keys.key')->where('t.language_code', '=', $targetCode);
            })
            ->leftJoin('app_translations as b', function ($join) use ($baseCode) {
                $join->on('b.key', '=', 'app_translation_keys.key')->where('b.language_code', '=', $baseCode);
            })
            ->select([
                'app_translation_keys.*',
                't.value as target_value',
                't.draft_value as target_draft',
                't.updated_by as target_updated_by',
                'b.value as base_value',
            ])
            ->orderBy('app_translation_keys.id');

        if ($q !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $grammar = $query->getQuery()->getGrammar();
            $query->where(function ($where) use ($like, $grammar) {
                foreach (['app_translation_keys.key', 'app_translation_keys.description', 't.value', 't.draft_value', 'b.value'] as $column) {
                    $where->orWhereRaw($grammar->wrap($column)." like ? escape '!'", [$like]);
                }
            });
        }

        match ($filter) {
            'missing' => $query->where(fn ($where) => $where->whereNull('t.value')->orWhere('t.value', '=', '')),
            'draft' => $query->whereNotNull('t.draft_value'),
            'changed' => $query->whereNotNull('t.value')->whereNotNull('t.updated_by'),
            'placeholders' => $query->whereNotNull('app_translation_keys.placeholders'),
            default => null,
        };

        if (isset($scope['only'])) {
            $query->whereIn('app_translation_keys.key', $scope['only']);
        }
        if (isset($scope['except']) && $scope['except'] !== []) {
            $query->whereNotIn('app_translation_keys.key', $scope['except']);
        }

        return $query;
    }
}
