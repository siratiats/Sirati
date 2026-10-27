<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppLanguage;
use App\Services\I18n\TranslationPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Public, read-only label bundles for the mobile app (published values of enabled languages only). */
class I18nController extends Controller
{
    public function languages(): JsonResponse
    {
        $languages = AppLanguage::query()->enabled()->ordered()->get();
        $default = $languages->firstWhere('is_default', true) ?? $languages->first();

        return response()->json([
            'default' => $default?->code,
            'data' => $languages->map(fn (AppLanguage $language) => [
                'code' => $language->code,
                'name' => $language->name,
                'native_name' => $language->native_name,
                'direction' => $language->direction,
                'fallback' => $language->effectiveFallback(),
                'version' => (int) $language->version,
            ])->values(),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function strings(Request $request, string $code, TranslationPublisher $publisher): JsonResponse|Response
    {
        $language = AppLanguage::query()->enabled()->where('code', $code)->first();
        if ($language === null) {
            return response()->json(['message' => 'Language not found.'], 404);
        }

        $etag = $language->etag();
        $headers = [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age='.(int) config('i18n.strings_max_age', 300),
        ];

        if ($this->matchesEtag((string) $request->header('If-None-Match', ''), $etag)) {
            return response('', 304, $headers);
        }

        return response()->json($publisher->payload($language), 200, $headers, JSON_UNESCAPED_UNICODE);
    }

    private function matchesEtag(string $ifNoneMatch, string $etag): bool
    {
        if (trim($ifNoneMatch) === '') {
            return false;
        }

        foreach (explode(',', $ifNoneMatch) as $candidate) {
            $candidate = trim($candidate);
            if (str_starts_with($candidate, 'W/')) {
                $candidate = substr($candidate, 2);
            }
            if ($candidate === '*' || $candidate === $etag) {
                return true;
            }
        }

        return false;
    }
}
