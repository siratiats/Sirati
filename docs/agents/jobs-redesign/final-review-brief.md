# Final review: job post quality (READ-ONLY, <= 450 words, ranked)

Do not edit files and do not run the app. Review Claude's two commits: `git diff 81f854d 9fb2f44 -- app tests database`
(`eb11846` adds the rules, `9fb2f44` applies Codex's review; `docs/agents/jobs-redesign/quality-review-codex.txt` lists
what Codex found and what was fixed). App side: `git -C flutter_app show 8d52e09`.

Key files: `app/Services/Jobs/JobPostClassifier.php`, `SaudiCities.php`, `JobTaxonomyMatcher::detectCity`,
`SaudiJobAggregatorService` (Telegram/Remotive adapters, `recordFeedRun`, `retireRejected`, `getFeedsHealth`),
`MobileContentController::jobNews`, `RepairJobListingsCommand`, `tests/Feature/JobPostQualityTest.php`.

To test behaviour you may run only `php vendor/bin/phpunit tests/Feature/JobPostQualityTest.php` (absolute paths).

Find what Codex missed:
1. Real jobs still wrongly dropped, or junk still published, with a concrete Arabic/English input.
2. Regressions from the Codex fixes (OFFER_CUE vs HIRING_CUE, workplace cue window, retire-on-reject unpublishing
   something curated or a duplicate's original, health warnings firing on normal runs).
3. Scraping failures likely in the next months and whether the monitoring would catch them.
4. Tests calibrated to examples rather than invariants.

Each finding: severity, file:line, failing input, suggested fix. End with VERDICT: APPROVE or CHANGES.
