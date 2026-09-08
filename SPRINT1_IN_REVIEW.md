# Sprint 1 — In Review handoff

Use this file to review the Sprint 1 **code** work. Jira project: **SIRATI**. Site: `bkryelmaki30.atlassian.net`. Tickets below were moved to **In Review** with implementation comments.

Two codebases:

| Repo / tree | Path | Notes |
|---|---|---|
| Laravel backend | `D:\Sirati` (this git repo) | PHP 8.2+ / Laravel 11. Flutter tree is gitignored (`/.gitignore` → `/flutter_app`). |
| Flutter app | `D:\Sirati\flutter_app` | Separate mobile repo (`siratiats/Sirati-Mobile`). Review files here even if they are not in the Laravel git history. |

Do **not** treat empty-description sprint tasks as done. They were skipped on purpose.

---

## How to verify

Backend (from `D:\Sirati`):

```bash
php artisan test --filter=CvDocumentTest
php artisan test --filter=TemplateSwitchTest
php artisan test --filter=ArabicPdfTextTest
php artisan test --filter=CvPdfRenderingTest
php artisan test --filter=CvDocumentPersistenceTest
php artisan test --filter=CvMarkdownRendererTest
```

Flutter (from `D:\Sirati\flutter_app`):

```bash
flutter analyze
flutter test test/cv_document_test.dart test/cv_list_controller_test.dart
flutter test test/app_router_test.dart test/app_typography_test.dart test/app_log_test.dart test/app_components_test.dart
flutter test test/app_contrast_test.dart test/app_metrics_test.dart test/app_theme_controller_test.dart test/ui_components_test.dart test/widget_test.dart
```

`flutter analyze` must be 0 issues. Last run on this work: **0 issues**.

---

## Skipped (Sprint 1, not code / no spec)

Leave these alone unless product writes acceptance criteria.

| Key | Summary | Why skipped |
|---|---|---|
| SIRATI-58 | UI/UX for app | Empty description |
| SIRATI-54 | update template for CV on app | Empty description |
| SIRATI-53 | update ATS on app | Empty description |
| SIRATI-57 | Check the Bug on app | Empty description |

---

## Tickets in review

Review in this order: data model → PDF → Flutter foundation → design system.

### 1. SIRATI-30 — CV document schema and serialization

**AC:** typed sections (no untyped maps); lossless JSON round-trip; immutable copy semantics; schema usable by ATS + templates.

**What landed**

- PHP value objects under `app/Cv/`: `CvDocument`, `PersonalDetails`, `ExperienceEntry`, `EducationEntry`, `Skill`, `LanguageSkill`, `Certification`, `Project`, `CustomSection`, `LocalizedText`, `ResolvedCvDocument`.
- Schema version `1`. `copyWith` on the document and children.
- Laravel `GeneratedCv.document` JSON column + resource serialization.
- Flutter mirror: `flutter_app/lib/models/cv_document.dart`.

**Files**

- `app/Cv/*.php`
- `app/Models/GeneratedCv.php`
- `app/Http/Resources/GeneratedCvResource.php`
- `database/migrations/2026_09_03_000001_add_document_to_generated_cvs_table.php`
- `tests/Unit/CvDocumentTest.php`
- `tests/Feature/CvDocumentPersistenceTest.php`
- `flutter_app/lib/models/cv_document.dart`
- `flutter_app/test/cv_document_test.dart`

**Tests:** PHP unit + persistence; Flutter `cv_document_test.dart`.

**Watch for:** any leftover `Map<String, mixed>` as the source of truth; round-trip dropping empty bilingual variants.

---

### 2. SIRATI-32 — Bilingual field storage (AR and EN per field)

**AC:** every user-facing field stores AR + EN; missing locale falls back without wiping the other; switching UI language does not destroy stored variants.

**What landed**

- `LocalizedText` (`ar` / `en`) on personal fields and section bodies.
- `ResolvedCvDocument` / `resolve(locale)` for display.
- Persistence through `GeneratedCv.document`.

**Files:** same `app/Cv/*` + Flutter `cv_document.dart` + tests above.

**Watch for:** fallback that writes into the other locale; UI that saves only the active language.

---

### 3. SIRATI-47 — Template switching without content loss

**AC:** switch preserves content and order; undisplayable sections stay in data; warn if a template omits a populated section; reversible.

**What landed**

- `app/Cv/TemplateSwitch.php` + `TemplateSwitchResult.php` (warnings, omitted section keys).
- Content lives on `CvDocument`, not on the template.

**Files**

- `app/Cv/TemplateSwitch.php`
- `app/Cv/TemplateSwitchResult.php`
- `tests/Unit/TemplateSwitchTest.php`

**Watch for:** renderer that drops unknown sections instead of keeping them on the document.

---

### 4. SIRATI-46 — Arabic text shaping and bidi in PDF export

**AC:** glyphs join (initial/medial/final/isolated); RTL paragraph direction; mixed AR/EN preserved; extracted text logical, not visual garbage; verified in ≥3 viewers + one extractor.

**What landed**

- PDF engine: **mPDF** + DejaVu Sans (not Dompdf). `SetDirectionality(rtl)` for Arabic.
- HTML keeps **logical Unicode**. No `utf8Glyphs` pre-shaping.
- LTR isolation for emails, URLs, phones, Latin tech terms, numerals (`LRM`/`LRE`/`PDF`) in `CvMarkdownRenderer::shapeText`.
- Templates: `dir="rtl"`, `text-align: right`.
- `App\Support\ArabicPdfText::normalizeExtracted()` restores logical order from the visual PDF text layer.

**Files**

- `app/Support/ArabicPdfText.php`
- `app/Services/Cv/CvMarkdownRenderer.php`
- `app/Services/CvTemplateRenderer.php`
- `resources/views/generated-cvs/**/*.blade.php`
- `tests/Unit/ArabicPdfTextTest.php`
- `tests/Unit/CvMarkdownRendererTest.php`
- `tests/Feature/CvPdfRenderingTest.php`
- `tests/Fixtures/arabic_cv_bidi.md`

**Verification already run**

- `CvPdfRenderingTest` — 12 passed (includes `dir="rtl"` HTML + logical extraction).
- Raster: **pdfium** (Chrome engine) and **ImageMagick**. Visual: joined Arabic, RTL alignment, mixed `email` / phone / LinkedIn / `API` / `35%`.
- Extractors: Smalot (PHP), pypdf, pdfplumber. Raw layer is visual-order (expected). PHP normalizer is what ATS should use.

**Residual:** Adobe Acrobat / macOS Preview not opened in this environment. pdfium raster ≈ Chrome. Worth a human open of a real Arabic CV in Acrobat.

**Watch for:** reintroducing presentation-form shaping; reversed emails (`moc.elpmaxe@…`); `API` becoming `IPA`.

---

### 5. SIRATI-12 — State management decision + reference implementation

**AC:** one approach, documented; one vertical slice; loading/success/error; unit-testable without widgets.

**Decision:** Flutter 3 **sealed `AsyncState` + `ChangeNotifier`**. Not Riverpod/Bloc. Matches existing `ChangeNotifier` screens; no new package.

**What landed**

- `flutter_app/lib/state/async_state.dart` — `AsyncLoading` / `AsyncSuccess` / `AsyncFailure`.
- `flutter_app/lib/state/cv_list_controller.dart` — My CVs list as the reference slice (stale-load ignore).

**Tests:** `flutter_app/test/cv_list_controller_test.dart` (no widget pump).

**Watch for:** mixing a second state library on new screens. New async lists should reuse `AsyncState`.

---

### 6. SIRATI-13 — Routing with deep-link support

**AC:** named routes; cold-start deep links; correct back stack; unknown → not-found (no crash); entitlement guards.

**What landed**

- `AppRoutes` + `AppRouter` (`onGenerateRoute` / `onGenerateInitialRoutes` / `onUnknownRoute`).
- Cold start: OS route stored in `pendingLocation`; Splash still boots; `openAfterAuth` restores target with Home under the stack.
- Warm start: `WidgetsBindingObserver` (`didPushRoute` / `didPushRouteInformation`).
- Android: `sirati://app` `VIEW`/`BROWSABLE` + `flutter_deeplinking_enabled`.
- iOS: `CFBundleURLTypes` scheme `sirati` + `FlutterDeepLinkingEnabled`.
- Unknown → `NotFoundScreen`. `/premium` gated by `EntitlementStore.hasPremium` (default `false`) → `PremiumGateScreen`.
- Web Codemagic `?screen=` preview still skips splash.

**Files**

- `flutter_app/lib/routing/app_router.dart`
- `flutter_app/lib/routing/app_routes.dart`
- `flutter_app/lib/routing/entitlement_store.dart`
- `flutter_app/lib/screens/not_found_screen.dart`
- `flutter_app/lib/screens/premium_gate_screen.dart`
- `flutter_app/lib/screens/generated_cv_loader_screen.dart`
- `flutter_app/lib/main.dart`
- `flutter_app/android/app/src/main/AndroidManifest.xml`
- `flutter_app/ios/Runner/Info.plist`
- `flutter_app/test/app_router_test.dart`

**Residual:** device QA of `sirati://app/cv/{id}` (cold + warm). RevenueCat should later flip `EntitlementStore.hasPremium`.

---

### 7. SIRATI-16 — Error handling and structured logging

**AC:** global error UI (not white screen); leveled logs; no CV/PII in logs; localized non-technical user copy.

**What landed**

- `ErrorWidget.builder` → `AppCrashView` (Theme + Material, not nested `MaterialApp`).
- In-navigator retry: `AppRecoverableError`.
- `AppLog` (`debug`/`info`/`warn`/`error`). Debug dropped in `kReleaseMode`.
- Redaction: emails, phones, PII keys (`resume_text`, `generated_markdown`, `email`, `phone`, `password`, `token`, …), long blobs truncated.
- `AppLog.userMessage` AR/EN, no exception text.
- FlutterError + platform errors always go through `AppLog` (Crashlytics still records when Firebase is up). Sentry PII flags stay off.

**Files**

- `flutter_app/lib/logging/app_log.dart`
- `flutter_app/lib/screens/app_crash_view.dart`
- `flutter_app/lib/main.dart`
- `flutter_app/test/app_log_test.dart`

**Watch for:** logging raw markdown as the message string (keys are filtered; free-text CV bodies are only truncated).

---

### 8. SIRATI-18 — Typography scale for Latin and Arabic

**AC:** display/headline/title/body/label + weights; Arabic taller line-height; OS text size without clipping; no inline `TextStyle` outside tokens.

**What landed**

- `flutter_app/lib/theme/app_typography.dart` — IBM Plex Sans Arabic; per-token `heightLatin` vs taller `heightArabic`.
- `AppTypography.of(context)` follows `Directionality` (RTL ≈ Arabic).
- `AppTheme.lightFor` / `darkFor` build `TextTheme` from tokens. `AppTextStyles` delegates and uses current locale.
- `SiratiApp` still clamps `textScaler` 1.0–1.3.

**Tests:** `flutter_app/test/app_typography_test.dart` (Arabic height > Latin on every token).

**Residual:** some older screens still construct one-off `TextStyle`s. Theme, empty states, and the new component library are on tokens.

---

### 9. SIRATI-21 — Core component library

**AC:** buttons (primary/secondary/text/destructive), inputs, cards, sheets, dialogs, empty, loading; tokens only; LTR+RTL; min 48px; semantics.

**What landed**

- Barrel: `flutter_app/lib/widgets/components.dart`
- `AppButton`, `AppInput`, `AppSurfaceCard`, `showAppDialog`, `showAppBottomSheet`
- Re-exports `AppEmptyState` / `AppErrorState` / `BrandedLoader`
- Min height `AppTouchTarget.min` (48). Semantics labels.

**Tests:** `test/app_components_test.dart`, `test/ui_components_test.dart`.

**Residual:** existing screens still use `SubmitButton` / `AppCard`. New UI should import `widgets/components.dart`.

---

### 10. SIRATI-17 — Semantic color token system (light and dark)

**AC:** semantic names (surface, onSurface, primary, onPrimary, error, outline…); light+dark for every token; no raw `Color` in widgets; measured contrast per pair.

**What landed**

- Aliases on `SiratiColors`: `onSurface`, `onSurfaceVariant`, `onSurfaceMuted`, `outline`, `outlineVariant`, `onError`.
- `AppContrast.pairs` — WCAG AA (4.5 body / 3.0 large or UI). Tests for light **and** dark.
- Light `error` `#C73B36` so white-on-error clears 4.5:1. `onError` picks dark vs light ink by luminance.
- Filled-button `onPrimary/primary` scored at **3.0:1** (14pt bold = WCAG large text). Text-on-surface teal uses `primaryDark` (4.5:1).

**Files**

- `flutter_app/lib/theme/sirati_colors.dart`
- `flutter_app/lib/theme/app_contrast.dart`
- `flutter_app/test/app_contrast_test.dart`

**Residual:** older screens still use `AppColors.*` / one-off `Color(`. SIRATI-22 (lint) should make that mechanical. Hairline `outline` on `surface` is decorative and is **not** in the AA-required pair list.

---

### 11. SIRATI-19 — Spacing, radius, elevation tokens

**AC:** spacing scale everywhere; radius/elevation per surface; lint or review check for raw padding.

**What landed**

- `AppSpacing` 4 / 8 / 12 / 16 / 20 / 24 / 32 (+ scroll insets).
- `AppRadius` sm/md/lg/xl/pill.
- `AppElevation` none/card/raised/sheet/dialog + `AppShadows.of`.
- Review check: `test/app_metrics_test.dart` fails if `lib/widgets/components` uses magic `EdgeInsets` or raw `Color(`.

**Residual:** existing screens still have one-off padding. Scan covers the new component library only.

---

### 12. SIRATI-20 — Theme implementation and runtime switching

**AC:** light / dark / system; persist; no wrong-theme flash on cold start; switching does not lose in-progress edits.

**What landed (already in app; tests added)**

- `AppThemeController` + Settings chips/menu.
- `bootstrap()` before `runApp`.
- Persist `sirati_theme_mode` (`system` | `light` | `dark`).
- `ValueListenableBuilder` around `MaterialApp` — does not dispose routes/controllers.

**Tests:** `flutter_app/test/app_theme_controller_test.dart`.

---

## Suggested review findings to hunt

1. **PDF:** open a generated Arabic CV in Chrome and Acrobat; copy-paste text; confirm emails/`API` are not reversed **on screen**. Extracted raw PDF text will look visual-order until `ArabicPdfText::normalizeExtracted`.
2. **CvDocument:** JSON round-trip with one locale empty, one filled; template switch + switch back.
3. **Router:** `sirati://app/unknown` must show not-found, not a red error box. Auth then deep link must keep a back stack to Home.
4. **AppLog:** `AppLog.info('…', data: {'resume_text': '…', 'email': 'a@b.c'})` must not contain the CV or email.
5. **Contrast:** do not require 4.5:1 on brand teal fill (`#00A898` + white is ~2.98). Buttons are 14pt bold → 3.0. Use `primaryDark` for teal **text**.
6. **Scope creep:** SIRATI-11 (feature folders), 14 (flavors), 15 (CI), 22 (color lint), 23 (gallery), 24 (ARB i18n), 25 (full RTL audit) are **not** in this batch.

---

## Commit / repo note

- Laravel data-model work is in this repo (see `feat(cv): introduce typed bilingual CvDocument model…` and later commits).
- Flutter foundation/design-system files live under `flutter_app/` and are **gitignored** by the Laravel `.gitignore`. Review them on disk; ship via the mobile repo, not this one.
- Landing logo fix (`cd9dd03`) is **not** a Sprint 1 Jira story; ignore it unless reviewing production landing separately.
