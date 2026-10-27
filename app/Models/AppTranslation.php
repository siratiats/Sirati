<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One key in one language.
 *
 * - `value` is the published string the public API serves (null = not translated, the app falls back).
 * - `draft_value` is an unpublished admin edit (null = no draft; "" = the draft clears the value).
 * - `updated_by` is the admin who last changed the published `value`; null means the value came from
 *   the bundled ARB import, which `i18n:import-arb` may refresh. Admin-published values are only
 *   replaced by `--force`.
 */
class AppTranslation extends Model
{
    protected $fillable = [
        'language_code',
        'key',
        'value',
        'draft_value',
        'updated_by',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(AppLanguage::class, 'language_code', 'code');
    }

    public function catalogKey(): BelongsTo
    {
        return $this->belongsTo(AppTranslationKey::class, 'key', 'key');
    }

    public function hasDraft(): bool
    {
        return $this->draft_value !== null;
    }
}
