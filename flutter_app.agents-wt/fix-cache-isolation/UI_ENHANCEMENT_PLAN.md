# Sirati Flutter — Prioritized UI Enhancement Plan

Scope: Material 3 polish, validation UX, loading/motion, accessibility. Reuse only `AppColors`, `AppSpacing`, `AppTextStyles`, `MotionDurations`, `MotionCurves`, `MotionSettings`. No new packages. Paths relative to `flutter_app/`.

**Baseline re-verified against code (2026-07-16):** P01–P08 confirmed still open: raw multiline `SnackBar` in `my_cvs` (~276), `cv_analysis` (~115), `generated_cv` (~83), `analysis_result` (~95); zero call sites pass `exception:`/`errorType:` to `AppErrorState`; `AppListTile` has `InkWell` only; skeleton is opacity-pulse only; no `PasswordStrengthMeter` exists. `AppSnackBar.fromException` **already** gates retry on `isRetryable` (§3.4 is a no-op). Several historical gaps are already partially closed — `cv_generator` uses `AppTextFormField` + validators; register phone/location validate; splash uses `BrandedLoader`; `AppFieldError` animates in; `AppFormErrorBanner` uses `error_outline_rounded`; `ApiErrorType` + typed `AppErrorState` icons exist; skeletons/`MotionReveal`/`PressScale` are adopted on more surfaces. This plan prioritizes **remaining** work only.

---

## 1. Priority Matrix

| ID | Enhancement | Screen(s) | Impact | Effort | Dependency |
|----|-------------|-----------|--------|--------|------------|
| P01 | Replace plain `SnackBar` with `AppSnackBar` (+ retry where `ApiException.isRetryable`) | `my_cvs`, `cv_analysis`, `generated_cv`, `analysis_result` | High | S | — |
| P02 | Wire `AppErrorState(exception:)` / `errorType:` at every FutureBuilder error branch | `home`, `my_cvs`, `job_news`, `education`, `education_detail`, `notifications`, `history`, `AppAsyncBody` | High | S | — |
| P03 | `cv_analysis` paste field: raw `TextFormField` → `AppTextFormField` + bilingual validator | `cv_analysis` | High | S | — |
| P04 | Wrap `AppListTile` (when `onTap != null`) in `PressScale` | All list surfaces via widget | High | S | — |
| P05 | Password strength meter under register password field | `register` | High | M | DS: `AppColors.success/warning/error` |
| P06 | Field success/valid chrome on `AppTextFormField` (green wash + check) | Auth + `cv_generator` + `cv_analysis` via shared field | High | M | DS: form_fields |
| P07 | Gradient shimmer sweep for `AppSkeleton` (replace opacity-only pulse) | All skeleton consumers | High | M | DS: app_skeleton + MotionSettings |
| P08 | Animated ATS score bar (tween value + label) | `history` (~236) **and** `analysis_result` (~319, ~409 — two static bars confirmed, not optional) | High | M | New widget |
| P09 | AI Enhance: branded loading on JD field (shimmer overlay) + button loading via `PressScale`/spinner swap | `cv_generator` | High | M | P07 preferred |
| P10 | Adopt `AppAsyncBody` on list screens still hand-rolling wait/error | `my_cvs`, `job_news`, `education`, `notifications`, `education_detail` | Med | M | P02 |
| P11 | `MotionReveal` on job_news card rows still missing order stagger | `job_news` | Med | S | — |
| P12 | Enhance button: replace ad-hoc spinner with pattern matching `SubmitButton` loading chrome | `cv_generator` | Med | S | — |
| P13 | Terms checkbox row: `PressScale` + larger tap target | `register` | Low | S | — |
| P14 | Soft press on `cv_generator` step chips / optional field groups | `cv_generator` | Low | S | — |
| P15 | Align `BrandedLoader` loop duration to `MotionDurations` family (document as skeleton-adjacent) | `splash` via `branded_loader` | Low | S | — |
| P16 | Optional field-level success copy EN/AR (“Looks good” / “يبدو جيداً”) after blur when valid | Auth screens | Med | S | P06 |
| P17 | `analysis_result` inline spinners → primary-themed / skeleton bones where layout known | `analysis_result` | Med | S | — |

Sort order: Impact desc, Effort asc (as table above).

---

## 2. Quick Wins (do first)

### QW1 — Unified snackbars (P01)
- **What:** Delete `_showError` / `_message` / `_showMessage` that call raw `ScaffoldMessenger.showSnackBar(SnackBar(...))`. Route through `AppSnackBar.error` / `.success` / `.fromException`.
- **Files + symbols:**
  - `lib/screens/my_cvs_screen.dart` → `_message`
  - `lib/screens/cv_analysis_screen.dart` → `_showError`
  - `lib/screens/generated_cv_screen.dart` → `_showMessage`
  - `lib/screens/analysis_result_screen.dart` → `_showError`
- **Reuse:** `AppSnackBar` (`lib/widgets/app_snack_bar.dart`), variants `error`/`success`/`info`/`warning`, `AppSnackBar.fromException(context, e, english: …, onRetry: …)` when catch is `ApiException` and `e.isRetryable`.
- **Done when:** Grep for `showSnackBar(SnackBar` under `lib/screens/` returns **zero** matches; errors show colored bar + icon; retry appears only for network/timeout/server.

### QW2 — Typed error icons at call sites (P02)
- **What:** Every `AppErrorState(...)` that has an `Object`/`ApiException` error must pass `exception:` or `errorType:`. Fix `AppAsyncBody` so it does not default all errors to network.
- **Files + symbols:**
  - `lib/widgets/loading/app_async_body.dart` → `AppAsyncBody.build` error branch (pass `exception: error is ApiException ? error : null`)
  - `lib/screens/home_screen.dart`, `my_cvs_screen.dart`, `job_news_screen.dart`, `education_screen.dart`, `education_detail_screen.dart`, `notifications_screen.dart`, `history_screen.dart` → error `AppErrorState` constructors
- **Reuse:** `AppErrorState` (`lib/widgets/empty_state.dart`) + `ApiException` / `ApiErrorType` (`lib/services/api_exception.dart`). Icons already mapped: `wifi_off_rounded`, `timer_off_outlined`, `lock_outline_rounded`, `search_off_rounded`, `cloud_off_rounded`, `error_outline_rounded`.
- **Done when:** Force a non-network failure (e.g. 404/422 mock) shows non-wifi icon; offline still shows wifi.

### QW3 — CV analysis paste field parity (P03)
- **What:** Replace resume paste `TextFormField` with `AppTextFormField`; require non-empty when no file attached (mirror existing submit rules).
- **File + symbol:** `lib/screens/cv_analysis_screen.dart` (~line 300 `TextFormField` on `_resumeTextController`).
- **Reuse:** `AppTextFormField`, `AppFormStyles`, spacing `AppSpacing.md` / `AppSpacing.xl` already on screen; optional top `AppFormErrorBanner` if submit blocked.
- **Done when:** Empty paste + no file shows `AppFieldError` under the field (EN/AR), not only a snackbar; field gets soft red wash via existing `softErrorFill`.

### QW4 — List tile press feedback (P04)
- **What:** When `onTap != null`, wrap tile content in `PressScale` before `InkWell` (or wrap the whole interactive child).
- **File + symbol:** `lib/widgets/app_list_tile.dart` → `AppListTile.build`.
- **Reuse:** `PressScale` (`lib/widgets/motion.dart`) — scale `0.975`, respects `MotionSettings.reduce` already.
- **Done when:** Tappable rows on home/history/notifications depress slightly; with Reduce Motion / large tablet, scale is static (no animation).

### QW5 — History ATS bar animation (P08 minimal path)
- **What:** Replace static `LinearProgressIndicator(value: score/100)` with a tweened bar that animates on first paint and when score changes.
- **File + symbol:** `lib/screens/history_screen.dart` → analysis list (~`LinearProgressIndicator` ~236) and generated CV list counterpart (~same pattern).
- **Reuse:** New `AnimatedAtsScoreBar` (see §5); colors from existing `_scoreColor` using `AppColors.tealDark` / `primary` / `amber` / `red`; duration `MotionDurations.slow`, curve `MotionCurves.enter`; if `MotionSettings.reduce` → snap to final value.
- **Done when:** Opening History, bars ease to score; pull-to-refresh with same score does not jarringly restart; reduced-motion snaps.

---

## 3. Design-System Hardening

Changes that unblock many screens. Touch only these symbols/files.

### 3.1 `lib/widgets/form_fields.dart`

| Symbol | Change |
|--------|--------|
| `AppFormStyles` | Add `successBorder` / `focusedSuccessBorder` using `AppColors.success` (widths mirror `errorBorder` / `focusedErrorBorder`). Add `successTextStyle` mirroring `errorTextStyle` with `AppColors.success`. |
| `AppTextFormField` | New optional `showSuccessWhenValid` (default `false`) + optional `successMessage`. When `!hasError && value non-empty && validator would return null` (only after field interacted / `autovalidateMode` active), paint fill `AppColors.successLight` @ ~0.35 alpha, border success, suffix check `Icons.check_circle_rounded` in `AppColors.success`. |
| `AppFieldError` | Already has fade+slide via `TweenAnimationBuilder` + `MotionDurations.medium` + `MotionCurves.enter` + `MotionSettings.reduce` — **do not rework**. |
| `AppFormErrorBanner` | Already `Icons.error_outline_rounded` — **do not rework**. |
| `AppFormSuccessBanner` | Already present — use on forgot-password / post-submit success inline if still ad-hoc. |

Reduced-motion: success border uses existing `AnimatedContainer` (`MotionDurations.fast` / `MotionCurves.state`); when reduce is on, duration effectively instant is acceptable (same as error path).

### 3.2 `lib/widgets/loading/app_skeleton.dart`

| Symbol | Change |
|--------|--------|
| `AppSkeleton` / `AppSkeletonScopeState` | Keep shared controller. Change decoration from solid `AppColors.surfaceHigh` opacity pulse to a **horizontal gradient sweep**: base `AppColors.surfaceHigh`, highlight `AppColors.surface` / `AppColors.surfaceLow`. Animate alignment of `LinearGradient` (or `ShaderMask`) over `MotionDurations.skeleton` with `MotionCurves.skeleton`. |
| RTL | Sweep direction: use `Directionality` so highlight moves **start → end** (rightward LTR, leftward RTL). |
| `MotionSettings.reduce` | Keep current static bone at opacity `0.7` — **no sweep**. |
| Prebuilts | `DashboardSkeleton`, `CvListSkeleton`, `JobNewsSkeleton`, `ListScreenSkeleton`, `EducationSkeleton` inherit automatically — no API change. |

### 3.3 `lib/widgets/loading/app_async_body.dart`

| Symbol | Change |
|--------|--------|
| Error branch (~line 43) | Pass `exception: snapshot.error is ApiException ? snapshot.error as ApiException : null` into `AppErrorState`. Do **not** invent new error taxonomy. |
| No-data branch (~line 52) | Currently generic "No data" with default (wifi) icon — pass `errorType: ApiErrorType.unknown`. |

### 3.4 `lib/widgets/app_snack_bar.dart`

| Symbol | Change |
|--------|--------|
| `AppSnackBar.fromException` | ✅ **Verified 2026-07-16:** retry already gated on `exception.isRetryable && onRetry != null` — no change needed. |
| No new colors | Stick to existing scheme mapping (`AppColors.error/success/warning/info` + light fills already in `_scheme`). |

### 3.5 `lib/widgets/motion.dart`

| Symbol | Change |
|--------|--------|
| `MotionDurations` / `MotionCurves` / `MotionSettings` | **No new duration constants** unless absolutely required; ATS bar + field success reuse `slow`/`medium`/`fast` + `enter`/`state`. |
| `PressScale` | No API change; expand adoption at call sites. |
| `MotionReveal` | No API change; ensure list `order:` indices stay ≤ `MotionReveal.maxStaggerIndex`. |

### 3.6 `lib/theme/app_theme.dart`

| Symbol | Change |
|--------|--------|
| `AppColors` / `AppSpacing` | **Do not invent new tokens.** Use `success`/`successLight`, `error`/`errorLight`, `warning`/`warningLight`, `primaryGradient`, `softShadow`, spacing scale as-is. |
| `SiratiMark` | Already used by `BrandedLoader` — no change. |

### 3.7 `lib/widgets/empty_state.dart` — `AppErrorState`

Icons already type-aware. Hardening is **call-site wiring** (P02), not new glyphs.

**Safety net (recommended, 1 line):** the fallback at ~line 143 is `errorType ?? exception?.type ?? ApiErrorType.network` — change the final fallback to `ApiErrorType.unknown`. Today every unwired call site shows a wifi icon for *any* error; with this change, unwired sites show a neutral `error_outline` instead, which is correct-by-default even before P02 lands everywhere. Pass `errorType: ApiErrorType.network` explicitly where offline is the known cause.

---

## 4. Per-Screen Worklist

### 4.1 `lib/screens/splash_screen.dart`
- Already uses `BrandedLoader` — keep.
- Optional P15: in `lib/widgets/loading/branded_loader.dart`, map pulse duration closer to `MotionDurations.skeleton` (or document why 1400ms); reduced-motion path already static `SiratiMark`.

### 4.2 `lib/screens/login_screen.dart`
- Snackbars already `AppSnackBar` — keep.
- `_SocialButton` already `PressScale` — keep.
- Enable `showSuccessWhenValid: true` on email/password after P06.
- No skeleton work.

### 4.3 `lib/screens/register_screen.dart`
- Phone/location validators already present — keep.
- Add `PasswordStrengthMeter` under password `AppTextFormField` (P05).
- Enable success state on name/email/phone/location/password after P06.
- Wrap terms `Checkbox` + label row with `PressScale` or enlarge hit target with `InkWell` + `PressScale` (P13). Bilingual labels unchanged.
- Keep `AppFormErrorBanner` + `SubmitButton`.

### 4.4 `lib/screens/forgot_password_screen.dart`
- Prefer `AppFormSuccessBanner` for “link sent” if still using a one-off container.
- Success field chrome on email after P06.

### 4.5 `lib/screens/home_screen.dart`
- Pass `exception:` into `AppErrorState` (P02).
- Optionally replace hand-rolled wait/error with `AppAsyncBody` + `DashboardSkeleton` (already loading widget) — P10.
- `MotionReveal` / `PressScale` largely present — audit only.

### 4.6 `lib/screens/my_cvs_screen.dart`
- `_message` → `AppSnackBar` (P01).
- `AppErrorState(exception:)` (P02).
- Loading already `CvListSkeleton` — after P07 gets shimmer free.
- `MotionReveal` / `PressScale` present on cards — keep.
- Optional: collapse FutureBuilder branches into `AppAsyncBody` (P10).

### 4.7 `lib/screens/cv_generator_screen.dart`
- Fields already `AppTextFormField` + validators — keep.
- Enhance control (~`OutlinedButton.icon` + `CircularProgressIndicator`): use loading label swap pattern like `SubmitButton`, wrap with `PressScale` (P12).
- While `_isEnhancingJobDescription`, overlay `AppSkeleton`-style shimmer (or `AiTextFieldShimmer`) on the job description field bounds (P09).
- Enable success chrome on required fields after step validation (P06).
- Optional: show `AppFormErrorBanner` when step validation fails (if still snack-only for step gate).
- Step chips: `PressScale` (P14).

### 4.8 `lib/screens/generated_cv_screen.dart`
- `_showMessage` → `AppSnackBar` (P01).
- Loading is not async-list; keep `MotionReveal`/`PressScale` on CTAs.
- No skeleton required unless download waits — if blocking, use small primary `CircularProgressIndicator` themed via `progressIndicatorTheme` already in `AppTheme`.

### 4.9 `lib/screens/cv_analysis_screen.dart`
- `_showError` → `AppSnackBar.error` / `.fromException` (P01).
- Paste `TextFormField` → `AppTextFormField` + validator (P03).
- Job title field already `AppTextFormField` — enable success state (P06).
- Keep `SubmitButton` loading.

### 4.10 `lib/screens/analysis_result_screen.dart`
- `_showError` → `AppSnackBar` (P01).
- Replace ad-hoc full-screen/list `CircularProgressIndicator` waits with `ListScreenSkeleton` or branded primary indicator (P17).
- Score ring `_AnimatedScoreRing` exists — ensure it respects `MotionSettings.reduce` (snap to value).
- Two static `LinearProgressIndicator` bars (~319, ~409) → `AnimatedAtsScoreBar` (P08 — definite, verified).

### 4.11 `lib/screens/history_screen.dart`
- `LinearProgressIndicator` → `AnimatedAtsScoreBar` (P08 / QW5).
- `AppErrorState(exception:)` (P02).
- Loading already `ListScreenSkeleton` — shimmer via P07.
- Cards already `PressScale` — keep.

### 4.12 `lib/screens/job_news_screen.dart`
- `AppErrorState(exception:)` (P02).
- Ensure featured + latest rows use `MotionReveal(order: i)` (P11); cards already `PressScale`.
- Optional `AppAsyncBody` (P10) with existing `JobNewsSkeleton`.

### 4.13 `lib/screens/education_screen.dart` / `education_detail_screen.dart`
- `AppErrorState(exception:)` (P02).
- List already `EducationSkeleton` + `MotionReveal`/`PressScale`.
- Detail already local `AppSkeletonScope` bones — inherit shimmer (P07).

### 4.14 `lib/screens/notifications_screen.dart`
- `AppErrorState(exception:)` (P02).
- `ListScreenSkeleton` + `MotionReveal` + `PressScale` present — keep.
- Optional `AppAsyncBody` (P10).

### 4.15 `lib/screens/privacy_policy_screen.dart`
- No loading/validation gaps in scope — **no UI work**.

---

## 5. New Reusable Widgets to Add

Only widgets that are genuinely missing.

### 5.1 `PasswordStrengthMeter`
- **Path:** `lib/widgets/password_strength_meter.dart`
- **Responsibility:** Given password `String`, compute score 0–3 (empty / weak / medium / strong) with simple rules (length ≥ 8, letter, digit, symbol) — no packages. Renders 3-segment bar + bilingual label.
- **Tokens:** Track `AppColors.border` / fill segments `AppColors.error` → `AppColors.warning` → `AppColors.success`; gaps `AppSpacing.xxs`; label `AppTextStyles.labelMd` / `bodySm`. Animate segment fill with `AnimatedContainer` `MotionDurations.fast` + `MotionCurves.state`; if `MotionSettings.reduce` → no tween, instant colors.
- **RTL:** `Row` of segments in ambient direction; labels `TextAlign.start`.
- **Consumers:** `register_screen.dart` only (under password field).

### 5.2 `AnimatedAtsScoreBar`
- **Path:** `lib/widgets/animated_ats_score_bar.dart`
- **Responsibility:** `score` 0–100, `color`, optional height (default 5). Tweens from previous/0 to `score/100` using `TweenAnimationBuilder` or short `AnimationController`.
- **Tokens:** Track `AppColors.border`; value color passed in (from screen `_scoreColor`); radius 4; duration `MotionDurations.slow`; curve `MotionCurves.enter`.
- **Reduced motion:** `value: score/100` immediately, no controller.
- **RTL:** Linear bar is direction-agnostic (fill LTR math is fine); parent row labels already `TextAlign.start`.
- **Consumers:** `history_screen.dart` (both tabs); optional linear sections on `analysis_result_screen.dart`.

### 5.3 `AiFieldLoadingOverlay` (or private in generator if single-use)
- **Path:** `lib/widgets/loading/ai_field_loading_overlay.dart` (prefer shared if analysis ever enhances text)
- **Responsibility:** Stack child field with semi-opaque shimmer bones while `isLoading`. Does not steal focus permanently; ignores pointers when loading.
- **Tokens:** Uses `AppSkeleton` / scope; padding `AppSpacing.xs`; border radius `AppFormStyles.radius`.
- **Reduced motion:** Static `AppColors.surfaceHigh` veil + small `CircularProgressIndicator` color `AppColors.primary` (theme).
- **Consumers:** `cv_generator_screen.dart` job description group during `_isEnhancingJobDescription`.

**Do not add:** second snackbar system, new color classes, dark theme, package-based shimmer.

---

## 6. Accessibility & RTL Checks

| Area | Requirement |
|------|-------------|
| Reduced motion | Every new animation gates on `MotionSettings.reduce(context)`: ATS bar snaps; password segments instant; shimmer → static bone; press scale already no-ops; success field border may still color-change without motion. |
| Text scale 1.3 | `AppFieldError`, banners, password strength labels, list tiles: use `Expanded`/`Flexible`, `maxLines` + `overflow: ellipsis` where fixed height; avoid fixed-height rows for multi-line AR error copy. Verify register form and history cards at textScale 1.3 without horizontal clip. |
| RTL | All new rows use `EdgeInsetsDirectional` / `TextAlign.start` / `AlignmentDirectional`. Shimmer sweep follows `Directionality` (start→end). Chevron in `AppListTile` already `arrow_forward_ios_rounded` (matchTextDirection). Password field stays `TextDirection.ltr` for input; strength labels use ambient locale direction. |
| Semantics | Password strength: `Semantics(liveRegion: true, label: …)` when level changes (EN/AR strings). Error banners already dismissible with icon buttons — keep `tooltip` or semantic label if missing. |
| Contrast | Success green `AppColors.success` on `successLight`; error red on `redLight` — no new greys. |
| Touch targets | Terms checkbox row ≥ 48dp height; social buttons already 50dp. |
| Bilingual | Every user-visible string in new widgets takes `english` or uses ambient `AppLocale.isEnglish(context)` with AR pair in the same widget. |

---

## 7. Verification Checklist

| # | Check | Pass? |
|---|-------|-------|
| 1 | `rg -U "showSnackBar\\(\\s*SnackBar" lib/screens` → 0 hits (**`-U` required** — all four current offenders put `SnackBar(` on the next line; without multiline flag this check passes falsely today) | yes/no |
| 2 | `my_cvs` download/error uses `AppSnackBar` with icon + floating behavior | yes/no |
| 3 | `cv_analysis` empty paste (no file) shows `AppFieldError` under field | yes/no |
| 4 | `cv_analysis` empty job title shows `AppFieldError` | yes/no |
| 5 | `cv_generator` empty name on step shows `AppFieldError` (not only snackbar) | yes/no |
| 6 | Register phone empty / short → bilingual field error | yes/no |
| 7 | Register location empty → bilingual field error | yes/no |
| 8 | Register password strength updates weak→medium→strong in EN and AR | yes/no |
| 9 | Valid email after interaction shows success chrome (green check) when P06 shipped | yes/no |
| 10 | Offline list load → `AppErrorState` wifi icon + Retry | yes/no |
| 11 | 404/validation error → non-wifi icon (`search_off` / `error_outline`) | yes/no |
| 12 | `AppAsyncBody` error path passes `ApiException` type through | yes/no |
| 13 | Home/My CVs/Job News/Education/Notifications/History show skeleton on first load | yes/no |
| 14 | Skeletons use gradient shimmer (not only opacity pulse) when motion allowed | yes/no |
| 15 | `MotionSettings.reduce` / `disableAnimations` → static skeletons, no ATS tween, no press scale animation | yes/no |
| 16 | History ATS bar animates to score; reduced-motion snaps | yes/no |
| 17 | `cv_generator` Enhance: button loading state + JD field overlay while `_isEnhancingJobDescription` | yes/no |
| 18 | Splash shows `BrandedLoader` / `SiratiMark`, not bare unthemed spinner only | yes/no |
| 19 | Tappable `AppListTile` rows depress via `PressScale` | yes/no |
| 20 | Job news list items stagger with `MotionReveal` (cap order) | yes/no |
| 21 | EN/AR locale switch: all new strings flip; RTL mirrors paddings and shimmer direction | yes/no |
| 22 | Text scale 1.3: register form + history cards — no clipped error text | yes/no |
| 23 | `AppFormErrorBanner` icon is `error_outline_rounded` (not info) | yes/no |
| 24 | `AppFieldError` fades/slides in when motion allowed | yes/no |
| 25 | No new pubspec dependencies added | yes/no |

---

## 8. Regression Guard (lightweight, no new packages)

- **Widget tests** (only for the three new widgets): `PasswordStrengthMeter` — score boundaries (7 vs 8 chars, letter/digit/symbol combos) + AR label rendering; `AnimatedAtsScoreBar` — final `value == score/100` and reduced-motion snap; `AiFieldLoadingOverlay` — pointer-ignore while loading.
- **Lint tripwires** (run in CI or pre-merge, all must return 0):
  - `rg -U "showSnackBar\(\s*SnackBar" lib/screens` (P01)
  - `rg "AppErrorState\(" lib/screens lib/widgets -A4 | rg -v "exception:|errorType:"` — manual scan for unwired call sites (P02)
- **Manual pass per PR:** run checklist §7 rows for the IDs shipped, in both EN and AR, once with Reduce Motion on.

---

## Implementation order (execution)

1. **P01 → P02 → P03 → P04** (same PR or sequential: pure adoption, zero visual-system risk).  
2. **P08** ATS bar widget + history.  
3. **P07** skeleton shimmer (unblocks perceived quality everywhere).  
4. **P05 + P06 + P16** register/auth field feedback.  
5. **P09 + P12** cv_generator AI loading.  
6. **P10 / P11 / P13 / P14 / P15 / P17** cleanup polish.

Stop when checklist rows for shipped IDs are all **yes**.
