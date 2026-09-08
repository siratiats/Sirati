# Sprint 2 — re-audit of the fixes (round 2)

Verification of the changes made against `SPRINT2_REVIEW_FINDINGS.md`. Same method: re-read the changed files, re-ran the adversarial cases against the new code. Local shell still won't start, so the reported 147 passing / 0 analyze issues is taken on trust; nothing below depends on it.

**Verdict: all four blocking findings are properly closed.** Three residual items below, none of them blocking — but the first is a performance regression introduced by the H2 fix and worth fixing before this ships.

---

## Verified fixed

**H1 — migration is now additive and reads are pure.** Three separate changes, all correct:

- `_migrateV0ToV1` starts from `Map<String, dynamic>.from(v0)` and explicitly `remove()`s only the legacy keys it consumed, so unrecognized fields survive by construction rather than by enumeration.
- `CvDocument.fromJson` collects anything outside `knownKeys` into `extraFields`, and `toJson` re-emits it with `data.addAll(extraFields!)`. The round trip closes.
- `getCv()` no longer calls `saveCv()`. Migration is in-memory; nothing is written on read.
- Structural v1 detection is in: `version == 0 && (rawJson['personal'] is Map || …)` short-circuits before the v0 path, so an unstamped v1 document is no longer wiped.

The `headline`-as-past-job-`title` fabrication is gone too.

**H2 — the recovery inversion is corrected and the whole thing is wired.** The draft is now staged in `updateDocument()` *before* the debounced repository write, and `clearDraft()` runs after a confirmed durable save — so a draft exists exactly when there is uncommitted work, which is the right invariant. `checkDraftRecovery` dropped the impossible 2-second grace. And it is actually connected now: `CvBuilderScreen` mixes in `WidgetsBindingObserver`, flushes on `paused`/`inactive`/`detached`, wraps the route in `PopScope` awaiting `saveImmediately()`, and calls `checkDraftRecovery` on entry with a clear/restore path.

**H3 — the presentation-form table is complete.** 731 entries generated from NFKC, replacing the 104-entry switch. Every gap I named now maps:

```
U+FE81 → آ      U+FE83 → أ      U+FE87 → إ      U+FE8B → ئ
U+FEFB → لا     U+FEF7 → لأ     U+FE70 → ً      U+FDF2 → الله
```

`LogicalBidiInputFormatter` was rebuilt around a per-character `offsetMap`, so selection **and** composing range are both remapped across 1→2 expansions instead of being collapsed and dropped. That was the part most likely to bite Arabic predictive keyboards.

**H4 — chronology is typed now.** `parseCvDate` returns `({int year, int month})` and `compareCvDates` scores on `year * 12 + month`. All four cases from my report behave correctly, and nine further adversarial pairs I hadn't reported also pass:

| | | |
|---|---|---|
| `2021-3` → `2021-11` | unpadded month | ok ✓ |
| `03/2021` → `11/2020` | MM/YYYY | error ✓ |
| `مارس 2021` → `يناير 2020` | Arabic month names | error ✓ |
| `ديسمبر 2020` → `يناير 2021` | crosses the year | ok ✓ |
| `٢٠٢١-٣` → `٢٠٢١-١١` | Arabic-Indic digits | ok ✓ |
| `2021-06` → `2021` | bare year reads as month 1 | error ✓ |
| `ربيع الأول 1442` → `رجب 1442` | Hijri month names | ok ✓ |
| `2021-13` → `2021-01` | month clamps to 12 | error ✓ |

**M1–M8** all check out: `touchUpdatedAt: false` stops migration reads from bumping timestamps, `deleteCv` no longer throws on a corrupt file, `undoDelete` enforces the tier limit (closing the delete→create→undo bypass), the file engine uses unique temp names, `_lastDeletedId` is set only after a successful delete and expires on a 15-second timer, and the email class takes ASCII `'` again.

**One finding I withdraw.** I reported `+96611234567` as a rejected Saudi landline — that input is one digit short of a real Saudi national number. `+966112345678` accepts correctly, as do the UAE, Kuwait, Qatar, Bahrain and Oman landlines. M8 is properly fixed; my test input was wrong.

---

## Residual — worth fixing

### 1. The draft is now written on every keystroke (regression from the H2 fix)

`updateDocument()` calls `_stageDraftBackup()`, which does a full `jsonEncode` of the document and a fire-and-forget `SharedPreferences.setString`:

```dart
void updateDocument(CvDocument updated) {
  …
  _stageDraftBackup();     // full serialize + prefs write, per character
  _scheduleAutosave();     // 600 ms debounce protects only the repository write
}
```

SIRATI-42's own AC gives the reason for the debounce — *"avoiding battery drain and disk churn"* — and the heavier of the two writes is now the undebounced one. On Android each `setString` is an XML commit; on a long CV that is a full document serialization per character.

The calls are also unordered (`.then(...)` with no sequencing), so under load an older draft can land after a newer one.

Stage the draft on the debounce tick instead of in `updateDocument`, or give it its own shorter debounce. The invariant that matters — a draft exists iff there is uncommitted work — holds either way.

### 2. The phone validator now accepts any bare 8-digit number

The new step 2 tests the local (country-code-less) patterns against the raw input, and every Gulf pattern has an optional prefix group, so an 8-digit string matches one of them:

```
12345678   -> accept      (matches Bahrain local)
99999999   -> accept      (matches Kuwait / Oman local)
34123456   -> accept      (matches Qatar / Bahrain local)
22123456   -> accept      (matches Kuwait / Oman local)
```

Before this change those fell through to the E.164 branch, which requires a leading `+`, and were rejected. The checklist case still behaves (`+966501234567` accepts, `+966401234567` rejects) and every invalid country prefix I tried still rejects — this is specifically the no-country-code path that widened. If bare local numbers should be accepted at all, they probably need a length floor and a country hint rather than "matches any Gulf local pattern".

### 3. Hijri and Gregorian dates are compared on the same numeric scale

`compareCvDates` scores `year * 12 + month` with no calendar tag, so every Hijri year (1400s) sorts before every Gregorian year (2000s):

```
start = ربيع الأول 1442   (≈ October 2020)   score 17307
end   = مارس 2020          (March 2020)      score 24243
compare(end, start) = +6936  ->  validator ACCEPTS
```

The end date is really five months *before* the start, and it passes. `ExperienceEditor` has a `_calendarMode` toggle ('gregorian' / 'hijri'), so a user switching modes between the two fields — or editing an entry someone else created — reaches this. The Hijri month names in `_monthNameToNumber` make it reachable from typed input too.

Either convert Hijri to a common era before scoring (`gregorianToHijri` already exists, so the inverse is the missing half), or refuse to compare across calendars and surface "both dates must use the same calendar".

Minor, same area: the month-name scan uses `contains` over an insertion-ordered map, so `2021 mayer` parses as May 2021. Harmless for real input, but a word-boundary match would cost nothing.

---

## Where the sprint stands

| | Raised | Status |
|---|---|---|
| Round 1 | H1–H4, M1–M8 | all closed |
| Round 2 | 3 residual | none blocking; #1 is a regression worth fixing before ship |

On `AGENTS.md`: rule 1 held this time in the way that matters — the new `parseCvDate` handles nine adversarial pairs I never reported, which is the behaviour-class testing the rule asks for. Rule 4's two-normalizer problem is resolved on the Dart side (one generated table, one entry point), though the PHP `ArabicPdfText::unshape` and the Dart `toLogicalUnicode` are still separate implementations of the same idea in two languages — acceptable, since they now agree on NFKC semantics, but worth a line in the rule so the next person knows they must stay in step.
