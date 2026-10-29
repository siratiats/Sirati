# Jobs scraping review — 2026-10-29

Method: 234 live posts (4 pages each of @jobmag, @ewdifh, @jobs2ksa, plus the Remotive API) run through the real ingestion pipeline in an isolated test database. Raw rows: `scrape-audit.json`. Reviewed by Claude, Codex, Muse and Grok (`scrape-review-*.txt`).

## Apply links (the fix already merged into the PRs)

Across all 216 Telegram posts: **0 Telegram apply links** (it was 100% before). 59 have an employer link, 83 an email, 74 none (they show the post's own how-to-apply text).

## What users get today

| Channel | Stored | Link | Email | None | Company found | Role matched | Body < 120 chars |
|---|---|---|---|---|---|---|---|
| jobmag | 78 | 6 | 60 | 12 | 9 | 50 | 2 |
| ewdifh | 71 | 47 | 0 | 24 | 25 | 22 | 1 |
| jobs2ksa | 67 | 6 | 23 | 38 | 11 | 17 | **28** |
| Remotive | 11 | 11 | 0 | 0 | 11 | 11 | 0 |

## Problems found (all reviewers agree)

1. **Image-only posts**: 28 of 67 jobs2ksa posts are just "وظائف في القطيف"; the job is in an attached image. These rows carry no information.
2. **Not jobs**: free courses, Zoom training and graduate admissions (about 12, mostly ewdifh) appear as jobs.
3. **Messy titles**: 136 of 234 titles start with emoji ("🔴)", "➡️"), and some are a hashtag line ("وظائف_الشرقية #الدمام").
4. **Employer missing or wrong**: found in only 45 of 216 Telegram rows; the regex sometimes captures junk ("نينجا عن 27 فرصة وظيفية"). English posts ("Arabian Homes is seeking…") are not handled.
5. **Remote jobs invisible in Arabic**: Remotive rows are stored as English and the feed filters by UI language, so Arabic users never see them. English jobmag posts are stored as Arabic. Several Remotive jobs require US/EU residence.
6. **Weak "مناسبة لي" coverage**: a role is matched for only 89 of 216 Telegram rows; one example classed "Technical Support" as HR.
7. **Missing cities**: 78 of 216 rows have no city. Qatif, Ahsa, Rabigh, Jazan and others are not in the city map, so the region filter misses them.
8. **Thin history**: only the newest page (about 20 posts) is read per channel per run.
9. **Schedule**: the aggregator runs every 3 hours (8 a day); Remotive asks for about 4 fetches a day and attribution (Codex, citing Remotive's API docs).

## Recommended fixes, ranked

| # | Fix | Value | Effort |
|---|---|---|---|
| 1 | **Publish gate**: drop image-only stubs (photo marker + short text + no apply destination); drop courses and admissions unless there is a hiring cue; keep Tamheer and training that ends in a job. No OCR for now (error-prone on Arabic posters, PII). | High | Low |
| 2 | **Title cleaning**: strip leading emoji/symbols (keep `.NET`, `C++`); skip hashtag-only lines and take the first line with a role or hiring verb. | High | Low |
| 3 | **Language per post + remote eligibility**: detect Arabic vs English from the text; the feed shows both languages; Remotive keeps only Worldwide/Saudi/MENA jobs. | High | Low |
| 4 | **City map**: add Qatif, Ahsa/Hofuf, Rabigh, Yanbu, Taif, Jazan, Najran, Hail, Baha, Arar, Jouf, Khamis Mushait and others, with one shared city-to-region table for ingestion and API. | High | Low |
| 5 | **Employer extraction v2**: "تعلن شركة X عن", "لدى (X)", "الشركة:", "X is seeking/hiring"; reject captures with numbers + "فرصة"; leave unknown as unknown. | Medium | Medium |
| 6 | **Multi-role posts**: keep one card and show the roles as chips. Split only when each role has its own clear section and apply details. Grok and Codex prefer chips; Muse prefers splitting. My call: chips, because splitting duplicates notifications and can invent apply targets. | Medium | Medium |
| 7 | **Better role matching**: match role headings first, not one keyword in the body; add Arabic variants; track the match rate per channel. | Medium | Medium |
| 8 | **More history**: page back with `?before=` until a known post (cap 3–4 pages), with a delay between requests. | Medium | Low |
| 9 | **Quality ranking + health**: internal completeness score (apply destination, role, company, city, detail); per-channel kept/fetched yield, link rate and rejection reasons, with an alert when yield drops. | Medium | Medium |
| 10 | **Scam signals**: down-rank or drop posts asking for fees, national ID or IBAN; never auto-verify. | Medium | Low |
| 11 | **Remotive schedule**: fetch it at most about 4 times a day, with attribution. | Low | Low |

## Fixes 1-4: implemented (2026-10-29)

Same 234 saved live posts, rerun through the new pipeline:

| | Before | After |
|---|---|---|
| Stored rows | 227 | 176 (16 courses/admissions/stubs dropped, 28 image headers dropped, 3 US/EU-only remote jobs dropped, reposts now dedupe because their titles match) |
| Titles starting with emoji / symbols | 136 | 0 |
| Hashtag-line titles | 6 | 0 |
| Telegram rows with a city | 138 of 216 (64%) | 132 of 168 (79%); "الخبرة" no longer tags a post as Khobar |
| Telegram rows with a role | 89 of 216 (41%) | 86 of 168 (51%) |
| Image-only stubs (body < 120 chars) | 31 | 1 |
| Rows marked English | 11 (Remotive only) | 26 (Remotive + 18 English jobmag posts) |

- `JobPostClassifier`: publish gate (`rejectionReason`), `titleFrom`, `languageOf`, `remoteOpenToSaudi`.
- `SaudiCities`: one city -> label -> region table (35 cities + 5 provinces) for ingestion and the region filter.
- Feed: aggregated posts show in both app languages; curated ones only in their own. The detail body follows the post's language.
- `jobs:repair-listings` applies the same rules to stored Telegram rows and hides Remotive rows whose eligibility was never checked; migration `2026_10_29_000002` runs it.

## Better sources

- **Public employer job boards (best next step)**: Greenhouse, Lever and Workable publish public JSON job boards per employer. Codex found Saudi employers on them (Foodics on Workable, Soum on Lever, Pronto on Greenhouse). These links come from the reviewer and still need checking. Build the employer list from apply domains we already see in the audit. Public access is not the same as republication rights: confirm terms per source.
- **Jadarat / Qiwa / Aramco careers**: the portals exist but no public API or feed is known. Don't scrape them; a partnership or official feed is needed.
- **LinkedIn, Indeed, Bayt, GulfTalent**: no lawful self-serve ingestion. Skip.
- **Telegram**: stay on the public `t.me/s` preview at a low rate. Long term, move volume toward official and ATS feeds.

## Risks

- Telegram preview scraping is a grey area: keep the rate low.
- Posts contain recruiters' personal phones and emails: expose only the apply contact.
- Fake jobs: watch for fees, visa payments, or WhatsApp-only contact with no company.
- Image OCR needs a privacy review first.
