# Sprint 4 — round 3 close-out

Verification of the changes made against `SPRINT4_REVIEW_ROUND2_VERIFICATION.md`. Same method: re-read the working tree, re-derive each claim, and trace the ones that cross a layer boundary all the way through.

**Coverage note.** Local Linux workspace still won't start, so the suites were not run by me. Nothing below depends on the reported green — every claim is re-derived from source, and the one open finding was traced through both the Dart write path and the PHP render path.

**Verdict: everything I raised in round 2 is closed, and the i18n work is now genuinely good.** One finding remains, and it is the *original* H1 finding surviving in the one place the new tests do not look: the export.

---

## Closed, and closed well

**The wizard is fully localized.** All three steps, zero Arabic UI literals:

| | round 2 | now |
|---|---|---|
| `personal_details_editor.dart` | 49 refs / 0 literals | 49 / 0 |
| `experience_education_editor.dart` | **0 refs / 37 literals** | **48 / 0** |
| `skills_languages_editor.dart` | **0 refs / 66 literals** | **63 / 0** |
| `section_editor_framework.dart` | 4 / 4 | 6 / 0 |

Catalogs went 129 → **220 keys, key-for-key in sync**, six CLDR forms on `cvCount` and `analysisCount` intact, and no English catalog value contains a single Arabic codepoint (checked all 220, not a sample).

**The storage-key separation is the right call, and better than what I proposed.** I suggested introducing stable keys (`'beginner'`, `'technical'`) plus a migration. `skill_storage_keys.dart` instead declares the *existing Arabic strings* as the schema:

```dart
/// These Arabic strings are the on-disk schema (`LocalizedText.ar`). Changing
/// them is a document-schema change and belongs in `cv_schema_migrator.dart` —
/// not an i18n catalog swap.
abstract final class SkillStorageKeys {
  static const beginner = 'مبتدئ';
```

That gets the display/storage split with **zero migration and zero risk to already-stored documents**, which my version did not. Naming the file `data/` rather than leaving the constants in the widget puts them on the right side of the layering rule too. The docblock stating the rule, and `_skillLevelLabel(l10n, stored)` with a `_ => stored` fallback for unknown values, are both the careful choices.

**`test/localization/l10n_adoption_test.dart` is the best test file in this repo.** It is the lint I asked for and three things I did not:

- AST-based Arabic-literal detection (`_ArabicLiteralVisitor` over the parsed unit) — rule 3, not a source regex.
- Widget-pump verification that the *rendered* chrome follows the ambient locale, in both directions, rather than checking that `l10n` is referenced.
- A test that pins the storage-key invariant against the very refactor that would break it: *"skills editor Arabic literals are persisted storage keys only"*, whitelisting `SkillStorageKeys.all ∪ LanguageLevelStorageKeys.all` and failing on anything else.

And the bullet test — *"new achievement bullets persist empty text, not a locale string"* — reads as a defect found and closed during the work rather than a test written to a report. That is rule 1 working the way it is supposed to.

**M1 (README) is fully closed**, including the part I did not ask for: the Codemagic exclusion is now written down as a decision with its reason (*"those jobs are macOS, and Windows-generated baselines fail there… Goldens gate on GitHub `windows-latest` only"*), and the tolerance section tells the next person how to re-derive the number instead of picking one.

**M2 (relative imports) is closed.** `_canonicalizeUri` now takes `fromFile` and resolves non-`package:` URIs through `_libRelative` + `_resolveExportTarget` before classifying. I ran the resolution logic against the four bypasses I described:

| from | import | resolves to | caught |
|---|---|---|---|
| `core/routing/app_router.dart` | `../../features/dashboard/…` | `package:sirati/features/dashboard/…` | ✓ |
| `core/foo.dart` | `../features/x.dart` | `package:sirati/features/x.dart` | ✓ |
| `shared/theme/app_theme.dart` | `../../features/settings/…` | `package:sirati/features/settings/…` | ✓ |
| `features/auth/presentation/login.dart` | `../../cv_builder/data/…` | `package:sirati/features/cv_builder/…` | ✓ |

All four now register as violations. The hole is shut.

---

## The one open finding — an English CV still exports Arabic proficiency

**Where the fix stopped.** The display/storage split is applied at the widget, but the *write* still fills only the Arabic variant:

```dart
// skills_languages_editor.dart
name:  LocalizedText(ar: name.trim()),
level: LocalizedText(ar: level),      // en left empty
category: LocalizedText(ar: category),
```

And `resolve` falls back — identically in both languages of the stack:

```dart
// lib/shared/models/cv_document.dart
String resolve(String language, {bool fallback = true}) {
  final primary = language == 'en' ? en : ar;
  if (primary.isNotEmpty || !fallback) return primary;
  return language == 'en' ? ar : en;      // ← English asks, Arabic answers
}
```
```php
// app/Cv/LocalizedText.php  — same shape
return $language === 'en' ? $this->ar : $this->en;
```

**This is live for languages, not theoretical.** `CvDocument::resolve()` renders the languages section by joining name and level:

```php
$level = $entry->level->resolve($language);
return trim($name.($level !== '' ? " ({$level})" : ''));
```

and `CvTemplateRenderer` passes that straight into the template as `'languages' => $resolved->languages`. So a candidate who sets their export language to English gets a Languages section reading:

```
Arabic (اللغة الأم (Native))
English (مهني متقدم (C1))
French (أساسي (A2))
```

Three of the five `LanguageLevelStorageKeys` carry no English at all beyond a CEFR code. This lands in the PDF a recruiter opens, and it is the same sentence I opened H1 with — *"an English user gets Arabic"* — just one layer further down than the tests reach.

**Skill level and category are latent, not live.** `CvDocument::resolve()` currently joins only `$skill->name`, so proficiency and category do not reach any template today. But they are written with the same `LocalizedText(ar: …)` pattern, so the day a template renders them — and a skills-with-levels template is an obvious next ask — the same defect appears with no code change.

**Why the new tests do not catch it.** The suite asserts the storage side and stops there:

```dart
expect(skill.level.ar, SkillStorageKeys.advanced);
expect(find.text(en.skillLevelAdvanced), findsWidgets);
```

Both are correct and both are about the editor. Nothing asserts what `resolve('en')` returns for that field, and nothing on the PHP side asserts an English export is free of Arabic script. That is the rule-5 shape exactly: the acceptance criterion is *"an English CV reads in English"*, and the tests verify the implementation detail that stands in for it.

**The fix.** Give `SkillStorageKeys` / `LanguageLevelStorageKeys` a parallel English map — the strings already exist in `app_en.arb` — and fill both variants at write time:

```dart
level: LocalizedText(ar: level, en: SkillStorageKeys.englishFor(level)),
```

Storage keys stay exactly as they are, so nothing about the schema decision changes and no migration is needed for the key itself. Documents already saved will still have an empty `en` and still fall back, so if backfilling matters, that *is* a `cv_schema_migrator.dart` job — and a safe one, since it only fills an empty field from a known key.

The assertion to add alongside it is one line on the backend: render an Arabic-authored document with `language: 'en'` and assert the output contains no `[\x{0600}-\x{06FF}]` outside the fields the candidate typed in Arabic themselves. That is the criterion; everything else is a proxy for it.

---

## Smaller notes, none blocking

- **`_ArabicLiteralVisitor` overrides `visitSimpleStringLiteral` only.** A `StringInterpolation` node — `'لديك $count سير ذاتية'` — is a different AST node and never reaches the visitor. There are zero Arabic interpolations in the guarded files today, so this is latent; but interpolated strings are disproportionately *user-facing copy*, which makes this the likeliest way a literal comes back. One more override closes it.
- **The English-catalog test pins six keys.** `expect(en.invalidSaudiPhone, …)`, `en.settings`, `en.cvEditorTitle`, and three more. The invariant is "no value in `app_en.arb` contains Arabic script" — a loop over the loaded catalog covers all 220 and cannot go stale as keys are added. It passes today either way; I checked all 220.
- **`ci_workflow_test.dart` is unchanged** — carried from round 2, still non-blocking. `contains('flutter test --tags golden')` and `contains('runs-on: windows-latest')` remain independent substring checks against the whole file, so nothing ties the runner OS to the golden job.
- **`test/golden/failures/` is committed** — 28 stale diff/master/test PNGs from a February run. That directory is comparator output, regenerated on every failure, and belongs in `.gitignore` next to `lib/l10n/generated/`. Same class as the L3 from round 2.
- **`analysisCount` still has no call site.** `cvCount` gained one this round; its twin did not. Fifteen catalog keys are unreferenced overall — mostly onboarding and auth chrome, which is fine as staging, but worth a glance so the catalog does not accumulate keys faster than screens.
- **`l10n_adoption_test.dart` imports `package:sirati/models/cv_document.dart` and `package:sirati/theme/app_theme.dart`** — the legacy barrels rather than the `shared/` paths. The layering test only walks `lib/`, so `test/` is unchecked. Cosmetic, but the barrels exist to be deleted eventually.

---

## Status

| | Round 2 finding | Status |
|---|---|---|
| H1 remainder | wizard steps 2 & 3 untranslated | **closed** — 0 Arabic UI literals across all three |
| H1 remainder | localized strings persisted as data | **closed for storage** — key/display split, no migration needed |
| — | *English export still resolves to Arabic* | **open** — live for languages, latent for skills |
| M1 | README stale on tolerance and CI | **closed** |
| M2 | relative imports invisible to layer tests | **closed** — verified on four bypass cases |
| L | `ci_workflow_test` substring assertions | open, non-blocking |
| L | `test/golden/failures/` committed | new, trivial |

**Sprint 4 is one small change from done.** Fill `en` on the two `LocalizedText` writes and add the backend assertion; everything else on this list is tightening, not fixing.

## On AGENTS.md

Rule 3 held for a second round — `l10n_adoption_test.dart` and the layering test both got *more* semantic rather than reaching for a regex, and the Arabic-literal check is AST-based even though a regex would have been trivially easier and would have passed.

The rule 6 I have proposed twice is now demonstrably the one that matters, and this round shows the sharper version of it. The catalog has a consumer, the goldens have a job, the storage keys have a test — every deliverable from round 2 got connected. What was not checked is the *end* of the chain: the artefact the candidate actually sends to an employer. The editor is localized, the catalog is complete, the storage is clean, and the PDF still says `مهني متقدم (C1)` in an English CV.

So I would write it with the destination named, not just a call site:

> **6. A deliverable is not done until something calls it — and the call chain is traced to the user-visible end.**
> Name the production call site in the Definition of Done, then follow the value to where a user sees it. For anything the user exports, downloads, or sends onward, the acceptance test asserts on that artefact, not on the layer that produces it.

Four sprints, four instances of build-then-don't-connect. This is the first one where the connection was made and the *last hop* was the gap — which is progress, and also the reason to write the rule with the last hop in it.
