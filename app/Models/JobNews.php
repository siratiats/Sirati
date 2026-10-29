<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JobNews extends Model
{
    protected $fillable = [
        'language',
        'title',
        'company',
        'location',
        'city',
        'is_remote',
        'body',
        'category',
        'job_title_id',
        'url',
        'apply_url',
        'apply_email',
        'fingerprint',
        'published_at',
        'valid_from',
        'valid_until',
        'sort_order',
        'is_published',
        'source',
        'source_row_key',
        'external_source',
        'external_id',
        'is_verified',
        'source_type',
        'employment_type',
        'experience_level',
        'salary_min',
        'salary_max',
        'skills_required',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_published' => 'boolean',
            'is_remote' => 'boolean',
            'is_verified' => 'boolean',
            'job_title_id' => 'integer',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'skills_required' => 'array',
        ];
    }

    /**
     * Identity of a vacancy across sources, so one job reposted by several
     * channels is stored once. Spelling noise (diacritics, tatweel, alef/ya/
     * ta-marbuta forms, case, punctuation, emoji) does not change it.
     *
     * The employer anchors the identity; without one, the apply destination
     * does. With neither (or a title too short to tell vacancies apart) it is
     * null and the post is never merged: two "مطلوب مهندس كهرباء في الدمام"
     * posts from unnamed employers may be different jobs.
     */
    public static function fingerprintFor(?string $title, ?string $company, ?string $city, ?string $destination = null): ?string
    {
        $normalize = static function (?string $value): string {
            $value = mb_strtolower(trim((string) $value), 'UTF-8');
            $value = preg_replace('/[\p{Mn}\x{0640}]/u', '', $value);
            $value = strtr($value, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و', 'ة' => 'ه']);
            $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string) $value);

            return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
        };

        $normalizedTitle = $normalize($title);
        if (mb_strlen(str_replace(' ', '', $normalizedTitle)) < 10) {
            return null;
        }

        $anchor = $normalize($company);
        if ($anchor === '') {
            $destination = self::normalizeDestination($destination);
            if ($destination === null) {
                return null;
            }
            $anchor = 'dest:'.$destination;
        }

        return sha1($normalizedTitle.'|'.$anchor.'|'.$normalize($city));
    }

    /**
     * Comparable form of an apply destination. Emails and URL scheme/host are
     * case-insensitive; `www.`, a trailing slash and tracking pairs (utm_*,
     * fbclid, gclid) do not matter. Everything else is kept verbatim: path and
     * query case, other query pairs in order, and the fragment (hash-routed
     * career sites put the requisition there: `/#/jobs/101`).
     */
    public static function normalizeDestination(?string $destination): ?string
    {
        $destination = trim((string) $destination);
        if ($destination === '') {
            return null;
        }
        $parts = str_contains($destination, '://') ? parse_url($destination) : false;
        if (! is_array($parts) || ! isset($parts['host'])) {
            return mb_strtolower($destination);
        }

        $host = preg_replace('/^www\./', '', strtolower($parts['host']));
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        // Raw pairs, not parse_str (which turns "req.id" into "req_id").
        $pairs = array_filter(
            explode('&', (string) ($parts['query'] ?? '')),
            fn (string $pair): bool => $pair !== ''
                && ! preg_match('/^(?:utm_[^=]*|fbclid|gclid)(?:=|$)/i', $pair),
        );
        $query = $pairs === [] ? '' : '?'.implode('&', $pairs);
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        return strtolower((string) ($parts['scheme'] ?? 'https')).'://'.$host.$path.$query.$fragment;
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = today();

        return $query
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today));
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeSourceType(Builder $query, string $type): Builder
    {
        return $query->where('source_type', $type);
    }
}
