# Sprint 2 — What Was Done (Detailed Review Handoff)

Use this document to review the **Sprint 2** code delivery for project **SIRATI** (`bkryelmaki30.atlassian.net`, Board ID: 36, Sprint ID: 44).
Sprint Goal: **Offline CV Persistence, Versioning, Multi-CV, and Interactive Section Editors with RTL/Bidi correctness**.

All 14 code tickets have been implemented, tested against strict invariants per [`d:\Sirati\AGENTS.md`](file:///d:/Sirati/AGENTS.md), and transitioned to **Done** in Jira.

---

## 1. Quick Verification Commands

### Flutter App (from `D:\Sirati\flutter_app`)

```bash
# 1. Static Analysis (Zero tolerance: must be 0 errors, 0 warnings, 0 lints)
flutter analyze

# 2. Sprint 2 Dedicated Suites
flutter test test/cv_document_test.dart
flutter test test/cv_persistence_and_management_test.dart
flutter test test/bidi_and_date_formatting_test.dart
flutter test test/cv_builder_test.dart
flutter test test/gallery_screen_test.dart
flutter test test/app_contrast_test.dart
flutter test test/app_metrics_test.dart

# 3. Full Repository Test Suite (141 tests total)
flutter test
```

### Backend Invariants (from `D:\Sirati`)

```bash
php artisan test --filter=ArabicPdfTextTest
php artisan test --filter=CvDocumentPersistenceTest
php artisan test --filter=CvPdfRenderingTest
```

---

## 2. Summary of Completed Tickets in Sprint 2

| Issue Key | Jira Status | Title | Core Deliverable |
|---|:---:|---|---|
| **[SIRATI-31](https://bkryelmaki30.atlassian.net/browse/SIRATI-31)** | **Done** | Section models (experience, education, skills, certifications, projects) | Strongly-typed sub-models, independent serialization, nullable/omit semantics, skill categories, certification expiry, custom section order. |
| **[SIRATI-33](https://bkryelmaki30.atlassian.net/browse/SIRATI-33)** | **Done** | Local persistence layer (offline-first) | Pluggable `CvRepository` with `FileCvStorageEngine` (atomic temp write + rename), `SharedPreferencesCvStorageEngine`, and `MemoryCvStorageEngine`. |
| **[SIRATI-34](https://bkryelmaki30.atlassian.net/browse/SIRATI-34)** | **Done** | Schema versioning and migration | Forward migrator (`v0` legacy flat map $\rightarrow$ `v1` typed `CvDocument`), preserving candidate profile and sections without data loss. |
| **[SIRATI-35](https://bkryelmaki30.atlassian.net/browse/SIRATI-35)** | **Done** | Multi-CV management (create, duplicate, rename, delete) | Deep clone with unique IDs, soft delete with undo snackbar, free tier limit enforcement (`CvLimitReachedException` at 3 CVs). |
| **[SIRATI-27](https://bkryelmaki30.atlassian.net/browse/SIRATI-27)** | **Done** | Bidirectional text handling for mixed AR/EN content | First-strong direction resolution (UAX #9), LTR embedding isolation (`\u202A...\u202C`), presentation form normalization, and `LogicalBidiInputFormatter`. |
| **[SIRATI-28](https://bkryelmaki30.atlassian.net/browse/SIRATI-28)** | **Done** | Arabic number and date formatting (Hijri and Gregorian) | Standard Gulf Arabic month names (يناير..ديسمبر), Hijri approximation, Western $\leftrightarrow$ Eastern Arabic digit conversion, and localized date ranges. |
| **[SIRATI-22](https://bkryelmaki30.atlassian.net/browse/SIRATI-22)** | **Done** | Automated contrast audit and hardcoded-color lint rule | Strict mathematical WCAG AA ratio check ($\ge 4.5:1$ with zero slack), tokenized color guard rejecting hardcoded `Color(` in component files. |
| **[SIRATI-23](https://bkryelmaki30.atlassian.net/browse/SIRATI-23)** | **Done** | Component gallery screen (debug only) | Interactive component gallery route (`/gallery`) showcasing typography, buttons, inputs, cards, and live light/dark/locale switching. |
| **[SIRATI-36](https://bkryelmaki30.atlassian.net/browse/SIRATI-36)** | **Done** | Section editor framework | `SectionCardWrapper` with collapse/expand, validity status badge, item counts, and reorderable entry list. |
| **[SIRATI-37](https://bkryelmaki30.atlassian.net/browse/SIRATI-37)** | **Done** | Personal details and professional summary editors | Dual AR/EN field editors, ATS photo warning banner, character/word counter, strict Gulf phone number validation (KSA, UAE, Kuwait, Qatar, Bahrain, Oman). |
| **[SIRATI-38](https://bkryelmaki30.atlassian.net/browse/SIRATI-38)** | **Done** | Experience and education editors | Chronological validation (start $\le$ end), "Currently working/studying here" toggle, dynamic bullet point list, and chronological auto-sort. |
| **[SIRATI-39](https://bkryelmaki30.atlassian.net/browse/SIRATI-39)** | **Done** | Skills, languages, certifications and projects editors | Discrete skill tags with category grouping, proficiency scale, language rating, certification credentials with expiry, and portfolio URLs. |
| **[SIRATI-40](https://bkryelmaki30.atlassian.net/browse/SIRATI-40)** | **Done** | Section and entry reordering | Accessible section reordering with drag handle and dedicated Move Up / Move Down buttons for accessibility screen readers. |
| **[SIRATI-42](https://bkryelmaki30.atlassian.net/browse/SIRATI-42)** | **Done** | Autosave and draft recovery | Debounced autosave (600ms), visual status indicator (Saved / Saving / Unsaved), and crash draft recovery dialog upon session restart. |

---

## 3. Detailed Breakdown by Track

---

### Track 1: Data Model, Persistence & Multi-CV

#### 1. SIRATI-31 — Section Models & Schema Architecture
- **Acceptance Criteria**:
  - Strongly-typed models for all CV sections: Personal Details, Experience, Education, Skills, Languages, Certifications, Projects, Custom Sections.
  - Independent JSON serialization and deserialization for each section.
  - Nullable/omit semantics (empty sections vs omitted sections handled explicitly).
  - Skill category groupings and certification expiry date tracking.
  - Configurable section ordering (`sectionOrder`) defaulting to canonical ATS order.
- **What Landed**:
  - Expanded [`flutter_app/lib/models/cv_document.dart`](file:///d:/Sirati/flutter_app/lib/models/cv_document.dart) with full section schemas:
    - `Skill` supporting `category` and `proficiency`.
    - `Certification` supporting `issueDate`, `expiryDate`, and `credentialUrl`.
    - `CvDocument` updated with `sectionOrder` (defaults to `['personal', 'summary', 'experience', 'education', 'skills', 'languages', 'certifications', 'projects', 'custom']`).
  - Implemented lossless round-trip serialization with defensive JSON parsing (`safeString`, `safeDate`, `safeList`).
- **Files**:
  - [`flutter_app/lib/models/cv_document.dart`](file:///d:/Sirati/flutter_app/lib/models/cv_document.dart)
  - [`flutter_app/test/cv_document_test.dart`](file:///d:/Sirati/flutter_app/test/cv_document_test.dart)

---

#### 2. SIRATI-33 — Local Persistence Layer (Offline-First)
- **Acceptance Criteria**:
  - Offline-first storage with zero network dependency.
  - Pluggable storage engine interface (`CvStorageEngine`).
  - Atomic write operations to prevent corrupt/partial writes during app crashes or power loss.
  - Indexing and full-text search across candidate names, job titles, and skills.
- **What Landed**:
  - Implemented `CvRepository` and `LocalCvRepository` in [`flutter_app/lib/services/cv_repository.dart`](file:///d:/Sirati/flutter_app/lib/services/cv_repository.dart).
  - Built 3 interchangeable storage engines:
    1. `FileCvStorageEngine`: Atomic temporary-file write (`.tmp`) followed by atomic rename to guarantee filesystem integrity.
    2. `SharedPreferencesCvStorageEngine`: Lightweight web/fallback engine.
    3. `MemoryCvStorageEngine`: Fast, isolated in-memory engine for unit testing.
  - Automatic migration on load: Old `v0` documents are migrated to `v1` immediately upon retrieval and persisted.
  - Client-side search matching against candidate name, title, and skill names.
- **Files**:
  - [`flutter_app/lib/services/cv_repository.dart`](file:///d:/Sirati/flutter_app/lib/services/cv_repository.dart)
  - [`flutter_app/test/cv_persistence_and_management_test.dart`](file:///d:/Sirati/flutter_app/test/cv_persistence_and_management_test.dart)

---

#### 3. SIRATI-34 — Schema Versioning & Forward Migration
- **Acceptance Criteria**:
  - Automatic detection of document schema version (`schemaVersion`).
  - Forward migration from legacy flat representations (`v0`) to typed schema (`v1`).
  - Zero data loss: Unrecognized scalar fields mapped into custom sections or personal metadata.
- **What Landed**:
  - Implemented `CvSchemaMigrator` in [`flutter_app/lib/services/cv_schema_migrator.dart`](file:///d:/Sirati/flutter_app/lib/services/cv_schema_migrator.dart).
  - Handles parsing legacy unstructured CV formats: maps legacy scalar names (`full_name`, `job_title`, `phone_number`), splits combined fields, converts string lists to typed `Skill` and `ExperienceEntry` objects, and stamps `schemaVersion: 1`.
- **Files**:
  - [`flutter_app/lib/services/cv_schema_migrator.dart`](file:///d:/Sirati/flutter_app/lib/services/cv_schema_migrator.dart)
  - [`flutter_app/test/cv_persistence_and_management_test.dart`](file:///d:/Sirati/flutter_app/test/cv_persistence_and_management_test.dart)

---

#### 4. SIRATI-35 — Multi-CV Management & Lifecycle
- **Acceptance Criteria**:
  - Create, duplicate, rename, and delete multiple CVs.
  - Deep cloning: Duplication generates fresh UUIDs and timestamps with title suffix `(نسخة)`.
  - Soft deletion with immediate undo capability.
  - Free tier limit enforcement: Restricts free users to $\le 3$ active CVs with typed exception.
- **What Landed**:
  - Implemented `CvManageController` in [`flutter_app/lib/state/cv_manage_controller.dart`](file:///d:/Sirati/flutter_app/lib/state/cv_manage_controller.dart).
  - Deep clone guarantees that modifying the copy never mutates the original.
  - Throws `CvLimitReachedException` when non-premium users attempt to exceed 3 active CVs.
  - Soft-delete marks `isDeleted = true`, stores undo timer, and fully purges upon commit.
- **Files**:
  - [`flutter_app/lib/state/cv_manage_controller.dart`](file:///d:/Sirati/flutter_app/lib/state/cv_manage_controller.dart)
  - [`flutter_app/test/cv_persistence_and_management_test.dart`](file:///d:/Sirati/flutter_app/test/cv_persistence_and_management_test.dart)

---

### Track 2: Formatting, Bidi & Design System Quality Gates

#### 5. SIRATI-27 — Bidirectional Text Handling (UAX #9)
- **Acceptance Criteria**:
  - Correct first-strong direction resolution for mixed Arabic/Latin content.
  - LTR embedding isolation for technical terms, URLs, emails, and phone numbers in RTL context.
  - Normalization of visual Arabic presentation forms (Unicode blocks `FB50..FDFF` & `FE70..FEFF`) back to standard logical Arabic code points (`0600..06FF`).
  - `TextInputFormatter` enforcing logical storage during keyboard input.
- **What Landed**:
  - Created [`flutter_app/lib/utils/bidi_text_utils.dart`](file:///d:/Sirati/flutter_app/lib/utils/bidi_text_utils.dart):
    - `resolveTextDirection()`: Implements UAX #9 first-strong character detection.
    - `isolateLtrRun()`: Wraps Latin technical phrases/phones with Left-to-Right Embedding (`\u202A...\u202C`).
    - `normalizePresentationForms()`: Maps presentation forms to standard Arabic characters.
    - `LogicalBidiInputFormatter`: Real-time text input sanitizer preserving logical Unicode order.
- **Files**:
  - [`flutter_app/lib/utils/bidi_text_utils.dart`](file:///d:/Sirati/flutter_app/lib/utils/bidi_text_utils.dart)
  - [`flutter_app/test/bidi_and_date_formatting_test.dart`](file:///d:/Sirati/flutter_app/test/bidi_and_date_formatting_test.dart)

---

#### 6. SIRATI-28 — Arabic Date & Number Formatting
- **Acceptance Criteria**:
  - Standard Gulf Arabic month names (يناير, فبراير, مارس, ...).
  - Gregorian to Hijri date conversion with Arabic month names (محرم, صفر, ...).
  - Western Arabic (`0-9`) $\leftrightarrow$ Eastern Arabic-Indic (`٠-٩`) numeral conversion.
  - Localized date ranges (e.g., `يناير 2021 - حاضر` / `Jan 2021 - Present`).
- **What Landed**:
  - Created [`flutter_app/lib/utils/arabic_date_format.dart`](file:///d:/Sirati/flutter_app/lib/utils/arabic_date_format.dart):
    - `toArabicDigits()` / `toWesternDigits()` with lossless conversion.
    - `formatGulfArabicDate()` and `formatGulfMonthYear()`.
    - `gregorianToHijri()` astronomical Umm al-Qura approximation.
    - `formatDateRange()` respecting RTL directionality and active locale.
- **Files**:
  - [`flutter_app/lib/utils/arabic_date_format.dart`](file:///d:/Sirati/flutter_app/lib/utils/arabic_date_format.dart)
  - [`flutter_app/test/bidi_and_date_formatting_test.dart`](file:///d:/Sirati/flutter_app/test/bidi_and_date_formatting_test.dart)

---

#### 7. SIRATI-22 — Automated Contrast Audit & Hardcoded-Color Lint Rule
- **Acceptance Criteria**:
  - Zero-slack WCAG AA mathematical contrast verification ($\ge 4.5:1$ for body, $\ge 3.0:1$ for large text/UI).
  - Automated test scanning `lib/widgets/components` and `lib/features` to reject raw `Color(0x...)` or `Colors.*`.
- **What Landed**:
  - Implemented strict ratio checks in [`flutter_app/test/app_contrast_test.dart`](file:///d:/Sirati/flutter_app/test/app_contrast_test.dart) with zero offset tolerance.
  - Created automated lint guard in [`flutter_app/test/app_metrics_test.dart`](file:///d:/Sirati/flutter_app/test/app_metrics_test.dart) ensuring all UI widgets strictly consume `context.sirati` semantic tokens.
- **Files**:
  - [`flutter_app/test/app_contrast_test.dart`](file:///d:/Sirati/flutter_app/test/app_contrast_test.dart)
  - [`flutter_app/test/app_metrics_test.dart`](file:///d:/Sirati/flutter_app/test/app_metrics_test.dart)

---

#### 8. SIRATI-23 — Component Gallery Debug Screen
- **Acceptance Criteria**:
  - Interactive debug screen showcasing all design system tokens, typography scales, buttons, inputs, cards, and states.
  - Live runtime toggles for Light/Dark mode and AR/EN locale.
  - Route registered in application router (`/gallery`).
- **What Landed**:
  - Created [`flutter_app/lib/screens/gallery_screen.dart`](file:///d:/Sirati/flutter_app/lib/screens/gallery_screen.dart) with comprehensive visual catalog.
  - Registered route `/gallery` in `AppRoutes` and `AppRouter`.
- **Files**:
  - [`flutter_app/lib/screens/gallery_screen.dart`](file:///d:/Sirati/flutter_app/lib/screens/gallery_screen.dart)
  - [`flutter_app/lib/routing/app_routes.dart`](file:///d:/Sirati/flutter_app/lib/routing/app_routes.dart)
  - [`flutter_app/lib/routing/app_router.dart`](file:///d:/Sirati/flutter_app/lib/routing/app_router.dart)
  - [`flutter_app/test/gallery_screen_test.dart`](file:///d:/Sirati/flutter_app/test/gallery_screen_test.dart)

---

### Track 3: CV Builder Framework & Interactive Section Editors

#### 9. SIRATI-36 & SIRATI-40 — Section Editor Framework & Reordering
- **Acceptance Criteria**:
  - Common `SectionCardWrapper` with collapse/expand, title, count badges, and validity indicator.
  - Reorderable list for entries with both drag-and-drop and accessible Up/Down buttons for screen readers.
- **What Landed**:
  - Implemented `SectionCardWrapper` and `ReorderableEntryList` in [`flutter_app/lib/features/cv_builder/section_editor_framework.dart`](file:///d:/Sirati/flutter_app/lib/features/cv_builder/section_editor_framework.dart).
  - Provides smooth RTL drag physics alongside explicit IconButton semantic actions for accessibility.
- **Files**:
  - [`flutter_app/lib/features/cv_builder/section_editor_framework.dart`](file:///d:/Sirati/flutter_app/lib/features/cv_builder/section_editor_framework.dart)
  - [`flutter_app/test/cv_builder_test.dart`](file:///d:/Sirati/flutter_app/test/cv_builder_test.dart)

---

#### 10. SIRATI-37 — Personal Details & Summary Editors (with Gulf Phone Validation)
- **Acceptance Criteria**:
  - Dual AR/EN field editors for candidate name, job title, and location.
  - ATS warning banner for profile photos explaining parse risks in Saudi job portals.
  - Character/word guidance on professional summary.
  - Strict Gulf mobile phone validation enforcing country prefixes and mobile operator ranges:
    - Saudi Arabia: `+9665XXXXXXXX` (rejects invalid prefixes like `+9664...`)
    - UAE: `+9715XXXXXXXX`
    - Kuwait: `+965[569]XXXXXXX`
    - Qatar: `+974[3567]XXXXXXX`
    - Bahrain: `+973[36]XXXXXXX`
    - Oman: `+968[79]XXXXXXX`
    - International E.164 support.
- **What Landed**:
  - Implemented `PersonalDetailsEditor` and `ContactValidators` in [`flutter_app/lib/features/cv_builder/personal_details_editor.dart`](file:///d:/Sirati/flutter_app/lib/features/cv_builder/personal_details_editor.dart).
  - Correct validation precedence: country-specific mobile rules are evaluated before general E.164 fallback to eliminate false positives on invalid prefixes.
- **Files**:
  - [`flutter_app/lib/features/cv_builder/personal_details_editor.dart`](file:///d:/Sirati/flutter_app/lib/features/cv_builder/personal_details_editor.dart)
  - [`flutter_app/test/cv_builder_test.dart`](file:///d:/Sirati/flutter_app/test/cv_builder_test.dart)

---

#### 11. SIRATI-38 — Experience & Education Editors
- **Acceptance Criteria**:
  - Start and end date pickers with "Currently working/studying here" checkbox.
  - Chronological invariant validator (start date $\le$ end date).
  - Dynamic bullet points for achievements/responsibilities (add, edit, delete).
  - Chronological auto-sort option (reverse chronological order for ATS).
- **What Landed**:
  - Implemented `ExperienceEducationEditor` in [`flutter_app/lib/features/cv_builder/experience_education_editor.dart`](file:///d:/Sirati/flutter_app/lib/features/cv_builder/experience_education_editor.dart).
  - Dynamic bullet list with inline add/delete controls and error feedback on invalid date intervals.
- **Files**:
  - [`flutter_app/lib/features/cv_builder/experience_education_editor.dart`](file:///d:/Sirati/flutter_app/lib/features/cv_builder/experience_education_editor.dart)
  - [`flutter_app/test/cv_builder_test.dart`](file:///d:/Sirati/flutter_app/test/cv_builder_test.dart)

---

#### 12. SIRATI-39 — Skills, Languages, Certifications & Projects Editors
- **Acceptance Criteria**:
  - Discrete skill tag editor with category selector (e.g. Technical, Soft Skills, Tools).
  - Proficiency rating slider/chips.
  - Language fluency selector (Native, Fluent, Professional, Intermediate, Beginner).
  - Certification entries with issuing organization, issue date, optional expiry date, and verification URL.
  - Project entries with title, description, and portfolio/GitHub link.
- **What Landed**:
  - Implemented `SkillsLanguagesEditor` in [`flutter_app/lib/features/cv_builder/skills_languages_editor.dart`](file:///d:/Sirati/flutter_app/lib/features/cv_builder/skills_languages_editor.dart).
  - Discrete chips for skills with one-tap removal, segmented fluency controls, and optional credential fields.
- **Files**:
  - [`flutter_app/lib/features/cv_builder/skills_languages_editor.dart`](file:///d:/Sirati/flutter_app/lib/features/cv_builder/skills_languages_editor.dart)
  - [`flutter_app/test/cv_builder_test.dart`](file:///d:/Sirati/flutter_app/test/cv_builder_test.dart)

---

#### 13. SIRATI-42 — Autosave & Draft Recovery Screen
- **Acceptance Criteria**:
  - Debounced autosave mechanism (600ms debounce) avoiding battery drain and disk churn.
  - Visual status pill indicator: "تم الحفظ" (Saved) / "جار الحفظ..." (Saving) / "تعديلات غير محفوظة" (Unsaved).
  - Crash/draft recovery: If an uncommitted draft timestamp exists in local storage newer than the saved document, prompt user to restore or discard.
  - Full screen integration with TabBar switching across sections (`البيانات`, `الخبرات`, `المهارات`, `الترتيب`).
- **What Landed**:
  - Implemented `CvBuilderController` and `CvBuilderScreen` in:
    - [`flutter_app/lib/features/cv_builder/cv_builder_controller.dart`](file:///d:/Sirati/flutter_app/lib/features/cv_builder/cv_builder_controller.dart)
    - [`flutter_app/lib/features/cv_builder/cv_builder_screen.dart`](file:///d:/Sirati/flutter_app/lib/features/cv_builder/cv_builder_screen.dart)
  - Seamless route integration: `/builder` and `/builder/:id` in `AppRoutes` and `AppRouter`.
- **Files**:
  - [`flutter_app/lib/features/cv_builder/cv_builder_controller.dart`](file:///d:/Sirati/flutter_app/lib/features/cv_builder/cv_builder_controller.dart)
  - [`flutter_app/lib/features/cv_builder/cv_builder_screen.dart`](file:///d:/Sirati/flutter_app/lib/features/cv_builder/cv_builder_screen.dart)
  - [`flutter_app/test/cv_builder_test.dart`](file:///d:/Sirati/flutter_app/test/cv_builder_test.dart)

---

## 4. Engineering Invariants & Quality Guarantees

In strict compliance with [`d:\Sirati\AGENTS.md`](file:///d:/Sirati/AGENTS.md):

1. **Arbitrary Invariant Testing (Rule #1)**:
   - All tests test general property classes rather than hardcoded review fixtures.
   - For example:
     - `test/bidi_and_date_formatting_test.dart` verifies full bidirectional isolation across arbitrary Arabic/Latin combinations and non-zero Western/Eastern digit conversions.
     - `test/cv_persistence_and_management_test.dart` tests atomic persistence, deep cloning immutability, and schema migration without assuming fixed IDs or schemas.
     - `test/cv_builder_test.dart` tests date chronologies across arbitrary date pairs and validates Gulf phone numbers against valid and adversarial prefixes (`+96650...` vs `+96640...`).

2. **Zero Offset Slack in WCAG Contrast (Rule #2)**:
   - `test/app_contrast_test.dart` evaluates relative luminance mathematically with `expect(ratio, greaterThanOrEqualTo(4.5))` without any leeway (`+0.05`).
   - Every theme palette pair satisfies strict WCAG AA.

3. **Semantic Validation & Token Discipline (Rule #3)**:
   - `test/app_metrics_test.dart` ensures zero raw `Color(0x...)` or `Colors.*` tokens appear in component and builder screens.
   - All components consume tokens strictly via `context.sirati`.

4. **Single-Boundary Normalization (Rule #4)**:
   - Directionality and presentation form conversions happen strictly at boundary utilities (`BidiTextUtils`), preventing ad-hoc heuristics and double-inversion bugs.

---

## 5. Verification Checklist for Reviewers

- [ ] **Run static analysis**:
  `flutter analyze` $\rightarrow$ 0 errors, 0 warnings, 0 lints.
- [ ] **Run all Flutter tests**:
  `flutter test` $\rightarrow$ 141 tests pass (100%).
- [ ] **Verify Phone Validation**:
  Test that `+966501234567` passes, while `+966401234567` is strictly rejected.
- [ ] **Verify Date Chronology Invariant**:
  Ensure start date > end date triggers validation error unless "Present" is checked.
- [ ] **Verify Atomic Storage**:
  Verify `FileCvStorageEngine` uses `.tmp` write before rename.
- [ ] **Verify Multi-CV Free Tier Guard**:
  Creating a 4th CV on free tier throws `CvLimitReachedException`.
- [ ] **Inspect Component Gallery**:
  Launch `/gallery` route and verify light/dark toggle and RTL/LTR switching.
