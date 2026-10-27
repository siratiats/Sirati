<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesAdmin;
use App\Http\Controllers\Controller;
use App\Models\AppLanguage;
use App\Models\AppTranslation;
use App\Models\AppTranslationKey;
use App\Services\I18n\ArbFile;
use App\Services\I18n\ArbImporter;
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

class LabelController extends Controller
{
    use AuthorizesAdmin;

    public const FILTERS = [
        'missing' => 'Missing translation',
        'draft' => 'Has draft changes',
        'changed' => 'Edited by an admin',
        'placeholders' => 'Has placeholders',
    ];

    public function __construct(
        private readonly TranslationValidator $validator,
        private readonly TranslationPublisher $publisher,
        private readonly ArbImporter $importer,
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
        if ($target !== null) {
            $baseCode = $base?->code ?? $target->code;
            $labels = $this->labelQuery($target->code, $baseCode, $q, $filter)
                ->paginate(50)
                ->withQueryString();

            // Chip counts use the same query as the list, so a chip's number is what clicking it shows.
            foreach (['' => null] + self::FILTERS as $value => $unused) {
                $filterCounts[$value] = $this->labelQuery($target->code, $baseCode, $q, (string) $value)->count();
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
        ]);
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

    private function labelQuery(string $targetCode, string $baseCode, string $q, string $filter): Builder
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

        return $query;
    }
}
