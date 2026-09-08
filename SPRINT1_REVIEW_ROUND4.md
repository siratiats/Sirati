# Sprint 1 — re-audit round 4 (close-out)

Verification of the fixes made against `SPRINT1_REVIEW_ROUND3.md`. Same method as before: re-read the changed files, re-ran the adversarial cases against the new code.

**Verdict: I'd sign this off.** R1 through R5 are all properly closed. Three small observations below, none blocking.

---

## Verified fixed

**R1 — the bidi guessing is gone.** `isAlreadyLogical()` deleted; `normalizeExtracted()` is now a pure one-shot inversion with the contract written into the docblock. Re-ran the full set — it is deterministic and involutive, 8 of 8 exact round-trips:

```
مدير                  -> ريدم                 -> مدير                  ✓
مهندس برمجيات        -> تايجمرب سدنهم        -> مهندس برمجيات        ✓
تطوير واجهات         -> تاهجاو ريوطت         -> تطوير واجهات         ✓
نائب الرئيس التنفيذي -> يذيفنتلا سيئرلا بئان -> نائب الرئيس التنفيذي ✓
```

and the three job titles that round 3 corrupted now restore correctly from visual order. This is the right shape: no heuristic, no hidden state, no way to be silently wrong.

**R5 — coverage restored and then some.** Seven tests now: presentation forms, `™`/`½` preservation, Arabic-Indic *and* Western digits, harakat anchoring by grapheme cluster, the three job titles, the Latin-heavy skills line, and LTR base direction. That's a real regression net rather than two examples lifted from a report.

**R3 — phone precision.** All four false positives are gone and every real format still redacts:

```
build 2026 09 04 ok        -> unchanged        call +966 50 123 4567  -> call [phone]
took 120 - 240 - 360 ms    -> unchanged        mobile 0501234567      -> mobile [phone]
matrix 10 20 30            -> unchanged        phone: (555) 123-4567  -> phone: [phone]
version 1.2.3 build 4567   -> unchanged        جوال: 0512345678       -> جوال: [phone]
```

**R4 — double sanitization.** `if (error is SanitizedAppException) return error;` is in place, so a custom type keeps its original class name through the whole handler chain.

**R2 — the gradient.** `_DashboardActionCard` moved to solid `primaryDark` with white text. Confirmed **6.50:1** — comfortably AA, and it removes the one place the ramp problem could have surfaced.

---

## Three small observations (non-blocking)

**1. `normalizeExtracted()` has no production caller.** Every reference in the repo is a test. The docblock now says "called strictly at the PDF extraction boundary" — but that boundary doesn't exist in the code yet. The function is correct, well-tested and ready; nothing consumes it.

That's a perfectly fine place to land, but it means SIRATI-46's line that "the PHP normalizer is what ATS should use" is still forward-looking. Worth recording as such on the ticket rather than as a shipped ATS path, so whoever wires up ingestion later knows the contract is theirs to honour — nothing enforces single-call at runtime, by design.

**2. The gradient guard bans the token rather than the misuse.** The new test fails if `primaryGradient` is mentioned *anywhere* outside `sirati_colors.dart` and `app_theme.dart` — not just where it would carry text. Combined with the fact that the token is now referenced nowhere in `lib/`, `primaryGradient` is dead code kept alive by `copyWith`/`lerp` plumbing and fenced off by a test.

Either outcome is defensible; the current one just isn't stable. The first developer who wants a decorative gradient hero will hit the failure and delete the test, which takes the reasoning with it. Cleaner to pick one:

- delete `primaryGradient` from both palettes and the copyWith/lerp plumbing, and let the test go with it; or
- relax the guard to "no `Text`/`Icon` in a container decorated with it" so decorative use stays legal and the constraint survives.

The mathematical assertions in the first half of that test are good documentation — keep those either way.

**3. Small number discrepancy.** The summary reports white-on-`primaryDark` as 6.58:1; it computes to **6.50:1**. No action — the assertion is `>= 4.5` and it passes eight ways from Sunday — but the figure appears in prose that may get quoted later.

---

## Where the sprint stands

Everything from rounds 1–3 is now closed or consciously accepted:

| Round | Raised | Status |
|---|---|---|
| 1 | H1–H4, M1–M11 | H2, H3, H4 closed. H1 closed for header/contacts; body still sources `generated_markdown` by design, documented. All M-tier closed except M2. |
| 2 | N1–N7 | All closed. |
| 3 | R1–R5 | All closed. |

**Still open by choice, worth its own ticket:** M2 — `AppLog.redact()` keeps 280 characters of a free-text message, and `_emit` prints at info/warn/error in release. Round 3 correctly removed the prose-guessing that was doing more harm than good; the durable fix is an API change (don't accept free text as the log *message*; route CV content through the key-filtered `data:` map where the allowlist actually works), not another regex.

One process note worth carrying into Sprint 2: across four rounds, the recurring failure was tests calibrated to the examples in a review rather than to the property being asserted — `passes` with its `+0.05`, the two bidi tests built from my own strings, the contrast pairs that didn't match what the components rendered. The final bidi suite is the counter-example and the model to copy: it tests the behaviour class, not the reported case.

---

## Addendum — verification of the round-4 follow-ups

Observations 1 and 3 are settled: the `normalizeExtracted()` docblock now names SIRATI-46 and states that single-call is the caller's responsibility, and the 6.58 figures are corrected to 6.50.

**Observation 2 is not settled.** The gradient guard was relaxed from a token ban to a source regex:

```dart
RegExp(r'(?:gradient:\s*[^,\n]*primaryGradient[\s\S]{0,300}?(?:Text|Icon|RichText)\s*\()')
```

Tested against realistic widget shapes, it catches one case and misses four — and it still fires on the decorative use it was relaxed to permit:

| Shape | Result |
|---|---|
| `gradient: c.primaryGradient` with `Text(...)` on the next line | caught ✓ |
| `Text` past the 300-char window (a normal `BoxDecoration` with radius, border and shadow is enough) | **missed** |
| child is a custom widget that renders text (`_PremiumBadgeLabel()`) | **missed** |
| child passed in as a parameter (`child: child`) | **missed** |
| gradient read through a local (`final g = c.primaryGradient`) | **missed** |
| gradient divider with an unrelated sibling `Text` in the same `Column` | **false positive** |

So the guard now permits the misuse in four common shapes while still blocking a legitimate decorative one. That is weaker than the version it replaced — the blunt token ban at least could not be wrong about what it was preventing.

This does not change the sign-off; nothing in `lib/` uses `primaryGradient` today, so there is nothing to catch. But the test now reads as protection it does not provide, which is the same failure mode this review kept running into. Two options that actually hold:

1. **Delete `primaryGradient`** from both palettes plus the `copyWith`/`lerp` plumbing, and delete the test with it. If a token cannot legally carry content and nothing uses it, it is not earning its keep.
2. **Move the check to widget level** — pump a widget that puts `Text` inside a `primaryGradient`-decorated container and assert it fails a contrast helper, rather than pattern-matching source text. Source regex cannot see through a child widget or a local variable, and no amount of tuning will change that.

Keep the arithmetic assertions in the first half of the test either way — those are accurate and they document *why* the constraint exists, which is the part worth preserving.
