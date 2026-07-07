<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-side accessor for editable landing-page content stored in site_settings.
 * Shared to every view as $cms (see AppServiceProvider).
 */
class SiteContent
{
    private const CACHE_KEY = 'site_settings_all';

    private ?Collection $items = null;

    /**
     * @return Collection<string, SiteSetting>
     */
    public function all(): Collection
    {
        if ($this->items !== null) {
            return $this->items;
        }

        return $this->items = Cache::rememberForever(self::CACHE_KEY, function () {
            return SiteSetting::query()->get()->keyBy('key');
        });
    }

    public function get(string $key): ?SiteSetting
    {
        return $this->all()->get($key);
    }

    /**
     * Localized text for a bilingual field, falling back to the other language.
     */
    public function text(string $key, string $lang = 'ar'): string
    {
        $setting = $this->get($key);

        if (! $setting) {
            return '';
        }

        return (string) ($lang === 'en'
            ? ($setting->value_en ?: $setting->value_ar)
            : ($setting->value_ar ?: $setting->value_en));
    }

    /**
     * Raw single value (image path, url, plain text, emoji).
     */
    public function value(string $key, string $default = ''): string
    {
        return (string) ($this->get($key)?->value ?? '') ?: $default;
    }

    /**
     * Emit both language spans for the landing page's Arabic/English toggle.
     */
    public function pair(string $key): string
    {
        $setting = $this->get($key);

        $ar = e($setting?->value_ar ?? '');
        $en = e($setting?->value_en ?? '');

        return '<span class="ar">'.$ar.'</span><span class="en-text">'.$en.'</span>';
    }

    /**
     * Public URL for an uploaded image, or null when none is set.
     */
    public function image(string $key): ?string
    {
        $path = $this->get($key)?->value;

        if (! $path) {
            return null;
        }

        return asset('storage/'.$path);
    }

    /**
     * Drop the cache and the per-instance memo (call after saving in admin).
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->items = null;
    }
}
