# Dashboard

The authenticated heart of the app: launch/session bootstrap, the splash and welcome flow, the bottom-tab shell (home, My CVs, education, job news), the education placeholder and detail view, the analyses/generated-CVs history, and debug/support screens. Used by every signed-in user on every launch.

## User flow

- **Splash (boot beat):** animated brand mark (~860 ms) while `SessionBootstrap.resolve()` runs; never waits on a failing dependency — every failure maps to a destination.
  - Stored token + verified user → home (`AppRouter.openAfterAuth`, preserves cold-start deep links).
  - Stored token + unverified email → email-verification screen with the server-returned address.
  - No token + onboarding not completed → onboarding tour; onboarding done → signed-out welcome.
  - Offline or server error with a stored token → home anyway (cached content stays usable).
- **Welcome (signed out):** language toggle, brand hero, value card, `Create New Account` (register), `Sign In` (re-checks for a token saved since the splash before opening login), `Privacy Policy`.
- **Home tab:** greeting header (`ScreenHeader` with avatar → settings, bell → notifications + badge refresh), offline-cache banner when served from cache, two stat cards (My CVs, Analyses — zero/missing counts render nothing), two action cards (Create CV → generator, Analyze CV → analysis form), Recent Activity (latest-news tile or empty state with `Create CV` shortcut; `History` and `View All` links). Pull-to-refresh bypasses cache; explicit Retry on error (no silent auto-retry).
- **Bottom tabs:** Home, My CVs (`سيراتي`), Education (with a "soon" dot), Job News. Android back on a non-home tab returns home first; on home, double-back within 2 s exits (iOS: no double-exit).
- **Education tab:** "coming soon" page, not a content list — hero, three upcoming-feature cards, `Notify Me on Launch` → `Interest Registered` (device-only flag + analytics event; honestly promises no push pipeline), CV cross-sell card → generator.
- **Education detail:** opens only with an id (today: from a push notification) or preloaded fallback data. Shows badge/role/duration chips, title, body; skeleton while loading, list-card fallback when the API fails but fallback data exists, error state with retry otherwise.
- **History:** two independent tabs (Analyses, Generated CVs). Each: skeleton → list with count header (analyses), pull-to-refresh, `Load more` pagination with failure-retry footer, empty state with a creation shortcut, tap → result/detail screen. One tab failing never hides the other.
- **Support screens:** `PremiumGateScreen` (deep link to a gated route without premium → back home), `NotFoundScreen` (bad link → back home), `ComponentGalleryScreen` (debug-only widget/token catalogue with theme and locale toggles).

## Entry points

- Routes (`AppRoutes` names): `/` (splash), `/home`, `/mycvs` (home with tab index 1), `/education`, `/education/{id}` (detail), `/history`, `/gallery`, `/premium`, `/not-found`.
- Tabs: `DashboardTab` order home → myCvs → education → jobNews; out-of-range indices clamp to home.
- Push notifications: `HomeScreen` sets `NotificationService.instance.onNotificationTap` while mounted (detached on dispose so post-logout taps buffer instead of using a dead context). `resolveNotificationTarget` maps `action_type`/`action_url`: `url` → external browser (scheme-validated); `screen` → route (`home`, `my-cvs`/`mycvs`, `education[/id]`, `job-news`/`jobs`, `cv-analysis`/`analysis-new`, `analysis/{id}` → history, `create-cv`/`cv-generator`, `notifications`, `settings`); anything unknown → notifications inbox, never dropped.
- Terminated-launch notifications are captured in `main.dart` and flushed to the same handler once the shell mounts.
- Home reports an `app_open` activity event on start for notification-recency policy, and requests push permission + registers the FCM token after first frame.

## Code map

| File | Responsibility |
|---|---|
| `lib/features/dashboard/presentation/splash_screen.dart` | Launch entry: runs bootstrap, routes to home / verify-email / onboarding / welcome |
| `lib/features/dashboard/presentation/splash/splash_boot_body.dart` | Two-beat splash animation (wordmark → brand mark), honouring reduced motion |
| `lib/features/dashboard/presentation/splash/welcome_body.dart` | Signed-out landing: language toggle, hero, register / sign-in / privacy |
| `lib/features/dashboard/application/session_bootstrap.dart` | `SessionBootstrap` + `LaunchDestination` sealed set; `sessionBootstrapProvider` |
| `lib/features/dashboard/presentation/home_screen.dart` | Authenticated shell: tab stack, Android back handling, notification-tap routing |
| `lib/features/dashboard/presentation/home/dashboard_home_tab.dart` | Home tab content: header, stats, action cards, recent activity |
| `lib/features/dashboard/presentation/home/dashboard_navigation_bar.dart` | Bottom nav with coming-soon pip on Education; RTL-aware ordering |
| `lib/features/dashboard/presentation/home/dashboard_action_card.dart` | Brand / neutral tappable cards with pill button and watermark |
| `lib/features/dashboard/presentation/home/dashboard_stat_card.dart` | Stat cards; `CountUpValue` animates once, hides on zero/missing |
| `lib/features/dashboard/domain/dashboard_tab.dart` | Tab enum, analytics `screenName`, index clamping |
| `lib/features/dashboard/domain/dashboard_summary.dart` | `/mobile/dashboard` payload parsing with static-copy fallbacks |
| `lib/features/dashboard/application/dashboard_controller.dart` | `dashboardProvider` (language-keyed, first load cached, later forced) |
| `lib/features/dashboard/domain/notification_target.dart` | `NotificationTarget` sealed set + payload/route resolvers |
| `lib/features/dashboard/presentation/education_screen.dart` | Coming-soon page with notify-me button and CV cross-sell |
| `lib/features/dashboard/presentation/education_detail_screen.dart` | Single education item: chips, title, body, skeleton/error/fallback states |
| `lib/features/dashboard/application/education_interest_controller.dart` | Device-persisted interest flag + `feature_interest` analytics |
| `lib/features/dashboard/presentation/history_screen.dart` | Two-tab history scaffold with refresh, retry, load-more wiring |
| `lib/features/dashboard/presentation/history/history_cards.dart` | Analysis / generated-CV rows, score badges, `LoadMoreFooter` |
| `lib/features/dashboard/application/history_controllers.dart` | Independent paged controllers with generation-guarded `loadMore` |
| `lib/features/dashboard/domain/history_page.dart` | Page model: deduping `append`, `inputMethodLabel` |
| `lib/features/dashboard/presentation/gallery_screen.dart` | Debug component gallery (`/gallery`, SIRATI-23) |
| `lib/features/dashboard/presentation/premium_gate_screen.dart` | Placeholder for entitlement-gated deep links |
| `lib/features/dashboard/presentation/not_found_screen.dart` | Invalid-link screen with go-home action |

## State and data

- `sessionBootstrapProvider` (plain `Provider`): token read → `GET /auth/me` → onboarding flag; storage errors degrade to signed-out, 401/403 degrade via `authenticated()` → null.
- `dashboardProvider(english)` (`AsyncNotifierProvider.autoDispose.family`): first `build` may use memory/disk cache, every later build forces network; syncs `SessionCache.unreadCount` from the payload; Riverpod `retry` disabled (explicit Retry button instead).
- `analysesHistoryProvider` / `generatedCvsHistoryProvider` (`AsyncNotifierProvider.autoDispose`): page 1 on build; `loadMore` guarded by `loadingMore`/`hasMore` and a generation counter so a pre-refresh fetch can't append to a refreshed list; failures keep rows and set `loadMoreFailed`.
- `educationInterestProvider` (`AsyncNotifierProvider.autoDispose`): device flag via `EducationInterestStore` (backed by `sirati_education_interest`); UI flips optimistically, storage failure only re-shows the button after restart; double-register writes once.
- Local data: greeting falls back to `SessionCache.user` name; unread badge falls back to `SessionCache.unreadCount` when the payload omits it. Sign-out/401 wipe (`AuthSessionGuard` + `wipeUserScopedStorage` + `MobileContentService.clearAllCaches`) clears session, disk caches, and the education-interest flag.
- Content caches use a generation boundary at sign-out/401. Late responses cannot warm memory or disk, and disk envelopes with a previous persisted epoch remain unreadable even if a platform write finishes after the wipe. Normal cache hits and offline fallback stay available within the current session.

## API

| Method + path | Used by | Notes |
|---|---|---|
| `GET /auth/me` | Splash bootstrap (`SessionBootstrap.fetchUser`) | Decides home vs verify-email; 401/403 → signed-out flow |
| `GET /mobile/dashboard?lang={ar,en}` | `dashboardProvider` via `MobileContentService.dashboard` | Fields: `profile{name,status}`, `stats{generated_cvs,analyses}`, `unread_notifications`, `primary_action` / `analysis_action` `{title,subtitle,button_label}`, `latest_news{title,subtitle}`; offline serves carry `__sirati_offline` markers |
| `GET /cv-analyses?page={n}&per_page=20` | Analyses history tab | `current_page`/`last_page` drive `hasMore`; dedupe by id on append |
| `GET /generated-cvs?page={n}&per_page=20` | Generated-CVs history tab | Same paging contract |
| `GET /mobile/education/{id}?lang={ar,en}` | Education detail screen | Direct fetch (no cache); falls back to navigation-time data on error |
| `POST /mobile/activity` | Shell start (`event: app_open`) | Keeps notification activity/recency accurate; fire-and-forget |
| `POST /mobile/notifications/{id}/opened` | Notification tap path | Records the open; best-effort |

- Errors surface through provider state into `AppAsyncBody` (localized message via `ApiException.displayMessage`, retry button); history `loadMore` failures stay inline with a retry footer.
- Only 401 triggers the session-expiry logout; 403 never logs out.

## Localization and RTL

- Arabic default; `AppLocale.isEnglish(context)` picks every string on these screens (all copy exists in both languages inline).
- `BidiText.isolateLtr('ATS')` keeps the Latin acronym ordered inside Arabic titles; `LocaleFormat.mixedTitle/mixedBody` shape server copy per locale.
- Greeting, dates (`AppFormat.relativeTime`), and digits (`AppFormat.digits`) localize; history cards use `TextAlign.start` and `PositionedDirectional` so RTL mirrors correctly.
- Bottom nav relies on ambient `Directionality` (Home sits start-side in both directions); action-card arrows use `DirectionalIcon` (mirrors exactly once).
- Unsupported-language payloads still render via static English/Arabic fallback copy rather than blank cards.

## Tests

| Test file | Guarantees |
|---|---|
| `test/features/dashboard/session_bootstrap_test.dart` | Exhaustive token × user × onboarding matrix resolves to the specified destination; 401 and 403 both fall through without stalling; no server call without a token |
| `test/features/dashboard/dashboard_controller_test.dart` | First load may use cache, later loads force network; unread count syncs to `SessionCache` |
| `test/features/dashboard/dashboard_domain_test.dart` | Tab index round-trip + clamp to home; unique analytics names; notification-route aliases and inbox fallback |
| `test/features/dashboard/dashboard_action_card_test.dart` | Every card text is AA (≥ 4.5:1) on its painted background, both tones × both themes |
| `test/features/dashboard/education_test.dart` | Interest survives restart, double-register writes once, storage failures never throw; notify button records without promising a message; education screen text is AA |
| `test/features/dashboard/history_test.dart` | Independent tab paging, load-more dedupe on shifting pages, failure keeps rows with retry |
| `test/features/dashboard/home_notification_handler_test.dart` | Shell owns the tap handler only while mounted; a newer shell keeps its handler when an older one disposes |
| `test/gallery_screen_test.dart` | Gallery renders all sections/variants/toggles; `/gallery` parses |
| `test/widget/splash_screen_test.dart` | Boot → onboarding → welcome phase transitions under a controlled bootstrap |

## Known gaps

- Education tab has no list: `MobileContentService.education()` (list endpoint `/mobile/education?lang=&type=`) has no callers — `lib/shared/services/mobile_content_service.dart:98`, verified by search; the detail endpoint is only reachable via push-tap ids.
- `EducationInterestStore` is device-only with no server waitlist: reinstalls and second devices lose the flag (`lib/features/dashboard/application/education_interest_controller.dart:17`).
- `EntitlementStore.hasPremium` is a hardcoded `false` placeholder, so `/premium` always gates (`lib/core/routing/entitlement_store.dart:8`).
- `AnalyticsService` is currently a disabled no-op (debug prints only), so `feature_interest` and tab `screen_view` events don't leave the device (`lib/core/network/analytics_service.dart:9`). The `app_open` heartbeat is separate and does leave: `HomeScreen` sends it via `NotificationEngagementService` to `POST /mobile/activity`.
- `ComponentGalleryScreen` asserts debug-only but `/gallery` is registered unconditionally in the route table (`lib/features/dashboard/presentation/gallery_screen.dart:36`).
