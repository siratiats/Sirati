# Sirati project memory (prepended to every new coder session)

Stack: Laravel API (PHP, tests: `php vendor/bin/phpunit`, not `php artisan test` in Muse's sandbox) + Flutter app in `flutter_app/` (a NESTED git repo; worktrees for it live separately). Read AGENTS.md first.

## Job Match (premium) learnings
- `SubscriptionGate::hasFullAccess()` is open whenever `MONETIZATION_ENABLED=false`; premium-only features need their own fail-closed assert (`assertCanJobMatch()`: 402 non-premium, 503 + `Log::critical` on bad config outside local/testing). `premium_until` overrides `is_premium`. `stateFor()` feeds login/profile, so feature failures must not break account access.
- `GenerateCvAdviceJob` re-scores without the JD; always pass the JD to `AtsScoringService::scoreDocument($doc, null, $jd)`.
- Any new AI operation must update `CvAiProvider`, all provider services, `OperationSchemas` (OpenAI + Anthropic) and `CachedCvAiProvider::PROMPT_VERSION` together; cache keys use the exact canonical CV snapshot hash (no lowercasing/digit folding).
- Fixtures for `JobMatch` need `request_fingerprint` and `cv_snapshot`. The API field is `job_description_input`. Idempotent replays, polling and apply must not spend or check daily quota; only creating a new match does.
- Mutating a `GeneratedCv` must sync `CvDocument` + `legacySnapshot()` + `generated_markdown`; avoid the generic update (re-runs AI).
- Flutter: `EntitlementStore.hasPremium` is a hard-coded false placeholder, use server `can_job_match`; `CvTargetJob` clips JD at 4000 chars; `GeneratedCv` must keep the canonical `document`; navigation composes in `features/app/sirati_route_table.dart`.

## Arabic PDF export
- Exported PDFs carry logical text via `/ActualText` (`LogicalTextMpdf`, reads mPDF private props with a constructor guard; mPDF is `^8.3`). Correct ActualText can hide wrong painted glyph order, so also check the raster.
- Structured CV sections bypass Markdown rendering, so bidi protection must cover final export HTML. mPDF hyphen breaks become spaces on extraction; use `hyphens: none`. Trailing punctuation must not join a Latin run.
- `LandingLeadTest::test_landing_page_can_be_viewed` fails on a clean checkout (pre-existing).

## Delegation notes
- Copilot `gemini-4-argon` rejects effort `xhigh`; stale Copilot sessions can't be resumed; Copilot must be trusted per worktree. `.agents/` and `.github/` are gitignored by default: guard files were force-tracked. Never run `cleanup-worktrees.ps1 -All` while a task's work is uncommitted in its worktree.

## Emulator / real-run notes (2026-10-19)
- Android build: agent-spawned processes cannot run Gradle on this PC (Java `Selector.open()` fails with "Unable to establish loopback connection"); run `flutter run` from the user's Terminal panel instead. Flutter must use JDK 21 (`flutter config --jdk-dir D:\tools\jdk-21`); Android Studio's bundled JDK 25 breaks Gradle 8.14. Emulator reaches the host API at `10.0.2.2`.
- Claude Code sets its own `ANTHROPIC_BASE_URL` (no `/v1`) in its shell, which overrides `.env` for Laravel started from there; override it when starting local servers from an agent shell. Claude Haiku 5.5 (`claude-haiku-5-5`) rejects `temperature`.
- `APP_PUBLIC_URL` defaults to https://siratie.com and the signed PDF links use it; for emulator tests point it at the local server or use the authenticated `/api/generated-cvs/{id}/download` route. `CvExportGuard` refuses PDFs for CVs without phone/email, so seeded test CVs need a phone.
- Job Match with a thin CV: every added skill/experience is `needs_user_fact`; the user must tick "I confirm this is true for me" (`confirmed_fact_keys`) before it can be applied.
- Delegation: Muse cannot write outside its own workspace or run `flutter`, so it does not fit work in the nested `flutter_app` repo; Codex/agy handle Flutter. Never kill a coder before checking its worktree for uncommitted work.

## Subscriptions (decision 2026-10-19)
- Premium is Apple in-app purchase via RevenueCat. Apple verification is pending (about 2026-10-26), so the Flutter app deliberately has NO purchase SDK, restore flow or checkout yet. Do not build or fake one: the paywall only explains premium and re-reads server entitlement (`/auth/me`). The backend plans API and RevenueCat webhook already exist.

## Flutter UI work learnings (2026-10-19..21)
- Generated l10n (`lib/l10n/generated/`) is git-ignored: run `flutter pub get && flutter gen-l10n` in every fresh checkout/worktree, or branding/l10n tests fail with stale strings ("Sirati" instead of "Siratie").
- Merging ARB files: use a structural key union (add keys from both sides, conflict only when both changed the same key), never text-merge; check en/ar key parity after.
- Worktrees: `test/ci_workflow_test.dart` fails in a worktree only (`../.github` missing); it passes on main. Merge to main, then run the full suite once.
- Coders: Codex wrapper cannot run Flutter (sandbox) so it writes tests blind; Grok and Antigravity can run Flutter; Muse cannot run Flutter or edit the nested repo (read-only audits only). Antigravity often returns no report. Grok hit its turn cap on 100+ turn jobs: keep tasks small.
- Fonts: only IBM Plex Sans Arabic 400/600/700 are shipped; never use w500/w800. A runtime test (`shipped_font_weights_test.dart`) checks pumped feature widgets.
- Design system: disabled/loading buttons must stay distinct and pass AA; form fields keep their own InputDecoration chrome (do not wrap the TextField in a filled container: it tints helper text); reduced motion must stop indeterminate animations; contrast probe counts each opacity layer once.
- Check ATS: hub passes `initialCvId` + `initialMarkdown` through the route table; supplied markdown is used without refetch.
- Production API is https://siratie.com/api; pushing the Laravel repo does not auto-deploy (checked 2026-10-21: new `/api/job-matches` 404 until the server pulls, migrates and restarts the queue worker).
- The Flutter and Laravel git remotes embed access tokens in their URLs: never print `git remote -v`.

## CV speed: PDF artifacts, Claude timeouts, queue (2026-10-26)
- PDFs are pre-rendered by RenderCvPdfJob (GeneratedCvObserver, after commit) into private disk `cv_pdfs` at cv-pdfs/{cv_id}/{fingerprint}.pdf; download serves the file, sync render is the fallback. Bump CvTemplateRenderer::RENDERER_VERSION when templates/fonts change.
- Mass deletes of generated_cvs bypass observers: call CvTemplateRenderer::deletePdfArtifacts($id) before and after (deleteAccount, PruneGuestAnalysesCommand).
- Tests that create a completed CV now push RenderCvPdfJob: reset with Queue::fake() after setup or assert specific AI jobs with assertNotPushed, never drop assertions.
- Claude long operations (max_tokens > 2048: generate_cv, analysis_advice, job_match) use ANTHROPIC_GENERATE_TIMEOUT (75s) inside a 155s budget; short ones keep 30s/50s because they run in the HTTP request. Claude has no DeepInfra fallback.
- Redis queue: oldest pending job is index 0 (RPUSH/LPOP). Listeners in app/Listeners are auto-discovered: never also Event::listen them. Payload createdAt is the original push time, so retries have no meaningful wait_ms.

## Pasted CV text (2026-10-26)
- Text pasted from another PDF arrives with hard line wraps and Latin ligatures (U+FB00-FB06). It is normalized once at ingestion: Utf8Sanitizer (ligatures, also sanitizeDeep for document/form_payload arrays) and CvTextLayout (unwrapLines, narrative that repeats bullets, one education entry per degree, "Name — Issuer"), called from GeneratedCv::setAttribute and LegacySectionParser. Never at render time; old rows were cleaned by migration 2026_10_26_000001.
- Writes that bypass the model (DB::table / query-builder updates) skip normalization.
- Classic template: entries may split across pages; only heading + first entry header are kept together (.keep / .entry-head). mPDF ignores page-break-after: avoid.
- CvDocument::displayDate prints ISO and MM/YYYY as "Nov 2025" / "نوفمبر 2025"; free text stays as written.
- Certification::fromArray reads "authority" before "issuer": write both.
- Line joining is strict: join only on a hyphen break, a trailing comma/connector (lowercase connector words), or a lowercase next line after a 4+ word line. No length rules (they joined separate achievements). Arabic wraps without a connector stay split by design.
- Classic template date cell must stay `direction: ltr`: the PDF text step already orders Arabic, so `rtl` prints an Arabic range end-first (checked visually 2026-10-26).

## Admin rebuild + remote labels (2026-10-27)
- Remote labels: Laravel `app_languages` / `app_translation_keys` / `app_translations` / `app_translation_revisions`; public `GET /api/i18n/languages` and `/api/i18n/languages/{code}/strings` (ETag `"code-version"`, 304). Edits are drafts; only `TranslationPublisher` publishes/rolls back, and every change to published strings, direction or fallback must go through `TranslationPublisher::bumpVersion` (version, ETag and cache key move together). Base catalog = `resources/i18n/app_*.arb`, loaded by `php artisan i18n:import-arb` (never clobbers admin edits without `--force`).
- ICU rules must match on both sides: no apostrophe escaping (l10n.yaml has no use-escaping), plural cases only =0/=1/=2 + zero/one/two/few/many/other, placeholders exactly equal to the catalog. Server validator: `TranslationValidator::validateForKey`; Flutter: `lib/core/l10n/icu_lite.dart`.
- Flutter: after any ARB key change run `flutter gen-l10n && dart run tool/gen_remote_l10n.dart` (committed `lib/l10n/remote_app_localizations.dart`; a sync test fails otherwise) and copy the ARB files into Laravel `resources/i18n/`. Never compare language codes to 'en'/'ar' directly: use `AppLocale.baseLanguage` / `isEnglish` for content language and `AppLocale.directionOf` for direction. Flutter `Locale()` renames deprecated codes (iw->he), so random-code tests skip codes where `Locale(c).languageCode != c`.
- Flutter CI runs `dart format --set-exit-if-changed`; several existing files are unformatted, so format only files you change.
- Admin: new shell `layouts/admin.blade.php` + `public/css/admin.css` (tokens; teal text must use `--teal-ink`, #12B3A6 fails AA on white). Admin controllers use trait `Concerns\AuthorizesAdmin`. Metrics in `Services\Admin\DashboardMetrics` (60s cache, `?refresh=1`). App locale is `ar`, so admin `diffForHumans()` needs `->locale('en')`. `ai_call_logs` has no status/cost column and only logs successful calls: failure rate comes from the `ai_status` columns.
- Blade: never mix inline `@php(...)` with `@php ... @endphp` in one view; inline `@section('x', $v)` is already escaped (print with `{!! !!}`); never pass a view variable named `errors`.
- Worktrees: a single junction `vendor -> D:\Sirati\vendor` makes Composer load `App\` from the main checkout (tests run main's code). Use a real `vendor\` with real copies of `vendor\composer` and `vendor\bin` plus one junction per package; check with `ReflectionClass(...)->getFileName()`. Copy the untracked `storage/app/firebase-credentials.json` for `SendTestNotificationCommandTest`. `php artisan tinker <file>` hangs waiting for input: bootstrap the kernel in a plain PHP script instead.

## Label screen previews (2026-10-28)
- Admin Labels shows app screenshots from `resources/i18n/screens/` (`manifest.json` + `<id>.<locale>.png`), read by `Services\I18n\LabelScreenCatalog`. Refresh: in flutter_app run `flutter test test_screens/capture_screens_test.dart`, copy `build/l10n_screens/*` into `resources/i18n/screens/`, commit.
- Capture records keys through a test-only `RemoteStringLookup` subclass fed by the bundled ARB messages; no production hook. Sample data equal to a label value gets that key.
- Test fixtures live in `tests/Fixtures` (capital F; Linux CI is case-sensitive). New `admin.labels.*` routes must be added to the route list in `AppLocalizationAdminTest`.
- In flutter_test, text whose style sets no font family (e.g. a ButtonStyle `textStyle`) renders as boxes.

## Jobs redesign + apply links (2026-10-29)
- Apply destination = `apply_method` url|email|none from `App\Services\Jobs\ApplyLinkExtractor` (server) and `ApplyDestination.of` (Flutter, `features/jobs/domain/apply_destination.dart`). Keep the two deny lists in sync. Telegram/social/shorteners are never Apply; the Telegram post is `source_url` ("View original post" only). Shorteners are not resolved (SSRF).
- Telegram `t.me/s/<channel>` wraps each post with the channel's avatar link `https://t.me/<channel>`: read links only from `tgme_widget_message_text`, line by line, after footer stripping. jobs2ksa ends every post with an Instagram/Snapchat/WhatsApp promo block; jobmag applies mostly by email; ewdifh has real employer links.
- No match % anywhere. "مناسبة لي" = role-only filter (job title / category / CV target title) ordered in SQL before LIMIT. A CASE needs a WHEN: CV-target-only users get no tier ORDER BY (an `ORDER BY 0` is a column position in SQLite).
- Aggregated rows: no invented company, no inferred "verified", no deadline label (retire after 21 days internally). Dedupe by `fingerprint` (title + company, else destination; null = never merge). `jobs:repair-listings` (run by the 2026-10-29 migration) cleans old rows.
- Flutter test helpers must read text via `paragraphOf()` (`test/support/contrast_probe.dart`): inside `SelectionArea` a Text renders a mouse region around the paragraph. Shipped font weights are 400/600/700 only; raw `Colors.*` outside the theme fails `app_metrics_test`.
- `LandingLeadTest::test_landing_page_can_be_viewed` and two `SaudiJobAggregatorTest` notification tests (quiet hours 01:00-02:00 UTC) fail independently of jobs work.

## Job post quality (2026-10-29)
- Aggregated posts pass `JobPostClassifier` once at ingestion: image-only headers, near-empty posts, courses and admissions are not stored; titles are cleaned there; `language` is the post's own (feed shows aggregated rows in both app languages).
- Cities live only in `SaudiCities` (code, Arabic label, region). Add a city there; matching is whole-word, so "الخبرة" is not Khobar.
- Remotive jobs are kept only when `candidate_required_location` admits Saudi Arabia (worldwide/EMEA/MENA/GCC/Saudi).
- Shell heredocs in this environment collapse doubled backslashes to one: write PHP regex edits with the Edit tool or `chr(92)`.

## Server-label audit (2026-10-30, Abobker85/Sirati#3)
- Every app screen is registered once in flutter_app `test/support/app_screens.dart` (`appScreens`), shared by the CI audit `test/localization/server_labels_audit_test.dart` (runs in codemagic `flutter test`) and the admin preview capture. A new screen or state goes there.
- The audit serves every catalog value with an invisible U+2060 prefix; painted text must be marked labels, harness sample data (`h.sample`, every mock-client JSON string, sample-name initials) or `ApiConfig.supportEmail`. Directional marks are stripped on both sides. Text equal to a label but typed in code still fails.
- Helpers that used `english ? '…' : '…'` now take `AppLocalizations` (`AppFormat.relativeTime`, `LocaleFormat.relativeSavedAt`, `AiErrorMessage.*`); domain models leave server titles empty and the screen shows its catalog label.
- `l10n.yaml` template is `app_ar.arb`: `@key` placeholder metadata goes in the Arabic file. The English catalog must contain no Arabic script (`l10n_adoption_test`).
- Laravel `resources/i18n/*.arb` had drifted 65 keys behind the app; the 2026-10-30 migration runs `i18n:import-arb` on deploy and `AppLabelCatalogDeployTest` checks the API serves every bundled key.
- Local `dart format` (3.12.2) rewraps many untouched files: format only the files you edit.
- Not yet catalogued: CV-content-language text written into the CV ("Present"/"حتى الآن", "Copy of …", "Untitled CV" metadata, achievement presets, metric pills); needs a CV-language lookup and a migration for stored values.
