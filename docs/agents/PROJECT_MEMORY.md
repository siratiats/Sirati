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
