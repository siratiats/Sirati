# Sirati — Release Readiness Plan

Scope: fix the 3 verified bugs from the account/settings review, run the pending migration, close the manual verification loop, then release-hardening (crash reporting, store requirements, forgot-password UX). Backend = repo root (Laravel), Flutter = `flutter_app/`.

---

## 1. Priority Matrix

| ID | Item | Layer | Impact | Effort |
|----|------|-------|--------|--------|
| R01 | Delete account must cascade-delete `cv_analyses` + `generated_cvs` | Backend | Critical | S |
| R02 | 403 must not trigger session logout — only 401 | Backend + Flutter | Critical | S |
| R03 | Rate-limit login/register/forgot-password/change-password/delete-account | Backend | High | S |
| R04 | Run `phone`/`location` migration on every deployed environment | Backend | Critical | S |
| R05 | Manual verification pass — `ACCOUNT_SETTINGS_PLAN.md` §5, all 17 rows, on device | QA | Critical | M |
| R06 | Backend feature tests for change-password, delete-account, update-profile | Backend | High | M |
| R07 | Crash reporting (Firebase Crashlytics) | Flutter | High | S |
| R08 | Store account-deletion + privacy disclosure prep (Apple + Google) | Product/Store | Critical | M |
| R09 | Forgot-password: deep link back into app instead of stranding on web page | Both | Med | M |
| R10 | `flutter analyze` + release build smoke test | Flutter | High | S |

Sort: Impact desc, Effort asc.

---

## 2. Bug Fixes (do first — same PR as the account/settings work)

### R01 — Delete account leaves orphaned data
**Problem:** `cv_analyses.user_id` and `generated_cvs.user_id` use `nullOnDelete()` (`2026_06_24_000002_add_user_ownership_to_cv_tables.php`). `MobileAuthController::deleteAccount()` deletes `fcmTokens` and `tokens` but not these — a "permanently deleted" account leaves the user's full CV content and analyses in the database with `user_id = null`, unreachable but not erased. Direct GDPR/App Store privacy-answer contradiction ("we delete your data" claims a store submission will require).

**Fix** — `app/Http/Controllers/MobileAuthController.php::deleteAccount()`, before `$user->delete()`:
```php
$user->cvAnalyses()->delete();
$user->generatedCvs()->delete();
$user->fcmTokens()->delete();
$user->tokens()->delete();
$user->delete();
```
Both relations exist on `User` already (`cvAnalyses()`, `generatedCvs()` — `User.php:54,59`). If generated CVs have stored PDF files on disk (check `GeneratedCvController` for a `pdf_path`/storage disk field), delete the file too — confirm during R06 test-writing whether an `Observer` or explicit `Storage::delete()` call is needed.

**Done when:** deleting an account with ≥1 analysis and ≥1 generated CV leaves zero rows referencing the former user id (verify via `DB::table('cv_analyses')->where('user_id', $id)->count()` in a test, not just visually).

### R02 — 403 incorrectly triggers global logout
**Problem:** `ApiClient._typeFromStatus` (`api_client.dart:192`) maps both 401 and 403 to `ApiErrorType.auth`, and `_send` (`api_client.dart:113`) fires `onAuthExpired` for *any* `ApiErrorType.auth`. A plain authorization failure — e.g. requesting a CV that belongs to another user, a policy check failing — has nothing to do with the token being invalid, but it silently logs the user out and redirects to Login. This is a false-positive session kill on every 403 the backend ever returns (including ones that don't exist yet).

**Fix, two parts:**
- **Backend:** confirm which 403s the API actually returns today (policy denials on `generated-cvs`/`cv-analyses` show/update/destroy — check `GeneratedCvController`/`CvAnalysisController` for `abort(403)` or policy calls). Where the intent is "not your resource," prefer `404` over `403` (standard practice — don't confirm existence to non-owners) if that doesn't conflict with existing tests.
- **Flutter (belt-and-suspenders regardless of the backend decision):** split `ApiErrorType.auth` into token-invalid (401) vs forbidden (403), or gate `onAuthExpired` on the raw status code rather than the mapped type. Minimal patch in `api_client.dart::_send`:
  ```dart
  if (response.statusCode == 401 &&
      _tokenProvider != null &&
      onAuthExpired != null) {
    onAuthExpired!();
  }
  ```
  Keep `ApiErrorType.auth` covering both for UI purposes (icon/message), just don't let 403 drive the logout side-effect.

**Done when:** a 403 response shows the normal `AppErrorState`/snackbar for that screen and does **not** clear the token or navigate to Login; a 401 still does.

### R03 — No rate limiting on auth endpoints
**Problem:** `bootstrap/app.php` has an empty `withMiddleware` callback and `routes/api.php` applies no `throttle` to any `/auth/*` route. `login`, `change-password`, and `delete-account` all check a submitted password against the hash with unlimited attempts — the latter two are reachable by anyone holding a valid (possibly stolen) token, turning them into offline-free brute-force oracles against the account password.

**Fix** — `routes/api.php`:
```php
Route::post('/auth/register', ...)->middleware('throttle:5,1');
Route::post('/auth/login', ...)->middleware('throttle:5,1');
Route::post('/auth/forgot-password', ...)->middleware('throttle:3,1');
```
Inside the `auth:sanctum` group:
```php
Route::post('/auth/change-password', ...)->middleware('throttle:5,1');
Route::delete('/auth/account', ...)->middleware('throttle:5,1');
```
Values are starting points (5 attempts/minute per IP) — tune after checking whether Laravel's default `api` throttle (60/min, applied automatically by `withRouting(api: ...)` in Laravel 11) already covers general abuse; these are tighter, endpoint-specific limits on top of it.

**Done when:** 6 rapid failed login attempts from one IP return `429 Too Many Requests` on the 6th; same for change-password/delete-account with a valid token + wrong password.

---

## 3. Deployment / Data

### R04 — Run the pending migration
`database/migrations/2026_07_16_000001_add_phone_location_to_users_table.php` exists in the repo but a migration file existing ≠ applied. Confirm `php artisan migrate` (or `migrate --force` in production) has run on every environment (local, staging, prod) — otherwise register requests referencing `phone`/`location` will 500 on any environment still missing the columns.

**Done when:** `php artisan migrate:status` shows the migration as `Ran` on every environment; a real register call with phone+location succeeds end-to-end against that environment.

---

## 4. Verification

### R05 — Manual checklist pass
Run all 17 rows of `ACCOUNT_SETTINGS_PLAN.md` §5 on a physical device (both EN/AR, once with reduced motion), not the simulator only — session-expiry and offline rows (#8–10) depend on real network transitions that don't reproduce reliably in a simulator. Log failures as new tasks; don't fix inline during the pass — separates verification from rework.

### R06 — Backend feature tests
New `tests/Feature/` cases (Laravel's default test setup — check `tests/Feature` for existing patterns to match, e.g. `ChangePasswordTest`, `DeleteAccountTest`, `UpdateProfileTest`):
- Change password: wrong current password → 422 with `current_password` error; correct → 200, old password no longer works, other tokens revoked, current token still valid.
- Delete account: wrong password → 422; correct → 200, user row gone, `cv_analyses`/`generated_cvs`/`fcm_tokens`/`tokens` all gone for that user id (this test will fail today until R01 lands — write it first, watch it fail, then fix R01).
- Update profile: name updates; email unchanged even if submitted (read-only enforcement, if not already validated away).
- Rate limiting: 6th rapid login attempt returns 429 (once R03 lands).

**Done when:** `php artisan test --filter=Auth` (or equivalent) passes, and the delete-account cascade test specifically fails before R01 and passes after.

---

## 5. Release Hardening

### R07 — Crash reporting
Firebase is already wired (`firebase_core`, `firebase_messaging` in `pubspec.yaml`; `Firebase.initializeApp()` in `main.dart`) — adding `firebase_crashlytics` is additive, not a new integration. Add the package, initialize in `main()` after `Firebase.initializeApp()`, wrap `runApp` in `runZonedGuarded` piping uncaught errors to Crashlytics, and set `FlutterError.onError`. Respect the existing bilingual/no-new-package discipline from the earlier plans — this is the one justified exception since Firebase is already a dependency.

### R08 — Store account-deletion & privacy requirements
Both Apple App Store and Google Play require, for any app offering account creation: an in-app path to delete the account (✅ now shipped — `delete_account_screen.dart`) and accurate data-safety/privacy-nutrition-label disclosures describing what's collected (name, email, phone, location, CV content, FCM token) and confirming deletion removes it (⚠️ blocked on R01). Prepare:
- Apple "App Privacy" declaration (data types collected, linked to identity, used for tracking: no).
- Google Play "Data safety" form (same categories).
- Update `privacy_policy_screen.dart` content if it doesn't yet mention phone/location collection or the delete-account capability.

**Done when:** both forms are filled and consistent with R01's actual cascade behavior — don't submit privacy claims the backend doesn't yet honor.

### R09 — Forgot-password deep link
Currently `forgotPassword()` sends a reset-link email that opens a web page outside the app (`Password::sendResetLink` — standard Laravel web reset flow). After reset, the user has to manually return to the app and log in. Consider a deep link (`sirati://reset-complete` or universal/app link) that the reset-confirmation web page redirects to, landing the user back on `LoginScreen` with a "password updated, log in" notice — mirrors the `sessionExpiredNotice` pattern already built in R02/A04. Lower priority than R01–R06; do after the bug fixes and store prep.

### R10 — Build smoke test
Before any release: `flutter analyze` (zero warnings on touched files at minimum), then a release build (`flutter build apk --release` / `flutter build ios --release`) launched on a real device covering: cold start → login → all 4 tabs → settings → logout → re-login → change password → delete account (on a disposable test user) → confirm account gone.

---

## 6. Verification Checklist

| # | Check | Pass? |
|---|-------|-------|
| 1 | Deleting an account with existing CVs/analyses leaves zero DB rows referencing that user id | yes/no |
| 2 | A 403 response does not clear the token or navigate to Login | yes/no |
| 3 | A 401 response still clears token + navigates to Login (unchanged) | yes/no |
| 4 | 6th rapid login attempt from one IP → 429 | yes/no |
| 5 | 6th rapid change-password/delete-account attempt (valid token, wrong password) → 429 | yes/no |
| 6 | `php artisan migrate:status` shows phone/location migration `Ran` on prod | yes/no |
| 7 | Register with phone+location on prod succeeds end-to-end | yes/no |
| 8 | All 17 rows of `ACCOUNT_SETTINGS_PLAN.md` §5 pass on a physical device, EN + AR | yes/no |
| 9 | New backend feature tests pass; delete-account cascade test fails-then-passes across R01 | yes/no |
| 10 | Crashlytics receives a test non-fatal error in Firebase console | yes/no |
| 11 | Apple + Google privacy/data-safety forms match actual deletion behavior | yes/no |
| 12 | `flutter analyze` clean; release build smoke test completes end-to-end on device | yes/no |

---

## Implementation Order

1. **R01 → R02 → R03** (bug fixes, one PR, backend-heavy — these are correctness/security issues, not polish).
2. **R04** (confirm/run migration — blocks R07 pass row 7).
3. **R06** (write tests, prove R01 with a failing→passing test).
4. **R05** (manual device pass, both languages).
5. **R10** (analyze + smoke build).
6. **R07** (Crashlytics — cheap, do before wider testing/beta so crashes are visible).
7. **R08** (store forms — do last, must reflect R01's real behavior).
8. **R09** (nice-to-have UX, no dependency on the rest — schedule whenever).
