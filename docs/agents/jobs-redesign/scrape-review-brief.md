# Scraping quality review (READ-ONLY, <= 450 words, ranked)

Goal: more, better jobs in the Sirati jobs feed (Saudi job seekers, Arabic first). Do not edit files.

Code: `app/Services/Jobs/SaudiJobAggregatorService.php` (feeds, Telegram/Remotive/RSS adapters, title/company/location extraction, persistAggregatedJob), `app/Services/Jobs/JobTaxonomyMatcher.php` (category, job title, city), `app/Services/Jobs/ApplyLinkExtractor.php`, `app/Http/Controllers/MobileContentController.php::jobNews` (feed filters by `language`).

Audit data (real pipeline over 234 live posts fetched 2026-10-29, 4 pages per channel + Remotive): `docs/agents/jobs-redesign/scrape-audit.json` (rows: title, company, city, category, job_title, apply, body).

Measured:
| channel | stored | link | email | none | company | role matched | body < 120 chars |
|---|---|---|---|---|---|---|---|
| jobmag | 78 | 6 | 60 | 12 | 9 | 50 | 2 |
| ewdifh | 71 | 47 | 0 | 24 | 25 | 22 | 1 |
| jobs2ksa | 67 | 6 | 23 | 38 | 11 | 17 | 28 |
| remotive | 11 | 11 | 0 | 0 | 11 | 11 | 0 |

Findings so far (Claude):
1. jobs2ksa: 28/67 posts are only "وظائف في القطيف" - the details are in an attached image (photo post), text is just a header. Useless rows.
2. 136/234 titles start with emoji/symbol noise ("🔴)", "➡️"); jobmag titles are sometimes a hashtag line ("وظائف_الشرقية #الدمام").
3. Non-job content mixed in: free courses, Zoom training, graduate-studies admission (ewdifh) ~12+.
4. 29 posts list several roles in one post (bulleted); one generic title.
5. Company found in only 45/216 Telegram rows; regex grabs junk ("نينجا عن 27 فرصة وظيفية"); English posts ("Arabian Homes is seeking...") not handled; jobmag English posts stored with language 'ar'.
6. Role match (job_title_id) 89/216 -> "مناسبة لي" coverage weak.
7. 78/216 rows without city; Qatif/Ahsa/Rabigh/Jazan not in the city map, so the region filter misses them.
8. Remotive rows are language 'en' and the feed filters `language = ar` for Arabic users, so remote jobs never show in the Arabic app; several require USA/EU residence.
9. Only one page per channel per run; 21-day retirement.

Answer:
1. Rank the fixes by user value / effort, with the concrete rule or approach for each (e.g. how to classify job vs course vs admission; photo-only posts: skip vs OCR; multi-role splitting vs role chips; title cleaning; company patterns; language handling).
2. Better sources for Saudi jobs that are legal/ToS-safe to ingest (official APIs, RSS, sitemaps, gov/semi-gov career portals, ATS public endpoints like Greenhouse/Lever/Workable boards of Saudi employers, Remotive filters). Say which you are confident exist vs guesses.
3. Quality signals to rank or drop posts (completeness score etc.) and how to measure source health over time.
4. Risks (ToS, rate limits, scams/fake jobs, PII in posts).
