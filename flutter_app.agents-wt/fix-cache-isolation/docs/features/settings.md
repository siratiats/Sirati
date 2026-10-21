# Settings

The settings feature provides user management, app preferences configuration, account security controls, in-app notification tracking, and legal disclosures for the Sirati app. It enables job seekers to personalize their target profession, change account credentials, manage push notifications, switch between Arabic and English, adjust light and dark themes, review data privacy practices, contact customer support, and permanently remove their account and associated documents under Saudi Personal Data Protection Law (PDPL) requirements.

## User flow

- **Settings Hub (`SettingsScreen`)**:
  - Displays user profile summary, categorized preference tiles, legal info, and account deletion.
  - Profile Summary Card (`ProfileSummaryCard`): shows user avatar initial, full name, localized target job title, and email.
  - Section actions:
    - Account: "Edit profile" (opens `ProfileScreen`) and "Change password" (opens `ChangePasswordScreen`).
    - Preferences: "Language" (switches Arabic/English), "Appearance" (`ThemeModeSelector` for System, Light, Dark), "Push notifications" (`Switch.adaptive` toggling `NotificationPreferenceController`).
    - About: "Privacy policy" (opens `PrivacyPolicyScreen`), "Contact us" (launches `mailto:` client), "App version" (displays `ApiConfig.appVersion`).
    - Danger Zone: "Log out" (triggers confirmation dialog, clears local storage, routes to `AppRoutes.login`) and "Delete account" (opens `DeleteAccountScreen`).
  - States:
    - Loading: shows skeleton placeholders (`AppSkeletonScope`) during initial profile fetch when no cached user exists.
    - Error: renders `AppErrorState` with retry button on network failure if no cached session user is available.
    - Loaded: renders list view with cached or fresh user data.
- **Profile Edit (`ProfileScreen`)**:
  - Pre-fills user details from `settingsUserProvider` or `SessionCache.instance.user`.
  - Fields:
    - Full name (`AppTextFormField`): editable, required, max 255 chars.
    - Job title (`JobTitlePickerField`): bottom sheet picker populated from `jobTitlesProvider` (`GET /mobile/job-titles`). Selecting "Other" displays a custom title text field (max 120 chars).
    - Email, Phone, Location: read-only fields with informational label ("Email cannot be changed here").
  - "Save changes" (`SubmitButton`): triggers `ProfileSaveController.save(ProfileUpdate)`. Tracks whether fields were modified (`_nameEdited`, `_jobTitleEdited`) so untouched fields send `JobTitleUnchanged()` and never overwrite server data.
  - States: Button shows loading spinner; validation banners highlight input issues; success snackbar pops back to settings.
- **Change Password (`ChangePasswordScreen`)**:
  - Collects Current password, New password (min 8 chars, distinct from current, with `PasswordStrengthMeter`), and Confirm new password.
  - Includes password visibility toggle buttons on each field.
  - "Update password" (`SubmitButton`): calls `/auth/change-password`.
  - States: Guarded against double submissions; shows `AppFormErrorBanner` on client validation failure; maps server 422 errors to current password field error; success snackbar pops back to settings.
- **Delete Account (`DeleteAccountScreen`)**:
  - Displays a high-contrast warning banner outlining irreversible deletion of all CVs, analyses, and history.
  - Collects account password for re-authentication.
  - "Delete my account" (`SubmitButton`): opens secondary confirmation dialog ("Delete forever" / "Cancel").
  - Confirmation triggers `AuthApiService.deleteAccount(password: ...)`.
  - States: Server confirms deletion -> attempts best-effort local cleanup (swallowing any cleanup exceptions) -> routes to `AppRoutes.splash`. Note that local wipe is not guaranteed: `deleteAccount()` swallows cleanup failures, and an error while clearing the auth token skips the remaining cleanup steps (`wipeUserScopedStorage()`, `forgetDevice()`, `clearUser()`). Incorrect password -> displays error snackbar and retains local session.
- **Notifications Inbox (`NotificationsScreen`)**:
  - Displays in-app notifications in a pull-to-refresh list.
  - AppBar provides "Read all" action button (enabled when unread notifications exist).
  - Cards (`NotificationCard`) show title, body, relative timestamp (`notificationTimeLabel`), and unread indicator dot.
  - Tapping a notification:
    - Optimistically marks item as read via `NotificationsInboxController.markRead` and updates `SessionCache.instance.unreadCount`.
    - If actionable (`actionType == 'screen'` or `'url'`), invokes `NotificationService.instance.onNotificationTap` (screen actions pop the inbox first so the destination is visible) and starts `inbox.markRead(item)` fire-and-forget (`unawaited`) without awaiting it and without error snackbar feedback.
    - If non-actionable, awaits `inbox.markRead(item)` and shows an error snackbar if the API call fails, reverting the optimistic read state.
  - States: Skeleton loader during initial load; empty state (`AppEmptyState`) when no items exist; pull-to-refresh reload.
- **Onboarding Tour (`OnboardingScreen`)**:
  - 3-page product tour (`PageView.builder`) explaining ATS CV analysis, scoring, and AI CV building.
  - Header displays `LanguageToggle`, Back arrow (pages 2-3), and "Skip" pill button.
  - Bottom controls: tabular step counter ("1 / 3"), interactive dot indicators (`_PageDots`), and "Next" / "Get started" button.
  - Swipe direction follows RTL/LTR reading direction. Completing or skipping saves `PreferenceStore.onboardingKey` and invokes `onFinished`.
- **Privacy Policy (`PrivacyPolicyScreen`)**:
  - Scrollable view outlining data collection, usage, third-party sub-processors (OpenAI, DeepInfra, RevenueCat, Firebase, Sentry), retention/deletion under PDPL, and support email.

## Entry points

- Named routes in `AppRoutes`:
  - `AppRoutes.settings` (`/settings`): main settings screen.
  - `AppRoutes.notifications` (`/notifications`): in-app notifications inbox.
  - `AppRoutes.privacy` (`/privacy`): privacy policy disclosures; accessible via web query `?screen=privacy`.
- Direct screen transitions:
  - `ProfileScreen`: pushed via `MaterialPageRoute` from the Settings profile card.
  - `ChangePasswordScreen`: pushed via `MaterialPageRoute` from Settings account section.
  - `DeleteAccountScreen`: pushed via `MaterialPageRoute` from Settings danger zone.
  - `OnboardingScreen`: hosted in `SplashScreen` during cold start if onboarding is incomplete.
- Deep links & push notifications:
  - Push notification payloads with `action_type: 'screen'` and `action_url: 'notifications'` or `'settings'`.

## Code map

| File path | Responsibility |
|---|---|
| `lib/features/settings/presentation/settings_screen.dart` | Main settings hub, section navigation, logout dialog, and support contact |
| `lib/features/settings/presentation/settings/settings_widgets.dart` | Settings presentation widgets (`SettingsSectionLabel`, `ProfileSummaryCard`, `ThemeModeSelector`) |
| `lib/features/settings/presentation/profile_screen.dart` | Profile editor screen, job title picker, name validation, read-only account details |
| `lib/features/settings/presentation/change_password_screen.dart` | Password change form, current password verification, new password strength meter |
| `lib/features/settings/presentation/delete_account_screen.dart` | Account deletion screen, warning banner, password verification, confirmation dialog |
| `lib/features/settings/presentation/notifications_screen.dart` | Notifications inbox screen, `NotificationCard`, pull-to-refresh, mark read routing |
| `lib/features/settings/presentation/onboarding_screen.dart` | 3-page product tour, step counter, interactive dots, skip/finish persistence |
| `lib/features/settings/presentation/privacy_policy_screen.dart` | Privacy policy disclosures, sub-processor list, and PDPL compliance information |
| `lib/features/settings/application/settings_controllers.dart` | Riverpod controllers (`settingsUserProvider`, `notificationPreferenceProvider`, `profileSaveProvider`, `notificationsInboxProvider`) |
| `lib/features/settings/domain/notification_item.dart` | `NotificationItem` model, `opensSomething`, `routePayload`, `asRead`, `listFrom` parser |
| `lib/features/settings/domain/profile_update.dart` | `ProfileRules` validation rules, `ProfileFieldError`, `JobTitleChange` sealed hierarchy, `ProfileUpdate` DTO |
| `lib/shared/theme/app_theme_controller.dart` | `AppThemeController` holding and persisting app-wide `ThemeMode` (`system`, `light`, `dark`) |
| `lib/core/utils/app_locale.dart` | `AppLocale` holding and persisting app language (`ar` or `en`) and text direction helpers |
| `lib/shared/services/auth_api_service.dart` | `AuthApiService` methods for profile updates, password changes, account deletion, and logout |
| `lib/shared/services/auth_api_service_provider.dart` | `authApiServiceProvider` Riverpod provider |
| `lib/shared/services/session_cache.dart` | `SessionCache` holding in-memory active user and unread notification badge count |
| `lib/shared/services/notification_service.dart` | `NotificationService` managing FCM token registration, unregistration, and push tap routing |
| `lib/shared/services/notification_engagement_service.dart` | `NotificationEngagementService` reporting telemetry, notification opens, and syncing server preferences |
| `lib/shared/services/mobile_content_service.dart` | `MobileContentService` managing inbox requests, mark-read endpoints, and job titles taxonomy |
| `lib/shared/services/mobile_content_service_provider.dart` | `mobileContentServiceProvider` Riverpod provider |
| `lib/shared/services/job_titles_provider.dart` | `jobTitlesProvider` providing job title taxonomy from `/mobile/job-titles` |
| `lib/core/storage/preference_store.dart` | `PreferenceStore` storing language, theme, notification preference, and onboarding flags |
| `lib/core/storage/user_scoped_storage.dart` | `UserScopedKeys` and `wipeUserScopedStorage` cleaning account data on logout/deletion |
| `lib/core/routing/app_routes.dart` | Named routes table (`AppRoutes.settings`, `AppRoutes.notifications`, `AppRoutes.privacy`) |

## State and data

- **Riverpod providers**:
  - `settingsUserProvider`: `FutureProvider.autoDispose<AuthUser?>` - loads profile from `/auth/me` with fallback to `SessionCache.instance.user`.
  - `notificationPrefsGatewayProvider`: `Provider<NotificationPrefsGateway>` - abstraction gateway for local/server notification preferences.
  - `notificationPreferenceProvider`: `AsyncNotifierProvider.autoDispose<NotificationPreferenceController, bool>` - manages push notification switch state.
  - `profileSaveProvider`: `NotifierProvider.autoDispose<ProfileSaveController, bool>` - tracks in-flight profile update requests.
  - `notificationsInboxProvider`: `AsyncNotifierProvider.autoDispose<NotificationsInboxController, List<NotificationItem>>` - manages in-app notifications list and unread states.
- **ValueNotifiers**:
  - `AppThemeController.themeMode`: `ValueNotifier<ThemeMode>` - live theme mode (`system`, `light`, `dark`).
  - `AppLocale.languageCode`: `ValueNotifier<String>` - live language code (`ar` or `en`).
  - `SessionCache.instance.user`: `ValueNotifier<AuthUser?>` - in-memory cached user session.
- **Sealed outcomes & changes**:
  - `ProfileSaveOutcome`: `ProfileSaved`, `ProfileRejected(error)`, `ProfileSaveFailed`, `ProfileSaveIgnored`.
  - `JobTitleChange`: `JobTitleUnchanged`, `JobTitleCleared`, `JobTitleSelected(id, otherText)`.
- **Caching**:
  - In-memory cache in `MobileContentService` for notifications (30-second TTL; never saved to disk).
  - In-memory cache for job titles (24-hour TTL; cached to disk under `sirati_cache_job_titles`).
  - `SessionCache.instance.unreadCount` synchronizes immediately with read/unread items.
- **Local storage keys**:
  - `FlutterSecureStorage`:
    - `sirati_lang` (`PreferenceStore.langKey`): language preference (`ar` or `en`). Preserved across sessions.
    - `sirati_theme_mode` (`PreferenceStore.themeModeKey`): theme selection (`system`, `light`, `dark`). Preserved across sessions.
    - `sirati_onboarding_completed` (`PreferenceStore.onboardingKey`): tour status (`1` or `0`). Preserved across sessions.
    - `sirati_notifications_enabled` (`PreferenceStore.notificationsKey`): push toggle preference (`1` or `0`). Account-scoped, wiped on logout and account deletion.
  - Account data wiped on Account Deletion or Logout via `wipeUserScopedStorage()` and `_clearLocalSession()`.

## API

- `GET /auth/me` (authenticated client):
  - Response: `{ "data": { "id": 1, "name": "...", "email": "...", "job_title": { ... }, "job_title_id": 5, "phone": "...", "location": "..." } }`.
- `PUT /auth/profile` (authenticated client):
  - Request: `name` (required, trimmed), optional `job_title_id`, optional `job_title_other` (or explicit nulls when cleared).
  - Response: `{ "data": { ...user } }`.
  - Errors: 422 for field validation errors.
- `POST /auth/change-password` (authenticated client):
  - Request: `current_password`, `password`, `password_confirmation`.
  - Response: `{ "message": "..." }`.
  - Errors: 422 for incorrect current password or weak new password.
- `DELETE /auth/account` (authenticated client):
  - Request: `{ "password": "..." }`.
  - Response: `{ "message": "..." }`.
  - Errors: 422 for incorrect password.
- `GET /mobile/notification-preferences` (authenticated client):
  - Response: `{ "data": { "enabled": true } }`.
- `PUT /mobile/notification-preferences` (authenticated client):
  - Request: `enabled` (bool), `language` (`ar` or `en`), `timezone_offset_minutes` (int).
  - Response: `{ "data": { ... } }`.
- `POST /fcm-tokens` (authenticated client):
  - Request: `token`, `device_id`, `platform`, `app_version`, `language`, `timezone_offset_minutes`, `notifications_enabled`.
- `DELETE /fcm-tokens` (authenticated client):
  - Request: `{ "token": "...", "opt_out": true }`.
- `GET /mobile/notifications` (authenticated client):
  - Response: `{ "data": { "items": [ { "id": 1, "title": "...", "body": "...", "is_read": false, "created_at": "...", "action_type": "...", "action_url": "..." } ] } }`.
- `POST /mobile/notifications/$id/read` (authenticated client):
  - Request: `{}`.
  - Response: `{ "data": { ... } }`.
- `POST /mobile/notifications/read-all` (authenticated client):
  - Request: `{}`.
- `POST /mobile/notifications/$notificationId/opened` (authenticated client):
  - Request: `{}`. Telemetry event reported when a notification is tapped.
- `GET /mobile/job-titles` (public client):
  - Response: `{ "data": [ { "id": 1, "slug": "...", "name_ar": "...", "name_en": "..." } ] }`.
- **Error handling**:
  - Errors parsed into typed `ApiException` instances.
  - Presentation displays alerts via `AppSnackBar.fromException` and maps backend validation dictionaries to field error states.

## Localization and RTL

- Fully bi-lingual in Arabic (`ar_SA`, RTL) and English (`en_US`, LTR).
- In-place locale switching:
  - Tapping Language tile or `LanguageToggle` updates `AppLocale.languageCode`, persisting to secure storage and rebuilding all screens immediately.
- Directional layout invariants:
  - List tiles, section headers, and switch controls align according to ambient `Directionality`.
  - Chevrons automatically flip orientation based on reading direction.
  - Numeric step counters ("1 / 3") and dates enforce Latin digits consistently across Arabic and English UI.
  - Email fields enforce `TextDirection.ltr`.
  - Onboarding `PageView` advances following reading direction (swipe right-to-left in RTL).

## Tests

- `test/features/settings/settings_test.dart`:
  - Validates `NotificationPreferenceController`: server value preference over local, fallback handling on unreadable storage, device unregistration even if server sync fails, rapid toggle coalescing, and execution completion after screen disposal.
  - Validates `SettingsScreen`: logout confirmation dialog, graceful recovery when local sign-out fails, navigation to login on success, and WCAG AA contrast compliance in light and dark themes.
  - Tests switch thumb vs track contrast >= 3.0:1 in both light and dark modes.
- `test/features/settings/account_security_test.dart`:
  - Tests `AuthApiService.deleteAccount`: incorrect password preserves local session and push tokens without unregistering; success cleans up session without trailing API calls.
  - Tests `DeleteAccountScreen`: keyboard Done and button clicks prevent duplicate confirmation dialogs.
  - Tests `ChangePasswordScreen`: enforces single in-flight request, re-validates password confirmation when new password is typed, and passes WCAG AA contrast tests.
- `test/features/settings/notifications_test.dart`:
  - Verifies `NotificationItem`: `opensSomething` logic (only non-empty url/screen), invalid row pruning.
  - Verifies `NotificationsInboxController`: mark read updates item and unread badge simultaneously, prevents redundant calls for read items, rolls back optimistic state on API failure.
  - Verifies `NotificationsScreen`: tap navigation dispatch, screen action popping inbox first, and WCAG AA contrast for inbox, privacy, and learning detail screens.
- `test/features/settings/onboarding_test.dart`:
  - Verifies swipe direction in RTL vs LTR, page entrance direction, system Back stepping back page by page before exiting, Skip button hit-target size (>= 48dp), and WCAG AA text and 3:1 graphic contrast.
- `test/features/settings/profile_test.dart`:
  - Unit tests for `ProfileRules`: name (255) and jobTitleOther (120) bounds and trimming.
  - Verifies `ProfileSaveController`: request shapes for unchanged, cleared, and selected job titles, and concurrency debouncing.
  - Verifies `ProfileScreen`: preserving existing job title when name is edited (even if catalogue fails to load or title was retired), and preventing late server responses from overwriting user-typed text.

## Known gaps

- `lib/features/settings/presentation/settings_screen.dart:43-47`: Navigation to `ProfileScreen`, `ChangePasswordScreen`, and `DeleteAccountScreen` uses direct `MaterialPageRoute` pushes rather than named routes from `AppRoutes`.
- `lib/features/settings/presentation/profile_screen.dart:260-281`: Email is read-only, and phone/location cannot be updated from the profile editor even though they are collected during registration.
- `lib/features/settings/presentation/settings_screen.dart:71-78`: Contact Us invokes a `mailto:` URI without checking whether an email client is installed; if launch fails, it shows a warning snackbar prompting the user to manually send an email.
- `lib/features/settings/presentation/notifications_screen.dart:49-50`: Tapping a notification with `actionType == 'screen'` unconditionally pops the navigator before delegating to `onNotificationTap`, assuming the inbox was pushed over the target destination.
- `lib/features/settings/application/settings_controllers.dart:87-109`: `NotificationPreferenceController.setEnabled`: if the device or server sync step fails, it returns `false` (triggering a warning snackbar), but retains the optimistic `AsyncData(enabled)` switch state without rolling back.
- `lib/shared/services/auth_api_service.dart:186-211`: `deleteAccount()` does not guarantee a complete local storage wipe: it swallows cleanup failures in `_clearLocalSession()`, and because cleanup operations execute sequentially, an error while clearing the auth token skips subsequent cleanup steps (`wipeUserScopedStorage()`, `forgetDevice()`, `clearUser()`, and cache clearing).
- `lib/features/settings/presentation/notifications_screen.dart:51`: For actionable notifications, `_open` initiates `inbox.markRead(item)` fire-and-forget using `unawaited()` and returns immediately without awaiting completion or providing failure feedback (no error snackbar) if the mark-read API request fails.
