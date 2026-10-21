# Sirati Flutter app: memory for coding agents

- Stack: Flutter 3.44, Dart 3.12, Riverpod 3 (`AsyncNotifierProvider.autoDispose`, sealed outcome classes). Arabic RTL is the default locale; English supported.
- Layout: `lib/core` (network, storage, routing, utils) -> `lib/shared` (models, services, theme, widgets) -> `lib/features/*`. Features: app, ats_scanner, auth, cv_builder, cv_export, dashboard, jobs, localization, settings.
- Colours: use `context.sirati` tokens. `primary` is a fill (white `onPrimary` text on it); use `primaryInk` for teal text. Raw `Color(0x…)` is blocked by `test/app_metrics_test.dart`.
- Contrast is tested: `test/support/contrast_probe.dart` (`expectAllTextAa`). Text >= 4.5:1, graphics >= 3:1.
- Direction icons: use `DirectionalIcon` (mirrors once in RTL).
- `lib/l10n/generated` is gitignored and generated; never commit it.
- Tests: `flutter test <path>`; goldens in `test/golden` (`--update-goldens` only when a visual change is intended).
- Backend API base: `lib/core/network/api_config.dart` (`https://siratie.com/api`).
- Feature docs live in `docs/features/*.md` (index: README.md); update the matching file when behaviour changes.
- Analysis history lives in `lib/features/dashboard` (`analysesHistoryProvider`, `GET /cv-analyses`), not in ats_scanner.
- Only HTTP 401 logs out; 403 is typed `auth` but never logs out. `AnalyticsService` is a debug-print no-op; `app_open` still goes to `POST /mobile/activity`.
- Known open bugs (documented, not fixed): job-news disk cache is not user-scoped and writes are unawaited; `deleteAccount()` local wipe is best-effort; actionable notification `markRead` is fire-and-forget.
