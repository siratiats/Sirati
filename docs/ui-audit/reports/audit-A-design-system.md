# Sirati design-system audit

Read-only audit completed; no files changed. Reviewed the product brief, brand specification, themes, shared widgets, entrypoints, branding assets, native Android resources, and production call sites.

Findings are ranked within each group. **Fix U-01 first**, followed by the contrast failures. Ratios use WCAG sRGB luminance, including alpha compositing; text must meet **≥4.5:1 without tolerance**. Device rendering and screen-reader behavior were not exercised.

### 1. Color & contrast

- **U-02 · high — Material selection colors fail in dark mode.** [app_theme.dart:224](D:/Sirati/flutter_app/lib/shared/theme/app_theme.dart:224) assigns `#E6EAE7` over secondary `#E0A03A`: **1.87:1**. Omitted container roles inherit these colors; the [personal-details language selector](D:/Sirati/flutter_app/lib/features/cv_builder/presentation/personal_details_editor.dart:266) consumes them through Flutter’s `SegmentedButton` defaults. Set `onSecondary: #1A1200` (**8.19:1**), `secondaryContainer: primaryLight`, and `onSecondaryContainer: primaryInk` (**5.74:1 light; 5.69:1 dark**). Selected options become readable.

- **U-03 · high — Components use fill colors as text colors.** [AppButton:165](D:/Sirati/flutter_app/lib/shared/widgets/components/app_button.dart:165) and [floating input labels:51](D:/Sirati/flutter_app/lib/shared/widgets/form_fields.dart:51) use `primary`. Dark `#007F73` on surface `#1A201D` is **3.38:1**. The [“currently here” label](D:/Sirati/flutter_app/lib/features/cv_builder/presentation/experience_education_editor.dart:490) uses primary on primaryLight: **4.33:1 light; 2.52:1 dark**. Use `primaryInk` for foregrounds: `#006A60` light / `#2FC4B2` dark. Keep `#007F73` for filled buttons with white text.

- **U-04 · high — Busy submit buttons fade their status text into the background.** [submit_button.dart:81](D:/Sirati/flutter_app/lib/shared/widgets/submit_button.dart:81) disables the button during loading and applies primary at 55% opacity. On the login background this becomes approximately `#70B5AC`; white “Signing in…” and spinner have **2.36:1** contrast. During loading retain opaque `#007F73` with `#FFFFFF` (**4.90:1**). Users must be able to read the operation’s status.

- **U-05 · high — Amber status labels fail in light mode.** [screen_header.dart:329](D:/Sirati/flutter_app/lib/shared/widgets/screen_header.dart:329) places `#C97F1B` on `#FDE9C7`: **2.70:1**. [PasswordStrengthMeter:49](D:/Sirati/flutter_app/lib/shared/widgets/password_strength_meter.dart:49) uses the same amber on the cream page: **3.01:1**. Use `onWarningContainer` (`#8A5612`) for both labels: **5.16:1** on the tint and **5.75:1** on cream. Keep amber for decorative fills.

- **U-06 · high — Retry and Undo links fail contrast.** [app_snack_bar.dart:280](D:/Sirati/flutter_app/lib/shared/widgets/app_snack_bar.dart:280) uses the status accent for action text. Light error action: `#C73B36/#FFE1DE` = **4.16:1**; success action: `#2E7D5B/#D7F0E5` = **4.16:1**. Both are live: [generation Retry](D:/Sirati/flutter_app/lib/features/cv_builder/presentation/cv_generator_screen.dart:436) and [enhancement Undo](D:/Sirati/flutter_app/lib/features/cv_builder/presentation/cv_generator_screen.dart:509). Use `onErrorContainer: #A32F2B` (**5.69:1**) and `onSuccessContainer: #256A4D` (**5.38:1**) for actions.

- **U-07 · high — Upcoming AI steps are doubly faded.** [ai_progress_overlay.dart:541](D:/Sirati/flutter_app/lib/shared/widgets/loading/ai_progress_overlay.dart:541) combines 60% text alpha with 55% row opacity. Effective text is approximately `#C7CBCA/#FFFFFF` (**1.64:1**) or `#3B433F/#1A201D` (**1.62:1**). Remove both text fades; use opaque `textHint` (**6.46:1 light; 4.58:1 dark**). Distinguish upcoming steps with `Icons.circle_outlined`, while retaining readable labels.

- **U-08 · med — Dark hint text is unsafe on raised surfaces.** [sir­ati_colors.dart:227](D:/Sirati/flutter_app/lib/shared/theme/sirati_colors.dart:227) defines `#7E8985`; the [empty-achievements hint](D:/Sirati/flutter_app/lib/features/cv_builder/presentation/experience_education_editor.dart:571) sits on `surfaceHigh: #2B332E`, giving **3.59:1**. Change dark `textHint` to `#95A19B` (**4.86:1** on surfaceHigh), or use `textSecondary` for raised-surface copy.

- **U-09 · med — Input boundaries are difficult to distinguish.** [form_fields.dart:40](D:/Sirati/flutter_app/lib/shared/widgets/form_fields.dart:40) uses borders with **1.33:1 light / 1.56:1 dark** against their input surfaces. Add a separate control-outline token: light `#778A84` (**3.65:1** on white), dark `#71857C` (**4.22:1** on surface). Apply it to enabled input borders; retain subtler borders for decorative separators.

### 2. Typography

- **U-10 · med — Components request font weights that are not bundled.** [pubspec.yaml:68](D:/Sirati/flutter_app/pubspec.yaml:68) declares only **400/600/700**, but [ScreenHeader:88](D:/Sirati/flutter_app/lib/shared/widgets/screen_header.dart:88) requests 800 and [form inputs:656](D:/Sirati/flutter_app/lib/shared/widgets/form_fields.dart:656) request 500. Use **700 for headings, 600 for labels, 400 for body/input text**. This gives the hierarchy actual matching font faces.

- **U-11 · med — Shared components bypass Arabic line-height and minimum copy sizes.** [ScreenHeader:85](D:/Sirati/flutter_app/lib/shared/widgets/screen_header.dart:85) uses height **1.22** in both languages; [inputs:653](D:/Sirati/flutter_app/lib/shared/widgets/form_fields.dart:653) use **15sp/1.35**, and [AI guidance:174](D:/Sirati/flutter_app/lib/shared/widgets/ai_cv_field.dart:174) drops to **11sp**. Resolve headings through a **20sp/700, Arabic 1.50 / Latin 1.28** token; inputs through **16sp/400, 1.65 / 1.40**; guidance through **13sp/400, 1.60 / 1.40**. Arabic instructions gain breathing room and remain legible on phones.

### 3. Icons & logo/branding

- **U-12 · med — In-app branding diverges from the approved identity.** [SiratiMark:435](D:/Sirati/flutter_app/lib/shared/theme/app_theme.dart:435) uses a circle and optional shadow; [splash wordmarks:135](D:/Sirati/flutter_app/lib/features/dashboard/presentation/splash/splash_boot_body.dart:135) inherit IBM Plex and display capitalized “Sirati.” The [brand specification](<D:/Sirati/Sirati Brand Identity.dc.html:117>) specifies the mobile squircle, lowercase Manrope Latin wordmark, Tajawal Arabic wordmark, and no mark shadow. Reuse approved outlined wordmarks, a **56dp tile with ≈14.6dp corners**, and the existing `#12384F → #0E8F86` gradient. Keep IBM Plex for interface text; preserve recognition between launcher, splash, and login.

- **U-13 · med — Android adaptive foreground contains an opaque background.** [pubspec.yaml:86](D:/Sirati/flutter_app/pubspec.yaml:86) reuses the full square master as foreground; [ic_launcher.xml:5](D:/Sirati/flutter_app/android/app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml:5) insets it **16%**. The inspected foreground PNG includes its own gradient square. Export a transparent white-mark foreground on a **108dp canvas**, keeping essential artwork within the central **66dp**, and supply the full gradient as the background layer. This removes the inset square boundary and makes launcher masking consistent.

### 4. Layout, spacing, hierarchy & CTAs

- **U-14 · med — Login reserves a fixed branding header while only the form scrolls.** [login_screen.dart:154](D:/Sirati/flutter_app/lib/features/auth/presentation/login_screen.dart:154) keeps language control, logo, title, and subtitle above the scrolling form. Its compact condition uses full screen height, so opening the keyboard does not activate it. Put header and form in one scroll view with **20dp horizontal gutters, 24dp section gaps, and a 54dp minimum CTA**; keep safe-area handling. Users can move branding out of the way while entering credentials.

- **U-15 · low — Equivalent cards use different elevation systems.** [legacy AppCard:405](D:/Sirati/flutter_app/lib/shared/theme/app_theme.dart:405) uses a **12dp blur**; [AppSurfaceCard:46](D:/Sirati/flutter_app/lib/shared/widgets/components/app_card.dart:46) uses elevation-derived **2.4dp blur**. Both have production consumers. Standardize ordinary cards on **16dp padding, 16dp radius, and elevation 0**; reserve elevation **6** for sheets and **12** for dialogs. Analysis and editing surfaces will feel like the same product.

**Literal inventory:** Across **81 presentation Dart files**, the lexical scan found **178 numeric font-size assignments in 43 files** and **512 numeric layout/radius matches in 58 files**. Examples include login’s 20/22sp heading and onboarding’s 23/26sp heading. These are inventory counts, not inferred widget violations. All **14 hardcoded color literals** occur in [cv_live_preview_pane.dart:9](D:/Sirati/flutter_app/lib/features/cv_builder/presentation/cv_live_preview_pane.dart:9), where fixed paper/document colors are intentional; no `Colors.*` references were found in presentation files.

### 5. UX flow & copy

- **U-16 · med — Shared AI feedback claims progress that timers cannot establish.** [ai_progress_overlay.dart:332](D:/Sirati/flutter_app/lib/shared/widgets/loading/ai_progress_overlay.dart:332) takes the maximum of server phase and timer step, so after **11 seconds** it can show “Saving your CV” while the server still reports queued. [Field loading messages:43](D:/Sirati/flutter_app/lib/shared/widgets/loading/ai_field_loading_overlay.dart:43) also claim market benchmarking during wording enhancement. Advance known phases only from server state; use an indeterminate indicator for unknown progress and retain the **30-second** long-wait message. Replace the field carousel with **“جارٍ تحسين الصياغة…” / “Improving wording…”**. Feedback should support trust in the result.

### 6. Loading, empty, error & offline states

- **U-17 · med — Native night launch deliberately paints the light background.** [values-night/styles.xml:3](D:/Sirati/flutter_app/android/app/src/main/res/values-night/styles.xml:3) uses the light theme and shared `#FAF7F2` splash color before Flutter paints dark mode. Add a night `splash_background: #121614` resource and dark native launch theme. Resolve [Flutter’s bootstrap system-bar style](D:/Sirati/flutter_app/lib/shared/theme/app_theme.dart:356) from the active appearance instead of always light. Dark-mode users avoid a bright cold-start flash.

### 7. Accessibility & RTL

- **U-01 · high — Semantics wrappers remove native interaction information.** [AppInput:53](D:/Sirati/flutter_app/lib/shared/widgets/components/app_input.dart:53) excludes its entire `TextField`; [AppButton:153](D:/Sirati/flutter_app/lib/shared/widgets/components/app_button.dart:153) excludes its native button; [AppListTile:117](D:/Sirati/flutter_app/lib/shared/widgets/app_list_tile.dart:117) excludes its `InkWell` without supplying a semantic tap action. These affect personal-details editing, section actions, and Home activity rows. Preserve native field/button semantics; for the explicitly merged list row, supply **`onTap: onTap`** on the outer semantics node. Retain value, selection, enabled state, and activation actions.

- **U-18 · high — Custom form errors are detached from their fields.** [form_fields.dart:691](D:/Sirati/flutter_app/lib/shared/widgets/form_fields.dart:691) sets native `errorText` to null and renders a separate `AppFieldError` without a live region. Login and registration consume this field. Pass the actual validation error into `InputDecoration`, use **13sp/600**, Arabic height **1.60** / Latin **1.40**, and avoid duplicate visual errors. This restores the field’s invalid state and error announcement.

- **U-19 · med — Shared controls bypass the declared 48dp touch minimum.** [LanguageToggle:27](D:/Sirati/flutter_app/lib/shared/widgets/language_toggle.dart:27) and [notification bell:180](D:/Sirati/flutter_app/lib/shared/widgets/screen_header.dart:180) are **42×42dp**; [snackbar actions:283](D:/Sirati/flutter_app/lib/shared/widgets/app_snack_bar.dart:283) explicitly shrink to **32dp** high. Make each interactive region at least **48×48dp**, keeping the existing glyphs centered. Language switching, notifications, and recovery actions become easier to tap.

- **U-20 · med — The app discards larger accessibility text preferences globally.** [main.dart:317](D:/Sirati/flutter_app/lib/main.dart:317) clamps scaling to **0.8–2.0**. Remove the global clamp and let controls grow through minimum heights and wrapping. Validate shared fields, buttons, and headers at **100%, 200%, and 300%** in Arabic and English.

### Keep these strengths

- Preserve the **400/600/700 IBM Plex Sans Arabic files**: their font tables include common Latin letters, digits, email punctuation, and Arabic glyphs; a separate Latin body font is unnecessary.
- Preserve the [script-specific typography tokens](D:/Sirati/flutter_app/lib/shared/theme/app_typography.dart:90), which already give Arabic more vertical room.
- Preserve opaque primary buttons: **white/#007F73 = 4.90:1** in both themes.
- Preserve [DirectionalIcon’s single-mirroring logic](D:/Sirati/flutter_app/lib/shared/widgets/directional_icon.dart:48) and onboarding’s direction-aware `PageView`.
- Preserve theme persistence, system appearance support, and the existing Material icon vocabulary.

**Validation for fixes:** Extend the existing contrast suite with the actual component pairs above and strict `ratio >= 4.5` assertions. Verify semantics using real widget trees; verify launcher masks and native-to-Flutter splash transitions on-device. No build or tests were run during this read-only audit.

**SCREENS TO REDESIGN IN STITCH:**

- **Login:** One scrolling branding-and-form layout that keeps credential entry usable with the keyboard and enlarged Arabic text.
- **Splash/welcome:** One approved bilingual brand lockup with consistent native-to-Flutter geometry and less nested logo framing.

LEARNINGS: Material container colors omitted from Sirati’s `ColorScheme` inherit base colors; the local Flutter SDK confirms this affects selected segmented controls.
LEARNINGS: `SiratiAddButton`, `ScoreBoosterCard`, and `AppFormSuccessBanner` currently have no presentation call sites; their defects should not be counted as live screen impact.
