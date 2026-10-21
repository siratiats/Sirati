# Sirati — Account, Settings & Remaining UX Plan

Scope: settings screen, logout, change password, profile, delete account, session-expiry handling, language persistence, and remaining UX gaps found in code review. Companion to `UI_ENHANCEMENT_PLAN.md` (visual polish — implemented). Covers **both** Flutter (`flutter_app/`) and Laravel backend (repo root).

**Audit findings (code-verified 2026-07-16):**

| # | Finding | Evidence |
|---|---------|----------|
| F1 | **No logout UI anywhere.** `AuthApiService.logout()` exists and works (unregisters FCM, revokes token, clears storage) but is never called from any screen. Users cannot sign out or switch accounts. | `rg logout lib/screens` → 0 hits |
| F2 | **No settings screen, no profile screen, no change-password screen.** | `lib/screens/` listing |
| F3 | **No change-password or delete-account endpoint on backend.** Only register/login/forgot-password/me/logout in `routes/api.php` + `MobileAuthController`. Delete-account is an **App Store / Play Store policy requirement** for apps with account creation. | `routes/api.php` |
| F4 | **Register silently discards phone + location.** `register_screen.dart` collects and validates both (lines ~218–260), but `AuthApiService.register()` never sends them and the backend validator doesn't accept them. User effort thrown away. | `auth_api_service.dart:35–57`, `MobileAuthController::register` |
| F5 | **Language choice is not persisted.** `AppLocale.languageCode` initializes from `Uri.base` query param only — on a real device the app resets to Arabic on every launch. | `app_locale.dart:10–12` |
| F6 | **No session-expiry handling.** Splash trusts token *existence* (`splash_screen.dart:39`) without validating; a revoked/expired token lands the user on Home where every call fails with `ApiErrorType.auth` and there is no path back to login (see F1). | `splash_screen.dart`, `api_client.dart:179` |
| F7 | **Dashboard tab has no header.** `_DashboardTab` computes `name` from the profile payload (~line 201) but never renders it — no greeting, no avatar, no notification bell on the Home tab. Other tabs use `ScreenHeader`; Home doesn't. Notifications are only reachable from other tabs' bells or push taps. | `home_screen.dart` |
| F8 | **`ProfileAvatar` is decorative.** Natural entry point for Settings, currently not tappable on any tab. | `screen_header.dart:192` |
| F9 | **Bell badge never shows.** `ScreenHeader.unreadCount` defaults to 0 and no call site passes a value, even though the notifications API exists. | `rg unreadCount lib/screens` → 0 hits |
| F10 | **`AuthApiService.me()` is dead code** — never called. No cached user identity client-side; avatar initial depends on dashboard payload. | `rg "\.me\(\)" lib` → 0 hits |
| F11 | **Privacy policy unreachable after login** (routed from splash/welcome flow only). | screen usage |

---

## 1. Priority Matrix

| ID | Enhancement | Layer | Impact | Effort | Dependency |
|----|-------------|-------|--------|--------|------------|
| A01 | Settings screen + entry points (tappable avatar on all tabs, header on Home tab) | Flutter | Critical | M | — |
| A02 | Logout flow (confirm dialog → `logout()` → clear → login) | Flutter | Critical | S | A01 (lives in Settings) |
| A03 | Change password: backend endpoint + screen | Both | Critical | M | A01 |
| A04 | Session-expiry handling: central 401 → force re-login; splash validates via `me()` | Flutter | Critical | M | A02 logic |
| A05 | Delete account: backend endpoint + confirm flow (store policy requirement) | Both | High | M | A01, A02 |
| A06 | Persist language (+ expose switch in Settings) | Flutter | High | S | — |
| A07 | Fix register phone/location: persist end-to-end (DB columns + validation + send) **or** remove fields — decide, don't discard silently | Both | High | S–M | — |
| A08 | Profile view/edit (name; email read-only) using `me()` + new update endpoint | Both | Med | M | A01 |
| A09 | Home tab `ScreenHeader` with greeting + avatar + bell (closes F7) | Flutter | High | S | — |
| A10 | Wire `unreadCount` badge from dashboard/notifications payload | Both* | Med | S | A09 |
| A11 | Privacy policy + app version rows in Settings "About" section | Flutter | Low | S | A01 |
| A12 | Notifications preference toggle in Settings (FCM register/unregister already exist) | Flutter | Low | S | A01 |

\* A10 backend part only if the dashboard payload doesn't already include an unread count — check `MobileContentController::dashboard` first.

---

## 2. Backend Work (Laravel)

All in `MobileAuthController` + `routes/api.php` unless noted. Keep bilingual AR-first messages consistent with existing responses.

### 2.1 `POST /auth/change-password` (auth:sanctum)
- Validate: `current_password` (required, must match via `Hash::check`), `password` (required, `min:8`, `confirmed`).
- On mismatch: `ValidationException::withMessages(['current_password' => 'كلمة المرور الحالية غير صحيحة.'])` → surfaces as `ApiErrorType.validation`.
- On success: **revoke all other tokens** (`$user->tokens()->where('id', '!=', currentAccessToken()->id)->delete()`) so stolen sessions die; keep current token so the user isn't logged out of the device they used.
- Response: `{"message": "تم تغيير كلمة المرور بنجاح."}`.

### 2.2 `DELETE /auth/account` (auth:sanctum)
- Validate: `password` (required, `Hash::check`).
- Delete FCM tokens, personal access tokens, then user (confirm cascade/FK behavior for analyses + generated CVs — soft-delete vs hard-delete is a product decision; default **hard delete** with cascading, matching store policy expectations).
- Response: `{"message": "تم حذف الحساب نهائياً."}`.

### 2.3 `PUT /auth/profile` (auth:sanctum) — for A08
- Validate: `name` (required, max:255). Email change is out of scope (needs re-verification flow) — return email read-only.
- Response: updated `userPayload`.

### 2.4 Register phone/location (A07 — if "keep" is chosen)
- Migration: nullable `phone` (string 30), `location` (string 120) on `users`.
- Accept + validate in `register()`; include in `userPayload`.
- If "remove" is chosen instead: delete the two fields from `register_screen.dart` and their validators; nothing else changes.

---

## 3. Flutter Work

### 3.1 `AuthApiService` additions (`lib/services/auth_api_service.dart`)
```dart
Future<String> changePassword({required String currentPassword, required String newPassword, required String confirmation});
Future<void> deleteAccount({required String password}); // then clearToken + unregister FCM
Future<AuthUser> updateProfile({required String name});
```
Reuse `ApiClient.postJson` / add `putJson`/`deleteJson` if missing. All errors flow through existing `ApiException`.

### 3.2 `SettingsScreen` (`lib/screens/settings_screen.dart`) — A01
Structure (all rows `AppListTile` + `PressScale`, sections separated by `AppSpacing.xl` and `AppTextStyles.labelMd` section labels):

1. **Profile card** — `ProfileAvatar` (large), name + email from cached `me()` (see 3.6); tap → `ProfileScreen` (A08). Skeleton bone while loading; `AppErrorState(exception:)` on failure.
2. **الحساب / Account** — Edit profile → `ProfileScreen`; Change password → `ChangePasswordScreen`.
3. **التفضيلات / Preferences** — Language row (current value + toggle, persists via 3.5); Notifications toggle (`Switch` → `NotificationService.registerToken()/unregisterToken()`, persist choice).
4. **حول / About** — Privacy policy → existing `PrivacyPolicyScreen`; App version row (compile-time constant `kAppVersion` in `api_config.dart`-style const — avoids new `package_info_plus` dep; bump with releases).
5. **Danger zone** — Logout row (`AppColors.error` tint, `Icons.logout_rounded`); Delete account row (`AppColors.error`, `Icons.delete_forever_rounded`).

Entry points:
- `ScreenHeader`: add optional `onAvatarTap` → wrap `ProfileAvatar` in `PressScale` + `InkWell`. All four tabs pass `() => push(SettingsScreen())`.
- Home tab: add `ScreenHeader` (A09) with `AppLocale.greeting(name, context)`, status chip from payload, bell → `NotificationsScreen`, avatar → Settings.

### 3.3 Logout flow — A02
- Confirm via `showDialog` (bilingual: "تسجيل الخروج؟ / Log out?"), destructive action styled `AppColors.error`.
- On confirm: loading state on dialog button → `AuthApiService.logout()` (already clears token in `finally`) → `Navigator.pushAndRemoveUntil(LoginScreen, (r) => false)`.
- Offline: `logout()` still clears local token in `finally` — never trap the user; show `AppSnackBar.info` if server call failed but proceed to login.

### 3.4 `ChangePasswordScreen` (`lib/screens/change_password_screen.dart`) — A03
- Three `AppTextFormField`s (current / new / confirm), all `obscureText` with visibility toggles, bilingual validators (reuse register rules; confirm must match new; new must differ from current).
- Reuse `PasswordStrengthMeter` under the new-password field.
- `SubmitButton` + `AppFormErrorBanner` on 422 (map `current_password` server error onto the current-password field via `AppFieldError`).
- Success: `AppSnackBar.success` + pop. Success message bilingual.

### 3.5 Language persistence — A06
- No new packages: persist in existing `FlutterSecureStorage` (add `sirati_lang` key to a small `PreferenceStore` beside `AuthTokenStore`, or extend `AuthTokenStore` → rename responsibility).
- `AppLocale.setLanguage` writes; `main()` reads before `runApp` (fallback: existing URL-param logic for web preview, then 'ar').
- `LanguageToggle` needs no change — it calls `setLanguage`.

### 3.6 Session identity + expiry — A04, F10
- **Splash:** if token exists → call `me()`. `ApiErrorType.auth` → clear token, go to Login with `AppSnackBar.info` ("انتهت الجلسة، سجّل الدخول مجدداً" / "Session expired, please log in again"). Network/timeout error → proceed to Home (offline-friendly; don't lock users out on airplane mode). Cache `AuthUser` in a simple `SessionCache` singleton (`ValueNotifier<AuthUser?>`) consumed by Settings/headers.
- **Central 401:** in `ApiClient`, on `ApiErrorType.auth` for authenticated endpoints, invoke an injectable `onAuthExpired` callback (set once in `main`) → clear token + `navigatorKey` redirect to Login (add `navigatorKey` to `MaterialApp`). Debounce so parallel failing calls redirect once.

### 3.7 Delete account flow — A05
- Settings row → confirmation screen/dialog: explains permanence (bilingual), requires typing password (`AppTextFormField`, obscured), destructive `SubmitButton` in `AppColors.error`.
- Second confirm dialog ("هل أنت متأكد؟ لا يمكن التراجع." / "Are you sure? This cannot be undone.").
- On success: clear token + FCM, `pushAndRemoveUntil(SplashScreen)`, `AppSnackBar.success`.

### 3.8 Unread badge — A10
- If dashboard payload has `unread_notifications`, pass into `ScreenHeader(unreadCount:)` on Home; other tabs may pass cached value from `SessionCache`. Refresh on returning from `NotificationsScreen`.

---

## 4. Accessibility, RTL & Bilingual (same bar as UI plan)

| Area | Requirement |
|------|-------------|
| RTL | Settings rows: `EdgeInsetsDirectional`, chevrons `matchTextDirection` (reuse `AppListTile`). Dialogs: actions follow ambient direction. |
| Bilingual | Every new string has an AR/EN pair in-widget (`AppLocale.isEnglish`). Server messages already AR — client wraps with typed fallbacks like existing screens. |
| Reduced motion | Dialogs/screens use default route transitions (already gated by `MotionSettings.reduce`). No new animation primitives needed. |
| Touch targets | All settings rows ≥ 48dp; destructive rows not adjacent to safe rows without an `AppSpacing.lg` gap. |
| Text scale 1.3 | Profile card + settings rows wrap (no fixed heights); verify AR at 1.3. |
| Semantics | Logout/delete rows: `Semantics(button: true)` with explicit labels; switches announce state. |

---

## 5. Verification Checklist

| # | Check | Pass? |
|---|-------|-------|
| 1 | Avatar on every tab opens Settings; Home tab shows greeting header + bell | yes/no |
| 2 | Logout: confirm → lands on Login; back button does not return to Home; token cleared (relaunch → Login) | yes/no |
| 3 | Logout offline still clears session locally | yes/no |
| 4 | Change password: wrong current password → field-level error on current field (not generic snackbar) | yes/no |
| 5 | Change password success → other devices' tokens revoked (verify via second session) | yes/no |
| 6 | New password shows strength meter; weak passwords blocked by validator | yes/no |
| 7 | Delete account: requires password + double confirm; user + tokens + FCM rows gone server-side | yes/no |
| 8 | Expired/revoked token on cold start → Login with "session expired" notice (not broken Home) | yes/no |
| 9 | Expired token mid-session (revoke server-side, then pull-to-refresh) → single redirect to Login | yes/no |
| 10 | Airplane mode with valid stored token → app still opens Home (no lockout) | yes/no |
| 11 | Language switched to EN, app killed and relaunched → still EN | yes/no |
| 12 | Notifications toggle off → FCM token deleted server-side; on → re-registered | yes/no |
| 13 | Register phone/location either persisted to `users` table **or** fields removed — no silent discard | yes/no |
| 14 | Privacy policy reachable from Settings post-login | yes/no |
| 15 | Bell badge shows unread count when > 0 | yes/no |
| 16 | All new strings render correctly in AR (RTL) and EN at textScale 1.3 | yes/no |
| 17 | `rg "\.me\(\)" lib` → ≥ 1 call site (splash bootstrap) | yes/no |

---

## 6. Implementation Order

1. **A06 + A09** — language persistence, Home header (small, independent, immediately visible).
2. **A01 + A02** — Settings screen + logout (unblocks everything; logout is the single most critical missing feature).
3. **A04** — session expiry (depends on logout redirect plumbing).
4. **A03** — change password (backend endpoint first, then screen).
5. **A05** — delete account (backend + flow).
6. **A07 + A08 + A10 + A11 + A12** — profile edit, register fields decision, badge, about rows, notification toggle.

Backend and Flutter halves of A03/A05/A08 can be built in parallel; Flutter screens can be developed against the validation contract above before endpoints land.
