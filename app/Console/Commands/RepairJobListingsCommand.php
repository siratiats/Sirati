<?php

namespace App\Console\Commands;

use App\Models\JobNews;
use App\Services\Jobs\ApplyLinkExtractor;
use Illuminate\Console\Command;

/**
 * Brings stored listings in line with the current apply-link and trust rules:
 * drops apply links that are not application pages (Telegram, social, shorteners),
 * picks up an email from the text, strips channel promo footers from aggregated
 * posts, clears placeholder employer names and fills the duplicate fingerprint.
 * Safe to run repeatedly.
 */
class RepairJobListingsCommand extends Command
{
    protected $signature = 'jobs:repair-listings';

    protected $description = 'Remove non-application apply links, promo footers and placeholder employers from job listings';

    /** Employer names the aggregator used to invent when none was found. */
    public const PLACEHOLDER_COMPANIES = ['جهة سعودية معتمدة', 'جهة سعودية رائدة', 'شركة عالمية'];

    public function handle(ApplyLinkExtractor $extractor): int
    {
        $changed = 0;

        JobNews::query()->orderBy('id')->chunkById(200, function ($rows) use ($extractor, &$changed): void {
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
}
