# Sprint 2 — code review findings

Review of the 14 tickets in `SPRINT2_IN_REVIEW.md`, judged against their acceptance criteria and `AGENTS.md`. Findings marked **verified** were reproduced by executing the shipped logic, not inferred from reading.

**Coverage note.** The local Linux workspace still fails to start on this machine, so I could not run `flutter analyze` / `flutter test` myself — the reported 141 passing is taken on trust and nothing below depends on it. I read the persistence, migration, multi-CV, bidi, date, builder-controller and validator code closely; `gallery_screen.dart`, `cv_builder_screen.dart` and `skills_languages_editor.dart` got a lighter pass.

**Verdict: not ready to sign off.** Four blocking findings. The most serious is a data-loss path that triggers on first open of any pre-Sprint-2 document, with no way back.

---

## H1 — Migration is lossy, and the lossy result is written over the original on read

**SIRATI-33 + SIRATI-34** · `cv_schema_migrator.dart`, `cv_repository.dart`

Three individually-defensible decisions combine into an irreversible one:

1. `_migrateV0ToV1` builds a **fresh map from an allowlist**. It reads ~16 known keys and copies exactly four through (`id`, `title`, `created_at`, `updated_at`). Everything else is dropped.
2. `custom_sections` is hardcoded to `[]` — there is no unknown-field capture anywhere in the file. The AC says *"Zero data loss: Unrecognized scalar fields mapped into custom sections or personal metadata."* That mapping does not exist.
3. `LocalCvRepository.getCv()` runs the migrator on every read and, on `wasMigrated`, immediately calls `saveCv(doc)` — **persisting the reduced document over the original**.

So the original is gone from disk the first time the CV list is opened. Verified against a realistic legacy payload:

```
fields present before : 18
fields carried after  : 13
silently dropped      : job_description_input, generated_markdown, ai_output,
                        criteria, score_total, grade, idempotency_key,
                        form_payload, template_slug, photo_url
custom_sections after migration: []
```

**And the version sniff makes it worse.** Anything without a `schema_version` key is treated as v0, including a v1 document that lost its stamp (a partial write, a hand-edited file, an older export). Running a v1-shaped map through `_migrateV0ToV1`:

```
personal.full_name  {ar: عبدالله, en: ''}   ->  {ar: '', en: ''}          wiped
summary             {ar: نبذة…, en: ''}    ->  {ar: "{ar: نبذة…, en: }"}  corrupted
experience/skills                          ->  preserved
```

The summary is not merely lost — `v0['summary']?.toString()` on a Dart `Map` yields the literal text `{ar: …, en: }`, which then gets stored as the Arabic string. Then it is persisted.

**Fix, in order of importance:**

- Stop auto-persisting inside `getCv()`. Migrate in memory, keep the original bytes until the user's next real save, or write the migrated copy to a **new** key and keep the v0 file as `<id>.v0.bak`.
- Make the migration additive: copy the whole input map, then overwrite the keys you understand, so unknown fields survive by default. That is the only shape that satisfies "zero data loss".
- Detect v1 structurally (`json['personal'] is Map`), not just by the absence of a stamp, and refuse to downgrade-migrate a document that already looks v1.

---

## H2 — Crash draft recovery cannot fire, and nothing calls it

**SIRATI-42** · `cv_builder_controller.dart`

The draft is written **inside** `saveImmediately()`, immediately *after* `_repository.saveCv()` succeeds:

```dart
await _repository.saveCv(_document);
// Also cache draft backup in SharedPreferences for crash/kill recovery
await prefs.setString('$draftPrefix${_document.id}', jsonEncode(_document.toJson()));
```

`checkDraftRecovery` then returns the draft only when it is newer than the saved document by at least two seconds:

```dart
if (draftDoc.updatedAt!.isAfter(lastSavedAt.add(const Duration(seconds: 2))))
```

The draft's `updatedAt` is set by `updateDocument`, so it is always **older** than the repository write that precedes it. The condition cannot be true. The branch is dead.

The mechanism is also inverted: a draft that only exists after a successful save is by definition not unsaved work. To recover a crash you must write the draft *before* — or independently of — the repository write.

And it is not wired up at all. Grepping `lib/` outside the controller:

```
clearDraft          — 0 call sites
checkDraftRecovery  — 0 call sites
saveImmediately     — 0 call sites
```

So: no recovery dialog on restart (the AC), no flush on screen pop or backgrounding (the docstring on `saveImmediately` claims it is "safe to call on app backgrounding, screen pop, or force quit" — nothing calls it there), and no `WidgetsBindingObserver` anywhere in the builder. Because `dispose()` only cancels the debounce timer without flushing, **navigating back within 600 ms of the last keystroke silently loses that edit.**

Drafts are also never cleared, so one full CV JSON per CV accumulates in `SharedPreferences` indefinitely — and on Android that store is read into memory at startup.

---

## H3 — The presentation-form normalizer misses hamza and the lam-alef ligature

**SIRATI-27** · `bidi_text_utils.dart`

`toLogicalUnicode()` gates on `0xFB50..0xFDFF || 0xFE70..0xFEFF`, then defers to a hand-written switch that starts at `0xFE8D` (plain Alef). Measured against the range it claims:

```
range the code claims to normalize : 832 code points
code points the switch maps        : 104
unmapped inside the claimed range  : 728
```

What falls through unchanged:

| | |
|---|---|
| `U+FE81` آ Alef with Madda | آدم |
| `U+FE83` أ Alef w/ Hamza above | أحمد |
| `U+FE87` إ Alef w/ Hamza below | إبراهيم |
| `U+FE8B` ئ Yeh with Hamza | رئيس |
| `U+FEFB` ﻻ **lam-alef — the commonest ligature in Arabic** | |
| `U+FE70..FE7F` tanween marks | |
| `U+FB50..FDFF` — all 688 Forms-A code points | the range check admits them; the switch has zero cases |

Hamzated alef opens a large share of Arabic given names, so this is not an edge case. The equivalent PHP normalizer (`ArabicPdfText::unshape`) handles all of it by delegating to NFKC — which is also the right answer here. Dart has no ICU `Normalizer`, but the `characters` package or a generated table from the Unicode decomposition data gets the same result without a hand-maintained switch that is 87% incomplete.

Related: `LogicalBidiInputFormatter` returns `TextSelection.collapsed(...)` and drops `composing`. Collapsing kills any active selection, and dropping the composing range mid-composition is a known cause of duplicated or garbled input with predictive Arabic keyboards. Its cursor arithmetic (`text.length - normalized.length` applied as a global offset) is also vacuous today — every mapping is 1:1, so the delta is always zero — and becomes wrong the moment lam-alef decomposition is added, since that is a 1→2 expansion.

---

## H4 — The chronological invariant is a lexicographic string compare

**SIRATI-38** · `experience_education_editor.dart`

```dart
if (e.compareTo(s) < 0) return 'تاريخ الانتهاء لا يمكن أن يسبق تاريخ البدء';
```

`startDate` / `endDate` are `String`, with no format enforced anywhere — `_addEducation` seeds them as bare years (`"2021"`). Verified:

| start | end | | result |
|---|---|---|---|
| `2021-3` | `2021-11` | unpadded month | **false error** |
| `03/2021` | `11/2020` | MM/YYYY — natural to type | **accepted** |
| `مارس 2021` | `يناير 2020` | Arabic month names | **accepted** |
| `2019` | `2021` | plain years | ok |
| `2021-03` | `2021-11` | zero-padded | ok |

The Arabic-month case matters because SIRATI-28 exists specifically to render `يناير 2021`-style strings — the sprint produces exactly the format this validator cannot compare. `_sortExperienceChronologically` / `_sortEducationChronologically` have the same problem (`bDate.compareTo(aDate)` on the same free strings).

Store dates as a typed value (`DateTime`, or a `{year, month}` record) and format at the edge. If they must stay strings, enforce zero-padded `YYYY-MM` at input and validate the format before comparing.

---

## Medium

**M1 — `listCvs()` rewrites every legacy CV's `updatedAt`, destroying real ordering.** `listCvs` calls `getCv` per id; `getCv` persists on migrate; `saveCv` unconditionally stamps `updatedAt = DateTime.now()`. So the first list of legacy documents sets them all to ~now, and the list is sorted by `updatedAt` descending. The user's actual last-edited order is gone. `saveCv` needs a way to persist without touching the timestamp.

**M2 — `listCvs()` fully parses every stored CV to build a list**, and `createCv`/`duplicateCv` call it once for the limit check and again via `load()`. Creating one CV parses every other CV twice, plus any migration writes those reads trigger. This is the list screen's hot path; a metadata index file is the usual fix.

**M3 — a corrupt document cannot be deleted.** `getCv` catches, logs, then `rethrow`s. `listCvs` wraps it in try/catch so the list survives, but `deleteCv` calls `getCv` first to populate the undo cache — so the one operation a user needs for a broken file is the one that throws. `deleteCv` should not need to read the document.

**M4 — the free-tier limit is bypassable via undo.** The check lives only in `CvManageController.createCv`/`duplicateCv`; `LocalCvRepository.saveCv` and `restoreCv` have none. At the 3-CV limit: delete one (→2), create one (→3), undo the delete (→4).

**M5 — "atomic write" is decorative in two of the three engines.** `MemoryCvStorageEngine.writeAtomic` is `_staging[id] = content; _store[id] = _staging.remove(id)!;` — two statements guarding no failure mode. `SharedPreferencesCvStorageEngine` writes `<id>_tmp`, then `<id>`, then removes the temp — but **nothing ever reads `_tmp` during recovery**, so a crash mid-sequence just leaks an orphan key holding a full CV. It also doubles the write cost for zero benefit, since `setString` is already atomic at that layer.

`FileCvStorageEngine` is the real one, with two gaps: there is no `fsync` of the *directory* after `rename`, so the rename may not survive power loss (the file-corruption guarantee holds; the durability one in the docstring does not); and every write for a given id shares one `<id>.tmp` path with no serialisation, so two overlapping `writeAtomic` calls — entirely plausible with a 600 ms debounce plus a migration-triggered save — can have one rename fail or land on a partially rewritten temp. Use a unique temp name per write and a per-id lock.

**M6 — soft delete is RAM-only and has no grace period, contrary to both docstring and handoff.** The handoff says "marks `isDeleted = true`, stores undo timer, and fully purges upon commit". The code hard-deletes from the engine and keeps the document in a `Map` on the repository instance. There is no `isDeleted` flag, no timer, and no purge — `_softDeleted` grows for the life of the process, and killing the app during the undo window loses the CV permanently. `CvManageController.deleteCv` also sets `_lastDeletedId` *before* awaiting the delete, so a failed delete still reports `canUndoDelete == true`.

**M7 — the email validator rejects ASCII apostrophes and accepts curly ones.** The character class contains `’` (U+2019) where `'` (U+0027) was intended:

```
o'brien@example.com    -> REJECT
o’brien@example.com    -> accept
d'angelo@shell.com.sa  -> REJECT
```

**M8 — Gulf landlines are rejected everywhere.** `+966 11 234 5678` (Riyadh), `+971 4 321 4321` (Dubai) and the Omani/Bahraini equivalents all fail, because the country branches require a mobile prefix and the generic E.164 fallback is only reached for non-Gulf numbers. That matches the AC as written ("Gulf **mobile** validation"), so this is a product question rather than a defect — but the field is a CV contact number, and a candidate who lists a landline currently cannot save. The checklist cases both behave correctly: `+966501234567` accepts, `+966401234567` rejects.

**M9 — SIRATI-22's contrast half is Sprint 1 work reported as Sprint 2 delivery.** The ticket says "Implemented strict ratio checks in `test/app_contrast_test.dart` with zero offset tolerance". That file's on-disk mtime is `1788433713977`, roughly 47 hours before every other Sprint 2 file and identical to what it was during the Sprint 1 round-3 review. The zero-slack property is real, but it came from removing `+ 0.05` from `ContrastPair.passes` in Sprint 1. The hardcoded-color lint in `app_metrics_test.dart` *is* new this sprint (scope extended to `lib/features`, plus a documented `// ignore: hardcoded_color -- reason` escape hatch, currently unused) — that part is fine.

---

## Low

- **The bidi utilities are almost entirely unwired.** Only `LogicalBidiInputFormatter` is referenced from `lib/` (one line in `personal_details_editor.dart`). `isolateLtr`, `isolateRtl`, `isolateEmbeddedLtrInArabic`, `sanitizeBidiControls` and `detectBaseDirection` have no production call sites. SIRATI-27's AC — "LTR embedding isolation for technical terms, URLs, emails, and phone numbers in RTL context" — is library-only, the same shape as `normalizeExtracted` in Sprint 1. Worth recording on the ticket rather than as shipped behaviour.
- **The handoff and the code disagree on which control characters are used.** The AC and summary both say `‪...‬` (LRE/PDF); the code uses `⁦...⁩` (LRI/PDI). The code is the better choice — isolates over embeddings — but the `lrm`, `rlm`, `lre` and `pdf` constants are declared and never used.
- **`updateDocument` runs a full `copyWith` of the document tree and `notifyListeners()` on every keystroke**, stamping a new `updatedAt` each time. For a long CV in a text-heavy editor that is a deep copy and a full rebuild per character.
- **`_migrateV0ToV1` fabricates data**: when converting `experience_input`, it sets the entry's `title` to the candidate's `headline`, asserting that the target job title was the title held at that job. Nearby, the `company` mapping is a ternary whose two branches are identical (`{'ar':'','en':''}`).
- **`detectBaseDirection` treats only `[A-Za-z]` as strong LTR.** In practice almost every Latin string has an ASCII letter early enough that this doesn't bite (I checked "École", "Œuvre", "Ñandú" — all resolve LTR correctly); a Greek- or Cyrillic-only institution name would fall through to the RTL fallback. Marginal, noted for completeness.
- **`CvSchemaMigrator`'s failure path reports the wrong version range** — the `catch` reads `currentV` after it has advanced and reports `toVersion: currentV + 1`. Harmless with one migration step, wrong as soon as there are two.
- **`CvMetadata.fromDocument` mints an id** (`cv_${millis}`) when the document has none, so metadata can carry an id that is not the storage key it was read from. `listCvs` should use the id it iterated.

---

## Where I'd start

1. **H1** — this is the only finding that destroys user data, and it does so on first open with no undo. Everything else can ship late; this cannot ship at all. The two-line version of the fix is: don't save inside `getCv`, and build the v1 map by copying the input rather than by allowlist.
2. **H2** — the recovery half of SIRATI-42 is not implemented, only scaffolded. Either wire it (write the draft on the debounce tick, check it on builder entry, clear it after a confirmed save, flush on `dispose` and on `AppLifecycleState.paused`) or move it out of Done.
3. **H4** then **H3** — both are correctness bugs in code that is wired up and running.
4. **M1/M3/M5** together — they are all `cv_repository.dart` and they share one afternoon.

## On AGENTS.md

Rules 2 and 3 held: the contrast assertions carry no slack, and the lint guard is a lexical token scan rather than an attempt to infer a widget tree, which is the legitimate use.

Rule 4 is the one to look at. It says normalization must happen "exactly once at the ingestion/extraction boundary" — but there are now two normalizers with different behaviour and different coverage (`ArabicPdfText::unshape` in PHP via NFKC, `BidiTextUtils.toLogicalUnicode` in Dart via a partial hand-written switch), and neither is anchored to a declared boundary. That is how H3 stayed invisible.

Rule 1 mostly held — the tests I read do test classes of input rather than single fixtures. The gap is different this time: the tests assert what the code *does* rather than what the AC *promises*. Nothing in the suite asks whether an unknown field survives migration, whether a draft can actually be recovered, or whether `مارس 2021` compares correctly — which is why all four H findings are green today. Worth adding a rule: **for every AC that uses the words "no data loss", "recovery", or "validates", write the test from the AC sentence, not from the implementation.**
