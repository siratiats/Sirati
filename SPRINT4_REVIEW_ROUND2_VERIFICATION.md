# Sprint 4 — re-audit of the fixes (round 2)

Verification of the changes made against `SPRINT4_REVIEW_FINDINGS.md`. Same method: re-read the changed files from the repo, re-derive each claim rather than take the summary.

**Coverage note.** The local Linux workspace still won't start here, so `flutter analyze` / `flutter test` were not run by me — the reported green is taken on trust and nothing below depends on it. One thing worth recording: my first pass this round read a stale snapshot in which the top-level `features/cv_builder/*.dart` files still held their pre-migration bodies. They are one-line barrels (46–56 bytes) in the repo. Everything below is from a fresh read of the working tree.

**Verdict: H2 is fully closed. H1 is about 60% closed and the remainder is now a different, harder problem than the one I described.** Both mediums and two of three lows are done.

---

## H2 — closed, and the test now enforces the right side of it

`.github/workflows/ci.yml` has a third job:

```yaml
  flutter-golden-tests:
    name: Flutter Golden Tests
    runs-on: windows-latest
    timeout-minutes: 15
    …
      - name: Run golden tests
        run: flutter test --tags golden
```

`windows-latest` matches where the baselines were generated, which is the detail that makes this a real gate rather than a red job on day one. The `echo "Flutter tests completed successfully."` step is gone, and `ci_workflow_test.dart` now asserts its *absence* alongside the two positive strings. That inversion — the suite previously enforced the exclusion, it now enforces the inclusion — is exactly the fix.

**One note, not blocking.** The three assertions are independent substring checks against a 2,751-byte file:

```dart
expect(content, contains('flutter test --tags golden'));
expect(content, contains('runs-on: windows-latest'));
```

Nothing ties them to the same job. Move `runs-on: windows-latest` to the backend job and put the golden job on `ubuntu-latest`, and the test stays green while every golden fails on rasterization noise. The invariant is *"some job runs `flutter test --tags golden` on the OS the baselines came from"*, and it is one small YAML parse away — `package:yaml` is already in the Flutter tool chain. This is AGENTS rule 1 in its mildest form: the assertion describes the text that happens to be there rather than the property that must hold. Worth a follow-up ticket, not a fix now.

**Codemagic was not updated.** `codemagic.yaml` lines 23 and 55 still run `flutter test --exclude-tags golden` in both jobs. That is defensible — goldens gate on GitHub, and Codemagic runs macOS where the Windows baselines would fail — but it should be a stated decision rather than an oversight, because `test/golden/README.md` currently describes it as the whole story (see M1).

## H1 — the user-facing half is fixed; the extraction is partial

**What is genuinely closed.** The catalogs went from 26 keys to **129, key-for-key in sync across `app_ar.arb` and `app_en.arb`**, and the six CLDR forms survived the expansion intact (`cvCount` and `analysisCount` both carry `zero`/`one`/`two`/`few`/`many`/`other`). Three screens are fully converted:

| File | `AppLocalizations` refs | Arabic literals | locale ternaries |
|---|---|---|---|
| `presentation/personal_details_editor.dart` | 49 | **0** | 0 |
| `settings/presentation/settings_screen.dart` | 45 | **0** | 9 |
| `presentation/cv_builder_screen.dart` | 41 | 2 (template display names) | 0 |

And the specific defect I called out — the monolingual validators — is properly gone. The signature changed rather than the strings:

```dart
static String? validateGulfPhone(String? value, AppLocalizations l10n) { … }
      return kuwaitPattern.hasMatch(cleaned) ? null : l10n.invalidKuwaitPhone;
```

Threading `l10n` through as a parameter instead of reaching for a context inside a static was the right call. An English user filling in step 1 now gets English errors. That was the live bug and it is fixed.

**What did not happen.** The CV builder is a three-step wizard, and only step 1 moved:

| Step | Widget | `l10n` | Arabic literals | locale gates |
|---|---|---|---|---|
| 1 | `PersonalDetailsEditor` | 49 | 0 | — |
| 2 | `ExperienceEducationEditor` | **0** | **37** | **0** |
| 3 | `SkillsLanguagesEditor` | **0** | **66** | **0** |

All three are mounted from the same `cv_builder_screen.dart` (lines 359, 364, 368). So an English user now walks a wizard that is in English on the first page and Arabic-only on the next two:

```
'تاريخ الانتهاء لا يمكن أن يسبق تاريخ البدء'   // end date before start date
'المسمى الوظيفي (بالعربية)'                     // job title
'مستوى الإتقان'                                 // proficiency level
```

That is the same class of defect as the one just fixed, in the same flow, one screen over. In one narrow sense the sprint made it more visible rather than less: a uniformly Arabic wizard reads as an untranslated product; a wizard that switches language between page 1 and page 2 reads as broken.

**And the remainder is not a mechanical string swap.** This is the part worth knowing before it is scheduled as "finish the extraction". In `skills_languages_editor.dart` the Arabic strings are the *stored values*, not just the labels:

```dart
String _level = 'متقدم';
String _category = 'مهارات تقنية';
…
DropdownMenuItem(value: 'مبتدئ', child: Text('مبتدئ (Beginner)')),
DropdownMenuItem(value: 'مهارات تقنية', child: Text('مهارات تقنية')),
…
widget.onAdd(_nameCtrl.text.trim(), _level, _category);
```

`_level` and `_category` go straight into the CV document. So the skill proficiency persisted for every CV ever built is an Arabic string literal, and an English-language CV export prints `مبتدئ` as the proficiency and `مهارات تقنية` as the category regardless of locale. Localizing the label is one line; making the *data* locale-independent means a stable key (an enum, or `'technical'`/`'beginner'`) with the Arabic and English as display strings in the catalog — plus a migration for rows already stored. `cv_schema_migrator.dart` exists and is where that belongs.

I would treat that as its own ticket rather than folding it into "extract the remaining strings", because the two have different risk profiles and only one of them touches persisted data.

**A smaller symptom of the same gap.** Of the 129 keys, 16 are referenced nowhere in `lib/` — including `cvCount` and `analysisCount`, the two six-form Arabic plurals. That pluralization is the single best piece of i18n engineering in this sprint and nothing calls it. The screen that would use it, `my_cvs_screen.dart`, still has 0 `l10n` references and 68 locale ternaries. `switchToArabic` / `switchToEnglish` are likewise unreferenced while `settings_screen.dart` retains 9 ternaries.

Two screens remain on the old ternary approach and are *bilingual*, so they are debt rather than defect: `my_cvs_screen.dart` (22 literals, 68 gates) and `cv_generator_screen.dart` (83 literals, 140 gates).

**Where I would draw the line for "SIRATI-24 done":** every string a user can reach without leaving the CV builder is in the catalog, and no persisted field holds a localized string. The two remaining wizard steps and the skills data model are that boundary. `my_cvs_screen` and `cv_generator_screen` can follow.

---

## M1 — closed in the code, stale in the doc

Tolerance went 2% → **0.5%**, with the reasoning in the file rather than in a commit message:

```dart
/// Default 0.5% of pixels. Same-OS engine noise is typically far below this;
/// cross-OS text rasterization is documented as well under 0.5%. A 2% budget
/// (~19k pixels on these baselines) could hide a footer line.
const defaultGoldenPrecisionTolerance = 0.005;
```

Naming the constant and documenting what the old number could hide is better than the number change alone. Two residuals:

- **`test/golden/README.md` was not updated.** It still says *"precision tolerance **0.02**"* and *"Codemagic preview jobs and GitHub CI run `flutter test --exclude-tags golden`"*. Both statements are now false, and the second contradicts the H2 fix in the same repo. The README is the artefact a new contributor reads first; right now it documents the state before this round.
- **The noise floor still is not measured here.** The docblock cites what cross-OS rasterization noise is *documented* to be, which is a reasonable prior, but 0.005 sits *at* the cited figure rather than above it — so if the real floor on this matrix is 0.4%, the margin is 20%, and the first flaky golden will be answered by raising the number again. One run of the golden job on a second OS image, with the observed `diffPercent` recorded in the README, converts the prior into a measurement and makes the next tolerance argument short.

Neither blocks. The code is right; the documentation around it is a round behind.

## M2 — closed, with one hole left open and one narrowed

**Barrel laundering is genuinely fixed.** `_canonicalizeUri` now follows single-export barrels to their target before classification, with a `seen` set guarding against cycles, and `_resolveExportTarget` handles both relative and `package:` export forms. I checked every barrel under `lib/widgets/` (and the cv_builder top level): all are single-export, so the `exports.length != 1` bail-out is not currently reachable. The docblock says what the test does — *"One-line barrels are followed to their target before classifying a URI"* — which keeps rule 3 honest.

**The `data/`/`controllers/` restriction is gone.** The cross-feature test now walks every file under `lib/features`, so `presentation/` — the place I flagged as most likely and most tempting — is covered. That was the substantive half of the finding.

**Still open: relative imports are invisible to all three layer tests.**

```dart
String _canonicalizeUri(String uri) {
  if (!uri.startsWith('package:sirati/')) return uri;   // ← returns unchanged
  …
}
bool _isPackage(String uri, String rest) => uri.startsWith('package:sirati/$rest');
```

A relative URI is returned as-is, `_isPackage` is false for it, and no violation is recorded. So `lib/core/foo.dart` containing `import '../features/dashboard/presentation/home_screen.dart';` passes the "core has zero dependencies on features" test. Same for `shared → features` and for cross-feature imports.

This is latent, not live — the migrated tree imports `package:sirati/…` exclusively, which I re-checked. But relative imports within `lib/` are idiomatic Dart and are what an IDE auto-import offers by default, so this is the form a violation is most likely to arrive in. The fix is one branch: resolve a relative URI against the importing file's directory into `package:sirati/<rel>` at the top of `_canonicalizeUri`, then let the rest of the function run unchanged. `_resolveExportTarget` already contains the path-joining logic to reuse.

The `{app, dashboard, localization}` wholesale exemption also remains. `app` and `localization` are defensible as composition roots; `dashboard` being exempt from all cross-feature rules is a larger carve-out than "composition root" implies, and worth either narrowing or explaining in the constant's name.

---

## Lows

- **Fixed, and better than asked.** `_widgetFor` now carries `assert(builder != null, …)` *and* throws `StateError` when null — so a missed registration is loud in debug and loud in release, rather than a blank screen in either.
- **Fixed.** `flutter_app/.gitignore` is now exactly `lib/l10n/generated/`. Worth confirming the directory was also untracked (`git rm -r --cached lib/l10n/generated`), since `.gitignore` does not remove files already in the index — if it is still tracked, the stale-generated-class hazard is unchanged.
- **Unchanged.** `app_locale.dart` still lives in `core/utils/` (with a barrel at `lib/app_locale.dart`) while a `features/localization/` directory exists. Cosmetic; noting it only so it does not silently become the convention.

---

## Status

| | Raised | Status |
|---|---|---|
| H1 — i18n built, unused | catalog + 3 screens converted, validators fixed | **partially closed** — wizard steps 2 & 3 untouched; localized strings persisted as data |
| H2 — goldens never run in CI | dedicated Windows job, test inverted | **closed** |
| M1 — 2% tolerance | 0.005 with rationale | **closed in code**; README stale on tolerance and on CI |
| M2 — layering blind spots | barrels followed, `presentation/` covered | **closed**; relative imports still invisible |
| L1 — silent blank route | assert + StateError | **closed** |
| L2 — `app_locale.dart` placement | — | open, cosmetic |
| L3 — generated l10n committed | gitignored | **closed** pending untrack |

**Where I would go next, in order:** the two wizard editors, because a language that changes between page 1 and page 2 of the same form is the most visible defect in the product right now; then the skills data model, as its own ticket, before more CVs are stored with Arabic proficiency values; then the two stale README paragraphs, which take a minute and currently mis-describe two fixes made this round.

## On AGENTS.md

Rule 3 held again — the layering test got *more* semantic this round rather than reaching for a regex to close the barrel hole, and the fix is the one that generalizes.

The rule 6 I proposed last round would have flagged the state this round arrived in, and cheaply. `cvCount` and `analysisCount` are 129-key-catalog entries with correct six-form Arabic pluralization and no call site; `ExperienceEducationEditor` and `SkillsLanguagesEditor` are the named consumers SIRATI-24's Definition of Done would have listed. It is the same shape as `normalizeExtracted`, the bidi utilities, and `previewPdfResponse` — and this time it recurred *inside* the sprint that was reviewed for it. That is the strongest argument for writing it down:

> **6. A deliverable is not done until something calls it.**
> For any ticket that adds a utility, catalog, or test suite, name the production call site or CI job that consumes it in the Definition of Done. If there is none yet, the ticket is infrastructure — say so on the ticket rather than marking it complete.

One addition I would now make to it, from the skills editor: **when a ticket localizes a screen, check whether any of its strings are persisted.** A displayed string is a one-line change; a stored one is a schema change with a migration behind it. Discovering that distinction during the extraction is how a two-day ticket becomes a two-week one.
