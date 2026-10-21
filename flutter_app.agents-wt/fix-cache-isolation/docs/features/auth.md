# Auth

The auth feature governs user onboarding, authentication, account registration, email verification, password recovery, session persistence, and secure credential cleanup for the Sirati app. It enables job seekers to securely authenticate and preserve access to their AI-generated CVs, ATS score history, and career preferences, while maintaining strict local data isolation between accounts on shared devices.

## User flow

- **Splash & Onboarding Gate (`SplashScreen`)**:
  - Resolves stored credentials on startup via `SessionBootstrap`.
  - If a valid token exists, queries `/auth/me`:
    - Verified user: routes to `HomeScreen` (and consumes any pending deep link).
    - Unverified user: replaces root route with `EmailVerificationScreen`.
    - 401 unauthenticated: purges local session and drops to unauthenticated flow.
    - Offline or network error: navigates to `HomeScreen` to serve cached data without trapping the user.
  - If unauthenticated, inspects `PreferenceStore.onboardingKey`:
    - Missing or false (first launch): displays the 3-page product tour (`OnboardingScreen`).
    - Completed: presents `WelcomeBody` with "Create Account", "Sign In", and "Privacy Policy" actions.
- **Sign In (`LoginScreen`)**:
  - User enters email (trimmed) and password (untrimmed; only non-empty presence is checked locally).
  - Main controls:
    - "Sign in" (`SubmitButton`): triggers `LoginController.signIn`. Concurrency-locked against double submission.
    - "Forgot password?": opens `ForgotPasswordScreen`.
    - "Create account": opens `RegisterScreen` (replaces route if stacked, pushes if root).
    - Language switcher (`LanguageToggle`): toggles Arabic/English in-place.
    - Third-party buttons ("Google", "Apple"): disabled while `socialSignInEnabled` is false; tapping displays an informative "coming soon" snackbar.
  - States:
    - Loading: button shows localized `Signing in...` spinner.
    - Session expired notice: displays one-shot snackbar if launched via mid-session 401 redirect (`sessionExpiredNotice: true`).
    - Validation error: highlights missing fields, displays top banner (`AppFormErrorBanner`), and triggers haptic feedback.
    - API failure: displays typed error message via `AppSnackBar.fromException` with a "Retry" button.
- **Registration (`RegisterScreen`)**:
  - Collects Full Name (max 255), Email (max 255), Phone Number (8–15 digits), Location (max 120), optional Job Title picker, custom Job Title if "Other" (max 120), Password (min 8 chars, with real-time `PasswordStrengthMeter`), and Terms & Conditions agreement (`TermsAgreement`).
  - Main controls:
    - "Create Account" (`SubmitButton`): invokes `RegisterController.submit`.
    - "Sign In" (`SignInPrompt`): returns to `LoginScreen`.
    - "Continue without account": bypasses authentication to preview the app in guest mode via `AppRouter.openAfterAuth`.
  - States:
    - Loading: submit button shows `Creating account...` spinner and ignores redundant inputs.
    - Validation failure: banner appears, unaccepted terms trigger a warning snackbar, and invalid fields highlight in red.
    - Success: transitions root route to `EmailVerificationScreen`.
- **Email OTP Verification (`EmailVerificationScreen`)**:
  - Always presented as the root route for accounts where `email_verified` is false.
  - Displays recipient email and a 6-digit numeric input field.
  - Auto-submits immediately when the 6th digit is typed, pasted, or autofilled, or on keyboard Done / "Verify email" tap.
  - Resend countdown timer: 60-second cooldown period prevents rapid resend calls.
  - Exit protection: `PopScope(canPop: false)` intercepts system Back and back button, calling `AuthApiService.logout()` and navigating to `LoginScreen` so an unverified session never lingers. If logout fails, displays an error snackbar and keeps the user on-screen.
  - Success: routes to `HomeScreen` via `AppRouter.openAfterAuth`.
- **Forgot & Reset Password (`ForgotPasswordScreen`)**:
  - Step 1 (Request OTP): user submits email address; calls `/auth/forgot-password`. Success advances to Step 2 and activates a 60-second cooldown timer.
  - Step 2 (Reset Password): user enters the 6-digit code, new password (min 8 chars with `PasswordStrengthMeter`), and confirmation password; calls `/auth/reset-password`. Success displays a snackbar and pops back to `LoginScreen`. User can tap "Use a different email" to revert to Step 1.
- **Mid-Session Expiry (`AuthSessionGuard`)**:
  - Listens to HTTP 401 responses globally across `ApiClient`.
  - Debounces concurrent 401 events, unregisters device push tokens, clears secure tokens, flushes memory and disk caches, wipes all account-scoped data, rotates the device FCM token, and replaces the navigator stack with `LoginScreen(sessionExpiredNotice: true)`.

## Entry points

- Named routes in `AppRoutes`:
  - `AppRoutes.splash` (`/`): app cold start; evaluates session and routes to home, onboarding, or welcome.
  - `AppRoutes.login` (`/login`): sign-in screen.
  - `AppRoutes.register` (`/register`): account creation screen; accessible via web query `?screen=register`.
- Direct screen transitions:
  - `EmailVerificationScreen`: pushed as root route via `replaceRoot` on registration or unverified sign-in.
  - `ForgotPasswordScreen`: pushed via `MaterialPageRoute` from `LoginScreen`.
  - `OnboardingScreen`: rendered within `SplashScreen` during the first-run tour.
- Deep links & OS intents:
  - Cold-start URLs (`sirati://app/...` or HTTPS) are held in `AppRouter.pendingLocation` and opened after successful authentication via `AppRouter.openAfterAuth`.

## Code map

| File path | Responsibility |
|---|---|
| `lib/features/auth/presentation/login_screen.dart` | Sign-in UI, credential forms, autofill, social sign-in stubs, and navigation |
| `lib/features/auth/presentation/register_screen.dart` | Registration UI, field validation, job title picker, password strength, terms checkbox |
| `lib/features/auth/presentation/register/register_widgets.dart` | Register presentation components (`RegisterHero`, `FieldLabel`, `TermsAgreement`, `SignInPrompt`, `fieldErrorText`) |
| `lib/features/auth/presentation/email_verification_screen.dart` | 6-digit OTP verification UI, Arabic digit formatter, 60s cooldown, logout-on-exit guard |
| `lib/features/auth/presentation/forgot_password_screen.dart` | Two-step password reset flow (OTP dispatch and password change) |
| `lib/features/auth/application/login_controller.dart` | `LoginController`, `loginControllerProvider`, in-flight concurrency lock, `LoginOutcome` sealed hierarchy |
| `lib/features/auth/application/register_controller.dart` | `RegisterController`, `registerControllerProvider`, registration submission, `RegisterOutcome` sealed hierarchy |
| `lib/features/auth/domain/registration_rules.dart` | Client-side registration rules (`RegistrationRules`), `FieldError` enum, `RegistrationInput` DTO, digit normalization export |
| `lib/features/auth/domain/social_sign_in.dart` | `socialSignInEnabled` boolean feature flag controlling OAuth UI visibility |
| `lib/features/auth/data/auth_session_guard.dart` | `AuthSessionGuard` handling mid-session 401s, request token freshness checks, cache purges, and login redirect |
| `lib/shared/models/auth_session.dart` | `AuthSession` and `AuthUser` domain models, JSON parsing, and localized job title resolution |
| `lib/shared/services/auth_api_service.dart` | `AuthApiService` managing HTTP calls for authentication, password recovery, verification, and session teardown |
| `lib/shared/services/auth_api_service_provider.dart` | `authApiServiceProvider` Riverpod provider |
| `lib/shared/services/session_cache.dart` | `SessionCache` in-memory singleton holding reactive `AuthUser` and unread notification badge counts |
| `lib/shared/services/session_user_provider.dart` | `sessionUserIdProvider` watching active user ID to invalidate user-scoped state on account switch |
| `lib/core/storage/auth_token_store.dart` | `AuthTokenStore` wrapping `FlutterSecureStorage` for the bearer token (`sirati_auth_token`) |
| `lib/core/storage/preference_store.dart` | `PreferenceStore` storing secure device and account preferences |
| `lib/core/storage/user_scoped_storage.dart` | `UserScopedKeys` and `wipeUserScopedStorage` deleting account data on sign-out while preserving device settings |
| `lib/core/storage/disk_cache.dart` | `DiskCache` offline JSON cache, purged on logout via `clear()` |
| `lib/features/dashboard/application/session_bootstrap.dart` | `SessionBootstrap` and `LaunchDestination` sealed hierarchy determining startup routing destination |
| `lib/features/dashboard/presentation/splash_screen.dart` | `SplashScreen` orchestrating startup transitions between boot, onboarding, and welcome |

## State and data

- **Riverpod providers**:
  - `loginControllerProvider`: `NotifierProvider.autoDispose<LoginController, bool>` - tracks whether a login request is currently in flight.
  - `registerControllerProvider`: `NotifierProvider.autoDispose<RegisterController, bool>` - tracks whether a registration request is in flight.
  - `authApiServiceProvider`: `Provider<AuthApiService>` - provides the authentication API client.
  - `sessionBootstrapProvider`: `Provider<SessionBootstrap>` - resolves the startup destination.
  - `sessionUserIdProvider`: `Provider<int?>` - emits the current authenticated user ID, invalidating dependent providers when the user changes.
- **Sealed outcomes**:
  - `LoginOutcome`: `LoginSucceeded(email, emailVerified)`, `LoginRejected(error)`, `LoginFailedUnexpectedly()`, `LoginIgnored()`.
  - `RegisterOutcome`: `RegisterSucceeded(email)`, `RegisterRejected(error)`, `RegisterFailedUnexpectedly()`, `RegisterIgnored()`.
  - `LaunchDestination`: `LaunchHome()`, `LaunchVerifyEmail(email)`, `LaunchOnboarding()`, `LaunchWelcome()`.
- **In-memory caching**:
  - `SessionCache.instance.user`: `ValueNotifier<AuthUser?>` holding the current user profile.
  - `MobileContentService.clearAllCaches()`: drops all in-memory content caches on logout or session expiry.
- **Local storage keys**:
  - `FlutterSecureStorage`:
    - `sirati_auth_token` (`AuthTokenStore`): bearer authorization token. Wiped on logout, 401 expiry, or account deletion.
    - `sirati_cv_draft` (`PreferenceStore.cvDraftKey`): CV generator draft JSON. Wiped on logout via `wipeUserScopedStorage()`.
    - `sirati_education_interest` (`PreferenceStore.educationInterestKey`): education interest opt-in. Wiped on logout.
    - `sirati_notifications_enabled` (`PreferenceStore.notificationsKey`): notification opt-in. Wiped on logout.
    - Preserved device settings (managed by `PreferenceStore`, never wiped on sign-out):
      - `sirati_lang` (`PreferenceStore.langKey`): language preference (`ar` or `en`).
      - `sirati_theme_mode` (`PreferenceStore.themeModeKey`): appearance mode (`system`, `light`, or `dark`).
      - `sirati_onboarding_completed` (`PreferenceStore.onboardingKey`): product tour completion flag (`1` or `0`).
      - `sirati_review_requested` (`PreferenceStore.reviewRequestedKey`): in-app review prompt status.
      - `sirati_cv_generation_count` (`PreferenceStore.cvGenerationCountKey`): successful generation counter on device.
  - `SharedPreferences`:
    - `sirati_cv_draft_<id>` (`UserScopedKeys.builderDraftPrefix`): builder draft per CV ID. Wiped on logout.
    - `sirati_cv_doc_<id>` (`UserScopedKeys.builderDocPrefix`): builder document per CV ID. Wiped on logout.
    - `sirati_cv_ids` (`UserScopedKeys.builderIndexKey`): index of builder document IDs. Wiped on logout.
    - `sirati_cache_*` (`DiskCache.keyPrefix`): offline content cache. Wiped on logout via `DiskCache.instance.clear()`.

## API

- `POST /auth/login` (public client):
  - Request: `email`, `password`, `device_name: 'sirati-mobile'`.
  - Response: `{ "data": { "token": "...", "token_type": "Bearer", "user": { ... } } }`.
  - Errors: 422 for invalid credentials or missing fields, 429 for rate limiting.
- `POST /auth/register` (public client):
  - Request: `name`, `email`, `password`, `password_confirmation`, `device_name: 'sirati-mobile'`, optional `phone`, `location`, `job_title_id`, `job_title_other`.
  - Response: `{ "data": { "token": "...", "token_type": "Bearer", "user": { ... } } }`.
  - Errors: 422 for duplicate email or field rule validation violations.
- `POST /auth/email/verify` (authenticated client):
  - Request: `code` (6-digit OTP).
  - Response: `{ "data": { ...user } }`.
  - Errors: 422 for invalid or expired OTP code.
- `POST /auth/email/resend` (authenticated client):
  - Request: `{}`.
  - Response: `{ "message": "..." }`.
- `POST /auth/forgot-password` (public client):
  - Request: `email`.
  - Response: `{ "message": "..." }`.
- `POST /auth/reset-password` (public client):
  - Request: `email`, `code`, `password`, `password_confirmation`.
  - Response: `{ "message": "..." }`.
  - Errors: 422 for invalid code or mismatched passwords.
- `POST /auth/logout` (authenticated remote client):
  - Request: `{}`. Invoked after local storage is wiped as a fire-and-forget remote token invalidation.
- `DELETE /fcm-tokens` (authenticated remote client):
  - Request: `{ "token": "<fcm_token>" }`. Unlinks the current device token before remote logout.
- `GET /auth/me` (authenticated client):
  - Response: `{ "data": { ...user } }`. Verifies session validity and checks `email_verified` during bootstrap.
- **Error handling**:
  - `ApiClient` throws `ApiException` with classified `ApiErrorType` (`auth`, `validation`, `rateLimit`, `network`, `server`, `unknown`), HTTP status code, and parsed error message / `errors` dictionary.
  - Presentation displays error feedback via `AppSnackBar.fromException` with optional localized retry actions.

## Localization and RTL

- Default language is Arabic (`ar_SA`, RTL); English (`en_US`, LTR) is fully supported.
- Direction-aware navigation:
  - Back buttons use `DirectionalIcon(Icons.arrow_back_ios_new_rounded)` which mirrors automatically in RTL.
  - Back gestures and header alignments follow ambient `Directionality`.
- BiDi text handling:
  - Form labels and instructional texts use `TextAlign.start`.
  - Credentials, emails, passwords, phone numbers, and OTP inputs explicitly enforce `TextDirection.ltr`.
  - Digit normalization: `LatinDigitsInputFormatter` and `latinDigits` automatically convert Arabic-Indic (٠-٩) and Extended Arabic-Indic (۰-۹) digits into standard ASCII digits (0-9).
- Language switching:
  - `LanguageToggle` in app headers triggers `AppLocale.setLanguage()`, updating reactive state and immediately rebuilding UI without restart.

## Tests

- `test/features/auth/login_test.dart`:
  - Validates `loginPasswordError` enforces presence without artificial length limits.
  - Ensures `LoginController` trims email, retains password whitespace, reports email verification status, prevents concurrent submissions (`LoginIgnored`), and handles API exceptions.
  - Verifies `LoginScreen` widget integration, keyboard Send deduplication, 401/422/429/500 error display in SnackBars, password visibility toggle tooltips, and WCAG AA contrast compliance in light and dark modes.
- `test/features/auth/register_controller_test.dart`:
  - Guarantees `RegisterController` normalizes inputs, mirrors password confirmation, falls back to typed email if server returns an empty email string, blocks double-submit races, and maps exceptions.
- `test/features/auth/register_screen_test.dart`:
  - Confirms keyboard Done and button submit fire only once while in-flight, verifies third-party OAuth buttons are hidden when `socialSignInEnabled` is false, and enforces WCAG AA contrast.
- `test/features/auth/registration_rules_test.dart`:
  - Unit tests field bounds: Name (255), Email (255 + format), Phone (8–15 digits, symbol sanitization, Arabic numeral conversion), Location (120), JobTitleOther (120, only when "Other"), and Password (minimum 8 characters untrimmed).
- `test/features/auth/email_verification_test.dart`:
  - Guarantees Arabic keyboard digits auto-convert and auto-submit upon reaching 6 digits, single in-flight submit locking, system Back sign-out to `LoginScreen`, error handling when local sign-out fails, and WCAG AA contrast.
- `test/features/auth/otp_and_reset_test.dart`:
  - Tests `LatinDigitsInputFormatter` handling ASCII, Arabic-Indic, Extended digits, paste operations with spaces/hyphens, and `ForgotPasswordScreen` light/dark WCAG AA contrast.
- `test/features/auth/sign_out_wipe_test.dart`:
  - Verifies `logout()` and `AuthSessionGuard.handleAuthExpired(401)` wipe account-specific secure storage and SharedPreferences while preserving device-level settings (`sirati_lang`, `sirati_theme_mode`, `sirati_onboarding_completed`).
  - Verifies isolation of in-memory CV builder documents between consecutive user accounts and resilience against storage write failures.
- `test/auth_session_guard_test.dart`:
  - Verifies a delayed 401 for an old token does not purge a newer token, failed account deletion preserves local credentials, logout clears token immediately, public login does not send stale bearer headers, and malformed responses become typed `ApiException` instances.
- `test/login_layout_test.dart`:
  - Asserts `AuthFormConstraint` constraints, centered branding header, and start-aligned form field labels.

## Known gaps

- `lib/features/auth/domain/social_sign_in.dart:7`: `socialSignInEnabled` is hardcoded to `false`. Google and Apple sign-in buttons are disabled; tapping them triggers stub snackbars in `lib/features/auth/presentation/login_screen.dart:114-132`.
- `lib/features/auth/presentation/login_screen.dart:258-262`: Email field in login screen checks only `!v.contains('@')`, whereas registration uses the stricter regex `RegistrationRules.email` in `lib/features/auth/domain/registration_rules.dart:23`.
- `lib/features/auth/presentation/register_screen.dart:425-436`: "Continue without account" directly invokes `AppRouter.openAfterAuth(context)`, bypassing authentication entirely to grant preview access to Home without clear entitlement boundary messaging on the registration screen.
- `lib/features/auth/presentation/register_screen.dart:58-60`: Direct dependency on `FirebaseCrashlytics.instance.log` inside `initState` instead of routing through an abstraction service.
- `lib/features/auth/presentation/email_verification_screen.dart:151-172`: If `logout()` throws during `_leave()`, the screen blocks exit via `PopScope(canPop: false)`, trapping the user on the verification screen until sign-out succeeds.
