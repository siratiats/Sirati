# Review: job post quality fixes + future scraping risks (READ-ONLY, <= 500 words)

Do not edit files. Review commit `eb11846` (backend) against `81f854d`: `git diff 81f854d eb11846 -- app tests database`.
Also app commit `8d52e09` in `flutter_app/` (`git -C flutter_app show 8d52e09`).

Files: `app/Services/Jobs/JobPostClassifier.php` (publish gate, titleFrom, languageOf, remoteOpenToSaudi),
`app/Services/Jobs/SaudiCities.php`, `JobTaxonomyMatcher::detectCity/locationLabel/normalizeText`,
`SaudiJobAggregatorService` (Telegram/Remotive/RSS adapters), `MobileContentController::jobNews` (language filter now
`language = X OR external_source IS NOT NULL`), `RepairJobListingsCommand` + migration `2026_10_29_000002`,
`tests/Feature/JobPostQualityTest.php`.

Measured on 234 saved live posts: emoji titles 136 -> 0, image stubs 31 -> 1, city 64% -> 79%, role 41% -> 51%,
stored 227 -> 176 (courses/admissions/stubs/US-only remote dropped; reposts dedupe).

Answer, ranked by severity, each with file:line and a concrete failing input:
1. Bugs: real jobs wrongly dropped (false rejects), junk still published, title cleaning that damages a real title,
   wrong city/language, regex issues (Unicode, catastrophic backtracking, Arabic prefixes), repair command not
   idempotent or touching curated rows, migration risk on a large production table.
2. Feed: does showing aggregated rows in both languages break anything (dedupe, notifications, dashboard, search,
   per-language counts)?
3. Future scraping failures: what will silently break later (Telegram markup changes, `t.me/s` rate limits/blocks,
   Remotive API changes, channels changing post style, new emoji/format, empty fetches) and what monitoring or
   fail-loud check should exist (per-channel yield, rejection reasons, alert thresholds). Say which are must-fix now
   vs later.
4. Tests: invariants that are missing or calibrated to examples.

End with VERDICT: APPROVE or CHANGES.
