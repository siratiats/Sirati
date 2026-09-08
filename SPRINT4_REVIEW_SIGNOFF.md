# Sprint 4 — sign-off

Verification of the single open finding from `SPRINT4_REVIEW_ROUND3_VERIFICATION.md`.

**Verdict: signed off.** The export fix is correct at both layers, and the tests assert the criterion rather than the mechanism. One residual, named below, is a data question rather than a code defect.

---

## The English export is fixed, at both layers

`SkillStorageKeys` and `LanguageLevelStorageKeys` each gained an `english` map and an `englishFor(stored)` lookup, and all three write sites now fill both variants:

```dart
level: LocalizedText(ar: level, en: SkillStorageKeys.englishFor(level)),
category: LocalizedText(ar: category, en: SkillStorageKeys.englishFor(category)),
```

The storage keys are untouched, so the schema decision from round 3 stands and no migration is needed for the key itself. The docblock names the reason the map is not driven off `AppLocalizations`:

> English export strings for [stored] schema keys. Independent of UI locale so an Arabic-session write still produces a bilingual document.

That is the right call and not an obvious one — a candidate editing in Arabic still gets an exportable English CV, which would not be true if the English string came from the ambient locale.

## The tests assert the acceptance criterion

Both layers are covered, and neither test is calibrated to my report.

`CvDocumentTest::test_english_resolve_of_bilingual_proficiency_has_no_arabic` builds a document with all five language levels and asserts on `resolve('en')`:

```php
$this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $resolved->languages);
```

`CvPdfRenderingTest::test_english_render_has_no_arabic_outside_candidate_typed_fields` goes further, and this is the one I would keep:

```php
$remainder = $html;
foreach ($candidateArabic as $typed) { $remainder = str_replace($typed, '', $remainder); }
$this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $remainder, …);
```

Strip what the candidate typed in Arabic, then assert **zero** Arabic script anywhere in the rendered document. That is the criterion I asked for, written as the criterion: it catches a leak in any field, in any template, including ones that do not exist yet — not just the five level strings that prompted it. Rendering through an RTL template (`classic_rtl`) while the export language is English is the right adversarial choice too, since that is where a direction-driven label would leak.

The per-level `assertStringContainsString($en)` / `assertStringNotContainsString($ar)` pairs are the useful specific half; the regex is the invariant. Both, in the right proportion.

---

## One residual — documents already saved are not backfilled

`cv_schema_migrator.dart` is unchanged. A skill or language written before this change is stored as `{ar: 'متقدم', en: ''}`, and both `resolve()` implementations fall back to `ar` when `en` is empty. So an English export of an **existing** document still prints the Arabic level; only documents written from here on are bilingual.

The new tests do not surface this, because both construct their fixtures with `en` already populated — they verify the fix, not the population.

Whether this matters is a question about your data, not about the code:

- **If there are no stored CVs to speak of yet** — the product is pre-launch, or the local documents are test data — this is nothing. Close it.
- **If there are**, it is a genuinely safe migration: fill an empty `en` from a known `ar` key via `SkillStorageKeys.english` / `LanguageLevelStorageKeys.english`, leave every unrecognised value alone. Ten lines in the migrator, no schema change, idempotent.

I would not hold the sprint for it. I would put it on the migrator ticket while the mapping is fresh in someone's head, because in six months the connection between `cv_schema_migrator.dart` and `skill_storage_keys.dart` is not obvious from either file.

## Carried forward, unchanged, none blocking

Repeating these once so they are on a list somewhere rather than only in a review document:

- `_ArabicLiteralVisitor` overrides `visitSimpleStringLiteral` only — a `StringInterpolation` containing Arabic is not seen. Zero today; one more override closes it.
- The English-catalog test pins six keys; a loop over the loaded catalog covers all 220 and cannot go stale.
- `ci_workflow_test.dart` asserts `'flutter test --tags golden'` and `'runs-on: windows-latest'` as independent substrings of the whole file, so nothing ties the runner OS to the golden job.
- `test/golden/failures/` is committed — comparator output, belongs in `.gitignore` beside `lib/l10n/generated/`.
- `analysisCount` still has no call site; 15 catalog keys are unreferenced.
- `l10n_adoption_test.dart` imports the legacy `models/` and `theme/` barrels rather than `shared/`; the layering test walks `lib/` only.

---

## Sprint 4 final status

| | Raised | Status |
|---|---|---|
| Round 1 | H1, H2, M1, M2, 3 lows | all closed |
| Round 2 | H1 remainder ×2, M1 README, M2 relative imports | all closed |
| Round 3 | English export resolves to Arabic | **closed** |
| Sign-off | legacy documents not backfilled | open — data question, not a defect |

**Sprint 4 is closed.**

---

## What this sprint is worth keeping

Four sprints in, the pattern that has generated almost every finding is the same one: something is built, tested in isolation, and never connected. `normalizeExtracted` had no caller. The bidi utilities had one. `previewPdfResponse` had no route. `AppLocalizations` had no consumer, then had one but stopped at the editor.

Sprint 4 is the first time the chain was followed all the way to the artefact a user actually receives — and it took three rounds to get there, with each round closing one more hop. That is the argument for the rule, in its final form:

> **6. A deliverable is not done until something calls it — and the call chain is traced to the user-visible end.**
> Name the production call site in the Definition of Done, then follow the value to where a user sees it. For anything the user exports, downloads, or sends onward, the acceptance test asserts on that artefact, not on the layer that produces it.

The second thing worth keeping is what happened to the storage-key problem. I proposed stable ASCII keys plus a migration; the implementation declared the *existing* Arabic strings as the schema and got the same separation with no migration and no risk to stored data. That is the better answer, and it came from someone reading the constraint rather than the recommendation. Reviews are more useful when they are treated that way — the finding is the fact, the suggested fix is just one way out of it.
