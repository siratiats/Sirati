# Sprint 6 — Implementation Brief

**For the implementing agent. Read this whole file before writing any code.**

Sprint 6 is active on board 36 (id 80), 9–23 Sept 2026.

**Goal:** close out Sprint 5 at the user-visible end, finish store submission, and make the
existing ATS score honest — so that when the job-description feature lands in Sprint 7,
there is a trustworthy baseline to measure it against.

9 tickets. Four are verification carried from Sprint 5, one is store submission (not
engineering), four are new ATS work.

Source documents at the repo root — read the relevant section before starting each ticket:

- `ATS_SCORING_REVIEW.md` — the evidence behind every ATS ticket, with the scorer run
  against three real CVs
- `DARK_MODE_CONTRAST_AUDIT.md` — the 12-screen list for SIRATI-80
- `AI_LAYER_AND_PERFORMANCE_REVIEW.md` — background for SIRATI-91 (not in this sprint)
- `SPRINT5_REVIEW_ROUND4_SIGNOFF.md` — what was verified in Sprint 5 and what was not

---

## Non-negotiables

The `AGENTS.md` invariants. Work that violates them gets sent back.

1. **Test the invariant, not the review example.** This sprint has the sharpest version of
   this rule yet: SIRATI-90 exists specifically so that SIRATI-87/88/89 are measured
   against ordering properties rather than hardcoded expected totals.
2. **Strict quality gates.** `flutter analyze` and the PHP suite clean.
3. **Semantic and architectural validation** — not "it compiles".
4. **Directionality and text boundaries** — every change checked in Arabic and English.
6. **A deliverable is not done until something calls it, and the call chain is traced to
   the user-visible end.** Four of this sprint's tickets are *specifically* about that
   step being skipped last sprint. Do not add to the list.
7. **Security gates fail closed.**

**Report honestly.** Sprint 5's completion report separated "exercised" from "could not
verify" throughout, and that made it reviewable. Do the same.

---

## Wave 1 — finish Sprint 5 (do this first, it is small and it is blocking)

These four landed as code and were verified in source. What is missing is the verification
their acceptance criteria actually ask for. They are **In Review**, not To Do — expect
them to close quickly, but expect the walk to find things.

| Ticket | What is left |
|---|---|
| **SIRATI-85** | Export an Arabic CV and *look at it* against a reference. IBM Plex Sans Arabic is registered and loads; that is not proof it shapes, ligates and positions correctly. Check Latin technical terms inside Arabic bullets too. |
| **SIRATI-80** | Walk the 12 screens named in `DARK_MODE_CONTRAST_AUDIT.md` in dark mode. The compiler now proves no call site can pick the light palette; it cannot prove each site picked the right *token*. A `textPrimary` that should have been `textSecondary` renders legibly and is still wrong. |
| **SIRATI-76** | Generation end to end in both languages on a real device, backgrounded mid-run. **Specific thing to watch:** on a persistent double-provider failure the job runs to ~520s while the client stops polling at 210s. Check what the user sees in that gap and whether the push notification actually arrives. If it does not, that is a bug, not a documentation gap. |
| **SIRATI-79** | M7 only — decide and enforce whether generated English copy may contain Arabic. It is a product decision; record it on the ticket either way. |

Anything these walks turn up gets filed as a new ticket, not folded silently into a commit.

**Not yours:** SIRATI-70 (store listings, privacy labels, crash-reporting DSN). It runs in
parallel and has the longest external lead time of anything on the board.

---

## Wave 2 — make the score honest

### Read this before touching the scorer

The current scorer, run unmodified against three CVs:

| CV | Total | Grade | Job match |
|---|---|---|---|
| Registered Nurse, 7 yrs ICU, real metrics, degree + licences | 69 | **C** | 20% |
| Arabic software developer, real metrics, proper sections | 71 | **B** | 57% |
| One run-on sentence of marketing buzzwords, no achievements, no employer | **92** | **A+** | 90% |

The junk CV beats both real ones. **The score currently rewards the behaviour that gets a
CV rejected by the human who reads it after the ATS.** That is what this wave fixes.

### SIRATI-90 — calibration corpus. **Start here.**

Everything else in this wave is unfalsifiable without it. Every weight in
`AtsScoringService` is currently a number nobody can check.

Assemble 20–30 real CVs: 10 strong, 10 weak, 10 Arabic, spanning at least six professions
including at least three outside the current eight categories. Anonymise them — names,
contact details, employers replaced — this is PDPL-relevant data and it is going into the
repo.

Then assert **ordering properties**, not expected totals:

| # | Invariant | Today |
|---|---|---|
| 1 | A strong CV outscores a weak one in the same profession | — |
| 2 | The keyword-stuffed CV never outscores a real CV | **fails** |
| 3 | Equivalent Arabic and English CVs score within N points | **fails** |
| 4 | An off-taxonomy CV can reach an A | **impossible** |
| 5 | Adding a phone number never raises the score | **fails** |

Four of five fail now. Run the suite before and after each of SIRATI-87/88/89 and report
the delta in that ticket. That is the measuring instrument for the whole wave — build it
first, or you are tuning blind.

### SIRATI-87 — stop rewarding keyword stuffing (Highest)

`QUANT_PATTERNS` ends with `/\b\d{2,}\b/u`. Run against a CV with **zero** achievement
metrics ("Responsible for filing and answering the telephone"):

```
quantifiedCount = 9
matches: 966 | 55 | 123 | 4567 | 31952 | 34423 | 2019 | 2025 | 2018
```

A phone number, a PO box, a postal code and three employment years — clearing the `>= 5`
threshold and taking the full **11 of 11** points, the largest sub-item in the rubric.
Then the strengths list congratulates the user on nine metrics.

Exclude year-like tokens; exclude numbers on any line carrying an email, phone or URL;
require the number to share a bullet with an action verb or a unit (%, SAR, hrs, users, ×);
count **distinct bullets containing a metric**, not raw regex hits. Cap keyword credit per
term so repetition stops paying.

**The acceptance criterion is one line: the keyword-stuffed CV scores below both real CVs.**
Assert it in the SIRATI-90 suite as an ordering invariant, not as three expected totals.

### SIRATI-88 — Arabic clitics and unmatched stems (High)

Two defects, both verified by executing the shipped patterns.

**Arabic prefixes.** Arabic is a `\w` character under `/u` (PCRE2 UCP is on), so `\b`
behaves — but Arabic attaches ال، و، ب، ل، لل directly to the word and the patterns list
bare surface forms:

| Heading, as Arabic CVs are actually written | Result |
|---|---|
| `الخبرة العملية`, `الملخص المهني`, `المؤهلات العلمية` | **miss** |
| `الجامعة`, `البكالوريوس`, `الكفاءات`, `مهاراتي` | **miss** |
| `وطورت`, `وأدرت` | **miss** |
| `الخبرات المهنية`, `المهارات التقنية`, `ملخص`, `نبذة` | match |

`الخبرات` is in the list so it matches; `الخبرة` — the singular, at least as common — is
not. `المؤهلات العلمية` is *the* standard Arabic education heading and is absent entirely.

Do not add more surface forms. Normalise then stem: strip tatweel and diacritics,
normalise أ/إ/آ → ا and ة → ه, then match an optional clitic prefix, e.g.
`(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?خبر(?:ة|ات)`.

**Truncated stems wrapped in `\b…\b` can never fire.** `certif` followed by `\b` cannot
match "Certifications". Also `competenc`/"Competencies", `credential`/"credentials",
`accreditat`/"accreditation", `award`/"awards", `license`/"licenses". This is why the nurse
scored education 5/10 — her Certifications heading, BLS/ACLS and SCFHS licence earned
**zero**, while the junk CV's bare "AWS certified" earned the full 5. Affects English as
much as Arabic. Drop the trailing `\b` or write them as `(?:certif\w*)`.

Test against **real** Arabic CVs from the SIRATI-90 corpus, not invented ones.

### SIRATI-89 — the marketing default (High)

`jobCategory()` ends `default => 'marketing'`. No healthcare, education, engineering,
construction, logistics, hospitality, legal, government or oil & gas — much of the Saudi
market, and the segments where a bilingual Arabic tool has least competition.

A nurse is scored against `seo`, `ppc`, `google ads`. She gets keywords **6/30** and is
told her CV is missing "marketing, campaign, brand, content, social media, seo".

The ceiling is provable: with no list keywords present the 3-point early-mention bonus is
unreachable, so **24 of 100 points are structurally unavailable and an off-taxonomy CV can
never earn an A.**

Replace the guess with a **neutral profile** that scores only transferable signals. Do not
expand the taxonomy — hand-typed keyword lists per profession is an infinite job, and
SIRATI-86 next sprint makes the taxonomy a fallback rather than the main path. When the
category is uncertain, say so in the UI rather than silently asserting a wrong one.

---

## Deliberately NOT in this sprint

**SIRATI-86 — score against the job description.** It is the biggest single accuracy win in
the product and it is deferred on purpose.

It changes what the number *means*: a new input, a new UI field, term extraction, and a
split into two numbers (job match vs CV strength). Building that on a scorer that still
rewards stuffing and still tells nurses to add SEO means you could not tell which change
caused which effect. Fix the scorer's honesty first, with SIRATI-90 as the instrument, then
add the job description in Sprint 7 against a baseline you trust.

If you disagree with that sequencing, say so before starting rather than half-doing both.

**SIRATI-91 — the AI advice layer.** Depends on SIRATI-89 and SIRATI-86. Note that when it
lands it will require bumping **both** `AnalysisAdviceSystemPrompt::VERSION` and
`CachedCvAiProvider::PROMPT_VERSION` — the second is the only cache-invalidation mechanism
there is.

---

## Traps

1. **Build SIRATI-90 before tuning weights.** Not alongside, not after. It is the only way
   to know whether a change helped.
2. **Do not expand `JOB_KEYWORDS`** as the fix for SIRATI-89. That is the infinite-job trap
   the ticket warns about.
3. **Do not start fixing the scorer while doing the Wave 1 walks** — note what you see and
   move on. Generated CVs are scored by this same scorer (`GenerateCvContentJob:69`), which
   is why it is tempting.
4. **The corpus is real CV data.** Anonymise before committing. PDPL applies and this is a
   Saudi product.
5. **`AtsScoringService` has no AI coupling** — it is pure PHP and fast. Do not introduce an
   AI call into scoring to solve a matching problem; that is SIRATI-86's job and even there
   v1 is deterministic.

---

## Definition of done

- `flutter analyze` clean; PHP suite green.
- The four Wave 1 tickets verified by actually running the app, with what you saw written
  down — not inferred from a passing build.
- The SIRATI-90 suite exists, runs in CI, and all five invariants pass.
- Before/after invariant results reported in SIRATI-87, 88 and 89 individually.
- The nurse CV from `ATS_SCORING_REVIEW.md` scores like a good CV, and the junk CV does
  not.
- Anything found but not fixed filed as a ticket.

## When you finish

Per ticket: what changed, which acceptance criteria you exercised and how, what you could
not verify, and anything found along the way that is not filed. Expect the load-bearing
claims to be checked against source.
