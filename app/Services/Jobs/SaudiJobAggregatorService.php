<?php

namespace App\Services\Jobs;

use App\Jobs\SendPushNotificationJob;
use App\Models\JobNews;
use App\Models\JobTitle;
use App\Models\NotificationDecision;
use App\Models\User;
use App\Services\Notifications\NotificationPolicyService;
use App\Services\Notifications\NotificationPreferenceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SaudiJobAggregatorService
{
    /**
     * Set of user IDs already notified during the current aggregation run to prevent spam.
     *
     * @var array<int, bool>
     */
    private array $notifiedUsersInBatch = [];

    public function __construct(
        private readonly JobTaxonomyMatcher $matcher,
    ) {}

    /**
     * Curated Saudi Job Sources (Telegram channels, Remote API, and RSS).
     */
    public const SAUDI_FEEDS = [
        [
            'source' => 'telegram_jobmag',
            'name' => 'مجلة وظائف السعودية (@jobmag)',
            'channel' => 'jobmag',
            'type' => 'telegram',
            'language' => 'ar',
        ],
        [
            'source' => 'telegram_ewdifh',
            'name' => 'أي وظيفة (@ewdifh)',
            'channel' => 'ewdifh',
            'type' => 'telegram',
            'language' => 'ar',
        ],
        [
            'source' => 'telegram_jobs2ksa',
            'name' => 'وظائف السعودية 24 (@jobs2ksa)',
            'channel' => 'jobs2ksa',
            'type' => 'telegram',
            'language' => 'ar',
        ],
        [
            'source' => 'remotive_remote',
            'name' => 'فرص العمل عن بعد (Remotive)',
            'url' => 'https://remotive.com/api/remote-jobs?limit=25',
            'type' => 'remotive_api',
            'language' => 'en',
        ],
    ];

    /**
     * Run aggregation across configured Saudi job sources.
     *
     * @param array<array<string, mixed>>|null $feeds
     * @return array{fetched: int, created: int, updated: int, errors: array<string>}
     */
    public function aggregateAll(?array $feeds = null): array
    {
        $feeds = $feeds ?? self::SAUDI_FEEDS;
        $totalFetched = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $errors = [];

        // Reset batch notifications tracking so users only receive max 1 notification per aggregate run
        $this->notifiedUsersInBatch = [];

        foreach ($feeds as $feed) {
            try {
                $result = $this->aggregateFeed($feed);
                $totalFetched += $result['fetched'];
                $totalCreated += $result['created'];
                $totalUpdated += $result['updated'];
            } catch (\Throwable $e) {
                Log::error('Saudi job aggregator feed failed', [
                    'source' => $feed['source'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                $this->recordFeedFailure($feed['source'] ?? 'unknown', $e->getMessage());
                $errors[] = "Feed " . ($feed['name'] ?? 'Unknown') . ": {$e->getMessage()}";
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
     * Route feed ingestion based on adapter type.
     */
    public function aggregateFeed(array $feed): array
    {
        return match ($feed['type'] ?? 'rss') {
            'telegram' => $this->aggregateTelegramFeed($feed),
            'remotive_api' => $this->aggregateRemotiveApi($feed),
            default => $this->aggregateRssFeed($feed),
        };
    }

    /**
     * Ingest live opportunities from a public Telegram channel.
     */
    public function aggregateTelegramFeed(array $feed): array
    {
        $channel = $feed['channel'] ?? basename($feed['url'] ?? '');
        $url = $feed['url'] ?? "https://t.me/s/{$channel}";

        $response = Http::timeout(25)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->get($url);

        if (! $response->successful()) {
            // Failure recording is centralised in aggregateAll()'s catch to
            // avoid double-incrementing consecutive_failures (SIRATI-96).
            throw new \RuntimeException("HTTP {$response->status()} while fetching Telegram channel {$url}");
        }

        $html = $response->body();
        if (trim($html) === '') {
            $this->recordFeedWarning($feed['source'], "Empty HTML body received from Telegram channel {$channel}");
            return ['fetched' => 0, 'created' => 0, 'updated' => 0];
        }

        $parts = explode('<div class="tgme_widget_message_wrap', $html);
        array_shift($parts); // Remove HTML preamble

        $fetched = 0;
        $created = 0;
        $updated = 0;

        foreach ($parts as $part) {
            if (! preg_match('/data-post="([^"]+)"/', $part, $pm)) {
                continue;
            }
            $postSlug = $pm[1]; // e.g. "jobmag/124146"
            $externalId = (string) (explode('/', $postSlug)[1] ?? $postSlug);

            if (! preg_match('/<div class="tgme_widget_message_text[^"]*"[^>]*>(.*?)<\/div>/s', $part, $tm)) {
                continue;
            }

            $rawText = $tm[1];
            $cleanText = trim(html_entity_decode(strip_tags(str_replace(['<br/>', '<br>', '<br />'], "\n", $rawText)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (mb_strlen($cleanText) < 15) {
                continue;
            }

            $fetched++;

            // Published timestamp
            $pubDate = now();
            if (preg_match('/<time[^>]*datetime="([^"]+)"/', $part, $dm)) {
                $pubDate = Carbon::parse($dm[1]);
            }

            // Extract external apply URL, fallback to telegram post link
            $applyUrl = null;
            if (preg_match_all('/href="([^"]+)"/', $part, $um)) {
                foreach ($um[1] as $candidate) {
                    if (filter_var($candidate, FILTER_VALIDATE_URL) && ! str_contains($candidate, 't.me/s/') && ! str_contains($candidate, 'telegram.org')) {
                        $applyUrl = $candidate;
                        break;
                    }
                }
            }
            if (! $applyUrl) {
                $applyUrl = "https://t.me/{$postSlug}";
            }

            // Clean title
            $lines = array_values(array_filter(explode("\n", $cleanText), fn ($l) => trim($l) !== ''));
            $firstLine = $lines[0] ?? '';
            $title = preg_replace('/^[\s\x{200e}\x{200f}\x{202a}-\x{202e}\(\)\[\]\{\}\-\*\#\:\!\.\،\?\|\/\\\@]+\s*/u', '', $firstLine);
            $title = trim(preg_replace('/[\s\)\]\}\:\-]+$/u', '', (string) $title));

            if (mb_strlen($title) < 5 && isset($lines[1])) {
                $title .= ' - ' . trim($lines[1]);
            }
            if (mb_strlen($title) > 180) {
                $title = mb_substr($title, 0, 177) . '...';
            }
            if ($title === '') {
                $title = Str::limit($cleanText, 60, '...');
            }

            $company = $this->extractCompany($cleanText);
            $location = $this->extractLocation($cleanText);
            $match = $this->matcher->match($title, $cleanText, $location);

            $jobData = [
                'language' => $feed['language'] ?? 'ar',
                'title' => Str::limit($title, 180, ''),
                'company' => Str::limit($company ?: 'جهة سعودية معتمدة', 160, ''),
                'location' => Str::limit($location ?: 'المملكة العربية السعودية', 160, ''),
                'body' => $cleanText,
                'url' => "https://t.me/{$postSlug}",
                'apply_url' => $applyUrl,
                'published_at' => $pubDate,
                'valid_from' => $pubDate->toDateString(),
                'valid_until' => $pubDate->copy()->addDays(30)->toDateString(),
                'is_published' => true,
                'source' => $feed['name'] ?? $feed['source'],
                'external_source' => $feed['source'],
                'external_id' => $externalId,
                'job_title_id' => $match['job_title_id'],
                'category' => $match['category'],
                'city' => $match['city'],
                'is_remote' => $match['is_remote'],
            ];

            $job = JobNews::query()->updateOrCreate(
                [
                    'external_source' => $feed['source'],
                    'external_id' => $externalId,
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

        if ($fetched === 0) {
            $this->recordFeedWarning($feed['source'], "Channel @{$channel} returned 0 parseable job posts");
        } else {
            $this->recordFeedSuccess($feed['source'], $fetched);
        }

        return [
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Ingest remote opportunities from Remotive API.
     */
    public function aggregateRemotiveApi(array $feed): array
    {
        $url = $feed['url'] ?? 'https://remotive.com/api/remote-jobs?limit=25';

        $response = Http::timeout(25)
            ->withHeaders([
                'User-Agent' => 'Sirati-Job-Bot/1.0 (+https://siratie.com)',
                'Accept' => 'application/json',
            ])
            ->get($url);

        if (! $response->successful()) {
            // Failure recording is centralised in aggregateAll()'s catch (SIRATI-96).
            throw new \RuntimeException("HTTP {$response->status()} while fetching Remotive API {$url}");
        }

        $data = $response->json();
        $jobs = $data['jobs'] ?? [];

        $fetched = 0;
        $created = 0;
        $updated = 0;

        foreach ($jobs as $item) {
            $title = trim($item['title'] ?? '');
            if ($title === '') {
                continue;
            }

            $fetched++;
            $externalId = (string) ($item['id'] ?? md5($title));
            $company = trim($item['company_name'] ?? 'شركة عالمية');
            $description = trim(strip_tags((string) ($item['description'] ?? '')));
            $urlLink = trim((string) ($item['url'] ?? ''));
            $pubDate = ! empty($item['publication_date']) ? Carbon::parse((string) $item['publication_date']) : now();

            $match = $this->matcher->match($title, $description, 'remote');

            $jobData = [
                'language' => $feed['language'] ?? 'en',
                'title' => Str::limit($title, 180, ''),
                'company' => Str::limit($company, 160, ''),
                'location' => 'عن بُعد (Worldwide Remote)',
                'body' => $description ?: $title,
                'url' => $urlLink,
                'apply_url' => $urlLink,
                'published_at' => $pubDate,
                'valid_from' => $pubDate->toDateString(),
                'valid_until' => $pubDate->copy()->addDays(30)->toDateString(),
                'is_published' => true,
                'source' => $feed['name'] ?? 'Remotive',
                'external_source' => $feed['source'],
                'external_id' => $externalId,
                'job_title_id' => $match['job_title_id'],
                'category' => $match['category'],
                'city' => null,
                'is_remote' => true,
            ];

            $job = JobNews::query()->updateOrCreate(
                [
                    'external_source' => $feed['source'],
                    'external_id' => $externalId,
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

        if ($fetched === 0) {
            $this->recordFeedWarning($feed['source'], 'Remotive API returned 0 job posts');
        } else {
            $this->recordFeedSuccess($feed['source'], $fetched);
        }

        return [
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Ingest a single RSS/XML feed.
     */
    public function aggregateRssFeed(array $feed): array
    {
        $response = Http::timeout(25)
            ->withHeaders(['User-Agent' => 'Sirati-Job-Bot/1.0 (+https://siratie.com)'])
            ->get($feed['url']);

        if (! $response->successful()) {
            // Failure recording is centralised in aggregateAll()'s catch (SIRATI-96).
            throw new \RuntimeException("HTTP {$response->status()} while fetching {$feed['url']}");
        }

        $xmlString = $response->body();
        if (trim($xmlString) === '') {
            $this->recordFeedWarning($feed['source'], 'Empty XML body received');
            return ['fetched' => 0, 'created' => 0, 'updated' => 0];
        }

        $xml = @simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false || ! isset($xml->channel->item)) {
            $this->recordFeedWarning($feed['source'], 'Invalid RSS XML structure');
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

            $company = $this->extractCompany($title);
            $location = $this->extractLocation($title.' '.$description);

            $match = $this->matcher->match($title, $description, $location);

            $jobData = [
                'language' => $feed['language'] ?? 'ar',
                'title' => Str::limit($title, 180, ''),
                'company' => Str::limit($company ?: 'جهة سعودية رائدة', 160, ''),
                'location' => Str::limit($location ?: 'المملكة العربية السعودية', 160, ''),
                'body' => $description ?: $title,
                'url' => $link,
                'apply_url' => $link,
                'published_at' => $pubDate,
                'valid_from' => $pubDate->toDateString(),
                'valid_until' => $pubDate->copy()->addDays(30)->toDateString(),
                'is_published' => true,
                'source' => $feed['name'] ?? $feed['source'],
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

        if ($fetched === 0) {
            $this->recordFeedWarning($feed['source'], 'RSS feed contained 0 items');
        } else {
            $this->recordFeedSuccess($feed['source'], $fetched);
        }

        return [
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Dispatch push notifications safely to interested candidates, respecting:
     * - Smart notifications global enable toggle
     * - User notification preference (opt-in)
     * - Quiet hours (e.g. 21:00 to 09:00)
     * - Minimum 24-hour gap between notifications
     * - Weekly notification cap
     * - Anti-batch flooding (max 1 notification per user per aggregation run)
     */
    public function notifyInterestedCandidates(JobNews $job): void
    {
        if (! $job->job_title_id) {
            return;
        }

        // 1. Check global toggle
        if (! config('smart_notifications.enabled')) {
            return;
        }

        /** @var NotificationPolicyService $policy */
        $policy = app(NotificationPolicyService::class);
        /** @var NotificationPreferenceService $preferences */
        $preferences = app(NotificationPreferenceService::class);

        // Find candidate users whose registered job title matches this job
        $users = User::query()
            ->where('job_title_id', $job->job_title_id)
            ->whereNotNull('email_verified_at')
            ->whereHas('fcmTokens', fn ($q) => $q->where('is_active', true))
            ->limit(100)
            ->get();

        $now = now();

        foreach ($users as $user) {
            // 2. Prevent sending multiple alerts to the same user in a single aggregation run
            if (isset($this->notifiedUsersInBatch[$user->id])) {
                continue;
            }

            $preference = $preferences->forUser($user);

            // 3. User opted in check
            if (! $preference->enabled) {
                continue;
            }

            // 4. Quiet hours check
            $localNow = $policy->localNow($preference, $now);
            if ($policy->inQuietHours($preference, $localNow)) {
                continue;
            }

            // 5. Check recent decisions for minimum 24h gap
            $recentDecisions = NotificationDecision::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->get();

            $lastActiveSend = $recentDecisions
                ->filter(fn (NotificationDecision $d) => in_array($d->status, NotificationDecision::ACTIVE_STATUSES, true))
                ->sortByDesc(fn (NotificationDecision $d) => $d->queued_at ?? $d->created_at)
                ->first();

            $minGapHours = (int) config('smart_notifications.minimum_gap_hours', 24);
            if ($lastActiveSend !== null) {
                $anchor = $lastActiveSend->queued_at ?? $lastActiveSend->created_at;
                if ($anchor !== null && $anchor->greaterThan($now->copy()->subHours($minGapHours))) {
                    continue;
                }
            }

            // 6. Check weekly notification cap
            $maxPerWeek = (int) ($preference->max_per_week ?: config('smart_notifications.default_max_per_week', 4));
            $weekCount = $recentDecisions
                ->filter(fn (NotificationDecision $d) => in_array($d->status, NotificationDecision::ACTIVE_STATUSES, true))
                ->count();

            if ($weekCount >= $maxPerWeek) {
                continue;
            }

            // 7. Idempotency per user per job
            $idempotencyKey = "realtime_job:{$user->id}:{$job->id}";
            if (NotificationDecision::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                continue;
            }

            // Mark user notified in this batch
            $this->notifiedUsersInBatch[$user->id] = true;

            $isEnglish = ($preference->language ?? 'ar') === 'en';
            $locationNote = $job->city ? ($isEnglish ? " in {$job->city}" : " في {$job->location}") : '';
            $title = $isEnglish ? '🎯 New Matching Job Opening' : '🎯 فرصة وظيفية جديدة تناسبك';
            $body = $isEnglish
                ? "New role: {$job->title}{$locationNote} at {$job->company}"
                : "تم نشر وظيفة جديدة: {$job->title}{$locationNote} لدى {$job->company}";

            $decision = NotificationDecision::query()->create([
                'user_id' => $user->id,
                'rule_key' => 'matching_job',
                'template_key' => 'matching_job',
                'context' => [
                    'type' => 'job_match',
                    'job_id' => (string) $job->id,
                    'job_title' => $job->title,
                    'company' => $job->company,
                    'action_type' => 'screen',
                    'action_url' => 'job-news/'.$job->id,
                    'language' => $preference->language ?? 'ar',
                ],
                'idempotency_key' => $idempotencyKey,
                'scheduled_for' => $now,
                'status' => 'queued',
                'queued_at' => $now,
            ]);

            SendPushNotificationJob::dispatch(
                userId: $user->id,
                title: $title,
                body: $body,
                type: 'job_match',
                actionType: 'screen',
                actionUrl: 'job-news/'.$job->id,
                data: [
                    'type' => 'job_match',
                    'job_id' => (string) $job->id,
                    'action_url' => 'job-news/'.$job->id,
                    'decision_id' => (string) $decision->id,
                ],
            );
        }
    }

    public function recordFeedSuccess(string $source, int $fetched): void
    {
        Cache::put("job_feed:{$source}:last_success_at", now()->toISOString(), now()->addDays(7));
        Cache::put("job_feed:{$source}:last_fetched_count", $fetched, now()->addDays(7));
        Cache::forget("job_feed:{$source}:consecutive_failures");
        Cache::forget("job_feed:{$source}:consecutive_zero_fetches");
    }

    public function recordFeedWarning(string $source, string $reason): void
    {
        $zeroCount = (int) Cache::get("job_feed:{$source}:consecutive_zero_fetches", 0) + 1;
        Cache::put("job_feed:{$source}:consecutive_zero_fetches", $zeroCount, now()->addDays(7));
        Cache::put("job_feed:{$source}:last_warning", $reason, now()->addDays(7));
        Cache::put("job_feed:{$source}:last_warning_at", now()->toISOString(), now()->addDays(7));

        Log::warning("Job feed {$source} warning: {$reason} (count: {$zeroCount})");
    }

    public function recordFeedFailure(string $source, string $error): void
    {
        $failCount = (int) Cache::get("job_feed:{$source}:consecutive_failures", 0) + 1;
        Cache::put("job_feed:{$source}:consecutive_failures", $failCount, now()->addDays(7));
        Cache::put("job_feed:{$source}:last_error", $error, now()->addDays(7));
        Cache::put("job_feed:{$source}:last_failure_at", now()->toISOString(), now()->addDays(7));

        Log::error("Job feed {$source} failed: {$error} (consecutive: {$failCount})");
    }

    /**
     * Get real-time health diagnostic for all configured feeds.
     *
     * @return list<array{
     *     source: string,
     *     name: string,
     *     type: string,
     *     healthy: bool,
     *     last_success_at: ?string,
     *     last_fetched_count: int,
     *     consecutive_failures: int,
     *     consecutive_zero_fetches: int,
     *     last_issue: ?string
     * }>
     */
    public function getFeedsHealth(): array
    {
        $health = [];
        foreach (self::SAUDI_FEEDS as $feed) {
            $source = $feed['source'];
            $lastSuccess = Cache::get("job_feed:{$source}:last_success_at");
            $lastCount = (int) Cache::get("job_feed:{$source}:last_fetched_count", 0);
            $consecutiveFails = (int) Cache::get("job_feed:{$source}:consecutive_failures", 0);
            $consecutiveZeroes = (int) Cache::get("job_feed:{$source}:consecutive_zero_fetches", 0);
            $lastError = Cache::get("job_feed:{$source}:last_error");
            $lastWarning = Cache::get("job_feed:{$source}:last_warning");

            $isHealthy = $consecutiveFails < 3 && $consecutiveZeroes < 3;

            $health[] = [
                'source' => $source,
                'name' => $feed['name'],
                'type' => $feed['type'],
                'healthy' => $isHealthy,
                'last_success_at' => $lastSuccess,
                'last_fetched_count' => $lastCount,
                'consecutive_failures' => $consecutiveFails,
                'consecutive_zero_fetches' => $consecutiveZeroes,
                'last_issue' => $lastError ?: $lastWarning,
            ];
        }

        return $health;
    }

    private function extractCompany(string $text): ?string
    {
        if (preg_match('/(?:لدى|في|عن|بـ|بواسطة)?\s*[\(\[]?(?:شركة|مجموعة|مؤسسة|بنك|مصرف|هيئة|مستشفى)\s+([^\)\],،\.\-\n]+)[\)\]]?/u', $text, $matches)) {
            $extracted = trim(strip_tags($matches[1]));
            $extracted = preg_replace('/^(?:عالمية|سعودية|كبرى|رائدة)\s+/u', '', $extracted);
            if (mb_strlen($extracted) >= 2 && mb_strlen($extracted) <= 50) {
                return $extracted;
            }
        }

        if (preg_match('/(?:لدى|في|بشركة|بـ|شركة)\s+([\p{Arabic}\w\s]+)/u', $text, $matches)) {
            $extracted = trim($matches[1]);
            if (mb_strlen($extracted) > 2 && mb_strlen($extracted) <= 50) {
                return $extracted;
            }
        }

        if (str_contains($text, '-')) {
            $parts = explode('-', $text);
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
            return 'عن بُعد (Remote)';
        }

        return 'المملكة العربية السعودية';
    }
}