<?php

namespace App\Console\Commands;

use App\Models\JobNews;
use App\Services\Jobs\ApplyLinkExtractor;
use App\Services\Jobs\JobPostClassifier;
use App\Services\Jobs\JobTaxonomyMatcher;
use Illuminate\Console\Command;

/**
 * Brings stored listings in line with the current apply-link and trust rules:
 * drops apply links that are not application pages (Telegram, social, shorteners),
 * picks up an email from the text, strips channel promo footers from aggregated
 * posts, clears placeholder employer names and fills the duplicate fingerprint.
 * Telegram posts get the current title, language and city rules, and those
 * that are not jobs (image-only headers, courses, admissions) are unpublished.
 * Remote jobs stored before their candidate location was checked are
 * unpublished; the next fetch republishes the ones open to Saudi Arabia.
 * Safe to run repeatedly.
 */
class RepairJobListingsCommand extends Command
{
    protected $signature = 'jobs:repair-listings';

    protected $description = 'Remove non-application apply links, promo footers and placeholder employers from job listings';

    /** Employer names the aggregator used to invent when none was found. */
    public const PLACEHOLDER_COMPANIES = ['جهة سعودية معتمدة', 'جهة سعودية رائدة', 'شركة عالمية'];

    /** The location every Remotive job got before eligibility was checked. */
    public const UNCHECKED_REMOTE_LOCATION = 'عن بُعد (Worldwide Remote)';

    public function handle(ApplyLinkExtractor $extractor, JobPostClassifier $classifier, JobTaxonomyMatcher $matcher): int
    {
        $changed = 0;

        JobNews::query()->orderBy('id')->chunkById(200, function ($rows) use ($extractor, $classifier, $matcher, &$changed): void {
            foreach ($rows as $job) {
                $aggregated = $job->external_source !== null;

                if ($job->apply_url !== null && ! $extractor->isAllowedApplyUrl($job->apply_url)) {
                    $job->apply_url = null;
                }

                if ($aggregated) {
                    $job->body = $extractor->stripPromoFooter((string) $job->body);
                    if (in_array($job->company, self::PLACEHOLDER_COMPANIES, true)) {
                        $job->company = null;
                    }
                }

                if ($job->apply_url === null && $job->apply_email === null) {
                    $job->apply_email = $extractor->fromText((string) $job->body)['email'];
                }

                // Only listings users can still see are re-classified, so the
                // work stays bounded by the 21-day lifetime, not the table size.
                $visible = $job->is_published && ($job->valid_until === null || ! $job->valid_until->isPast() || $job->valid_until->isToday());

                if ($visible && str_starts_with((string) $job->external_source, 'telegram_')) {
                    $this->reclassifyTelegramPost($job, $classifier, $matcher);
                } elseif ($visible && $aggregated && $job->location === self::UNCHECKED_REMOTE_LOCATION) {
                    $job->is_published = false;
                }

                $job->fingerprint = JobNews::fingerprintFor($job->title, $job->company, $job->city, $job->apply_url ?? $job->apply_email);

                if ($job->isDirty()) {
                    $job->save();
                    $changed++;
                }
            }
        });

        $this->info("Repaired {$changed} job listing(s).");

        return self::SUCCESS;
    }

    /** Re-derives what ingestion derives from the stored post text. */
    private function reclassifyTelegramPost(JobNews $job, JobPostClassifier $classifier, JobTaxonomyMatcher $matcher): void
    {
        $body = (string) $job->body;
        $apply = ['url' => $job->apply_url, 'email' => $job->apply_email];
        // Whether the post had a photo was not stored; most Telegram job
        // headers did, so the stricter photo bar applies. It never drops a
        // post that names a role or has a way to apply.
        if ($classifier->rejectionReason($body, $apply, hasMedia: true) !== null) {
            $job->is_published = false;

            return;
        }

        // Only titles the old rules left messy are re-derived.
        $title = $classifier->isMessyTitle((string) $job->title) ? $classifier->titleFrom($body) : '';
        if ($title !== '') {
            $job->title = mb_substr($title, 0, 180);
        }
        $job->language = $classifier->languageOf($job->title."\n".$body);
        $job->location = mb_substr($matcher->locationLabel($body), 0, 160);

        $match = $matcher->match($job->title, $body, $job->location);
        $job->city = $match['city'];
        $job->is_remote = $match['is_remote'];
        // A role the current rules no longer find is cleared, not kept.
        $job->job_title_id = $match['job_title_id'];
        $job->category = $match['category'];
    }
}
