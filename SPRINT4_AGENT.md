# Sprint 4 Agent Execution Runbook: Code-Side Architecture, Flavors, i18n & RTL

> **Instructions for the implementing Agent:**
> - Strictly **ignore all UX/UI tasks** (SIRATI-59 through SIRATI-65) and marketing tasks (SIRATI-52, 55, 56). Focus 100% on the code-side engineering backlog.
> - Strictly adhere to **`AGENTS.md`** invariants:
>   1. Test general invariants across arbitrary valid inputs and boundary conditions, not just specific review strings.
>   2. Strict quality gates (no diluted assertions or leeway offsets).
>   3. Never use regular expressions over Dart source to guess AST or widget hierarchies. Validate via real component trees or AST analysis.
>   4. Directionality: normalize once at ingestion/extraction boundaries; never guess via visual orthographic heuristics.

---

## 1. Sprint 4 Scope & Backlog Mapping

| Jira Key | Story Title | Priority | Core Objective |
|---|---|---|---|
| **SIRATI-11** | Project scaffold and feature-first folder architecture | Highest | Restructure `lib/` into `core/`, `shared/`, `features/`; enforce layering boundary contracts; 0 analyze warnings. |
| **SIRATI-14** | Environment configuration and flavors (dev, staging, prod) | High | Configure `dev`, `staging`, `prod` flavors via `--dart-define`; flavor banner in non-prod; zero committed secrets. |
| **SIRATI-24** | i18n infrastructure and string extraction | Highest | `l10n.yaml`, ARB catalogs (`app_ar.arb`, `app_en.arb`), generated delegates, parameter interpolation, Arabic 6-case pluralization. |
| **SIRATI-25** | RTL layout support | Highest | `EdgeInsetsDirectional`, `DirectionalIcon` / `matchTextDirection`, alignment invariants, RTL navigation and swipe gestures. |
| **SIRATI-26** | Locale switching and persistence | High | Settings & header language toggle, instant layout flip, draft editing state preservation, Gulf locale default. |
| **SIRATI-29** | RTL golden tests in CI | Medium | Golden coverage for key screens (LTR/RTL × Light/Dark), CI failure on mismatch, documented update workflow. |
| **Sprint 1 M2** | Structured logging redesign (`AppLog`) | High | Replace 280-char message truncation with structured event tokens (`AppLogEvent`), eliminating CV résumé leaks in release. |

---

## 2. Detailed Technical Specifications & Recipes

### 2.1 Sprint 1 M2 Carryover: Structured Logging (`AppLog`)
- **Problem**: `AppLog.redact()` truncates long messages and keeps the first 280 characters, leaking résumé paragraphs into production logcat/Sentry when free-text is logged.
- **Solution**:
  - `lib/logging/app_log_event.dart` defines `enum AppLogEvent` covering app lifecycle, auth, CV builder, export, and ATS domains.
  - `AppLog.event(AppLogEvent event, {AppLogLevel level, Map<String, Object?>? data, Object? error, StackTrace? stackTrace})` provides the primary type-safe API.
  - Ban free-text CV bodies in log messages. In release mode, only structured event identifiers and sanitized payloads are emitted.
  - All dynamic data is passed via `data:`, where `redactMap()` rigorously checks every key against `_piiKeys` (case-insensitively) and sanitizes nested collections.
  - Test: `flutter_app/test/app_log_test.dart`.

---

### 2.2 SIRATI-11: Feature-First Architecture & Layering Rules
- **Directory Layout**:
  ```
  flutter_app/lib/
  ├── core/
  │   ├── errors/          # app_crash_view.dart
  │   ├── flavors/         # app_flavor.dart, flavor_banner.dart
  │   ├── logging/         # app_log.dart, app_log_event.dart
  │   ├── network/         # api_client.dart, api_exception.dart, api_config.dart
  │   ├── routing/         # app_router.dart, app_routes.dart, entitlement_store.dart
  │   ├── storage/         # preference_store.dart, auth_token_store.dart, disk_cache.dart, session_cache.dart
  │   └── utils/           # bidi_text.dart, bidi_text_utils.dart, app_format.dart, arabic_date_format.dart, etc.
  ├── shared/
  │   ├── models/          # job_title.dart, job_news.dart
  │   ├── theme/           # app_theme.dart, sirati_colors.dart, app_typography.dart, app_contrast.dart
  │   └── widgets/         # reusable UI components (form_fields.dart, submit_button.dart, app_snack_bar.dart, etc.)
  ├── features/
  │   ├── auth/            # presentation/, data/, models/
  │   ├── cv_builder/      # presentation/, controllers/, data/, models/
  │   ├── cv_export/       # presentation/, controllers/, utils/, models/
  │   ├── ats_scanner/     # presentation/, models/
  │   ├── dashboard/       # presentation/
  │   ├── jobs/            # presentation/
  │   ├── settings/        # presentation/
  │   └── localization/    # app_locale.dart, l10n/
  ├── main.dart
  ├── main_dev.dart
  ├── main_staging.dart
  └── main_prod.dart
  ```
- **Layering Boundary Rules (Document in `flutter_app/README.md`)**:
  1. `core/` has **zero** dependencies on `shared/` or `features/`.
  2. `shared/` may depend on `core/`, but **never** on `features/`.
  3. `features/` may depend on `core/` and `shared/`.
  4. Cross-feature direct imports are strictly prohibited (communicate via router or shared contracts).
- **Enforcement**: Create `test/architecture/layering_invariants_test.dart` to verify package import boundaries.

---

### 2.3 SIRATI-14: Environment Configuration & Flavors
- **Flavor Definitions**:
  - `lib/core/flavors/app_flavor.dart`:
    ```dart
    enum AppFlavor { dev, staging, prod }
    ```
  - Read via `--dart-define=FLAVOR=dev|staging|prod`.
- **Flavor Banner**:
  - `lib/core/flavors/flavor_banner.dart`:
    - Wraps root `MaterialApp` or screen sceneries.
    - In `dev`: Shows green/amber ribbon with "DEV".
    - In `staging`: Shows blue ribbon with "STAGING".
    - In `prod`: No banner rendered.
- **Entry Points**:
  - `lib/main_dev.dart`: Bootstraps with `AppFlavor.dev`.
  - `lib/main_staging.dart`: Bootstraps with `AppFlavor.staging`.
  - `lib/main_prod.dart`: Bootstraps with `AppFlavor.prod`.
  - `lib/main.dart`: Dynamic fallback reading environment.
- **Security Check**:
  - Zero API credentials or secrets committed in git.
  - Secrets injected via `--dart-define=SIRATI_API_BASE_URL=...` and `--dart-define=SENTRY_DSN=...`.

---

### 2.4 SIRATI-24: i18n Infrastructure & String Extraction
- **Configuration**:
  - `pubspec.yaml`:
    ```yaml
    flutter:
      generate: true
    ```
  - `flutter_app/l10n.yaml`:
    ```yaml
    arb-dir: lib/l10n
    template-arb-file: app_ar.arb
    output-localization-file: app_localizations.dart
    nullable-getter: false
    untranslated-messages-file: lib/l10n/untranslated.json
    ```
- **Catalogs**:
  - `lib/l10n/app_ar.arb`: Arabic translations (primary, Saudi/GCC vernacular, Arabic 6-form plurals: `zero`, `one`, `two`, `few`, `many`, `other`).
  - `lib/l10n/app_en.arb`: English translations.
  - Parameter interpolation: `{name}`, `{count}`, `{score}`.
- **Delegates**:
  - Register `AppLocalizations.delegate`, `GlobalMaterialLocalizations.delegate`, `GlobalWidgetsLocalizations.delegate`, `GlobalCupertinoLocalizations.delegate` in `MaterialApp`.
- **Validation**:
  - Create `test/localization/i18n_extraction_test.dart` to assert that pluralization cases and interpolations format cleanly.

---

### 2.5 SIRATI-25: RTL Layout Support
- **Directional Padding & Alignment**:
  - Replace any remaining `EdgeInsets.only(left/right)` with `EdgeInsetsDirectional.only(start/end)`.
  - Use `AlignmentDirectional.centerStart` and `AlignmentDirectional.centerEnd`.
- **Directional Icons**:
  - Implement `lib/shared/widgets/directional_icon.dart` or set `matchTextDirection: true` on directional `Icon` widgets (`Icons.arrow_forward`, `Icons.chevron_right`, `Icons.arrow_back`, etc.).
- **Invariants Test**:
  - Create `test/localization/directional_layout_invariants_test.dart` verifying that mirrored widgets flip coordinate positions between LTR and RTL.

---

### 2.6 SIRATI-26: Locale Switching & Persistence
- **Storage & Bootstrapping**:
  - Enhance `lib/features/localization/app_locale.dart` to detect device locale on first launch (defaulting to Arabic for Saudi/Gulf devices).
  - Persist chosen language code (`ar` / `en`) in `PreferenceStore`.
- **Dynamic Toggle**:
  - Instant locale toggle flips `MaterialApp.locale` and `Directionality` immediately without restarting the app.
  - State preservation: `CvBuilderController` state and text fields remain intact across locale toggles.
- **Tests**:
  - `test/localization/locale_switching_and_persistence_test.dart`.

---

### 2.7 SIRATI-29: RTL Golden Tests & CI
- **Coverage**:
  - Golden matrix: 4 variants per key screen (`light_ltr`, `light_rtl`, `dark_ltr`, `dark_rtl`).
  - Key screens:
    1. Dashboard / Home
    2. CV Builder Screen
    3. CV Live Preview Screen
    4. ATS Scanner / Analysis Screen
- **Execution & Tolerance**:
  - Use `test/golden/tolerant_golden.dart` (precision tolerance: 0.02) to bridge cross-OS font rasterization noise.
  - Document update command in `flutter_app/test/golden/README.md`:
    ```bash
    flutter test test/golden --update-goldens
    ```
  - Keep `--exclude-tags golden` on generic preview CI jobs in `codemagic.yaml`, and document standalone golden verification.

---

## 3. Step-by-Step Execution Sequence

1. **Step 1: Complete AppLog Structured Logging (Sprint 1 M2)**:
   - Ensure `lib/logging/app_log_event.dart` and `lib/logging/app_log.dart` are active.
   - Run `flutter test test/app_log_test.dart` $\to$ verify all 12 tests pass.
2. **Step 2: Implement Flavors & Entry Points (SIRATI-14)**:
   - Create `lib/core/flavors/app_flavor.dart` and `lib/core/flavors/flavor_banner.dart`.
   - Create `lib/main_dev.dart`, `lib/main_staging.dart`, `lib/main_prod.dart`.
   - Update `lib/main.dart` with `FlavorBanner`.
3. **Step 3: Setup i18n & RTL Infrastructure (SIRATI-24, 25, 26)**:
   - Add `generate: true` to `pubspec.yaml`, create `l10n.yaml`.
   - Write `lib/l10n/app_ar.arb` and `lib/l10n/app_en.arb`.
   - Run `flutter gen-l10n` to compile localization classes.
   - Implement `DirectionalIcon` in `lib/shared/widgets/directional_icon.dart`.
   - Connect delegates to `AppLocale` and `main.dart`.
   - Add localization and directional tests in `test/localization/`.
4. **Step 4: Execute Feature-First Folder Restructure (SIRATI-11)**:
   - Organize into `core/`, `shared/`, `features/`.
   - Maintain backwards-compatible barrel files to prevent broken imports.
   - Update `flutter_app/README.md` with layering contracts.
   - Add `test/architecture/layering_invariants_test.dart`.
5. **Step 5: RTL Golden Tests Expansion (SIRATI-29)**:
   - Expand `test/golden/theme_direction_golden_test.dart`.
   - Update `test/golden/README.md` with instructions.
6. **Step 6: Quality Gate Verification**:
   - `flutter analyze` $\to$ 0 errors, 0 warnings.
   - `flutter test --exclude-tags golden` $\to$ all unit/widget/layering tests pass.
   - `php artisan test` $\to$ all 253 backend tests pass.

---

## 4. Definition of Done for Sprint 4 Signoff

- [ ] Zero UX/UI design tasks touched.
- [ ] `flutter analyze` passes with 0 warnings.
- [ ] All Flutter tests pass (`flutter test --exclude-tags golden`).
- [ ] Architectural boundary test (`test/architecture/layering_invariants_test.dart`) passes.
- [ ] All 253 backend tests pass (`php artisan test`).
- [ ] `AppLog` leaks completely eradicated (M2 closed).
- [ ] Flavors `dev`, `staging`, `prod` runnable via `--dart-define=FLAVOR=...`.
- [ ] Arabic/English localized via generated `AppLocalizations` delegates.
- [ ] Golden baselines and instructions documented.
