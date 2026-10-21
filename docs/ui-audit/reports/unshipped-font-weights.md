# Unshipped font weights — Flutter audit

## Summary (10 lines)

1. Shipped: IBM Plex Sans Arabic only — 400 Regular, 600 SemiBold, 700 Bold (`flutter_app/pubspec.yaml:70-78`); no Latin family is declared.
2. Assets confirm the same three files in `flutter_app/assets/fonts/` (Regular/SemiBold/Bold `.ttf`); no other `.ttf`/`.otf` ships.
3. Total: **57 hits** in `flutter_app/lib` (241 Dart files scanned) — 39× `w800`, 18× `w500`; 0× `w100`/`w200`/`w300`/`w900`.
4. Indirect theme tokens: **0 additional hits** — all 13 `AppTypography` tokens use only 400/600/700 (see Indirect section).
5. No `FontVariation`/`wght`, numeric weight, dot-shorthand (`.w500`), or `FontWeight` variable/getter resolving to an unshipped weight was found.
6. Proposal rule: `w800 → w700` everywhere; `w500 → w400` for body/secondary/hint/inactive text (16 hits), `w500 → w600` for standalone document/status emphasis (2 hits).
7. Impact: 16 hits are in `lib/shared/` (shared), 1 in `lib/core/` (app-wide banner), 40 in feature screens/widgets (screen).
8. By feature: cv_builder 10, dashboard 10, jobs 10, shared 16, ats_scanner 4, auth 3, settings 3, core 1 (= 57).
9. Worst files: `splash_boot_body.dart`, `job_news_detail_screen.dart`, `screen_header.dart` (4 each); full Top 10 below.
10. No weight is chosen by locale or text-scale; locale changes string/direction/line-height only, state ternaries change weight (see Locale section). Read-only audit: no Dart edited, no `flutter`/`dart` run.

## Method and definitions

- Every `FontWeight.wNNN` occurrence in `flutter_app/lib` was enumerated, then each hit was read in its surrounding widget/style (no regex inference about widget hierarchy; class/widget names below come from reading the enclosing code).
- `shared` = under `flutter_app/lib/shared/` (reused app-wide; high impact). `shared (core)` = app-wide wrapper under `lib/core/`. `screen` = everything else, including feature-local widget files.
- Proposed rationale is the 5-word phrase after the dash in the Proposed column.

## Hits by feature folder

### core — 1 hit

| file:line | current weight | role | proposed shipped weight | shared-or-screen |
|---|---|---|---|---|
| flutter_app/lib/core/flavors/flavor_banner.dart:20 | w800 | `FlavorBanner` `Banner.textStyle` — non-prod flavour label | w700 — emphasis: nearest shipped bold weight | shared (core) |

### features/ats_scanner — 4 hits

| file:line | current weight | role | proposed shipped weight | shared-or-screen |
|---|---|---|---|---|
| flutter_app/lib/features/ats_scanner/presentation/analysis_result/recommendations_tab.dart:163 | w800 | `_NumberMarker` number `Text` | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/ats_scanner/presentation/analysis_result/score_ring.dart:64 | w800 | `ScoreRing` score number `Text` (30px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/ats_scanner/presentation/analysis_result/score_ring.dart:76 | w500 | `ScoreRing` `/100` suffix `Text` (12px, secondary) | w400 — body: nearest shipped regular weight | screen |
| flutter_app/lib/features/ats_scanner/presentation/analysis_result/score_tab.dart:102 | w800 | `ScoreTab` grade pill `Text` | w700 — emphasis: nearest shipped bold weight | screen |

### features/auth — 3 hits

| file:line | current weight | role | proposed shipped weight | shared-or-screen |
|---|---|---|---|---|
| flutter_app/lib/features/auth/presentation/email_verification_screen.dart:249 | w800 | Screen heading `Text` "Verify your email" (22px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/auth/presentation/login_screen.dart:200 | w800 | Login heading `Text` "Welcome to Sirati" | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/auth/presentation/register/register_widgets.dart:65 | w800 | `RegisterHero` heading `Text` "Join Us Today" | w700 — emphasis: nearest shipped bold weight | screen |

### features/cv_builder — 10 hits

| file:line | current weight | role | proposed shipped weight | shared-or-screen |
|---|---|---|---|---|
| flutter_app/lib/features/cv_builder/presentation/cv_generator/draft_restore_banner.dart:65 | w500 | Saved-draft relative timestamp `Text` (12px, secondary) | w400 — body: nearest shipped regular weight | screen |
| flutter_app/lib/features/cv_builder/presentation/cv_generator/steps/personal_step.dart:233 | w500 | AI-enhance hint `Text` (13px, secondary) | w400 — body: nearest shipped regular weight | screen |
| flutter_app/lib/features/cv_builder/presentation/cv_generator/wizard_widgets.dart:114 | w800 | `SectionHeading` heading `Text` (17px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/cv_builder/presentation/cv_live_preview_pane.dart:362 | w500 | CV preview entry company·location `Text` (10.5px, document sub-line) | w600 — emphasis: nearest shipped semibold weight | screen |
| flutter_app/lib/features/cv_builder/presentation/generated_cv/cv_document_view.dart:138 | w500 | `CvStatusBanner` message `Text` (13px, primary ink, sole row text) | w600 — emphasis: nearest shipped semibold weight | screen |
| flutter_app/lib/features/cv_builder/presentation/my_cvs/cv_document_card.dart:194 | w800 | `_StatusBadge` label `Text` | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/cv_builder/presentation/my_cvs/cv_template_picker.dart:33 | w800 | Template picker sheet heading `Text` (18px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/cv_builder/presentation/my_cvs/cv_template_picker.dart:55 | w800 | `ListTile.title` template name `TextStyle` | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/cv_builder/presentation/widgets/achievement_builder_sheet.dart:269 | w800 | Achievement sheet title `Text` (16px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/cv_builder/presentation/widgets/achievement_builder_sheet.dart:281 | w500 | Achievement sheet formula subtitle `Text` (12px, secondary) | w400 — body: nearest shipped regular weight | screen |

### features/dashboard — 10 hits

| file:line | current weight | role | proposed shipped weight | shared-or-screen |
|---|---|---|---|---|
| flutter_app/lib/features/dashboard/presentation/education_screen.dart:230 | w800 | "COMING SOON" badge `Text` | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/dashboard/presentation/history/history_cards.dart:46 | w800 | History score badge number `Text` (18px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/dashboard/presentation/home/dashboard_cv_hero.dart:171 | w800 | CV hero score `%` `Text` (22px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/dashboard/presentation/home/dashboard_home_tab.dart:318 | w800 | Activity section title `Text` (15px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/dashboard/presentation/splash/splash_boot_body.dart:140 | w800 | Splash wordmark `Text` (34px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/dashboard/presentation/splash/splash_boot_body.dart:150 | w500 | Splash status `Text` via `AppTextStyles.bodySm.copyWith` (secondary) | w400 — body: nearest shipped regular weight | screen |
| flutter_app/lib/features/dashboard/presentation/splash/splash_boot_body.dart:165 | w800 | Reduced-motion splash wordmark `Text` (22px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/dashboard/presentation/splash/splash_boot_body.dart:210 | w800 | Animated splash wordmark `Text` (22px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/dashboard/presentation/splash/welcome_body.dart:68 | w800 | Welcome wordmark `Text` (28px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/dashboard/presentation/splash/welcome_body.dart:163 | w800 | Welcome info-card title `Text` (20px) | w700 — emphasis: nearest shipped bold weight | screen |

### features/jobs — 10 hits

| file:line | current weight | role | proposed shipped weight | shared-or-screen |
|---|---|---|---|---|
| flutter_app/lib/features/jobs/presentation/job_news_detail_screen.dart:114 | w800 | `JobTitleDisplay.primaryStyle` job title (25px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/jobs/presentation/job_news_detail_screen.dart:189 | w800 | Compatibility section heading `Text` | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/jobs/presentation/job_news_detail_screen.dart:208 | w800 | Match score `%` `Text` | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/jobs/presentation/job_news_detail_screen.dart:305 | w500 | Unmatched skill chip `Text` (ternary: matched `w700` / this) | w400 — body: nearest shipped regular weight | screen |
| flutter_app/lib/features/jobs/presentation/widgets/featured_job_card.dart:148 | w800 | Match `%` `Text` | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/jobs/presentation/widgets/featured_job_card.dart:163 | w800 | `JobTitleDisplay.primaryStyle` job title (17px) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/jobs/presentation/widgets/job_news_card.dart:135 | w800 | Match `%` `Text` | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/jobs/presentation/widgets/job_news_card.dart:185 | w500 | Company·location meta `Text` (13px, secondary) | w400 — body: nearest shipped regular weight | screen |
| flutter_app/lib/features/jobs/presentation/widgets/job_news_card.dart:256 | w500 | Unmatched skill chip `Text` (ternary: matched `w700` / this) | w400 — body: nearest shipped regular weight | screen |
| flutter_app/lib/features/jobs/presentation/widgets/job_news_filters_bar.dart:53 | w500 | Unselected `ChoiceChip.labelStyle` (ternary: selected `w700` / this) | w400 — body: nearest shipped regular weight | screen |

### features/settings — 3 hits

| file:line | current weight | role | proposed shipped weight | shared-or-screen |
|---|---|---|---|---|
| flutter_app/lib/features/settings/presentation/notifications_screen.dart:202 | w800 | Unread notification title (`AppTextStyles.titleSm.copyWith`, ternary: read `w600` / this) | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/settings/presentation/onboarding_screen.dart:328 | w800 | `_OnboardingPageView` page title `Text` | w700 — emphasis: nearest shipped bold weight | screen |
| flutter_app/lib/features/settings/presentation/privacy_policy_screen.dart:95 | w800 | Policy section title `Text` (semantic header) | w700 — emphasis: nearest shipped bold weight | screen |

### shared — 16 hits

| file:line | current weight | role | proposed shipped weight | shared-or-screen |
|---|---|---|---|---|
| flutter_app/lib/shared/widgets/ai_cv_field.dart:175 | w500 | AI-enhance hint `Text` (11px, secondary) | w400 — body: nearest shipped regular weight | shared |
| flutter_app/lib/shared/widgets/form_fields.dart:59 | w500 | `AppFormStyles.inputThemeFor` `hintStyle` (feeds `AppTheme` `inputDecorationTheme`) | w400 — body: nearest shipped regular weight | shared |
| flutter_app/lib/shared/widgets/form_fields.dart:656 | w500 | `AppTextFormField` typed-input `TextStyle` (15px, primary) | w400 — body: nearest shipped regular weight | shared |
| flutter_app/lib/shared/widgets/job_title_picker_field.dart:210 | w800 | Picker sheet title (`AppTextStyles.titleMd.copyWith`) | w700 — emphasis: nearest shipped bold weight | shared |
| flutter_app/lib/shared/widgets/job_title_picker_field.dart:257 | w800 | Selected title row `Text` (ternary: this / `w600`) | w700 — emphasis: nearest shipped bold weight | shared |
| flutter_app/lib/shared/widgets/language_toggle.dart:40 | w800 | `LanguageToggle` AR/EN label `Text` | w700 — emphasis: nearest shipped bold weight | shared |
| flutter_app/lib/shared/widgets/loading/ai_progress_overlay.dart:441 | w500 | Progress footer note `Text` (12px, hint colour) | w400 — body: nearest shipped regular weight | shared |
| flutter_app/lib/shared/widgets/loading/ai_progress_overlay.dart:579 | w500 | Inactive checklist step label `Text` (ternary: active `w700` / this) | w400 — body: nearest shipped regular weight | shared |
| flutter_app/lib/shared/widgets/offline_cache_banner.dart:49 | w500 | Offline status `Text` (12px, hint colour) | w400 — body: nearest shipped regular weight | shared |
| flutter_app/lib/shared/widgets/score_booster_card.dart:207 | w800 | Card heading `Text` "Career readiness" | w700 — emphasis: nearest shipped bold weight | shared |
| flutter_app/lib/shared/widgets/score_booster_card.dart:216 | w800 | Readiness `pct%` `Text` | w700 — emphasis: nearest shipped bold weight | shared |
| flutter_app/lib/shared/widgets/score_booster_card.dart:258 | w800 | Boost-points pill `Text` | w700 — emphasis: nearest shipped bold weight | shared |
| flutter_app/lib/shared/widgets/screen_header.dart:88 | w800 | `ScreenHeader` title `Text` (all main tabs) | w700 — emphasis: nearest shipped bold weight | shared |
| flutter_app/lib/shared/widgets/screen_header.dart:102 | w500 | `ScreenHeader` subtitle `Text` (13.5px, secondary) | w400 — body: nearest shipped regular weight | shared |
| flutter_app/lib/shared/widgets/screen_header.dart:212 | w800 | `NotificationBellButton` unread-count badge `Text` | w700 — emphasis: nearest shipped bold weight | shared |
| flutter_app/lib/shared/widgets/screen_header.dart:263 | w800 | `ProfileAvatar` initial-letter `Text` | w700 — emphasis: nearest shipped bold weight | shared |

## Top 10 files by hit count

Ties are ordered alphabetically by path. Ranks 6–10 are the first five of the eight files tied at 2 hits; the remaining 2-hit files are `form_fields.dart`, `job_title_picker_field.dart`, `ai_progress_overlay.dart`, followed by 37 files with 1 hit each.

| rank | file | hits |
|---|---|---|
| 1 | flutter_app/lib/features/dashboard/presentation/splash/splash_boot_body.dart | 4 |
| 2 | flutter_app/lib/features/jobs/presentation/job_news_detail_screen.dart | 4 |
| 3 | flutter_app/lib/shared/widgets/screen_header.dart | 4 |
| 4 | flutter_app/lib/features/jobs/presentation/widgets/job_news_card.dart | 3 |
| 5 | flutter_app/lib/shared/widgets/score_booster_card.dart | 3 |
| 6 | flutter_app/lib/features/ats_scanner/presentation/analysis_result/score_ring.dart | 2 |
| 7 | flutter_app/lib/features/cv_builder/presentation/my_cvs/cv_template_picker.dart | 2 |
| 8 | flutter_app/lib/features/cv_builder/presentation/widgets/achievement_builder_sheet.dart | 2 |
| 9 | flutter_app/lib/features/dashboard/presentation/splash/welcome_body.dart | 2 |
| 10 | flutter_app/lib/features/jobs/presentation/widgets/featured_job_card.dart | 2 |

## Indirect (token/constant) findings

- `flutter_app/lib/shared/theme/app_typography.dart`: all 13 `AppTypeToken`s are shipped weights — `displayLg/displayMd/headlineLg/headlineMd/titleLg/titleMd/titleSm/labelLg` = w700, `bodyLg/bodyMd/bodySm` = w400, `labelMd/labelSm` = w600 (lines 45–131). The file's own comment (lines 36–41) already states this ceiling. **0 indirect hits.**
- `flutter_app/lib/shared/theme/app_theme.dart`: `AppTextStyles` and the `ThemeData` text styles only delegate to those tokens (`resolve(...)`); no literal weight of its own. `flutter_app/lib/shared/theme/sirati_colors.dart` and `app_contrast.dart` contain no `FontWeight` use.
- `flutter_app/lib/shared/widgets/form_fields.dart:59,656` are counted as direct hits above, but note their reach: `AppFormStyles.inputThemeFor` is installed as `AppTheme.inputDecorationTheme` (`app_theme.dart:284`), so line 59 affects every form field app-wide despite living outside `shared/theme/`.
- Elsewhere: no constant, variable, getter, or function returns an unshipped `FontWeight`; the only non-`wNNN` form found is one shipped-equivalent `FontWeight.bold` (`cv_builder_screen.dart:296`, = w700, not a hit).

## Locale / text-scale conditional weight

- **None.** No hit — and no `fontWeight` anywhere in `lib/` — is selected by locale, language, direction, or text scale.
- What locale actually changes: `AppTypeToken.resolve(arabic:)` in `app_typography.dart:23-31` picks line-height only; the weight is the token constant. `AppTextStyles._arabicDefault()` (`app_theme.dart:138`) does the same. Hits sitting under an `english ? '…' : '…'` string ternary (e.g. login, splash wordmark) use one constant weight for both languages.
- What text scale / width actually changes: `ScreenHeader` uses `constraints.maxWidth < 340` only for title size and subtitle line count (weight stays w800/w500); `cv_analysis_screen.dart:374,416` uses `MediaQuery.textScalerOf` for toolbar height and button stacking, with no weight involvement.
- Weight ternaries that do exist are state-based, not locale/scale: `item.isRead` (notifications_screen.dart:202), `selected` (job_title_picker_field.dart:257), `selectedPreset` (job_news_filters_bar.dart:51-53), `isMatched` (job_news_detail_screen.dart:303-305; job_news_card.dart:256), `active` (ai_progress_overlay.dart:579). Their shipped-side partners (w600/w700) are not hits; the unshipped side is listed in the tables.

## Verification

- Cross-check counts: 39 (w800) + 18 (w500) = 57 table rows; group counts 1 + 4 + 3 + 10 + 10 + 10 + 3 + 16 = 57. Full-library `FontWeight.wNNN` census for reference: w700 96, w600 45, w800 39, w500 18, w400 5.
- Files were read, not executed: no Dart file was modified and neither `flutter` nor `dart` was run, per the audit constraints.

