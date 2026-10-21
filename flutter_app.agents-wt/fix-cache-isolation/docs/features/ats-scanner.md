# ATS scanner

Job seekers score a CV against a target role. They upload a PDF or TXT, or paste text, optionally add a job description, and get an ATS score, keyword coverage, and short recommendations. From the result they can ask the backend to generate an improved CV. Past runs live on the History screen.

## User flow

1. **Start an analysis** (`CvAnalysisScreen`). App bar title is "CV Analysis" / "تحليل السيرة الذاتية". The form has four blocks:
   - Target job title (required, max 160 characters).
   - Job description, collapsed by default (optional, max 4000 characters). The header expands it.
   - Upload zone: "Tap to upload PDF or TXT", max 5 MB. A chosen file shows `SelectedFileTile` with Remove. Too large or not PDF/TXT shows an error snackbar and is not kept.
   - Paste resume text (about 12 lines, grows). An "OR" divider sits between upload and paste. One of file or non-blank text is required; both may be sent together.
   - **Analyze CV** (`SubmitButton`). Disabled-looking loading label "Analyzing..." while the request runs. Invalid fields validate only after the first submit attempt.
2. **Waiting.** `AiProgressOverlay` (`AiProgressKind.analysis`) covers the form. Cancel shows "Cancelled" / "تم الإلغاء" and ignores the late response. Polling pauses while the app is `paused`, `hidden`, or `detached`; that time does not count toward the timeout.
3. **After the server answers**, the overlay closes and the result is pushed. Snackbars:
   - Poll timed out: score is ready, AI suggestions may still be running.
   - `ai_status` failed: error plus Retry, which submits again.
   - `classification_warning` set: English copy says the file may not be a resume; Arabic shows the server string.
   - `ApiException`: `AppSnackBar.fromException`. Anything else: a generic analysis error.
4. **Result** (`AnalysisResultScreen`), two tabs:
   - **Score & Criteria**: animated ring (0–100, skipped when reduce-motion is on), grade chip, band sentence, target title, job-match percent and bar, benchmark category, keyword-source badge, criterion rows (`score / max` and a percent chip), found and missing keyword chips.
   - **Recommendations**: strengths (check marks) and numbered quick wins. Each list has an empty sentence when the array is empty. A static "Ready to upgrade your CV?" card sits under them. It does not navigate.
   - App bar has `LanguageToggle`. Bottom bar: **Generate Improved CV**. Same overlay pattern (`AiProgressKind.generation`). Success opens `/cv/{id}`. Timeout or AI failure still opens that CV and shows a warning or a Retry snackbar. Cancel does nothing.
5. **History** (`HistoryScreen`, dashboard). Tabs: Analyses and Generated CVs (the second tab is the CV builder list). Analyses: skeleton while loading, `AppErrorState` with Retry, pull-to-refresh, empty state whose action opens `CvAnalysisScreen`. Each card shows title, score badge, score bar, input method, and date. Tap opens `AnalysisResultScreen` with the list payload (no extra fetch). A footer loads the next page; a failed page keeps the rows and offers retry. Refresh in the app bar reloads both tabs.
6. **Open a saved id** (`CvAnalysisLoaderScreen` at `/analysis/{id}`): branded loader, then the result. Failure shows `ApiException.displayMessage` or "Could not open this analysis." with Retry.

Keyword chips: found chips are not tappable. Missing chips open a bottom sheet with generic ATS advice and "Got it". More than 18 chips collapse behind "+N more" / "Show less".

## Entry points

| Entry | What opens |
| --- | --- |
| Home action card | Pushes `CvAnalysisScreen` (`dashboard_home_tab.dart`). Copy comes from dashboard `analysis_action`, with a local fallback title. |
| Home "History" | Pushes `HistoryScreen`. |
| `AppRoutes.history` (`/history`) | `HistoryScreen`. Web preview `?screen=history`. |
| `AppRoutes.analysis(id)` (`/analysis/{id}`) | `CvAnalysisLoaderScreen`. `sirati://app/analysis/{id}` parses the same way. |
| Path under `/analysis/` with no numeric id | `CvAnalysisScreen`. |
| Notification `action_type: screen` | `cv-analysis` or `analysis-new` pushes `CvAnalysisScreen`. `analysis` without an id does the same. `analysis/{id}` pushes `HistoryScreen` and drops the id (`notification_target.dart`). |
| Result "Generate Improved CV" | `Navigator.pushNamed(AppRoutes.cv(id))` → generated-CV loader. |

There is no ATS tab in the bottom bar. The home stat "Analyses" is a count only; it does not navigate.

## Code map

| Path | Responsibility |
| --- | --- |
| `lib/features/ats_scanner/presentation/cv_analysis_screen.dart` | Form, file pick, submit, poll, navigate to the result. |
| `lib/features/ats_scanner/presentation/cv_analysis/analysis_form_sections.dart` | Section title, collapsible job description, upload zone, selected file, OR divider. |
| `lib/features/ats_scanner/domain/analysis_draft.dart` | Length and file rules, draft fingerprint, idempotency key ring. |
| `lib/features/ats_scanner/presentation/cv_analysis_loader_screen.dart` | Fetch one analysis by id. |
| `lib/features/ats_scanner/presentation/analysis_result_screen.dart` | Two tabs and improved-CV action. |
| `lib/features/ats_scanner/presentation/analysis_result/score_tab.dart` | Score hero, criteria, keyword card. |
| `lib/features/ats_scanner/presentation/analysis_result/score_ring.dart` | Animated 0–100 ring. |
| `lib/features/ats_scanner/presentation/analysis_result/score_band_style.dart` | Band and criterion colours and sentences. |
| `lib/features/ats_scanner/domain/score_band.dart` | `ScoreBand`, `CriterionBand`, `criterionRatio`. |
| `lib/features/ats_scanner/presentation/analysis_result/keyword_group.dart` | Chips, overflow, missing-keyword sheet. |
| `lib/features/ats_scanner/presentation/analysis_result/keyword_source_badge.dart` | Job-description vs category scoring badge. |
| `lib/features/ats_scanner/presentation/analysis_result/recommendations_tab.dart` | Strengths, quick wins, upgrade card. |
| `lib/features/ats_scanner/application/improved_cv_controller.dart` | `ImprovedCvOutcome` and in-flight flag per analysis id. |
| `lib/shared/models/cv_analysis.dart` | `CvAnalysis`, `ScoreCriterion`, `AnalysisWeakness`. |
| `lib/shared/services/cv_api_service.dart` | Submit, get, list, poll, generate-from-analysis. |
| `lib/features/dashboard/presentation/history_screen.dart` | Analyses list UI. |
| `lib/features/dashboard/presentation/history/history_cards.dart` | `AnalysisHistoryCard`. |
| `lib/features/dashboard/application/history_controllers.dart` | `analysesHistoryProvider`. |
| `lib/features/dashboard/domain/history_page.dart` | Paging model and `inputMethodLabel`. |

## State and data

No ATS Riverpod provider owns the form. Submit state (`_isLoading`, `_submitted`, `_aiRequestGen`, chosen `PlatformFile`) stays on the screen. `IdempotencyKeyRing` keeps one key per draft fingerprint until a completed submit calls `consume()`. A retry of the same draft reuses the key; an edit gets a new one.

`improvedCvControllerProvider` is `NotifierProvider.autoDispose.family<…, bool, int>`. State is `true` while generation runs. Outcomes: `ImprovedCvReady` (id, `timedOut`, `aiFailed`, optional `aiError`), `ImprovedCvCancelled`, `ImprovedCvFailed`. A newer call or `cancel()` bumps a generation counter so a late response cannot navigate.

`analysesHistoryProvider` is an autoDispose `AsyncNotifier` of `HistoryPage<CvAnalysis>` (`retry` disabled). Page size is 20. `loadMore` appends and skips ids already shown. A failed next page sets `loadMoreFailed` and keeps current rows. A refresh bumps a generation so an in-flight load-more cannot append onto the new list.

Analyses are not written to SharedPreferences or secure storage. Logout and session expiry do not need a dedicated ATS wipe. `MobileContentService.invalidateCvRelated()` runs after a successful improved CV (`my-cvs` and `dashboard` memory cache only).

Score bands from `scoreTotal`: excellent ≥ 80, very good ≥ 65, fair ≥ 50, otherwise weak. Criterion band from `score/max` clamped to 0–1: strong ≥ 0.8, good ≥ 0.6, otherwise needs work. `max <= 0` yields ratio 0. `keywordSource` is `job_description` only when the JSON says that; every other value becomes `category`.

## API

All calls use `ApiClient` (`Authorization: Bearer` when a token exists, and `X-Sirati-Async: 1` on every request). Paths are relative to `ApiConfig.baseUrl` (`https://siratie.com/api` by default). Bodies are the `data` object. List paging reads `meta.current_page` and `meta.last_page`.

| Call | Method and path | Fields that matter |
| --- | --- | --- |
| `submitAnalysis` | `POST /cv-analyses` multipart | `target_job_title` (trimmed). `resume_text` only if non-blank. `job_description` only if non-blank. File part name `resume_file`. Header `Idempotency-Key` when the screen supplies one. |
| `getAnalysis` | `GET /cv-analyses/{id}` | Same `CvAnalysis` shape. |
| `pollAnalysis` | repeats `GET /cv-analyses/{id}` | While `ai_status` is `queued` or `processing`. Delay 2s, then 5s after 30s of active wait. Stops at 210s with `timedOut`, or earlier on cancel. |
| `listAnalysesPage` | `GET /cv-analyses?page={n}&per_page=20` | History. |
| `generateCvFromAnalysis` | `POST /cv-analyses/{id}/generated-cv` | JSON body is `{}` from this feature (no idempotency key). Then `pollGeneratedCv` via `GET /generated-cvs/{id}`. |

`CvAnalysis.fromJson` reads: `id`, `target_job_title`, `original_filename`, `input_method` (default `paste`), `score_total`, `grade`, `job_match`, `category` (default `general`), `category_label`, `category_label_en`, `is_general_profile` (also true when category is `general`), `keyword_source`, `classification_warning`, `criteria[]` (`label`, `score`, `max`), `strengths[]`, `weaknesses[]` (`priority`, `issue`, `fix`), `keywords_found[]`, `keywords_missing[]`, `quick_wins[]`, `ai_status` (default `not_configured`), `ai_feedback`, `ai_error`, `created_at`.

Errors: `ApiException` (including Laravel 422 `errors` as `displayMessage`) is shown on the form, the loader, history, and generation. Poll timeout is not an exception; the last payload is shown. A 401 is handled by the session guard, which clears the mobile-content caches.

## Localization and RTL

Form, result, and history chrome are inline English/Arabic from `AppLocale.isEnglish`, not ARB, except the history count `analysisCount` in `app_en.arb` / `app_ar.arb`. The result screen wraps itself in `AppLocale.direction` and adds `LanguageToggle`. The form does not.

File names on the selected-file tile are forced LTR via `BidiText.isolateLtr`. History titles use `LocaleFormat.mixedTitle`. `CvAnalysis.inputMethodLabel` is Arabic only (`ملف`, `ملف ونص`, `لصق نص`); the history card uses `inputMethodLabel` in `history_page.dart`, which has both languages. Keyword and criterion labels are server strings and are not reshaped. The result screen does not use `DirectionalIcon`.

## Tests

| File | What it locks |
| --- | --- |
| `test/features/ats_scanner/analysis_draft_test.dart` | Limits, PDF/TXT and 5 MB rules, fingerprint, idempotency reuse, `pausesPolling`. |
| `test/features/ats_scanner/score_band_test.dart` | Band thresholds and finite `criterionRatio`. |
| `test/features/ats_scanner/cv_analysis_screen_test.dart` | Form and selected-file text meet AA in light and dark; upload zone is a button. |
| `test/features/ats_scanner/analysis_result_contrast_test.dart` | Score tab AA across bands and keyword sources; both tabs. |
| `test/features/ats_scanner/improved_cv_controller_test.dart` | In-flight flag, timeout, AI failure, cancel, supersede, dispose. |
| `test/async_cv_polling_test.dart` | Queued → completed UI, failed AI still shows the result, timeout still routes to the result, cancel stops polling. |
| `test/cv_api_service_test.dart` | Multipart submit, list JSON, 422 message, poll interval. |
| `test/features/dashboard/history_test.dart` | Paging, no duplicate ids, independent tab failure, history-card AA, localized input-method labels. |
| `test/app_router_test.dart` | `/analysis/42` builds `CvAnalysisLoaderScreen` with that id. |
| `test/dark_mode_12_screen_walk_test.dart` | Dark `CvAnalysisScreen` labels meet AA. |
| `test/golden/theme_direction_golden_test.dart` | Static `GoldenAtsScannerScreen` fixture only. It is not `CvAnalysisScreen`. |

## Known gaps

- `weaknesses` is parsed (`lib/shared/models/cv_analysis.dart:78`) and never rendered. Recommendations show only `strengths` and `quick_wins` (`lib/features/ats_scanner/presentation/analysis_result/recommendations_tab.dart:30`).
- `CvAnalysis.inputMethodLabel` ignores locale (`lib/shared/models/cv_analysis.dart:105`). History uses `inputMethodLabel` in `lib/features/dashboard/domain/history_page.dart:53` instead. `test/cv_api_service_test.dart` expects the Arabic paste label from the model getter.
- Notification route `analysis/{id}` opens History and discards the id (`lib/features/dashboard/domain/notification_target.dart:109`). The named route `/analysis/{id}` does load that analysis.
- English classification copy ignores the server string; Arabic shows `classification_warning` as-is (`lib/features/ats_scanner/presentation/cv_analysis_screen.dart:206`).
- History header uses `analysisCount(page.items.length)`, the rows loaded so far, not a server total (`lib/features/dashboard/presentation/history_screen.dart:179`).
- Improved-CV generation sends an empty body and no idempotency key (`lib/features/ats_scanner/application/improved_cv_controller.dart:76`).
- No on-disk cache for analyses. Offline history and deep links fail with the error state.
- The upgrade card on the recommendations tab is not a button (`lib/features/ats_scanner/presentation/analysis_result/recommendations_tab.dart:200`). The only generate control is the bottom bar.
