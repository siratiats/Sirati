# Dark Mode Text Contrast — Exhaustive Audit

Scope: every `.dart` file under `flutter_app/lib` (99 files staged and parsed, including
all of `lib/features/**/presentation`, `lib/shared/widgets/**`, `lib/shared/theme/**`,
`lib/core`, `lib/routing`, `lib/screens`).

Method: balanced-paren parse of every `AppTextStyles.*(...)` call, classifying each by
whether a palette is passed, and then whether a trailing `.copyWith(... color: ...)`
chain (at any depth) overrides the resulting colour. Plus greps for direct
`SiratiColors.light` references, hardcoded `Colors.black`/`Colors.white` text colours,
and hardcoded `Color(0x...)` text colours.

Coverage note: `device_bash` has been unavailable for this entire session
("Workspace unavailable"), so files were staged into the review container and analysed
there. This is a static analysis. No screen was rendered in dark mode to confirm
visually; the two screenshots you sent (Job News, My CVs) both land inside the finding
set below, which is the evidence that the static rule matches reality.

---

## Root cause (unchanged, confirmed)

`lib/shared/theme/app_theme.dart:126-180` — seven helpers, each shaped:

```dart
static TextStyle titleMd([SiratiColors? colors, bool? arabic]) {
  final c = colors ?? SiratiColors.light;   // <-- silently falls back to LIGHT
  return AppTypography.titleMd.resolve(
    arabic: arabic ?? _arabicDefault(),
    color: c.textPrimary,                    // light textPrimary = 0xFF171D1B
  );
}
```

The palette tokens themselves are correct in both modes
(`sirati_colors.dart:128-130` light, `:173-175` dark). The defect is that the palette
argument is optional and its default is a *specific* palette rather than the *current*
one. Any screen that omits the argument renders near-black text
(`0xFF171D1B`) on the dark surface.

`test/app_contrast_test.dart` tests token pairs within each palette. Both palettes pass.
It structurally cannot catch a widget that resolves the wrong palette's token, which is
why this shipped.

---

## Findings

**45** `AppTextStyles.*()` calls pass no palette.
**18** of those are saved by a trailing `.copyWith(color: ...)`.
**27** are live dark-mode contrast defects, across **12 files**.

### Broken — 27 call sites

| Count | File | Lines |
|---|---|---|
| 5 | `features/auth/presentation/forgot_password_screen.dart` | 211, 218, 268, 302, 348 |
| 4 | `features/dashboard/presentation/education_screen.dart` | 100, 239, 330, 377 |
| 3 | `features/ats_scanner/presentation/cv_analysis_screen.dart` | 264, 297, 419 |
| 3 | `features/dashboard/presentation/history_screen.dart` | 313, 460, 466 |
| 2 | `features/jobs/presentation/job_news_screen.dart` | 289, 482 |
| 2 | `features/cv_builder/presentation/my_cvs_screen.dart` | 146, 462 |
| 2 | `features/settings/presentation/notifications_screen.dart` | 161, 182 |
| 2 | `features/dashboard/presentation/education_detail_screen.dart` | 131, 149 |
| 1 | `shared/widgets/job_title_picker_field.dart` | 210 |
| 1 | `shared/widgets/app_list_tile.dart` | 88 |
| 1 | `features/jobs/presentation/widgets/job_title_display.dart` | 50 |
| 1 | `features/dashboard/presentation/home_screen.dart` | 646 |

The two shared widgets matter more than their count suggests: `app_list_tile.dart:88`
is the subtitle line of a list tile reused across settings, history and notifications,
and `job_title_picker_field.dart:210` is the label of the job-title picker used in the
CV builder and the ATS scanner. One fix there clears several screens.

`job_title_display.dart:50` is the fallback `primaryStyle ?? AppTextStyles.titleMd()`.
`job_news_screen.dart:482` passes a broken style *into* it, so the job list is broken
by two independent paths — fixing only the widget default will not clear that screen.

### Confirmed against your screenshots

- Job News — `job_news_screen.dart:289` ("Latest Postings" heading) and `:482` /
  `job_title_display.dart:50` (card titles). Matches.
- My CVs — `my_cvs_screen.dart:462` (CV name) and `:146` (the count line). Matches.

### Not defects (checked and cleared)

- `splash_screen.dart` (4 calls), `delete_account_screen.dart` (2),
  `onboarding_screen.dart:260`, `settings_screen.dart:533`,
  `change_password_screen.dart:261`, `register_screen.dart:593`,
  `password_strength_meter.dart:96`, `app_list_tile.dart:77`,
  `education_screen.dart:247/336/385`, `my_cvs_screen.dart:469`,
  `notifications_screen.dart:188` — all pass no palette but override the colour in a
  trailing `copyWith`. They are fragile (the next edit that drops the `copyWith`
  reintroduces the bug) but not currently broken.
- `Colors.white` at `job_news_screen.dart:834-871`, `education_screen.dart:212`,
  `screen_header.dart:205/260`, `ai_progress_overlay.dart:609` — all sit on brand
  gradient/solid surfaces that are the same in both palettes. Correct.
- The `primaryLight` chip / `primary` text pair in the Job News hero card computes to a
  6.3:1 contrast ratio in the dark palette. Passes AA.

### Latent hazard, same shape

`shared/widgets/form_fields.dart:67`

```dart
static InputDecorationTheme get inputTheme => inputThemeFor(SiratiColors.light);
```

A light-hardcoded convenience getter sitting next to the correct `inputThemeFor(c)` API,
exactly the AppTextStyles pattern. It currently has **zero call sites** — `app_theme.dart:271`
correctly calls `inputThemeFor(c)` — so it is not a live bug. It is a loaded gun: the
first widget that reaches for `AppFormStyles.inputTheme` gets light-mode fills, borders,
label, hint and error colours on a dark screen, i.e. every input on that screen at once.
Delete it in the same pass.

`screens/app_crash_view.dart:21` and `core/errors/app_crash_view.dart:19` both hardcode a
light background. Low priority (crash screen), but note there appear to be **two**
`app_crash_view.dart` files — worth confirming which one is wired up.

---

## Recommended fix

Do **not** fix this the way SIRATI-63 fixed it. SIRATI-63 patched the profile screen's
call sites (`profile_screen.dart:204-206`) with `.copyWith(color: context.sirati.…)`
and added a test for that one screen. That is why 27 sites in 12 other files are still
broken today: the defect is in the API's default, and patching callers leaves the
default in place to catch the next caller.

**Make the palette required.**

```dart
static TextStyle titleMd(SiratiColors colors, {bool? arabic}) { … }
```

Removing the default turns every unsafe call site into a compile error. `flutter analyze`
then enumerates the work list exhaustively and — more importantly — the bug becomes
unrepresentable rather than merely absent. Call sites become
`AppTextStyles.titleMd(context.sirati)`, and the 18 currently-mitigated sites lose their
now-redundant `copyWith(color:)`.

If required-argument churn across ~56 call sites is judged too large for this sprint, the
weaker alternative is to strip `color` from the helpers entirely and let `Text` inherit
from the theme's `DefaultTextStyle` — smaller diff, but it changes the resolved colour
everywhere at once and needs a visual pass on both palettes.

**Add the invariant test.** Per AGENTS.md rule 1, the test must assert the invariant, not
the reviewed examples. A repo-wide test that parses `lib/**` and asserts zero
`AppTextStyles.*` calls without a palette argument (and zero references to
`SiratiColors.light` outside `lib/shared/theme/`) is the test that would have caught this
and will catch the next one. The per-screen contrast test added in SIRATI-63 should stay,
but it is not the guard.

**Then verify at the user-visible end** (AGENTS.md rule 6): run the app in dark mode and
walk the 12 screens above. A green `flutter analyze` is the start of the verification,
not the end of it.
