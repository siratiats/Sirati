# Job news

Signed-in users browse job postings in the dashboard **Job News** tab (Arabic label "الوظائف"). They search and filter, see a match score when the API sends one, open a posting, apply on an external site, or start a CV tailored to that job. There is no saved-jobs list.

## User flow

1. **List** (`JobNewsScreen`). Header title is the payload `title`, or "Job News" / "أخبار الوظائف". The bell opens `/notifications` (badge from `SessionCache.unreadCount`). The avatar opens `/settings`.
   - If `profile_context.has_profile` is true, a banner shows `job_title_name` (fallback `career_field`) and `location` (fallback `city`). **Match** / **Matched** toggles the `matched` preset.
   - Search field, debounced 350 ms. Hint: "Search for a job or company...". The query is trimmed before the request.
   - Preset chips: All, Best Match (`matched`), Government (`gov`), Enterprise (`enterprise`), Tamheer (`tamheer`), Remote (`remote`).
   - Category chips: All, Tech, Finance, Health (`all`, `tech`, `finance`, `health`).
   - City chips: All Locations, Riyadh, Jeddah, Dammam, Khobar, Mecca, Medina, Remote (`all`, `riyadh`, `jeddah`, `dammam`, `khobar`, `mecca`, `medina`, `remote`).
   - Loading: `JobNewsSkeleton`. A later reload keeps the previous list and shows a thin progress bar when the provider still has a value (`feed.isLoading`).
   - Error with no retained value: `AppErrorState`. `ApiException.displayMessage`, otherwise "Could not load job news." Retry invalidates the feed. The provider does not auto-retry.
   - Offline success: `OfflineCacheBanner` ("Offline — showing saved data") when the payload is marked `__sirati_offline`.
   - Pull-to-refresh always bypasses the memory cache.
   - First item is the featured card (badges, up to three skills, salary range, a label that says Apply Now). Every later item is a `JobNewsCard`. The first of those is badged "New" because it is index 0, not because of a date. Tap either card to open details.
   - No rows at all, or only the featured row: under "Latest Postings" the empty sentence is "No job news is published yet."
2. **Details** (`JobNewsDetailScreen`) use the list object. They do not call `jobNewsItem`. App bar: "Job Details" / "تفاصيل الخبر".
   - Verified / Tamheer badges, bilingual title, chips for company, location, salary range, published time, and valid-until.
   - If `match_score` is present: percent (clamped 0–100), bar, and every `match_reasons` line. Score ≥ 80 uses the high-match colours (`highMatchThreshold`).
   - All `skills_required`. A skill is marked matched only by `isSkillMatched` against `matched_skills` (case, spacing, and whole-word; "Java" does not match "JavaScript").
   - Body via `LocaleFormat.mixedBody`.
   - **Tailor My CV for this Job** pushes `/create-cv` with a `CvTargetJob` (title clipped to 160, description to 4000).
   - **Apply on Official Site** only if `apply_url` or `url` is non-empty (`actionUrl`). `launchSafeExternalUrl`; failure snackbar "Could not open the application page."
3. **Saved jobs.** The list card draws `Icons.bookmark_border_rounded` (`job_news_card.dart`). It has no tap handler, no provider, and no API. Nothing is stored.

Returning to the tab (`isActive` goes from false to true) calls `refreshJobNews`, which refetches with `force: true`. The standalone `/job-news` route uses `isActive: true` always, so that refresh-on-focus does not run.

## Entry points

| Entry | What opens |
| --- | --- |
| Bottom nav `DashboardTab.jobNews` | `JobNewsScreen(isActive: …)` inside `HomeScreen`. Labels: "Job News" / "الوظائف". |
| `AppRoutes.jobNews` (`/job-news`) | Standalone `JobNewsScreen`. Web preview `?screen=job-news`. `sirati://` paths parse through `AppRouter`. |
| Notification `action_type: screen` with `job-news` or `jobs` | `OpenTab(DashboardTab.jobNews)`. No job id is read (`notification_target.dart`). |
| Detail tailor button | `AppRoutes.createCv` plus `CvTargetJob`. |
| Detail apply button | External `apply_url`, else `url`. |

There is no `/job-news/{id}` route.

## Code map

| Path | Responsibility |
| --- | --- |
| `lib/features/jobs/presentation/job_news_screen.dart` | Tab layout, search debounce, refresh-on-focus, open detail. |
| `lib/features/jobs/presentation/job_news_detail_screen.dart` | Detail, tailor, external apply. |
| `lib/features/jobs/presentation/widgets/job_news_filters_bar.dart` | Preset, category, and city chips; search field. |
| `lib/features/jobs/presentation/widgets/profile_context_banner.dart` | Profile banner and match toggle. |
| `lib/features/jobs/presentation/widgets/featured_job_card.dart` | First-item card. |
| `lib/features/jobs/presentation/widgets/job_news_card.dart` | List row. |
| `lib/features/jobs/presentation/widgets/job_news_common.dart` | `MetaChip`, empty copy. |
| `lib/features/jobs/presentation/widgets/job_news_format.dart` | Published and valid-until labels. |
| `lib/features/jobs/presentation/widgets/job_title_display.dart` | Split bilingual titles. |
| `lib/features/jobs/application/job_news_controllers.dart` | Filter notifier and feed notifier. |
| `lib/features/jobs/domain/job_news_filters.dart` | Filter value and query mapping. |
| `lib/features/jobs/domain/job_news_feed.dart` | Feed, profile context, match helpers. |
| `lib/shared/models/job_news.dart` | `JobNews.fromJson`. |
| `lib/shared/models/cv_target_job.dart` | Clip rules for the tailor hand-off. |
| `lib/shared/services/mobile_content_service.dart` | `jobNews`, `jobNewsItem`, memory and disk cache. |

## State and data

`jobNewsFiltersProvider` is an autoDispose `Notifier<JobNewsFilters>`. Default preset, category, and city are `all`; query is empty. Setters: `setPreset`, `setCategory`, `setCity`, `setQuery` (trims), `toggleMatched` (`matched` ↔ `all`). Not written to SharedPreferences. Dropped when the provider is disposed.

`jobNewsFeedProvider` is an autoDispose family on the English flag (`retry` disabled). It watches the filters. The first build may use cache (`force: false`). Every later build sets `force: true` (filter change, `refresh`, return to the tab). `refreshJobNews` awaits that refresh and swallows the error so pull-to-refresh does not throw; the provider still exposes the error.

`MobileContentService` keeps a process-wide memory cache, TTL 60 seconds, or 30 seconds when `q` is non-empty. Key shape: `job-news:{lang}:{category}:{preset}:{city}:{jobTitleId}:{remote}:{verified}:{source}:{employment}:{query}`.

Disk cache (`DiskCache`, SharedPreferences prefix `sirati_cache_`) is only the unfiltered feed. `DiskCache.newsKey` is `sirati_cache_news_ar` or `sirati_cache_news_en`; each stored envelope carries a session epoch. Filtered and searched responses are memory-only. On network failure the service returns the last memory entry, else a disk entry from the current epoch, and sets `__sirati_offline`. Schema or epoch mismatch, or a payload over about 200 KB, is not kept. Schema version 2 discards older envelopes that have no epoch.

Logout (`AuthApiService._clearLocalSession`) and session expiry (`AuthSessionGuard`) await `MobileContentService.clearAllCaches()`. This advances the memory generation synchronously, clears memory, then rotates a persisted disk epoch and removes every `sirati_cache_*` entry, including legacy unscoped entries. All `_cached` endpoints capture the generation before fetching: an old response still reaches its original caller, but cannot populate either cache or use a stale offline fallback. Disk reads and writes awaiting preferences also check their generation. Already-dispatched platform writes can finish after the wipe, but retain the old epoch and cannot be read by the next session, even after an app restart. New disk operations wait for pending clears. The epoch marker (`sirati_content_cache_epoch`) survives entry removal and contains no account data.

`JobNewsFeed.featured` is `items.first` or null. `latest` is `items.sublist(1)`, or empty when there is zero or one item, so a single job is not drawn twice.

## API

`GET` through `ApiClient` (`Authorization: Bearer` when a token exists, `Accept: application/json`, `X-Sirati-Async: 1`). Paths are relative to `ApiConfig.baseUrl`.

| Call | Method and path | Query |
| --- | --- | --- |
| `jobNews` | `GET /mobile/job-news` | `lang` is `en` or `ar`. Omitted when the filter is `all` or empty: `category`, `preset`, `city`, `q`. `is_remote=1` when preset or city is `remote`. The service can also send `job_title_id`, `is_verified=1`, `source_type`, `employment_type`. This feature never sets those. A city chip of Remote still sends `city=remote` and `is_remote=1`. |
| `jobNewsItem` | `GET /mobile/job-news/{id}?lang=` | Implemented on the service. No screen calls it. |

The UI reads `data` as: `title`, `items[]`, `profile_context`. Each item via `JobNews.fromJson`: `id`, `language` (default `ar`), `title`, `company`, `location`, `city`, `is_remote`, `body`, `category` (default `general`), `job_title_id`, `url`, `apply_url`, `valid_from`, `valid_until`, `valid_until_label`, `published_label`, `published_at`, `is_verified`, `source_type` (default `general`), `source_type_label`, `source_badge`, `employment_type` (default `full_time`), `experience_level`, `salary_min`, `salary_max`, `salary_range`, `skills_required`, `match_score`, `match_reasons`, `matched_skills`, `missing_skills`.

Flags: `isGov` when `source_type == gov`; `isEnterprise` when `enterprise`; `isTamheer` when `source_type` or `employment_type` is `tamheer`. Apply link is `apply_url`, then `url`.

Profile: `has_profile` must be boolean true. Career text prefers `job_title_name` over `career_field`. City text prefers `location` over `city`.

Errors: a thrown `ApiException` or other error with no cached payload becomes the list error state. Malformed `items` entries that are not maps are skipped. A 401 clears caches via the session guard.

## Localization and RTL

Copy on this feature is inline from `AppLocale.isEnglish`, not ARB. `lang` on the request follows that flag, so Arabic and English are separate cache entries.

`JobTitleDisplay` splits a title with `ParsedJobTitle`: Arabic primary on top (RTL), English secondary forced LTR. Company on the featured card and detail uses `LocaleFormat.mixedTitle` (Latin tokens protected in Arabic). The body uses `LocaleFormat.mixedBody`. Dates use `AppFormat.relativeTime` / `shortDate` from `published_at` / `valid_until`, with API label strings and `AppFormat.digits` as fallback.

The search icon is a prefix in English and a suffix in Arabic (physical left vs right), not `DirectionalIcon`. List cards hard-code "Details →" and "التفاصيل ←" (`job_news_card.dart`). The bottom nav relies on ambient `Directionality`, so Job News sits at the start edge (right in Arabic).

## Tests

| File | What it locks |
| --- | --- |
| `test/features/jobs/job_news_domain_test.dart` | Featured/latest do not duplicate; bad items skipped; default title; offline marker; profile key preference; `has_profile`; `isHighMatch`; `all` omitted from params; remote rule; filter equality. |
| `test/features/jobs/job_news_controllers_test.dart` | First load `force: false`, later loads `force: true`; city `remote` plus category and trimmed query; `toggleMatched`; one failure, no silent retry. |
| `test/shared/mobile_content_cache_isolation_test.dart` | Every cached endpoint rejects late storage after a session clear; delayed platform writes remain unreadable after storage reload; stale offline fallbacks cannot refill memory; normal memory hits and same-session disk fallback still work; legacy entries are wiped. |
| `test/features/jobs/job_news_detail_test.dart` | `isSkillMatched` whole words; score clamp; `CvTargetJob` clip; detail marks only real matches; Tailor pushes the job into create-cv; detail text AA. |
| `test/unit/job_news_model_test.dart` | Full JSON parse, gov/enterprise/tamheer flags, null-safe defaults. |
| `test/features/dashboard/dashboard_domain_test.dart` | Notification routes `job-news` and `jobs` open the job-news tab. |
| `test/dark_mode_12_screen_walk_test.dart` | Dark `JobNewsScreen` section heading and card title meet AA. |

## Known gaps

- No saved jobs. The bookmark icon is decorative (`lib/features/jobs/presentation/widgets/job_news_card.dart:184`).
- `jobNewsItem` is unused (`lib/shared/services/mobile_content_service.dart:201`). Detail can be stale and cannot be opened from a notification id.
- JSON `action_url` and `is_featured` are not read (`lib/shared/models/job_news.dart:68`). Apply uses only `apply_url` and `url` (`lib/shared/models/job_news.dart:119`).
- `missing_skills`, `experience_level`, `salary_min`, and `salary_max` are parsed (`lib/shared/models/job_news.dart:97`) and not shown. Salary on screen is `salary_range` only.
- Featured "Apply Now" is not a separate control (`lib/features/jobs/presentation/widgets/featured_job_card.dart:227`). The card's tap opens details (`lib/features/jobs/presentation/job_news_screen.dart:67`).
- With exactly one posting, that card is featured and "Latest Postings" still shows the empty sentence (`lib/features/jobs/domain/job_news_feed.dart:64`, `lib/features/jobs/presentation/job_news_screen.dart:256`).
- List-card skill highlight uses `contains` (`lib/features/jobs/presentation/widgets/job_news_card.dart:249`), which is looser than `isSkillMatched` on the detail screen (`lib/features/jobs/domain/job_news_feed.dart:106`).
- Preset `IconData` values are stored and never drawn (`lib/features/jobs/presentation/widgets/job_news_filters_bar.dart:85`).
- Category and city lists are hard-coded (`lib/features/jobs/presentation/widgets/job_news_filters_bar.dart:157` and `:220`), not loaded from the API.
- `AppAsyncBody` is given `fallbackOnEmptyError` (`lib/features/jobs/presentation/job_news_screen.dart:101`), but that value is used only when there is no error and no data (`lib/shared/widgets/loading/app_async_body.dart:69`). A failed first load shows the error state, not `JobNewsFeed.empty`.
- "New" on the first latest card is positional (`isNew: index == 0` at `lib/features/jobs/presentation/job_news_screen.dart:279`), including after a filter change.
