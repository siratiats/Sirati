# Sprint 6 — Final Assessment: ATS Scorer and Store Readiness

Verified against source on 2026-09-13. I re-ran the shipped `AtsScoringService` against the
same three CVs used throughout this review thread (the scorer has zero framework
dependencies and uses only `collect()->contains()`, so the harness is faithful), inspected
the calibration corpus, and read the dark-mode walk test.

---

## 1. Did the ATS score improve? Yes — and in the right direction

The goal was never a *higher* score. It was an *honest* one. Here is the whole arc on the
same three documents:

| CV | Sprint 5 | Review R1 | **Now** |
|---|---|---|---|
| Keyword-stuffed junk | 92 (A+) | 81 (A) | **45 (F)** |
| Registered Nurse (off-taxonomy) | 69 (C) | 77 (B) | **76 (B)** |
| Arabic software developer | 71 (B) | 74 (B) | **72 (B)** |
| Weak admin CV (no metrics) | 38 (F) | — | **29 (F)** |

**This is the correct shape.** The junk CV fell 47 points; the two real CVs barely moved.
Had the real CVs jumped as well, that would have meant the scale was inflated rather than
the scorer fixed.

The junk CV now scores **31 points below** the nurse and **27 below** the Arabic developer.
SIRATI-87's acceptance criterion — *"the keyword-stuffed CV scores below both real CVs"* —
genuinely passes now, on the exact document it was written about. Two rounds ago it
failed; one round ago it still failed at 81 vs 77. It is closed.

Per-criterion, the junk CV's collapse is where it should be:

```
keywords     27/30  ->   4/30    density penalty working
format       15/15  ->   7/15    no longer free
structure    15/15  ->  12/15    headings alone earn less
experience    4/20  ->   4/20    already correct
```

**Verified alongside it:** the corpus is genuinely **32 fixtures with 17 Arabic** — both
claims check out. The Arabic clitic work is real: the Arabic CV's headings
(`الملخص المهني`, `الخبرة العملية`, `المؤهلات العلمية`) now all resolve, which is why its
structure is 15/15 and education 10/10 where it used to lose both.

### One remaining leak — padding still pays about 6 points

Adding a phone number and a PO box line to a zero-metric admin CV:

```
base                 total=27   experience=4/20   contact=3/5
with phone + PO box  total=33   experience=4/20   contact=3/5
```

The quantified-metrics inflation is genuinely gone — `experience` does not move, which is
exactly what the calibration test asserts, and that assertion is correct. But the **total
still rises 6 points**, and it is not coming from experience or contact. It comes from
`format`/`structure`, which still reward a document being longer and having more lines
regardless of what those lines say.

So the invariant as originally worded — *"adding a phone number never raises the score"* —
still fails at the total, through a different door. The calibration test passes because it
is scoped to the experience criterion.

This is small at current magnitudes (6 points out of 100, on a CV that scores 29 either
way) and it does not threaten any of the ordering invariants. **It is not a launch
blocker.** File it and fix it when `format` is next touched: a criterion that rewards line
count is measuring the wrong thing, and it is the last place where padding beats content.

### A product calibration question worth answering before launch

The two *real* CVs score 76 and 72 — solid Bs. The project's own strong fixtures score
88–94. The difference is length and richness: my test CVs are ~15 lines, the fixtures are
~40.

That is arguably correct behaviour — a thin CV *is* thinner. But it means a typical real
user with a one-page CV may land in the low 70s and see a "B". Before launch, run 20–30
**real** user CVs through and look at the distribution. If most land at 70, either the
scale or the grade boundaries need adjusting, because users read a B as "the product thinks
my CV is mediocre" and that shapes whether they trust the advice. This is a calibration
decision, not a defect.

---

## 2. Dark mode — the walk test is real

`dark_mode_12_screen_walk_test.dart` genuinely pumps screens (`EducationScreen`,
`ForgotPasswordScreen`, `EducationDetailScreen`, and the rest), finds real `Text` widgets
by their rendered strings, reads `.style?.color` off the pumped widget, computes the
contrast ratio and asserts **both** `ratio >= 4.5` **and** that the colour is not light
`textPrimary`. It taps into a bottom sheet and drags to scroll for off-screen content.

This is the substance of what the ticket asked for, automated. It is a real step up from
the palette invariant test, and it found real defects — the `textHint` → `textSecondary`
corrections in `education_screen.dart` and `notifications_screen.dart` are exactly the
"right palette, wrong token" class that the compiler could never catch.

Two things it still cannot see, both already filed as **SIRATI-92**: non-text contrast
(icons, chips, borders, the dim bookmark icons) and colours that come from an unexpected
painted ancestor rather than the surface the test supplies. Worth one human pass on a
device, but this no longer blocks.

---

## 3. Is it fit to publish to the store?

**The CV product is in good shape. The blocker is not code — it is SIRATI-70, and it has
been In Progress since Sprint 4.**

Honest answer: **not yet, and the gap is compliance paperwork, not engineering.**

### What must be done before submission

1. **Privacy labels / Data safety form — the most likely rejection.** The app sends CV
   content (full name, phone, email, employment history, uploaded files) to OpenAI and
   DeepInfra. Apple's privacy nutrition labels and Google's Data safety form both require
   that to be declared, and declaring less than you collect is both a rejection and a
   compliance problem. Walk the real inventory: name, email, phone, employment history,
   uploaded CV files, FCM token, notification engagement events.
2. **Privacy policy must name the AI sub-processor.** PDPL requires disclosing that
   personal data leaves to a third-party processor. The `/privacy-policy` route exists; the
   *text* needs to say OpenAI/DeepInfra explicitly, state retention, and give the support
   address.
3. **Bilingual store listings** — Arabic and English title, subtitle, description,
   keywords, what's-new. The Arabic listing is the primary market, not a translation
   afterthought. Have a native speaker review it.
4. **Screenshots** per required device size, both languages, showing RTL.
5. **Crash reporting on a real DSN** — confirm a deliberate crash in a production-flavour
   build actually arrives. An unconfigured DSN passes the config test and reports nothing.
6. **Production environment values.** `QUEUE_WORKER_TIMEOUT=180`, `DB_QUEUE_RETRY_AFTER=240`,
   `DEEPINFRA_GENERATE_TIMEOUT=90`, and confirm `storage/app/mpdf/ttfontdata` is writable
   and survives deploys. `/up` now fails closed on the first two, so the deploy will report
   unhealthy until they are set — set them in the same change.

### What I would also do, though it is not strictly blocking

7. **One real-device pass of the generation flow**, both languages, backgrounded mid-run.
   The code path is verified and tested; nobody has watched it happen on a phone. For a
   first store submission that is basic diligence, and it is an hour.
8. **Decide the premium story.** Premium is currently enabled for testing via a seeder and
   there is no purchase path (deferred pending your business discussion). Shipping free is
   fine — but the listing must not describe purchases that do not exist, and if any
   in-app-purchase metadata is declared without a working flow, that is a rejection.
9. **Run the score distribution check** from §1 before users see a number you have not
   sanity-checked at scale.

---

## Bottom line

The thing you asked about — the ATS score — went from actively misleading to defensible.
A CV that was pure keyword stuffing scored an A+ six days ago and scores an F today, while
two genuinely good CVs held their ground. That was the hard part, and it is done.

What stands between you and the store is a compliance package with a long external lead
time, and it is the one item that has not moved in three sprints. App review can take days
and a rejection restarts the clock — so SIRATI-70 is now the critical path, and starting
the privacy declarations this week matters more than any remaining code.
