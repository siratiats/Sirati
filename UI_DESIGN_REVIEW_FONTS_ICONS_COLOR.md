# Sirati Flutter App — Static UI Review (Typography · Icons · Color · Design System)

**Date:** 2026-09-17
**Scope:** `flutter_app/lib` (248 Dart files) — design-system integrity only. No runtime/emulator verification; every claim below is traced to source with `file:line` and, where contrast is involved, to a computed WCAG ratio.
**Method:** token-layer read-through → usage census by grep → WCAG math on the actual hex values → cross-check against the existing guard tests.

---

## 1. Verdict

The design system itself is genuinely well built. The token layer (`AppTypography`, `SiratiColors`, `AppSpacing`, `AppRadius`, `AppElevation`) is more thoughtful than most Flutter apps at this stage — in particular the **per-script line-height model** (Arabic gets taller leading at every token) and the **`DirectionalIcon` discipline** (0 raw directional icons in 334 icon references) are excellent and should not be touched.

The problem is not the system. The problem is that **the screens largely don't use it**, and **the guard tests were scoped so narrowly that they certify the parts that were already clean**. Concretely:

| Layer | Discipline | Evidence |
|---|---|---|
| Spacing | **Good** — 89% tokenized | 298 `AppSpacing.*` vs 37 raw `EdgeInsets.all(n)` |
| RTL / bidi | **Very good** | 9 `DirectionalIcon`, 0 raw directional `Icon(Icons.chevron_*)` |
| Color | **Mixed** | 79 hardcoded color literals outside the theme layer |
| Typography | **Poor** — system bypassed | **232** inline `fontSize:` across **25 distinct sizes** |
| Icons | **Poor** — no single style | 168 `_rounded` + 88 `_outlined` + 78 filled, mixed *within* screens |
| i18n copy | **Poor** — half bypasses ARB | 282 inline `english ? 'x' : 'y'` vs ~270 ARB keys |

Three findings are real, user-visible bugs (P0/P1). The rest is drift that will keep getting worse until the guard tests are widened.

---

## 2. What is working — do not "fix" these

1. **Arabic-aware type tokens.** `AppTypeToken` carries `heightLatin`/`heightArabic` and resolves off `Directionality` (`shared/theme/app_typography.dart:22-32`). This is the right model for a bilingual Gulf product and it is correctly unit-tested (`test/app_typography_test.dart`).
2. **Directional icon mirroring.** `DirectionalIcon` (`shared/widgets/directional_icon.dart`) plus **zero** raw directional icon usages anywhere in `lib/`. Rare discipline.
3. **Theme wiring.** `main.dart:274-275` correctly uses `AppTheme.lightFor(arabic:)` / `darkFor(arabic:)`, so the script-specific theme variants actually reach the tree.
4. **The A4 preview escape hatch.** `features/cv_builder/presentation/cv_live_preview_pane.dart:9-37` isolates the CV-paper palette into a named `_PaperPalette` with a documented `// ignore: hardcoded_color -- a4_paper_simulation` on every line. This is exactly how a legitimate exception should look — it is **not** a violation and should stay hardcoded (CV paper is white in every theme).
5. **No asset-image dependency.** 0 `Image.asset` / `AssetImage` in `lib/`; the brand mark is `CustomPaint` (`shared/theme/app_theme.dart:394+`). Nothing to break on density buckets.

---

## 3. Findings, by priority

### P0-1 — The brand teal fails AA as a foreground on every light surface

`SiratiColors.light.primary` = `#00A898`. Measured:

- `primary` on `background` (#FAF7F2): **2.79:1**
- `primary` on `surface` (#FFFFFF): **2.98:1**

AA needs 4.5:1 for these label sizes (14px/w700 is *not* "large text"; large = 18.66px bold or 24px), and even the relaxed 3:1 non-text threshold fails.

The theme uses `c.primary` as a **foreground** in five places:

| Element | Line | Result in light mode |
|---|---|---|
| `OutlinedButton` label + 1.5px border | `shared/theme/app_theme.dart:250-251` | 2.79:1 label, 2.79:1 border |
| `TextButton` label (47 usages) | `shared/theme/app_theme.dart:260` | 2.79:1 |
| `TabBar` label + indicator | `shared/theme/app_theme.dart:299,301` | 2.98:1 |
| `BottomNavigationBar` selected item (11px label!) | `shared/theme/app_theme.dart:291` | 2.98:1 |
| `LinearProgressIndicator` track fill | `shared/theme/app_theme.dart:307` | 2.98:1 |

**The team already proved this number.** `test/app_metrics_test.dart` contains a test literally asserting that white on `#00A898` is only 2.98:1 and therefore the gradient token had to be eliminated. The identical 2.98:1 failure was then left in place in the other direction — teal ink on white — across buttons, tabs and the bottom nav.

**Fix:** use `primaryDark` (#006A60 → **6.50:1** on surface, **6.05:1** on background) for *foreground* roles in light mode, keeping `primary` for *fills* (where `onPrimary` gives an acceptable 4.67:1). Dark mode is fine as-is (`primary` on `surface` = 7.62:1) so this must be a per-brightness value, not a global swap.

### P0-2 — Dark-mode chip labels fail AA

`chipTheme` pairs `backgroundColor: c.primaryLight` with `labelStyle` color `c.primaryDark` (`shared/theme/app_theme.dart:275-278`).

- Light: `#006A60` on `#DDF6F3` = **5.74:1** ✅
- Dark: `#1A9E8F` on `#0E3B36` = **3.73:1** ❌ (labelMd is 12px/w600 → needs 4.5:1)

29 `Chip(` usages inherit this. This is the concrete instance of the long-standing dark-mode contrast issue.

**Fix:** in the dark palette, chip label should resolve to `primary` (#2FC4B2 on #0E3B36 = 6.8:1) rather than `primaryDark`.

### P0-3 — `ColorScheme.onError` is unreadable in dark mode

`shared/theme/app_theme.dart:218` sets `onError: isDark ? c.textPrimary : Colors.white`.

- Light: white on `#C73B36` = 5.11:1 ✅
- Dark: `#E6EAE7` on `#F2726C` = **2.34:1** ❌ — light grey ink on a light coral fill

Ironically `SiratiColors` already ships a smarter `onError` getter (`sirati_colors.dart:84-88`) that picks dark ink `#1A0504` for exactly this case — giving ~7:1 — but the `ColorScheme` ignores it and hardcodes its own logic.

**Fix:** `onError: c.onError`. One-word change; delete the duplicated ternary.

### P1-1 — 232 inline `fontSize:` values bypass the type scale

The token scale defines 10 sizes (11, 12, 12.5, 14, 16, 18, 20, 24, 28, 32). The screens use **25**: 9, 9.5, 10, 10.5, 11, 11.5, 12, 12.5, 13, 13.5, 14, 14.5, 15, 15.5, 16, 16.5, 17, 18, 20, 22, 25, 26, 28, 30, 34.

Worst offenders: `features/jobs/presentation/job_news_screen.dart` (39), `features/ats_scanner/presentation/analysis_result_screen.dart` (26), `features/cv_builder/presentation/generated_cv_screen.dart` (14), `features/cv_builder/presentation/cv_generator_screen.dart` (12).

Two consequences beyond visual inconsistency:

- **Arabic leading is lost.** An inline `TextStyle(fontSize: 11.5, ...)` carries no `height`, so the entire per-script line-height model is silently discarded on exactly the strings that are bilingual. Example: `job_news_screen.dart:1179-1186` renders Arabic body copy at 11.5px with default leading.
- **43 occurrences sit below 12px** (9 / 9.5 / 10 / 10.5 / 11). Arabic needs *more* optical size than Latin, not less; sub-11px Arabic on a phone is effectively decorative.

### P1-2 — Icon style is three styles at once

334 icon references, 154 distinct icons, split **168 rounded / 88 outlined / 78 filled**. Six screens mix all three *within a single file*, including both auth entry points:

`features/auth/presentation/login_screen.dart`, `register_screen.dart`, `features/cv_builder/presentation/cv_generator_screen.dart`, `generated_cv_screen.dart`, `features/ats_scanner/presentation/cv_analysis_screen.dart`, `features/dashboard/presentation/history_screen.dart`

Rounded and outlined Material icons differ in stroke weight and terminal shape; side by side in one row this reads as unfinished rather than as a style choice. Pick one family (rounded suits the 16-18px corner radii) and enforce it.

### P1-3 — `education_screen.dart` ships a different brand

`features/dashboard/presentation/education_screen.dart` hardcodes a Tailwind palette that has nothing to do with Sirati and, being `const`, **does not respond to dark mode at all**:

| Line | Value | What it is |
|---|---|---|
| 31 | `#0D9488` | Tailwind teal-600 (not brand `#00A898`) |
| 108-109 | `#0284C7` / `#E0F2FE` | Tailwind sky-600 / sky-100 |
| 123-124 | `#0D9488` / `#CCFBF1` | Tailwind teal-600 / teal-100 |
| 138-139 | `#7C3AED` / `#EDE9FE` | **violet**-600 / violet-100 — off-brand entirely |

In dark mode the `#E0F2FE` / `#CCFBF1` / `#EDE9FE` icon plates stay near-white against a `#1A201D` surface. This looks pasted from the `new_stitch_design` HTML mockups rather than themed.

### P1-4 — `NavigationBar` (M3) is used but never themed

`app_theme.dart` themes `bottomNavigationBarTheme` (the **M2** widget) but defines no `navigationBarTheme` and no `segmentedButtonTheme`. Meanwhile the code uses M3 `NavigationBar(` in 3 places and `SegmentedButton` in 3 (`experience_education_editor.dart:190`, `personal_details_editor.dart:267`, `cv_export_dialog.dart:132`) — none of which set `indicatorColor`.

Both widgets draw their selected state from `ColorScheme.secondaryContainer`, which this theme never specifies (`app_theme.dart:209-220` sets only 10 of the M3 roles). It therefore falls back off `secondary`, which is **amber** `#C97F1B` — an amber selection pill inside a teal product, next to a teal-themed `BottomNavigationBar`. Since `colorScheme.*` is referenced **0 times** in widget code (everything goes through `context.sirati`), this gap only surfaces through built-in M3 widgets, which is why it has gone unnoticed.

**Verify on device** (this is the one finding I could not confirm statically — Flutter's role-fallback chain should be checked against the SDK version in use), then add explicit `navigationBarTheme` / `segmentedButtonTheme` or fill in the container roles.

### P2 — Secondary observations

- **`w800` is requested but never shipped.** `pubspec.yaml` declares only weights 400/600/700, while four tokens ask for `FontWeight.w800` (`app_typography.dart:40,46,53,66` — displayLg, displayMd, headlineLg, titleLg). Flutter falls back to the 700 file, so `titleLg` (18/w800) and `titleMd` (16/w700) differ only by 2px, and the display tier has no weight advantage at all. Either ship `IBMPlexSansArabic-ExtraBold.ttf` or change the tokens to `w700` so the code states what actually renders.
- **282 inline bilingual ternaries** (`english ? '...' : '...'`) vs ~270 ARB keys — roughly half the product copy never reaches `l10n/app_*.arb`, and `l10n/untranslated.json` exists. Copy can't be reviewed, QA'd or handed to a translator in this shape.
- **Status-chip tints are low contrast where they're used as text-on-tint:** `warning`/`warningLight` = **2.70:1**, `success`/`successLight` = **4.16:1**, `error`/`errorLight` = **4.16:1** (light mode). Fine as pure fills, failing if a label sits on them — check `cv_export_dialog.dart:230,257`.
- **No illustration layer.** Zero image assets; empty states are Material glyphs (`shared/widgets/empty_state.dart`). Functional, but for a career product the empty/first-run moments are where a little art buys a lot of trust. A design call, not a defect.
- **Spacing drift is minor:** off-scale `EdgeInsets.all()` values 22, 18, 14, 10, 7, 3, 2 (37 total). Low priority given 298 correct usages.
- **Repo hygiene:** ~18MB of stale design artifacts inside `flutter_app/` (`new_stitch_design/` 7.0M, `new_stitch_design.zip` 6.2M, `feature_screenshots/` 5.4M, `new_design/` 284K), plus a stray `-d/` directory at repo root.

---

## 4. The meta-finding: the guard tests certify the clean parts

This matters more than any single defect, because it is why the drift is invisible in CI.

**`test/app_metrics_test.dart` — hardcoded-color ban:** scoped to only `lib/shared/widgets/components`, `lib/features/cv_builder`, `lib/features/cv_export`. Unscanned: `features/jobs` (28 violations), `features/dashboard` (education_screen's whole off-brand palette), `features/ats_scanner`, `features/auth`, `features/settings`, and all of `shared/widgets/` outside `components/`. Worse, inside the scanned dirs it **exempts four files by name** — `cv_generator_screen.dart`, `generated_cv_screen.dart`, `generated_cv_loader_screen.dart`, `my_cvs_screen.dart` — with the comment "not in the original builder-feature scan set". Those are four of the largest builder screens, permanently grandfathered.

**Same file — magic-number `EdgeInsets` ban:** scans `lib/shared/widgets/components` only. Five files.

**`test/app_contrast_test.dart`:** iterates `AppContrast.pairs()`, which lists 11 pairs — all text-on-surface, all passing. It omits every pair that actually fails: `primary`/`surface`, `primaryDark`/`primaryLight` (the chip), `onError`/`error`, and every status `x`/`xLight` pairing. The suite is green and the app has four AA failures.

**There is no typography guard at all** — nothing forbids inline `fontSize:`, which is how 232 of them accumulated.

---

## 5. Recommended order of work

1. **P0 contrast triad** (~1 hour, no visual redesign): `onError: c.onError`; per-brightness foreground token so light mode uses `primaryDark` for button/tab/nav labels; dark chip label → `primary`.
2. **Widen the guards before fixing anything else** — otherwise the next fix re-drifts. Extend the color scan to all of `lib/`, delete the four-file exemption (fix what it exposes), add `primary`/`surface`, chip and `onError` pairs to `AppContrast.pairs`, and add a typography guard capping inline `fontSize:` outside `shared/theme`.
3. **Icon family sweep** — pick rounded, migrate the 166 non-rounded references, add a guard.
4. **`education_screen.dart` re-theme** onto `context.sirati` tokens.
5. **Typography migration**, screen by screen, largest offender first (`job_news_screen.dart`), with a golden test per screen in both themes and both scripts.
6. **`NavigationBar`/`SegmentedButton` theming** once verified on device.
7. **Copy migration to ARB**, last — highest volume, lowest visual risk.

---

## 6. Copy-paste agent prompt — P0 + guard widening

```
Repo: D:\Sirati\flutter_app  (Flutter, bilingual ar/en, teal brand)

TASK A — fix three WCAG AA failures in the theme layer.

A1. lib/shared/theme/app_theme.dart:218
    Replace `onError: isDark ? c.textPrimary : Colors.white`
    with    `onError: c.onError`
    (SiratiColors already exposes a luminance-aware onError getter at
     lib/shared/theme/sirati_colors.dart:84-88.)
    Reason: dark mode currently renders #E6EAE7 on #F2726C = 2.34:1.

A2. Add a foreground-safe brand token instead of using `primary` as ink on
    light surfaces. In lib/shared/theme/sirati_colors.dart add a field
    `primaryInk` to SiratiColors (+ copyWith + lerp + both palettes):
      light.primaryInk = Color(0xFF006A60)   // 6.50:1 on surface
      dark.primaryInk  = Color(0xFF2FC4B2)   // 7.62:1 on surface
    Then in lib/shared/theme/app_theme.dart swap `c.primary` -> `c.primaryInk`
    at lines 250, 251, 260, 299, 301, 291 (OutlinedButton fg + border,
    TextButton fg, TabBar labelColor + indicatorColor, BottomNav
    selectedItemColor). Leave ElevatedButton's `backgroundColor: c.primary`
    (line ~243) alone — fills are fine; only ink changes.

A3. lib/shared/theme/app_theme.dart:278
    Chip labelStyle color must be `c.primaryInk` too (dark mode currently
    renders #1A9E8F on #0E3B36 = 3.73:1 for a 12px label).

TASK B — widen the guards so this cannot regress.

B1. lib/shared/theme/app_contrast.dart — add these pairs to AppContrast.pairs():
      primaryInk/surface 4.5, primaryInk/background 4.5,
      primaryInk/primaryLight 4.5   (chip label on chip fill)
      onError/error 4.5,
      success/successLight 4.5, warning/warningLight 4.5,
      error/errorLight 4.5, info/infoLight 4.5
    Expect the four status pairs to FAIL on first run — darken the `x` tone
    (not the tint) until each clears 4.5:1, then re-run.

B2. test/app_metrics_test.dart — in the hardcoded-color test:
      - change `dirs` to a single Directory('lib') scan;
      - DELETE the four-filename exemption block (cv_generator_screen.dart,
        generated_cv_screen.dart, generated_cv_loader_screen.dart,
        my_cvs_screen.dart);
      - keep the `// ignore: hardcoded_color -- <reason>` escape hatch.
    Then fix what it surfaces. Known offenders:
      features/dashboard/presentation/education_screen.dart:31,108,109,123,124,138,139
        -> map to context.sirati tokens (these are Tailwind sky/teal/violet,
           off-brand and non-reactive to dark mode)
      features/jobs/presentation/job_news_screen.dart:558,568,584,852,863,873
        -> use success/successLight tokens
    Colors.white sitting on a brand-colored gradient hero (job_news_screen
    1573-1746) is legitimate — tag those with the escape hatch, do not
    re-token them.
    Do NOT touch cv_live_preview_pane.dart — its _PaperPalette is a
    correctly documented A4-paper exception.

B3. test/app_metrics_test.dart — add a new typography guard:
    scan all of lib/ except lib/shared/theme and cv_live_preview_pane.dart;
    fail on any `fontSize:` literal; message should point to AppTypography
    tokens. Expect ~215 failures — do NOT fix them in this task, instead
    write the failing list to UI_TYPOGRAPHY_DEBT.md, sorted by file
    descending, and mark the test `skip: true` with a TODO referencing that
    file so the migration can be done screen by screen.

ACCEPTANCE CRITERIA
  - `flutter analyze` clean.
  - `flutter test` green (with B3 skipped as specified).
  - No new `Color(0x` / `Colors.` literal added outside an escape hatch.
  - Manually assert in a widget test that, in BOTH brightnesses, a
    TextButton label, a TabBar selected label and a Chip label each reach
    >= 4.5:1 against their actual painted background.
  - UI_TYPOGRAPHY_DEBT.md exists and lists every inline fontSize with
    file:line.
  - Do not restyle any layout, spacing or icon in this task.
```

---

## 7. Not covered here

Runtime behavior was not verified — no emulator run, so nothing below is assessed: actual rendered Arabic shaping and diacritic clipping, overflow under large system text scale (the app sets no `textScaler` clamp, and there are 43 sub-12px inline sizes), tap-target sizes in situ (`AppTouchTarget.min` is referenced only 7 times), motion/transition feel, and keyboard-avoidance in the CV builder forms. A device pass should target those specifically.

---

## 8. Round 2 — applied 2026-09-18

Scope taken: the P0 contrast triad + guard widening + the font-weight decision. `job_news_screen.dart` was deliberately left untouched (it had ~1000 lines of uncommitted work in the tree).

**Corrections to the numbers in §3 above:** `primaryDark` on background is 6.08:1 (not 6.05); dark-mode `primary` on `primaryLight` is 5.69:1 (not 6.8); `ElevatedButton`'s `backgroundColor: c.primary` is at line 239 (not ~243).

### Token changes — `lib/shared/theme/sirati_colors.dart`

| Token | Light | Dark | Purpose |
|---|---|---|---|
| `primaryInk` | `#006A60` | `#2FC4B2` | brand **ink**; `primary` stays a fill-only color |
| `onSuccessContainer` | `#256A4D` | `#5FBF92` | text/icons on `successLight` |
| `onWarningContainer` | `#8A5612` | `#E0A03A` | text/icons on `warningLight` |
| `onErrorContainer` | `#A32F2B` | `#F2726C` | text/icons on `errorLight` |
| `onInfoContainer` | `#006A60` | `#2FC4B2` | text/icons on `infoLight` |

### Fixes

| Was | Now | Where |
|---|---|---|
| `onError: isDark ? c.textPrimary : Colors.white` → 2.34:1 dark | `onError: c.onError` → **6.93:1** | `app_theme.dart:221` |
| OutlinedButton label + border `c.primary` → 2.79:1 | `c.primaryInk` → **6.08:1** | `app_theme.dart:253-254` |
| TextButton label `c.primary` → 2.79:1 | `c.primaryInk` → **6.08:1** | `app_theme.dart:263` |
| Chip label `c.primaryDark` → 3.73:1 dark | `c.primaryInk` → **5.69:1** dark / 5.74:1 light | `app_theme.dart:281` |
| BottomNav selected `c.primary` → 2.98:1 | `c.primaryInk` → **6.50:1** | `app_theme.dart:294` |
| TabBar label + indicator `c.primary` → 2.98:1 | `c.primaryInk` → **6.50:1** | `app_theme.dart:302,304` |
| Progress bar `c.primary` vs track → 2.24:1 | `c.primaryInk` → 4.9:1 | `app_theme.dart` progressIndicatorTheme |
| Watermark banner text/icon `c.warning` on tint → 2.70:1 | `c.onWarningContainer` → **5.16:1** | `cv_export_dialog.dart` |
| Premium-locked text/icon `c.error` on tint → 4.16:1 | `c.onErrorContainer` → **5.69:1** | `cv_export_dialog.dart` |
| Upgrade button ink `c.onPrimary` on error → 2.72:1 | `c.onError` → **5.11:1** | `cv_export_dialog.dart` |

### Typography — the ExtraBold plan does not survive contact with the typeface

**IBM Plex Sans Arabic has no ExtraBold.** Verified against the official `@ibm/plex-sans-arabic` npm package: the family ships Thin, ExtraLight, Light, Regular, Text, Medium, SemiBold, **Bold** — 700 is the ceiling. The four `w800` tokens were therefore always rendering as Bold, and no file could have been added to change that.

The four tokens are now `w700`, with the ceiling documented in `app_typography.dart` so nobody re-raises them. The display tier must earn hierarchy from size, color and spacing. If more weight nuance is wanted later, `Medium` (500) and `Text` (450) exist in the family and could be added to `pubspec.yaml`.

### Guards

- `AppContrast.pairs()` — 11 → **18** pairs. The 7 new ones are exactly the classes of pair the old list omitted: brand ink on both neutral surfaces, chip label on chip fill, and each status ink on its own tint. Verified by evaluating the real palettes against the real pair list: **0 failures in both themes.**
- `app_metrics_test.dart` — the three-directory scan and the four-filename exemption are gone. It now scans **all of `lib/`** except `lib/shared/theme/`, with a frozen `legacyColorDebt` map (18 files, 82 violations). The map can only shrink: a violation in a clean file fails, a debt file going over budget fails, and a debt file reaching zero fails until its entry is deleted. Simulated against the current tree: **passes.**

Note on why the old suite was green: `AppContrast.pairs()` already contained `onError / error` and it passed — because it measured `SiratiColors.onError`, the smart getter, while the theme used a different hand-rolled ternary. The guard was testing the token, not the code path that ships.

### Not done in this pass

- **~23 remaining `c.primary`-as-foreground call sites** in screen code (13 `Icon` colors, ~10 inside `TextStyle`), still 2.79–2.98:1 in light mode. These need `primaryInk` too; a grep can't distinguish fill from ink, so this is a manual sweep.
- `education_screen.dart` off-brand Tailwind palette (P1-3), icon-family sweep (P1-2), typography debt (P1-1), `NavigationBar`/`SegmentedButton` theming (P1-4).

### Verification status

Contrast math and the ratchet were verified by re-implementing both tests against the actual source files — the Dart and Python luminance formulas are identical, so these results should hold. **`flutter analyze` and `flutter test` have not been run**: there is no Flutter SDK in this session's environment. Run both before committing.
