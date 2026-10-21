# App Shell

Overview of everything outside individual features: startup in `lib/main.dart` (+ `main_dev/staging/prod.dart`), routing in `lib/core/routing` (composed by `lib/features/app`), theming and colour tokens in `lib/shared/theme`, networking (`ApiClient`), storage, push notifications/FCM, analytics, and flavors. Used by every screen; start here when onboarding.

## User flow

- **Cold start:** `startSiratiApp()` → flavor init → Sentry init → `registerSiratiRouteWidgets()` + `AppRouter.install()` → error handlers → load language, then theme (each best-effort, never blocking) → Firebase stack (failure-safe: app still launches without Firebase) → `AuthSessionGuard.install` → portrait/edge-to-edge chrome → `runApp` on the splash route.
- **First paint:** `SiratiApp` rebuilds on language or theme change; non-mobile desktop/web constrains content to a 430 px centered column; text scaling clamps to 0.8–2.0; `Directionality` is forced from the app locale (not the OS).
- **Routing:** splash-first always — cold-start deep links are stashed in `AppRouter.pendingLocation` and the splash goes first; after auth, `openAfterAuth` rebuilds home-then-deep-link so back never lands on splash. Unknown routes → `/not-found`; entitlement-gated routes without premium → `/premium`.
- **Crash safety:** widget errors render `AppCrashView` and are logged redacted; platform errors go to `AppLog` (+ Crashlytics/Sentry when Firebase is up); PII is redacted/sanitized before any sink.
- **Notifications:** permission is requested after the home shell is visible; the FCM token registers with the backend only when signed in (5-minute dedupe); foreground messages show as local notifications; taps route via the shell handler or buffer until it mounts; sign-out unregisters, deletes the device token, and forgets the last registration so the next account registers immediately.
- **Offline:** content services are network-first with memory TTL then disk fallback, flagged `__sirati_offline` so UI shows an `OfflineCacheBanner` instead of stale-looking data.

## Entry points

- Entrypoints: `lib/main.dart` (shared `startSiratiApp`), `lib/main_dev.dart` (dev), `lib/main_staging.dart` (staging), `lib/main_prod.dart` (prod).
- Routes (`AppRoutes`): `/`, `/home`, `/login`, `/register`, `/history`, `/create-cv`, `/mycvs`, `/education`, `/education/{id}`, `/job-news`, `/privacy`, `/settings`, `/notifications`, `/premium`, `/gallery`, `/builder`, `/builder/{stringId}`, `/cv/{id}`, `/analysis/{id}`, `/not-found`. (No bare `/analysis` route — `parse` marks it unknown → not-found; the analysis form opens via direct navigation, e.g. the notification aliases `cv-analysis`/`analysis-new`.)
- Deep links: `sirati://app/...` warm-start via a `WidgetsBindingObserver`; cold-start links stash to `pendingLocation`. Web preview: `?screen=` (register, create-cv, mycvs, education, history, job-news, privacy, home) and `?lang=` (ar/en, also seeds locale bootstrap).
- Notifications open: tabs (`home`, `my-cvs`, `education[/id]`, `job-news`), screens (notifications, cv-analysis, history, cv-generator, settings), or external URLs — see the dashboard doc for the full mapping.

## Code map

| File | Responsibility |
|---|---|
| `lib/main.dart` | `startSiratiApp` bootstrap + `SiratiApp` widget (locale/theme listeners, MaterialApp, width clamp, forced Directionality) |
| `lib/main_dev.dart` / `lib/main_staging.dart` / `lib/main_prod.dart` | Flavor-pinning entrypoints |
| `lib/features/app/sirati_route_table.dart` | Composition root: route name → feature screen (keeps `core/` free of `features/` imports) |
| `lib/core/routing/app_router.dart` | Deep-link parsing, initial-route resolution, entitlement guard, `pendingLocation`, `openAfterAuth` |
| `lib/core/routing/app_routes.dart` | Route-name constants and `ParsedRoute` |
| `lib/core/routing/entitlement_store.dart` | Premium flag (placeholder) consulted by the router |
| `lib/core/network/api_client.dart` | HTTP verbs, Bearer [REDACTED] injection, 45 s timeout, error mapping, 401 hook |
| `lib/core/network/api_config.dart` | Base URL, app version, support email (compile-time overridable) |
| `lib/core/network/api_exception.dart` | Typed errors (`auth`, `validation`, `notFound`, `rateLimited`, `server`, `network`, `timeout`) + localized display message |
| `lib/core/network/analytics_service.dart` | Analytics facade: screen views, funnel events, sanitized params |
| `lib/core/storage/auth_token_store.dart` | Secure-storage session token (`sirati_auth_token`) |
| `lib/core/storage/preference_store.dart` | Secure-storage prefs: language, theme, onboarding, notifications, CV draft, review, generation count, education interest |
| `lib/core/storage/disk_cache.dart` | `SharedPreferences` JSON offline cache (dashboard, CVs, news, job titles; ~200 KB cap, schema-versioned) |
| `lib/core/storage/user_scoped_storage.dart` | Account-vs-device key split + `wipeUserScopedStorage` for sign-out |
| `lib/core/flavors/app_flavor.dart` | dev/staging/prod selection, banner colour/label |
| `lib/core/flavors/flavor_banner.dart` | Non-prod build ribbon |
| `lib/core/logging/app_log.dart` | Structured, PII-redacting logger (event-id messages, key-filtered data) |
| `lib/core/errors/app_crash_view.dart` | Fallback widget for framework build errors |
| `lib/core/utils/app_locale.dart` | Locale policy: bootstrap priority, `languageCode` notifier, direction helpers, greeting |
| `lib/core/utils/bidi_text.dart` / `bidi_text_utils.dart` | BiDi isolation, greeting, avatar initial |
| `lib/core/utils/locale_format.dart` / `arabic_date_format.dart` / `app_format.dart` / `latin_digits.dart` | Mixed-locale titles/bodies, Arabic dates, relative time, digit shaping |
| `lib/features/localization/app_locale.dart` | Re-export of the core locale API for feature code |
| `lib/shared/theme/sirati_colors.dart` | `SiratiColors` token set (light + dark), `context.sirati` accessor |
| `lib/shared/theme/app_theme.dart` | Material themes per locale (`lightFor`/`darkFor`), spacing, text styles |
| `lib/shared/theme/app_theme_controller.dart` | `themeMode` notifier: bootstrap, persist, analytics on change |
| `lib/shared/services/notification_service.dart` | FCM: permissions, token register/unregister, foreground display, tap buffering |
| `lib/shared/services/notification_engagement_service.dart` | Activity/open/conversion/preference telemetry for smart scheduling |
| `lib/shared/services/mobile_content_service.dart` | Cached content gateway (dashboard, CVs, education, job news, notifications) |
| `lib/shared/services/session_cache.dart` | In-memory session identity (`user`, `unreadCount`) |
| `lib/shared/widgets/directional_icon.dart` | Once-mirroring RTL icon wrapper |
| `lib/l10n/app_ar.arb` / `lib/l10n/app_en.arb` | Arabic / English string catalogues |

## State and data

- No global store: locale (`AppLocale.languageCode`), theme (`AppThemeController.themeMode`), and session identity (`SessionCache`) are `ValueNotifier`s observed directly by `MaterialApp`; feature data lives in Riverpod providers per feature.
- Persisted keys (secure storage): `sirati_lang`, `sirati_theme_mode`, `sirati_onboarding_completed`, `sirati_notifications_enabled`, `sirati_cv_draft`, `sirati_review_requested`, `sirati_cv_generation_count`, `sirati_education_interest`, `sirati_auth_token`. Device-scoped (survive sign-out): language, theme, onboarding. Wiped on sign-out/401: token, `DiskCache` (`sirati_cache_*`), CV draft, notifications opt-in, education interest, builder drafts/docs.
- `DiskCache` envelope (`schemaVersion`, `savedAt`, `data`): schema mismatch or oversize (> ~200 KB) payloads are dropped silently; CVs/news caches are language-scoped so ar/en never overwrite each other; notifications are never disk-cached.
- `MobileContentService` memory cache: 60 s TTL by default (job titles 24 h, notification list 30 s); `force` bypasses; `invalidateCvRelated()` clears dashboard + CV lists after mutations.

## API

- Base: `https://siratie.com/api` (override via `--dart-define=SIRATI_API_BASE_URL`). Every request sends `Accept: application/json` + `X-Sirati-Async: 1`, plus `Bearer` auth when a token exists.
- Shell-owned endpoints (all appear in the notification/engagement services):

| Method + path | Purpose |
|---|---|
| `POST /fcm-tokens` | Register device token (`token`, `device_id`, `platform`, `app_version`, `language`, `timezone_offset_minutes`, `notifications_enabled`) |
| `DELETE /fcm-tokens` | Unregister on logout / opt-out (`token`, optional `opt_out`) |
| `POST /mobile/activity` | Activity heartbeat (`language`, `timezone_offset_minutes`, optional `event`) |
| `POST /mobile/notifications/{id}/opened` | Notification-open telemetry |
| `POST /mobile/conversions` | Conversion telemetry (`conversion_type`, optional ids) |
| `PUT /mobile/notification-preferences` | Sync push opt-in + language/timezone |
| `GET /mobile/notification-preferences` | Read server opt-in (mirrored to device) |
| `GET /auth/me` | Session validation at bootstrap |

- Shared content surface via `MobileContentService`: `GET /mobile/dashboard?lang=`, `GET /mobile/my-cvs?lang=`, `GET /mobile/education?lang=&type=`, `GET /mobile/education/{id}?lang=`, `GET /mobile/job-news?…`, `GET /mobile/job-titles`, `GET /mobile/notifications`, `POST /mobile/notifications/{id}/read`, `POST /mobile/notifications/read-all`.
- Error mapping: HTTP 401 → `auth` (+ `onAuthExpired` → single guarded logout redirect, stale-token-safe); 403 → `auth` type but never logs out; 404/422/429/5xx typed; timeouts (45 s), empty/unparseable bodies, and transport failures map to localized Arabic-first messages. Multipart upload supported for file-bearing calls.

## Localization and RTL

- Product default is Arabic: bootstrap priority is URL `?lang=` → stored `sirati_lang` → device locales (Arabic or Gulf country code → `ar`; English → `en`) → `ar`. Supported: `ar-SA`, `en-US`.
- `SiratiApp` forces `Directionality` from the app locale and picks `lightFor/darkFor(arabic:)` so Arabic letterforms/shape differ per locale; text scaling clamps to 0.8–2.0.
- Rules: `TextAlign.start` / directional alignments (never left/right), `DirectionalIcon` for start/end glyphs (mirrors once; skips icons with `matchTextDirection`), `BidiText` isolation for Latin inside Arabic, `LocaleFormat` for mixed server copy, localized digits/dates.
- Strings come from the `.arb` files; the build-time generated localizations are gitignored and never committed.

## Tests

| Test file | Guarantees |
|---|---|
| `test/app_router_test.dart` | Named routes, trailing-slash strip, CV/analysis ids, `sirati://app` links, unknown-route flagging without crashes |
| `test/auth_session_guard_test.dart` | Stale 401 for an old token never clears a newer session; expiry wipes once and redirects to login |
| `test/shared/notification_token_test.dart` | Token register/unregister payloads, rotation on delete, auth-gated registration |
| `test/app_theme_controller_test.dart` | Theme bootstrap from storage, persistence on change, invalid values fall back to system |
| `test/flavors/app_flavor_test.dart` | dev/staging banner + labels, prod clean |
| `test/localization/locale_switching_and_persistence_test.dart` | Switch persists, restart restores, URL/device priority order |
| `test/localization/l10n_adoption_test.dart` + `i18n_extraction_test.dart` | No hardcoded user strings outside the catalogue; catalogue parity ar/en |
| `test/bidi_and_date_formatting_test.dart` | BiDi isolation, mixed titles, Arabic dates, digit shaping |
| `test/app_log_test.dart` | PII redaction (emails, phones, IDs, CV fields) before any sink; release drops free text |
| `test/app_contrast_test.dart` + `test/app_metrics_test.dart` | Token contrast invariants; raw `Color(0x…)` ban outside token palettes |

## Known gaps

- `AnalyticsService` is a disabled facade: every method debug-prints and returns, so no screen/funnel/preference event currently leaves the device (`lib/core/network/analytics_service.dart:14`).
- `EntitlementStore.hasPremium` defaults to `false` with RevenueCat yet to replace it — gated routes are unreachable until then (`lib/core/routing/entitlement_store.dart:5`).
- Missing Firebase config (no plist/json, e.g. previews) silently disables push, analytics, and Crashlytics while the app still runs (`lib/main.dart:150`).
- Sentry DSN/environment/release arrive via `--dart-define` with empty defaults — unset means events go nowhere (`lib/main.dart:49`).
- Non-mobile platforms squeeze the whole app into a 430 px column — a stopgap, not a responsive layout (`lib/main.dart:304`).
- `DiskCache` silently drops payloads over ~200 KB, which can leave large responses permanently uncached offline (`lib/core/storage/disk_cache.dart:28`).
