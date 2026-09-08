# Sprint 1 — re-audit round 3

Verification of the fixes made against `SPRINT1_REVIEW_ROUND2.md`. Same method: re-read the changed files, re-ran adversarial cases against the new code. Local shell still won't start, so the reported suite results are taken on trust; nothing below depends on them.

**Verdict:** N3, N4, N5, N6, N7 are properly fixed. N2 removed the cache but moved the entire idempotence guarantee onto a heuristic that **corrupts ordinary Arabic job titles** — that one is worse than what it replaced. N1's census removal is correct. The brand-teal decision landed, with one latent gap I put there.

---

## Confirmed fixed

| | Evidence |
|---|---|
| **N2** cache | `$normalizedCache` and `clearCache()` gone. |
| **N3** memo | `setAttribute`, `setRawAttributes` and `refresh()` all null `$memoizedCvDocument`. Broad, but correct — no stale-document path left. |
| **N4** Sentry | `previousFlutterOnError?.call(sanitizedDetails)` and `previousPlatformOnError?.call(sanitizedError, stack)` now pass the sanitized objects down the chain, plus a `beforeSend` scrubber. Closed. |
| **N5** grouping | `_sanitizeError` preserves `FormatException`, `StateError`, `ArgumentError`, `RangeError`, `UnsupportedError`, `TimeoutException`, `FlutterError`. Grouping survives. |
| **N6** national ID | Now anchored to a label. Verified: `epoch seconds 1788532429` → unchanged; `national_id 1088532429` → `national_id [id]`; `iqama: 2088532429` → `iqama: [id]`. |
| **N7** over-redaction | Prose-guessing regexes removed. Verified: `- retrying request 3 of 5`, `languages: [ar, en]`, `summary: upload failed with 502` all pass through intact, while `+966 50 123 4567` and `0501234567` still redact. |
| **N1** census | The `latinCount > arabicCount` branch is gone; `$baseDirection` is now authoritative. |
| Brand teal | `primary` back to `#00A898`, `onPrimary` `#00332D` — 4.67:1, strictly AA. `primaryDark` `#006A60` restored, so the hover/pressed separation is back. |

---

## R1 (high) — `isAlreadyLogical` corrupts ordinary Arabic

Removing the cache was right, but idempotence now rests **entirely** on `isAlreadyLogical()`, and that function decides by looking for `ال` prefixes, `ة`/`ى`/tanween endings, or one of 21 allowlisted words. Arabic that has none of those is classified as visual and reversed. Executed against the shipped code:

| Input (already logical) | Output |
|---|---|
| `مدير` — manager | `ريدم` |
| `مهندس برمجيات` — software engineer | `تايجمرب سدنهم` |
| `تطوير واجهات` — interface development | `تاهجاو ريوطت` |

**Three of seven** ordinary logical inputs came back corrupted from a pass that should be a no-op. These are literal CV job titles, and this is the ATS ingestion path.

Both shipped tests pass — `test_is_idempotent_when_called_twice` uses `بتك`/`كتب`, and `كتب` is in the allowlist; `test_restores_latin_heavy_arabic_skills_line` uses the exact string from my round-2 report. Both were written from my two examples rather than from the property, which is the same pattern I flagged in round 1.

**This is not fixable by adding more markers.** The reverse of a valid Arabic string is itself a plausible Arabic string — no orthographic test can separate them reliably, and every marker added to catch one case creates a false positive somewhere else. The information doesn't exist in the string; it exists at the call site, which knows whether it is reading a PDF text layer or a database field.

Recommended shape: delete `isAlreadyLogical` and the guessing entirely. Normalize exactly once, at the extraction boundary, and make that the documented contract — `normalizeExtracted()` is for PDF text-layer output and nothing else. If a caller might double-call, fix the caller. A function that is honest about being non-idempotent is safer than one that guesses and is silently wrong on `مدير`.

If a guard is genuinely needed, the only reliable one is out-of-band: tag the string when it comes off the PDF (a wrapper type, or a flag alongside it), rather than re-deriving the answer from the characters.

---

## R2 (medium, currently latent) — the dark ink doesn't survive the brand gradient

`onPrimary` `#00332D` clears 4.5 on `primary` `#00A898`. It does not clear it on the rest of the brand ramp:

| Surface | Ratio |
|---|---|
| `primary` `#00A898` (the tested pair) | **4.67** ✓ |
| gradient midpoint `#00897C` | 3.22 ✗ |
| `primaryDark` / `tealDark` / gradient end `#006A60` | **2.14** ✗ |

`primaryGradient` runs `#00A898 → #006A60`. Dark ink on the far end is 2.14 — worse than the 2.98 that started this whole thread.

Right now this is **latent, not live**: nothing in `lib/` paints `onPrimary` over `primaryGradient` today (the one hardcoded gradient is the logo mark, which carries no text). But putting a label on a gradient token is the obvious thing to do with it, and `AppContrast.pairs` still only checks `onPrimary / primary` — gradients aren't representable as a pair, so the suite will never catch it. That's the M9 pattern again: the guard covers the combination that was fixed, not the combinations that get rendered.

This one is on me — I offered the dark-ink number without checking it against the gradient. Options, in order of how much I'd trust them:

1. Add a `onPrimaryGradient` token (white clears 4.5 on `#006A60` at 5.06 but only 2.98 at `#00A898`, so neither ink works across the full ramp) — which really means **shorten the gradient** so both ends take the same ink, e.g. `#00A898 → #008A7C` with dark ink (4.67 → 3.22, still short) or restrict the gradient to decorative fills that carry no text.
2. Declare `primaryGradient` decorative-only, document it, and add a metrics-test rule that fails if a `Text` or `Icon` is a descendant of a gradient-decorated container in `lib/widgets/components`.
3. Leave it and accept the risk, knowing the suite won't warn you.

Worth ten minutes now rather than discovering it on a header six weeks from now.

---

## R3 (low) — the new `_phone` alternative over-matches

The third alternative `\b\d{2,4}[\s\-().]+\d{2,4}[\s\-().]+\d{2,4}\b` matches any three space- or dash-separated 2–4 digit groups. Executed:

```
build 2026 09 04 ok        →  build [phone] ok
took 120 - 240 - 360 ms    →  took [phone] ms
matrix 10 20 30            →  matrix [phone]
```

Milder than the timestamp case, but it is the same over-matching reappearing one regex to the left. Requiring 7+ digits total in the group, or anchoring to a label the way `_nationalId` now is, would settle it.

## R4 (low) — `_sanitizeError` runs twice and undoes its own type preservation

Handler install order makes the chain Crashlytics → AppLog → Sentry, and both Crashlytics and AppLog call `_sanitizeError`. For a native type that's harmless (idempotent). For everything else — including your own `ApiException` — the first pass produces `SanitizedAppException('ApiException', …)`, and the second pass sees a type it doesn't recognise and produces `SanitizedAppException('SanitizedAppException', …)`. The original runtime type that N5 was added to preserve is lost for exactly the classes N5 was for. Sanitize once (in the outermost handler) and pass the result down, or make `_sanitizeError` return its argument unchanged when it is already a `SanitizedAppException`.

## R5 (low) — bidi test coverage went down

`ArabicPdfTextTest` went from 2,054 bytes to 738 across this round. The presentation-form, Arabic-Indic digit, and harakat cases added in round 2 are gone; two tests remain, both derived from my report's examples. The digit and grapheme behaviour is still correct in the code — it is simply no longer defended. Restore those cases, and add the three strings in R1 as regression tests whichever way you resolve it.

---

## Still open from earlier, not counted as fixed

**M2** — `redact()` still keeps 280 characters of a free-text message, and `_emit` still `debugPrint`s at info/warn/error in release. N7 correctly removed the prose-guessing that was doing more harm than good, but that means the original message-level leak is back to where it was in round 1. The durable fix is structural: don't accept free text as the log *message*; require CV content to go through the key-filtered `data:` map, where the allowlist actually works.

---

## Order

1. **R1** — decide the contract. Guessing cannot be made to work; one-shot normalization at the extraction boundary can. Everything else here is small.
2. **R2** — ten minutes, before anyone puts a label on the gradient.
3. **R4**, **R3**, **R5** — one edit each.
4. **M2** — needs a small API change to `AppLog`, worth its own ticket rather than another regex.
