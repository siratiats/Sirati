# Release Readiness Status

Updated: 2026-07-16

| ID | Item | Status |
|----|------|--------|
| R01 | Cascade-delete CVs/analyses on account delete | **Done** — `MobileAuthController::deleteAccount` |
| R02 | 403 must not logout — only 401 | **Done** — `ApiClient._send` gates on `statusCode == 401` (backend already uses 404 for non-owned resources) |
| R03 | Rate-limit auth endpoints | **Done** — throttle on register/login/forgot/change-password/delete |
| R04 | phone/location migration | **Done locally** — run `php artisan migrate --force` on staging/prod |
| R05 | Manual 17-row device checklist | **Pending human QA** — `ACCOUNT_SETTINGS_PLAN.md` §5 on physical device |
| R06 | Backend feature tests | **Done** — `tests/Feature/MobileAccountLifecycleTest.php` (7 passing) |
| R07 | Crashlytics | **Done** — package + `main.dart` + Android Gradle plugin (run `flutter pub get`) |
| R08 | Store privacy disclosures | **Partial** — in-app privacy policy updated; Apple/Google console forms still product task |
| R09 | Forgot-password deep link | **Not started** (lower priority) |
| R10 | flutter analyze + release smoke | **Pending** — run locally after `flutter pub get` |

## Commands

```bash
# Backend (each environment)
php artisan migrate --force
php artisan test --filter=MobileAccount
php artisan test --filter=MobileAuth

# Flutter
cd flutter_app
flutter pub get
flutter analyze
flutter build apk --release   # or ios
```

## Smoke path (device)

Cold start → login → 4 tabs → Settings → logout → re-login → change password → disposable delete-account → confirm gone.
