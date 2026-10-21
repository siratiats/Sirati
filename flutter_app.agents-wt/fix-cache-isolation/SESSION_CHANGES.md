# Sirati Flutter — Session Change Log

This document summarizes everything implemented across the agent prompts in this session (splash polish → dark mode → final cleanup).

**Scope:** `flutter_app/` (Sirati mobile app)  
**Languages:** English + Arabic (RTL)

---

## Table of contents

1. [Splash screen enhancements (P0–P3)](#1-splash-screen-enhancements-p0p3)
2. [Onboarding (P4)](#2-onboarding-p4)
3. [Settings — Replay intro](#3-settings--replay-intro)
4. [Motion: accessibility vs tablet stagger](#4-motion-accessibility-vs-tablet-stagger)
5. [Splash: non-blocking session bootstrap](#5-splash-non-blocking-session-bootstrap)
6. [Honest empty/error data (no fake personal data)](#6-honest-emptyerror-data-no-fake-personal-data)
7. [Lazy lists (My CVs + Job News)](#7-lazy-lists-my-cvs--job-news)
8. [Large text / fixed-height overflow](#8-large-text--fixed-height-overflow)
9. [Pull-to-refresh, back handling, a11y targets](#9-pull-to-refresh-back-handling-a11y-targets)
10. [Template preview images](#10-template-preview-images)
11. [Dashboard motion polish + CV success beat](#11-dashboard-motion-polish--cv-success-beat)
12. [CV Generator fixed footer + progress bar](#12-cv-generator-fixed-footer--progress-bar)
13. [Dark mode](#13-dark-mode)
14. [Final cleanup](#14-final-cleanup)
15. [Regression checklist (code-level)](#15-regression-checklist-code-level)
16. [Key files touched](#16-key-files-touched)

---

## 1. Splash screen enhancements (P0–P3)

### P0 — Native launch continuity
- Android: brand cream `#FAF7F2` (`splash_background`), centered splash logos (mdpi–xxxhdpi), launch + normal themes.
- iOS: LaunchScreen cream background; real `LaunchImage` assets (replaced empty placeholders).
- Goal: no white → cream flash on cold start.

### P1 — Welcome UI
- Value card: elevated surface + border + soft shadow (no invisible same-as-bg “card”).
- Removed nested secondary logo and dead “Browse templates first” line.
- Compact feature chips: ATS score / AI CV / Bilingual (later simplified when full onboarding shipped).

### P2 — Motion & CTAs
- `MotionStateSwitcher` for boot → welcome (and later onboarding).
- Staggered `MotionReveal`, `SubmitButton` + `PressScale`, RTL-friendly forward arrow.

**Primary file:** `lib/screens/splash_screen.dart`  
**Native:** Android `launch_background`, `colors.xml`, `styles.xml`; iOS `LaunchScreen.storyboard`, LaunchImage assets.

---

## 2. Onboarding (P4)

### Flow
```
Cold start → Bootstrap
  ├─ valid token → Home (immediate)
  ├─ first run   → Onboarding (3 pages) → Welcome CTAs
  └─ seen before → Welcome CTAs
```

### Features
- 3 bilingual pages: Welcome / Instant ATS / AI CV.
- Skip, page dots, Next / Get started.
- RTL: `PageView(reverse: true)` + directional arrow.
- Persisted via `PreferenceStore` key `sirati_onboarding_completed`.
- Cream/brand look (not the old multi-color mock).

**New file:** `lib/screens/onboarding_screen.dart`  
**Updated:** `preference_store.dart`, `splash_screen.dart`, `test/widget_test.dart`

---

## 3. Settings — Replay intro

- **Settings → About → Replay app intro** / **إعادة مقدمة التطبيق**
- Opens full-screen onboarding without logout; finish/skip/back returns to Settings.
- Does not reset cold-start flag in a way that blocks future use; completion stays marked.

**File:** `lib/screens/settings_screen.dart`

---

## 4. Motion: accessibility vs tablet stagger

### Problem
`MotionSettings.reduce()` treated `shortestSide >= 700` as reduce-motion, killing **all** animation on tablets (including skeleton shimmer).

### Fix
| API | Meaning |
|-----|---------|
| `MotionSettings.reduce` | **Only** `MediaQuery.disableAnimations` (a11y) |
| `MotionSettings.limitStagger` | a11y **or** large screen — used **only** by `MotionReveal` delay |

All other motion (page transitions, tabs, nav icons, press scale, skeletons) uses a11y-only `reduce`.

**File:** `lib/widgets/motion.dart`

---

## 5. Splash: non-blocking session bootstrap

### Problem
Cold start with a token awaited `me()` before navigating → long blank splash.

### Fix
1. Token present → `registerToken()` + `pushReplacement(Home)` **immediately**.
2. Background `me()` (unawaited).
3. **401:** `ApiClient` → `AuthSessionGuard` (clear session + login notice) — no splash duplicate redirect.
4. Network/timeout/server → stay on Home (offline-friendly).
5. Logged-out path unchanged.

**File:** `lib/screens/splash_screen.dart`

---

## 6. Honest empty/error data (no fake personal data)

### Removed
- Fake name “Mohammed”/«محمد», fake stats `145` / `2`, fake “2 hours ago · Riyadh” activity.
- Fake My CVs list (3 invented CVs + ATS %).
- Fake Education profile “Ahmed”, target role, invented study cards.

### Replaced with
- Stats: **em-dash `—`** when absent.
- Greeting: API → SessionCache → neutral **Welcome** / **مرحباً**.
- Empty activity: `AppEmptyState`.
- Product marketing copy only on Create/Analyze action cards.

**Files:** `home_screen.dart`, `my_cvs_screen.dart`, `education_screen.dart`, `bidi_text.dart`

---

## 7. Lazy lists (My CVs + Job News)

### My CVs
- `ListView.builder`: header, empty state or cards, create CTA.
- Template picker also builder-based.

### Job News
- `CustomScrollView` + header sliver + `AnimatedSwitcher` (featured) + `SliverList.builder` (rows).
- Pull-to-refresh + `AlwaysScrollableScrollPhysics` preserved.
- Detail: `CustomScrollView` for consistency.

**Files:** `my_cvs_screen.dart`, `job_news_screen.dart`

---

## 8. Large text / fixed-height overflow

### Changes
- Dashboard stat/action cards: `ConstrainedBox(minHeight: …)` instead of fixed height.
- App-wide text scale clamp **1.0–1.3** in `MaterialApp` builder.
- `SubmitButton`: `minimumSize` only (no `fixedSize` clip).
- FittedBox / minHeight on several chrome controls (avatars, score ring, social buttons, etc.).

**Files:** `home_screen.dart`, `main.dart`, `submit_button.dart`, and related screens/widgets.

---

## 9. Pull-to-refresh, back handling, a11y targets

### Pull-to-refresh
- **Dashboard** and **My CVs** wrap scrollables in `RefreshIndicator` + `AlwaysScrollableScrollPhysics`.
- Job News / History / Notifications already had refresh.

### Android back at Home root
1. Non-dashboard tab → return to Dashboard.
2. On Dashboard: first back → snackbar *“Press back again to exit”* / *“اضغط رجوع مرة أخرى للخروج”*; second within 2s → `SystemNavigator.pop()`.
3. iOS: still silent at root (no double-exit).

### A11y
- “View All” hit target ≥ 44px height.
- Notifications bell, avatar, FAB: Tooltip + Semantics (localized).
- Stat cards: `MergeSemantics` (“My CVs, 12”).
- List tiles: single semantic button label; decorative icons excluded.

**Files:** `home_screen.dart`, `my_cvs_screen.dart`, `screen_header.dart`, `add_button.dart`, `app_list_tile.dart`

---

## 10. Template preview images

Shared widget `lib/widgets/template_preview.dart` (`TemplatePreview`):

| Feature | Implementation |
|---------|----------------|
| Memory | `cacheWidth: (44 * devicePixelRatio).round()` |
| Loading | `AppSkeleton(44×56, r:8)` |
| Fade-in | 200ms, `MotionCurves.enter` |
| Reduce motion | No fade |
| Errors | Screen-specific `errorFallback` |

Used by My CVs and Generated CV template pickers.

---

## 11. Dashboard motion polish + CV success beat

### Dashboard
- Loading / error / data wrapped in `MotionStateSwitcher` (`'loading'` / `'error'` / `'data'`).
- Stat numbers: count-up 0→n once (~600ms, `MotionCurves.enter`); stable across rebuilds/tabs; reduce → snap.

### CV generation success
- `SuccessBeat.play(context)`: check icon scale/fade + `HapticFeedback.mediumImpact`; ~700ms then navigate.
- Reduce motion: haptic only, navigate immediately.

**Files:** `home_screen.dart`, `widgets/success_beat.dart`, `cv_generator_screen.dart`

---

## 12. CV Generator fixed footer + progress bar

### Fixed bottom bar
- Back / Next / Generate moved **out** of the step `ListView` into a stable footer (`SafeArea`, surface, top border, soft upward shadow).
- ListView bottom padding reduced; only step fields scroll/animate.

### Progress bar
- Under “Step x of y”: 4px rounded bar, animates with `MotionDurations.slow` + `MotionCurves.state`.
- Fill from **start edge** (`AlignmentDirectional.centerStart` + `FractionallySizedBox`) → LTR left→right, RTL right→left.
- Reduce motion: jump, no tween.
- Removed large commented-out old step-indicator block.

**File:** `lib/screens/cv_generator_screen.dart`

---

## 13. Dark mode

### Theme system
| Piece | Detail |
|-------|--------|
| `SiratiColors` | `ThemeExtension` with all brand/semantic roles; `context.sirati` |
| Light palette | Existing `AppColors` values |
| Dark palette | Teal brand: bg `#121614`, surface `#1A201D`, primary `#2FC4B2`, text ramp for AA |
| `AppTheme.light` / `.dark` | Full component themes (app bar, buttons, inputs, chips, bottom nav, etc.) |
| Persistence | `PreferenceStore` `sirati_theme_mode` + `AppThemeController` |
| Settings | Appearance chips: System / Light / Dark (bilingual) |
| Migration | Widgets + screens prefer `context.sirati.*`; `AppColors` remains light source of truth |

### Files
- `lib/theme/sirati_colors.dart`
- `lib/theme/app_theme.dart`
- `lib/theme/app_theme_controller.dart`
- `lib/services/preference_store.dart`
- `lib/main.dart`
- `lib/screens/settings_screen.dart`
- Most screens/widgets updated for theme colors

---

## 14. Final cleanup

| Task | Result |
|------|--------|
| Preview routes web-only | `kIsWeb ? query['screen'] : null` |
| Edge-to-edge | `SystemUiMode.edgeToEdge`; transparent system bars; Android styles + cutout mode |
| Global overlay style | `AppBarTheme.systemOverlayStyle` + MaterialApp builder |
| Line endings | `.gitattributes` (`* text=auto eol=lf`) at repo + `flutter_app/` |
| Format | `dart format lib test` (dozens of files) |
| Analyze | Theme/`const` issues cleaned; remaining env issue: `firebase_crashlytics` needs `flutter pub get` with real Git on PATH |

---

## 15. Regression checklist (code-level)

| Scenario | Expected | Code support |
|----------|----------|--------------|
| Arabic RTL | Slides, arrows, watermarks, progress mirror | `MotionAxis`, directional icons, `AlignmentDirectional` progress fill |
| Reduce motion | No animations; usable | `MotionSettings.reduce` across motion system |
| Max font | No overflow on key screens | Text scale clamp + minHeight cards + flexible buttons |
| Offline + token | Home, honest placeholders, no login wall | Immediate Home bootstrap + silent network failures |
| TalkBack | Meaningful labels | Semantics on chrome + lists + stats |
| Long lists | Smooth scroll | Builder/sliver lists + stagger limit |
| Back | Tab→dashboard; double-back exits with hint | `PopScope` on Home |

**Device QA still recommended** for TalkBack, DevTools 100-item scroll, and real Android 15 edge-to-edge screenshots.

---

## 16. Key files touched

### New
- `lib/screens/onboarding_screen.dart`
- `lib/widgets/template_preview.dart`
- `lib/widgets/success_beat.dart`
- `lib/theme/sirati_colors.dart`
- `lib/theme/app_theme_controller.dart`
- `flutter_app/.gitattributes`
- `SESSION_CHANGES.md` (this file)

### Core / infrastructure
- `lib/main.dart`
- `lib/theme/app_theme.dart`
- `lib/widgets/motion.dart`
- `lib/services/preference_store.dart`
- `lib/utils/bidi_text.dart`
- Android splash + edge-to-edge styles; iOS launch assets

### Screens (selected)
- `splash_screen.dart`, `home_screen.dart`, `settings_screen.dart`
- `my_cvs_screen.dart`, `job_news_screen.dart`, `cv_generator_screen.dart`
- Auth, education, history, analysis, notifications, etc. (theme + polish)

### Widgets (selected)
- `screen_header.dart`, `add_button.dart`, `app_list_tile.dart`, `submit_button.dart`
- Loading: `app_skeleton.dart`, `branded_loader.dart`, snackbars, form fields, etc.

---

## Suggested commit message (optional)

```
feat(flutter): splash/onboarding polish, motion a11y, honest empties,
lazy lists, large-text safety, refresh/back/a11y, template thumbs,
dashboard motion, CV wizard footer, dark mode, final cleanup
```

---

*Generated from the multi-prompt implementation session. Update this file if follow-up work continues on the same epics.*
