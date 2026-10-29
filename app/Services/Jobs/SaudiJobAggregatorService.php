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
        private readonly ApplyLinkExtractor $applyLinks,
        private readonly JobPostClassifier $classifier,
    ) {}

    /** Aggregated posts rarely state a deadline; they retire after this many days. */
    public const AGGREGATED_LIFETIME_DAYS = 21;

    /** A feed with no successful run for this long is unhealthy. */
    public const STALE_AFTER_HOURS = 12;

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
        $rejected = [];

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
            $fullText = trim(html_entity_decode(strip_tags(str_replace(['<br/>', '<br>', '<br />'], "\n", $rawText)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $cleanText = $this->applyLinks->stripPromoFooter($fullText);
            if (mb_strlen($cleanText) < 15) {
                continue;
            }

            $fetched++;

            // Only links inside the message text count (the wrapper also holds
            // the channel's own avatar link). No link or email means no apply
            // action; the Telegram post is kept as the source `url` only.
            $apply = $this->applyLinks->fromMessageHtml($rawText);

            // Image-only headers, near-empty posts, courses and admissions are
            // not stored at all.
            $hasMedia = (bool) preg_match('/tgme_widget_message_(?:photo|video|document|grouped)/', $part);
            $reason = $this->classifier->rejectionReason($cleanText, $apply, $hasMedia);
            if ($reason !== null) {
                $rejected[$reason] = ($rejected[$reason] ?? 0) + 1;
                $this->retireRejected($feed['source'], $externalId);
                continue;
            }

            // Published timestamp
            $pubDate = now();
            if (preg_match('/<time[^>]*datetime="([^"]+)"/', $part, $dm)) {
                $pubDate = Carbon::parse($dm[1]);
            }

            $title = $this->classifier->titleFrom($cleanText);
            if ($title === '') {
                $title = Str::limit($cleanText, 60, '...');
            }

            // Unknown employer stays unknown: only an explicit "شركة/مجموعة/…" name counts.
            $company = $this->extractCompany($cleanText, strict: true);
            $location = $this->extractLocation($cleanText);
            $match = $this->matcher->match($title, $cleanText, $location);

            $jobData = [
                'language' => $this->classifier->languageOf($title."\n".$cleanText),
                'title' => Str::limit($title, 180, ''),
                'company' => $company !== null ? Str::limit($company, 160, '') : null,
                'location' => Str::limit($location ?: 'المملكة العربية السعودية', 160, ''),
                'body' => $cleanText,
                'url' => "https://t.me/{$postSlug}",
                'apply_url' => $apply['url'],
                'apply_email' => $apply['email'],
                'published_at' => $pubDate,
                'valid_from' => $pubDate->toDateString(),
                'valid_until' => $pubDate->copy()->addDays(self::AGGREGATED_LIFETIME_DAYS)->toDateString(),
                'is_published' => true,
                'source' => $feed['name'] ?? $feed['source'],
                'external_source' => $feed['source'],
                'external_id' => $externalId,
                'job_title_id' => $match['job_title_id'],
                'category' => $match['category'],
                'city' => $match['city'],
                'is_remote' => $match['is_remote'],
            ];

            match ($this->persistAggregatedJob($jobData)) {
                'created' => $created++,
                'updated' => $updated++,
                default => null,
            };
        }

        if ($fetched === 0) {
            $this->recordFeedWarning($feed['source'], "Channel @{$channel} returned 0 parseable job posts");
        } else {
            $this->recordFeedRun($feed['source'], $fetched, $rejected);
        }

        return [
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
            'rejected' => $rejected,
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
        if (! is_array($data['jobs'] ?? null)) {
            $this->recordFeedWarning($feed['source'], 'Remotive API response has no jobs list (format changed?)');

            return ['fetched' => 0, 'created' => 0, 'updated' => 0];
        }
        $jobs = $data['jobs'];

        $fetched = 0;
        $created = 0;
        $updated = 0;
        $rejected = [];

        foreach ($jobs as $item) {
            $title = trim($item['title'] ?? '');
            if ($title === '') {
                continue;
            }

            $fetched++;
            $externalId = (string) ($item['id'] ?? md5($title));

            // Most remote jobs are open only to residents of other regions.
            $requiredLocation = trim((string) ($item['candidate_required_location'] ?? ''));
            if (! $this->classifier->remoteOpenToSaudi($requiredLocation)) {
                $rejected['closed_to_saudi'] = ($rejected['closed_to_saudi'] ?? 0) + 1;
                $this->retireRejected($feed['source'], $externalId);
                continue;
            }

            $company = trim((string) ($item['company_name'] ?? ''));
            $description = trim(strip_tags((string) ($item['description'] ?? '')));
            $urlLink = trim((string) ($item['url'] ?? ''));
            $pubDate = ! empty($item['publication_date']) ? Carbon::parse((string) $item['publication_date']) : now();

            $match = $this->matcher->match($title, $description, 'remote');

            $jobData = [
                'language' => $this->classifier->languageOf($title."\n".$description),
                'title' => Str::limit($title, 180, ''),
                'company' => $company !== '' ? Str::limit($company, 160, '') : null,
                'location' => Str::limit('عن بُعد ('.$requiredLocation.')', 160, ''),
                'body' => $description ?: $title,
                'url' => $urlLink,
                'apply_url' => $this->applyLinks->isAllowedApplyUrl($urlLink) ? $urlLink : null,
                'apply_email' => null,
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

            match ($this->persistAggregatedJob($jobData)) {
                'created' => $created++,
                'updated' => $updated++,
                default => null,
            };
        }

        if ($fetched === 0) {
            $this->recordFeedWarning($feed['source'], 'Remotive API returned 0 job posts');
        } else {
            $this->recordFeedRun($feed['source'], $fetched, $rejected);
        }

        return [
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
            'rejected' => $rejected,
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
                'language' => $this->classifier->languageOf($title."\n".$description),
                'title' => Str::limit($title, 180, ''),
                'company' => $company !== null ? Str::limit($company, 160, '') : null,
                'location' => Str::limit($location ?: 'المملكة العربية السعودية', 160, ''),
                'body' => $description ?: $title,
                'url' => $link,
                'apply_url' => $this->applyLinks->isAllowedApplyUrl($link) ? $link : null,
                'apply_email' => null,
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

            match ($this->persistAggregatedJob($jobData)) {
                'created' => $created++,
                'updated' => $updated++,
                default => null,
            };
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
     * Store one aggregated post. The same vacancy reposted by another source
     * (same fingerprint, still active) is not stored again; if the repost has
     * an apply destination the original lacks, the original gains it. A
     * re-fetch never replaces a stored apply destination with a missing one.
     *
     * @param  array<string, mixed>  $jobData
     * @return 'created'|'updated'|'duplicate'
     */
    private function persistAggregatedJob(array $jobData): string
    {
        $hasApply = $jobData['apply_url'] !== null || $jobData['apply_email'] !== null;

        $existing = JobNews::query()
            ->where('external_source', $jobData['external_source'])
            ->where('external_id', $jobData['external_id'])
            ->first();

        // A re-fetch never downgrades the destination: a stored link stays
        // unless a new link replaces it; a stored email stays unless the
        // re-fetch has some destination. Identity uses the values that will
        // actually be stored.
        if ($existing !== null) {
            if ($jobData['apply_url'] === null && $existing->apply_url !== null) {
                $jobData['apply_url'] = $existing->apply_url;
            }
            if (! $hasApply && $existing->apply_email !== null) {
                $jobData['apply_email'] = $existing->apply_email;
            }
        }

        $jobData['fingerprint'] = JobNews::fingerprintFor(
            $jobData['title'],
            $jobData['company'],
            $jobData['city'],
            $jobData['apply_url'] ?? $jobData['apply_email'],
        );
        $hasApply = $jobData['apply_url'] !== null || $jobData['apply_email'] !== null;

        if ($existing === null && $jobData['fingerprint'] !== null) {
            $original = JobNews::query()
                ->active()
                ->where('fingerprint', $jobData['fingerprint'])
                ->orderBy('id')
                ->first();

            // Different apply destinations mean different vacancies (e.g. two
            // requisitions with the same title): never merge those.
            $originalDestination = $original?->apply_url ?? $original?->apply_email;
            $newDestination = $jobData['apply_url'] ?? $jobData['apply_email'];
            $conflict = $originalDestination !== null && $newDestination !== null
                && JobNews::normalizeDestination($originalDestination) !== JobNews::normalizeDestination($newDestination);

            if ($original !== null && ! $conflict) {
                if ($hasApply && $original->apply_url === null && $original->apply_email === null) {
                    $original->forceFill([
                        'apply_url' => $jobData['apply_url'],
                        'apply_email' => $jobData['apply_email'],
                    ])->save();
                }

                return 'duplicate';
            }
        }

        if ($existing !== null) {
            $existing->fill($jobData)->save();

            return 'updated';
        }

        $job = JobNews::query()->create($jobData);
        $this->notifyInterestedCandidates($job);

        return 'created';
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

            $isEnglish = ($preference->language ?? 'ar') === 'en';
            $locationNote = $job->city ? ($isEnglish ? " in {$job->city}" : " في {$job->location}") : '';
            $title = $isEnglish ? '🎯 New Matching Job Opening' : '🎯 فرصة وظيفية جديدة تناسبك';
            $body = $isEnglish
                ? "New role: {$job->title}{$locationNote} at {$job->company}"
                : "تم نشر وظيفة جديدة: {$job->title}{$locationNote} لدى {$job->company}";

            // The unique idempotency_key makes this atomic across concurrent runs.
            $decision = NotificationDecision::query()->createOrFirst([
                'idempotency_key' => $idempotencyKey,
            ], [
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
                'scheduled_for' => $now,
                'status' => 'queued',
                'queued_at' => $now,
            ]);

            if (! $decision->wasRecentlyCreated) {
                continue;
            }

            // Mark user notified in this batch
            $this->notifiedUsersInBatch[$user->id] = true;

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

    /**
     * A run that parsed posts. Rejections are kept per reason; a run where
     * every parsed post was rejected counts as a warning (a channel that only
     * posts courses, or a markup change that breaks extraction), so three in a
     * row make the feed unhealthy.
     *
     * @param  array<string, int>  $rejected
     */
    public function recordFeedRun(string $source, int $fetched, array $rejected): void
    {
        $accepted = $fetched - array_sum($rejected);
        Cache::put("job_feed:{$source}:last_accepted_count", $accepted, now()->addDays(7));
        Cache::put("job_feed:{$source}:last_rejections", $rejected, now()->addDays(7));

        if ($accepted <= 0) {
            $reasons = collect($rejected)->map(fn (int $n, string $r): string => "{$r}={$n}")->implode(', ');
            $this->recordFeedWarning($source, "Parsed {$fetched} posts but accepted none ({$reasons})");

            return;
        }

        $this->recordFeedSuccess($source, $fetched);
    }

    /**
     * A previously stored post that now fails the rules (edited into a
     * non-job, or a remote job closed to Saudi Arabia) is unpublished.
     */
    private function retireRejected(string $source, string $externalId): void
    {
        JobNews::query()
            ->where('external_source', $source)
            ->where('external_id', $externalId)
            ->where('is_published', true)
            ->update(['is_published' => false]);
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
     *     last_accepted_count: int,
     *     last_rejections: array<string, int>,
     *     stale: bool,
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

            // The scheduler runs every few hours; no success for 12 hours
            // means runs are missing or failing silently.
            $stale = $lastSuccess !== null && Carbon::parse($lastSuccess)->lt(now()->subHours(self::STALE_AFTER_HOURS));
            $isHealthy = $consecutiveFails < 3 && $consecutiveZeroes < 3 && ! $stale;

            $health[] = [
                'source' => $source,
                'name' => $feed['name'],
                'type' => $feed['type'],
                'healthy' => $isHealthy,
                'last_success_at' => $lastSuccess,
                'last_fetched_count' => $lastCount,
                'consecutive_failures' => $consecutiveFails,
                'consecutive_zero_fetches' => $consecutiveZeroes,
                'last_accepted_count' => (int) Cache::get("job_feed:{$source}:last_accepted_count", 0),
                'last_rejections' => (array) Cache::get("job_feed:{$source}:last_rejections", []),
                'stale' => $stale,
                'last_issue' => $stale ? 'No successful run for over '.self::STALE_AFTER_HOURS.' hours' : ($lastError ?: $lastWarning),
            ];
        }

        return $health;
    }

    private function extractCompany(string $text, bool $strict = false): ?string
    {
        if (preg_match('/(?:لدى|في|عن|بـ|بواسطة)?\s*[\(\[]?(?:شركة|مجموعة|مؤسسة|بنك|مصرف|هيئة|مستشفى)\s+([^\)\],،\.\-\n]+)[\)\]]?/u', $text, $matches)) {
            $extracted = trim(strip_tags($matches[1]));
            $extracted = preg_replace('/^(?:عالمية|سعودية|كبرى|رائدة)\s+/u', '', $extracted);
            if (mb_strlen($extracted) >= 2 && mb_strlen($extracted) <= 50) {
                return $extracted;
            }
        }

        if ($strict) {
            return null;
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
        return $this->matcher->locationLabel($text);
    }
}
