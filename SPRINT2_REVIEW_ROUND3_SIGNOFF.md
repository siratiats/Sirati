# Sprint 2 — round 3 close-out

Verification of the three residuals from `SPRINT2_REVIEW_ROUND2_VERIFICATION.md`. Same method throughout: re-read the changed code, re-ran the adversarial cases plus new ones the fixes invited.

**Verdict: I'd sign this off.** All three residuals are properly closed, and the Hijri work is the strongest thing in the sprint.

---

## Verified fixed

**Residual 1 — draft churn.** `updateDocument()` now calls `_scheduleDraftBackup()` (200 ms debounce) instead of serializing inline, `_draftSequence` drops stale out-of-order writes, and `_isDirty` gates the write so a clean document doesn't get re-staged. Both timers are cancelled in `dispose()`. The per-keystroke `jsonEncode` + `SharedPreferences.setString` is gone.

**Residual 2 — bare 8-digit numbers.** The local patterns now require the national trunk `0` and the country patterns dropped their optional prefix group. Re-ran the full matrix — every case behaves, with no regressions:

```
12345678 / 99999999 / 34123456 / 22123456   reject     (were accepted)
+966501234567 accept    +966401234567 reject           (checklist, unchanged)
0512345678 / 0112345678 / 043214321         accept     (locals still work)
+966112345678 / +97143214321 / +96522123456 accept
+97444123456 / +97317123456 / +96824123456  accept
+12025550123                                accept     (E.164 intact)
05123456789 / +9715012345 / 96650123456     reject     (length edges)
```

**Residual 3 — cross-calendar comparison.** This one is done properly. `hijriToGregorian` is an **exact inverse** of `gregorianToHijri` — I round-tripped all 612 year-months from 1400–1450 AH with zero mismatches — and the anchor dates land on the real Umm al-Qura values:

| | converts to | actual |
|---|---|---|
| Rabiʿ al-Awwal 1442 | 2020-10-18 | 18 Oct 2020 ✓ |
| Rajab 1442 | 2021-02-13 | 13 Feb 2021 ✓ |
| Ramadan 1445 | 2024-03-11 | 11 Mar 2024 ✓ |

So the comparison now works across calendars:

```
start ربيع الأول 1442 -> 2020-10   score 24250
end   مارس 2020        -> 2020-03   score 24243
compare(end, start) = -7  -> correctly REJECTED
homogeneous 1442-03 -> 1442-07 = +4 -> still accepted
```

The word-boundary fix for month names is in as well, so `2021 mayer` no longer parses as May.

---

## Two cosmetic notes, no action needed

**`text.contains('ه')` in the Hijri sniff is redundant and can only misfire.** The clause reads:

```dart
final isHijri = _hijriMonthNames.contains(name) || year < 1700
    || text.contains('هـ') || text.contains('ه');
```

`ه` is one of the commonest letters in Arabic, and this is a free-text field. I checked all twelve Gregorian Arabic month names and a handful of realistic date strings — none contain it, so nothing misfires today. But `year < 1700` and the Hijri month-name list already cover every real case, so the bare-`ه` test adds no coverage and only adds a way to be wrong later (a note like `مارس 2021 - الشهر الأول` would trip it). The `هـ` variant is worth keeping; the bare one isn't.

**`saveImmediately()` awaits `_stageDraftBackup()` before the repository write**, so an autosave now does prefs-write → repo-write → prefs-remove, and the draft it writes is almost always identical to the one the 200 ms timer already staged. Harmless at a 600 ms debounce, just redundant I/O.

---

## Sprint 2 status

| | Raised | Status |
|---|---|---|
| Round 1 | H1–H4, M1–M8 | all closed |
| Round 2 | 3 residuals | all closed |
| Round 3 | 2 cosmetic notes | no action needed |

Nothing outstanding. For the record, one finding of mine was withdrawn along the way — the "rejected Saudi landline" in round 1 was my own malformed test input, not a defect.

## Worth carrying into Sprint 3

The pattern that produced all four blocking findings this sprint was different from Sprint 1's, and it is worth naming because the fix is cheap.

Sprint 1 kept shipping tests calibrated to the examples in a review. `AGENTS.md` rule 1 closed that, and it held — the round-1 date parser handled nine adversarial pairs I had never reported, and the round-3 Hijri converter is exact over a 50-year span I chose arbitrarily. That rule is working.

What replaced it: **tests that assert what the code does rather than what the AC promises.** Every one of H1–H4 was green when the sprint was handed over. Nothing in the suite asked whether an unknown field survives migration, whether a draft can actually be recovered, or whether `مارس 2021` compares correctly — because those questions come from the acceptance criteria, not from reading the implementation.

Suggested addition to `AGENTS.md`:

> **5. Test the acceptance criterion, not the implementation.**
> For every AC containing "no data loss", "recovery", "validates", "atomic", or "normalizes", write at least one test directly from that sentence before reading the code that satisfies it. If the AC says unrecognized fields are preserved, the test feeds an unrecognized field through a full round trip and asserts it is still there.

The other thing worth keeping is the shape the Hijri fix took: a real algorithm with an inverse, verified against a range rather than a handful of dates. That is the standard the rest of the codebase should be held to.
