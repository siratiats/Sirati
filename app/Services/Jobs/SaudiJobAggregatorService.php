<?php

namespace App\Services\Jobs;

use App\Jobs\SendPushNotificationJob;
use App\Models\JobNews;
use App\Models\JobTitle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SaudiJobAggregatorService
{
    public function __construct(
        private readonly JobTaxonomyMatcher $matcher,
    ) {}

    /**
     * Curated Saudi Job RSS / JSON feeds.
     */
    public const SAUDI_FEEDS = [
        [
            'source' => 'wazaif_sa',
            'name' => 'Wazaif Saudi Arabia',
            'url' => 'https://www.wazaif.net/rss.xml',
            'type' => 'rss',
            'language' => 'ar',
        ],
        [
            'source' => 'tanqeeb_sa',
            'name' => 'Tanqeeb Saudi Arabia',
            'url' => 'https://saudi.tanqeeb.com/rss/all/jobs.xml',
            'type' => 'rss',
            'language' => 'ar',
        ],
    ];

    /**
     * Run aggregation across configured Saudi job sources.
     *
     * @return array{fetched: int, created: int, updated: int, errors: array<string>}
     */
    public function aggregateAll(): array
    {
        $totalFetched = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $errors = [];

        foreach (self::SAUDI_FEEDS as $feed) {
            try {
                $result = $this->aggregateFeed($feed);
                $totalFetched += $result['fetched'];
                $totalCreated += $result['created'];
                $totalUpdated += $result['updated'];
            } catch (\Throwable $e) {
                Log::error('Saudi job aggregator feed failed', [
                    'source' => $feed['source'],
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "Feed {$feed['name']}: {$e->getMessage()}";
            }
        }

        return [
            'fetched' => $totalFetched,
            'created' => $totalCreated,
            'updated' => $totalUpdated,
            'errors' => $errors,
        ];
    }

    /**
     * Ingest a single RSS/XML feed.
     */
    public function aggregateFeed(array $feed): array
    {
        $response = Http::timeout(25)
            ->withHeaders(['User-Agent' => 'Sirati-Job-Bot/1.0 (+https://siratie.com)'])
            ->get($feed['url']);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} while fetching {$feed['url']}");
        }

        $xmlString = $response->body();
        if (trim($xmlString) === '') {
            return ['fetched' => 0, 'created' => 0, 'updated' => 0];
        }

        $xml = @simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false || ! isset($xml->channel->item)) {
            return ['fetched' => 0, 'created' => 0, 'updated' => 0];
        }

        $fetched = 0;
        $created = 0;
        $updated = 0;

        foreach ($xml->channel->item as $item) {
            $fetched++;
            $title = trim((string) $item->title);
            $link = trim((string) $item->link);
            $description = trim(strip_tags((string) $item->description));
            $pubDate = ! empty($item->pubDate) ? Carbon::parse((string) $item->pubDate) : now();
            $guid = ! empty($item->guid) ? trim((string) $item->guid) : md5($link ?: $title);

            if ($title === '') continue;

            // Extract company name if included in brackets or title format (e.g. "Title - Company")
            $company = $this->extractCompany($title);
            $location = $this->extractLocation($title.' '.$description);

            // Match taxonomy & city
            $match = $this->matcher->match($title, $description, $location);

            $jobData = [
                'language' => $feed['language'] ?? 'ar',
                'title' => Str::limit($title, 180, ''),
                'company' => Str::limit($company ?: 'شركة سعودية رائدة', 160, ''),
                'location' => Str::limit($location ?: 'المملكة العربية السعودية', 160, ''),
                'body' => $description ?: $title,
                'url' => $link,
                'apply_url' => $link,
                'published_at' => $pubDate,
                'valid_from' => $pubDate->toDateString(),
                'valid_until' => $pubDate->copy()->addDays(30)->toDateString(),
                'is_published' => true,
                'source' => $feed['source'],
                'external_source' => $feed['source'],
                'external_id' => $guid,
                'job_title_id' => $match['job_title_id'],
                'category' => $match['category'],
                'city' => $match['city'],
                'is_remote' => $match['is_remote'],
            ];

            $job = JobNews::query()->updateOrCreate(
                [
                    'external_source' => $feed['source'],
                    'external_id' => $guid,
                ],
                $jobData,
            );

            if ($job->wasRecentlyCreated) {
                $created++;
                $this->notifyInterestedCandidates($job);
            } else {
                $updated++;
            }
        }

        return [
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Dispatch smart push notifications to candidates matching this job title.
     */
    public function notifyInterestedCandidates(JobNews $job): void
    {
        if (! $job->job_title_id) return;

        // Find users with this job title
        $users = User::query()
            ->where('job_title_id', $job->job_title_id)
            ->whereNotNull('email_verified_at')
            ->limit(200)
            ->get();

        foreach ($users as $user) {
            $locationNote = $job->city ? ' في '.($job->location ?: 'السعودية') : '';
            $title = '🎯 فرصة وظيفية جديدة تناسبك';
            $body = "تم نشر وظيفة جديدة: {$job->title}{$locationNote} لدى {$job->company}";

            SendPushNotificationJob::dispatch(
                userId: $user->id,
                title: $title,
                body: $body,
                type: 'job_match',
                actionType: 'job_news',
                actionUrl: '/mobile/job-news/'.$job->id,
                data: [
                    'type' => 'job_match',
                    'job_id' => (string) $job->id,
                    'action_url' => '/mobile/job-news/'.$job->id,
                ],
            );
        }
    }

    private function extractCompany(string $title): ?string
    {
        if (preg_match('/(?:لدى|في|بشركة|بـ|شركة)\s+([\p{Arabic}\w\s]+)/u', $title, $matches)) {
            $extracted = trim($matches[1]);
            return mb_strlen($extracted) > 2 ? $extracted : null;
        }

        if (str_contains($title, '-')) {
            $parts = explode('-', $title);
            $candidate = trim(end($parts));
            if (mb_strlen($candidate) >= 3 && mb_strlen($candidate) <= 40) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractLocation(string $text): ?string
    {
        $cityMatch = $this->matcher->detectCity($text);
        if ($cityMatch['city']) {
            $map = [
                'riyadh' => 'الرياض',
                'jeddah' => 'جدة',
                'dammam' => 'الدمام',
                'khobar' => 'الخبر',
                'mecca' => 'مكة المكرمة',
                'medina' => 'المدينة المنورة',
                'dhahran' => 'الظهران',
                'jubail' => 'الجبيل',
                'tabuk' => 'تبوك',
                'qassim' => 'القصيم',
                'abha' => 'أبها',
            ];
            return $map[$cityMatch['city']] ?? 'المملكة العربية السعودية';
        }

        if ($cityMatch['is_remote']) {
            return 'عن بعد (Remote)';
        }

        return 'المملكة العربية السعودية';
    }
}
