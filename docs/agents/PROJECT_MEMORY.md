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
