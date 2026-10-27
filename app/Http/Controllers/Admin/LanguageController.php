<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesAdmin;
use App\Http\Controllers\Controller;
use App\Models\AppLanguage;
use App\Models\AppTranslation;
use App\Models\AppTranslationKey;
use App\Models\AppTranslationRevision;
use App\Services\I18n\InvalidDraftsException;
use App\Services\I18n\TranslationPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class LanguageController extends Controller
{
    use AuthorizesAdmin;

    public const CODE_PATTERN = '/^[a-z]{2,3}([_-][A-Za-z0-9]{2,8})?$/';

    public function __construct(private readonly TranslationPublisher $publisher)
    {
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $languages = AppLanguage::query()->ordered()->get();
        $stats = self::languageStats($languages->pluck('code')->all());
        $revisions = AppTranslationRevision::query()
            ->with('publisher:id,name,email')
            ->orderByDesc('version')
            ->get(['id', 'language_code', 'version', 'published_by', 'created_at'])
            ->groupBy('language_code')
            ->map(fn ($items) => $items->take(15));

        return view('admin.languages', [
            'languages' => $languages,
            'stats' => $stats,
            'revisions' => $revisions,
            'totalKeys' => AppTranslationKey::query()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:16', 'regex:'.self::CODE_PATTERN, Rule::unique('app_languages', 'code')],
            'name' => ['required', 'string', 'max:80'],
            'native_name' => ['required', 'string', 'max:80'],
            'direction' => ['required', Rule::in(AppLanguage::DIRECTIONS)],
            'fallback_code' => ['nullable', 'string', Rule::exists('app_languages', 'code'), 'different:code'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        AppLanguage::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'native_name' => $data['native_name'],
            'direction' => $data['direction'],
            'fallback_code' => $data['fallback_code'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_enabled' => $request->boolean('is_enabled'),
            'is_default' => false,
            'version' => 0,
        ]);

        return redirect()->route('admin.languages.index')->with('status', "Language {$data['code']} added.");
    }

    public function update(Request $request, string $code): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $language = AppLanguage::query()->where('code', $code)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'native_name' => ['sometimes', 'required', 'string', 'max:80'],
            'direction' => ['sometimes', 'required', Rule::in(AppLanguage::DIRECTIONS)],
            'fallback_code' => ['sometimes', 'nullable', 'string', Rule::exists('app_languages', 'code'), Rule::notIn([$language->code])],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $wantsEnabled = array_key_exists('is_enabled', $data) ? (bool) $data['is_enabled'] : $language->is_enabled;
        $wantsDefault = array_key_exists('is_default', $data) ? (bool) $data['is_default'] : $language->is_default;

        if ($language->is_default && ! $wantsDefault) {
            throw ValidationException::withMessages(['is_default' => 'Make another language the default instead of unsetting it here.']);
        }
        if ($wantsDefault && ! $wantsEnabled) {
            throw ValidationException::withMessages(['is_enabled' => 'The default language must stay enabled.']);
        }

        DB::transaction(function () use ($language, $data, $wantsEnabled, $wantsDefault, $request) {
            $language->fill(array_intersect_key($data, array_flip(['name', 'native_name', 'direction', 'fallback_code', 'sort_order'])));
            $language->is_enabled = $wantsEnabled;

            if ($wantsDefault && ! $language->is_default) {
                AppLanguage::query()->where('id', '!=', $language->id)->update(['is_default' => false]);
            }
            $language->is_default = $wantsDefault;

            // direction and fallback are part of the strings payload, which is cached and ETagged per version.
            $payloadChanged = $language->isDirty(['direction', 'fallback_code']);
            $language->save();

            if ($payloadChanged) {
                $this->publisher->bumpVersion($language, $request->user()?->id);
            }
        });

        return redirect()->route('admin.languages.index')->with('status', "Language {$language->code} updated.");
    }

    public function publish(Request $request, string $code): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $language = AppLanguage::query()->where('code', $code)->firstOrFail();

        try {
            $result = $this->publisher->publish($language, $request->user()?->id);
        } catch (InvalidDraftsException $exception) {
            return back()->with('i18n_error', $exception->getMessage())->with('i18n_errors', $exception->errors);
        } catch (RuntimeException $exception) {
            return back()->with('i18n_error', $exception->getMessage());
        }

        return back()->with('status', "Published {$result['published']} label(s) for {$language->code}; now version {$result['version']}.");
    }

    public function rollback(Request $request, string $code): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $language = AppLanguage::query()->where('code', $code)->firstOrFail();

        $data = $request->validate(['revision' => ['required', 'integer']]);
        $revision = AppTranslationRevision::query()
            ->where('language_code', $language->code)
            ->whereKey($data['revision'])
            ->first();

        if ($revision === null) {
            throw ValidationException::withMessages(['revision' => 'That revision does not exist for this language.']);
        }

        $result = $this->publisher->rollback($language, $revision, $request->user()?->id);
        $message = "Restored version {$revision->version} of {$language->code} as version {$result['version']}.";
        if ($result['skipped'] !== []) {
            $message .= ' '.count($result['skipped']).' value(s) no longer match the catalog and were not restored.';
        }

        return back()->with('status', $message);
    }

    public function destroy(Request $request, string $code): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $language = AppLanguage::query()->where('code', $code)->firstOrFail();

        if ($language->is_default) {
            return back()->with('i18n_error', 'The default language cannot be deleted.');
        }

        DB::transaction(function () use ($language, $request) {
            // Languages that fell back to this one now fall back to nothing; their payload changed.
            AppLanguage::query()->where('fallback_code', $language->code)->get()
                ->each(function (AppLanguage $dependent) use ($request) {
                    $dependent->forceFill(['fallback_code' => null])->save();
                    $this->publisher->bumpVersion($dependent, $request->user()?->id);
                });
            AppTranslation::query()->where('language_code', $language->code)->delete();
            AppTranslationRevision::query()->where('language_code', $language->code)->delete();
            $language->delete();
        });
        Cache::forget(TranslationPublisher::cacheKey($language, (int) $language->version));

        return redirect()->route('admin.languages.index')->with('status', "Language {$language->code} deleted.");
    }

    /**
     * Published coverage and draft counts per language.
     *
     * @param  list<string>  $codes
     * @return array<string, array{translated: int, drafts: int, coverage: float}>
     */
    public static function languageStats(array $codes): array
    {
        $total = AppTranslationKey::query()->count();

        $translated = AppTranslation::query()
            ->join('app_translation_keys', 'app_translation_keys.key', '=', 'app_translations.key')
            ->whereIn('app_translations.language_code', $codes)
            ->whereNotNull('app_translations.value')
            ->where('app_translations.value', '!=', '')
            ->groupBy('app_translations.language_code')
            ->selectRaw('app_translations.language_code as code, count(*) as aggregate')
            ->pluck('aggregate', 'code');

        $drafts = AppTranslation::query()
            ->whereIn('language_code', $codes)
            ->whereNotNull('draft_value')
            ->groupBy('language_code')
            ->selectRaw('language_code as code, count(*) as aggregate')
            ->pluck('aggregate', 'code');

        $stats = [];
        foreach ($codes as $code) {
            $count = (int) ($translated[$code] ?? 0);
            $stats[$code] = [
                'translated' => $count,
                'drafts' => (int) ($drafts[$code] ?? 0),
                'coverage' => $total > 0 ? round($count * 100 / $total, 1) : 0.0,
            ];
        }

        return $stats;
    }
}
