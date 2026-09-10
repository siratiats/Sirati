# Sprint 6 — Review Round 1

Verification of the Sprint 6 completion report against source. Files staged this round. I
re-ran the shipped `AtsScoringService` in a PHP shim (the service has **zero** framework
dependencies — no `config()`, `app()`, `Cache::`, `DB::` — and uses only
`collect()->contains()`, so the shim is faithful), and I opened the Arabic PDF and looked
at it.

The engineering moved in the right direction. **The report's headline numbers do not mean
what they say**, and the strongest single claim — SIRATI-87's acceptance criterion — still
fails on the CV it was written about.

---

## Confirmed, and genuinely good

**SIRATI-85 is verified. I looked at the PDF.** Arabic shapes and ligates correctly, the
RTL layout is right, section headings and body sit on the correct margin, date ranges are
LTR-isolated on the left, and Latin technical terms (`PHP 8.3`, `Laravel`, `Kubernetes`,
`RESTful API`, `1,000,000`, `80ms`) render inline inside Arabic sentences without breaking
direction. Sentence-final periods land at the left end of the line, which is correct. This
is a real artefact and a real pass — the one place in this report where the evidence
matches the claim.

**It also confirms SIRATI-78 at the user-visible end for the first time.** The PDF shows
one visual block per job — title, employer, city, date range right-aligned, bullets — which
is exactly what the `_sections.blade.php` entries renderer was always able to do and never
got fed. That was never visually confirmed before now.

**`AtsCalibrationTest` is correctly shaped.** Ordering properties, not expected totals;
`dump()` for diagnostics rather than assertions on magnitudes; five invariants matching the
brief. This is AGENTS.md rule 1 done properly.

**Category is traced end to end** — migration → `CvAnalysisResource` → Blade view → Flutter
`AnalysisResultScreen`. That is rule 6 done right, and it was not asked for.

**Invariant 5 independently verified.** Scoring the nurse CV with and without its phone
line gives 77 and 77. The phone-number inflation is genuinely gone.

**SIRATI-92 filed** for the Job News dark-mode polish rather than folded in silently.

---

## Finding 1 — the before/after table is not a before/after — HIGH

The report presents:

> Keyword-Stuffed Junk CV: dropped from **92 (A+)** to **19 (F)**
> ICU Nurse CV: rose from **69 (C)** to **89 (A)**
> Arabic Developer CV: rose from **71 (B)** to **90 (A+)**

The 92 / 69 / 71 figures are mine, from `ATS_SCORING_REVIEW.md`, measured against three
specific CV texts. The 19 / 89 / 90 figures are measured against **different documents** —
new fixtures in `tests/Support/AtsCalibrationCorpus.php`.

`benchmarkJunk()` is a **single line**:

```
I worked at a company from 2019 to 2025 and did marketing and campaign and brand
and content and social media and seo and sem and ppc and google ads and meta ads
and analytics.
```

The CV that scored 92 had that sentence **plus** a name, email, phone, LinkedIn, a Summary
heading, an Experience heading, an Education section and a Skills line. Those earn format
15 + structure 15 + education 10 + summary 5 + contact 5 = **50 points of scaffolding**
before a single keyword is counted. Stripping them out is what produces 19 — not a harsher
scorer.

`benchmarkNurse()` is likewise a much stronger document than the one that scored 69: two
roles instead of one, twenty bullets instead of three, four certifications, a GPA, a
skills section, an EHR list.

So "92 → 19" and "69 → 89" are not measurements of the same thing at two points in time.
The scorer did change and probably improved — but the magnitude claimed is not supported by
what was run, and these numbers should not go in a ticket or a stakeholder update as
before/after.

## Finding 2 — SIRATI-87's acceptance criterion still fails on the CV it was written about — HIGH

The brief stated it as one line: *"the keyword-stuffed CV scores below both real CVs."*

Re-running the **shipped** scorer against the exact three CVs from `ATS_SCORING_REVIEW.md`:

| CV | Before | Now | Grade |
|---|---|---|---|
| Keyword-stuffed junk | 92 | **81** | **A** |
| Registered Nurse (off-taxonomy) | 69 | **77** | B |
| Arabic software developer | 71 | **74** | B |

**The junk CV still outscores both real CVs**, by 4 and 7 points. It improved by 11; the
real CVs improved by 8 and 3. The gap narrowed and did not close.

The per-criterion breakdown shows why, and it is actionable:

```
KEYWORD-STUFFED JUNK — total 81 (A)
  format      15/15      <- unchanged, still free
  keywords    27/30
  structure   15/15      <- headings alone
  experience   4/20      <- correctly punished
  education   10/10      <- "Bachelor degree 2018" + "AWS certified"
  summary      5/5
  contact      5/5
```

SIRATI-87 fixed the quantifier inflation properly — experience fell from 15 to 4, which is
the right answer. But **stuffing still pays because the rubric hands out 50 points for
document furniture**: having a heading called "Summary", an email, a phone and the word
"Bachelor" is worth half the total regardless of what the document says. `format` in
particular is still 15/15 for every input, exactly as flagged in `ATS_SCORING_REVIEW.md` §7
— a criterion every CV passes carries no information and inflates every score by a flat
amount.

The corpus invariant passes because the fixture was reduced to a bare sentence with none of
that furniture. Against the adversarial document the ticket was written around, the defect
survives.

**This is the finding to act on.** Either the scaffolding criteria need to scale with
content (a "Summary" heading with no summary under it should not score 5/5), or keyword
density with absent achievements needs to carry a real penalty rather than just losing the
experience points.

## Finding 3 — the corpus is 14 synthetic CVs, not 20–30 real ones — MEDIUM

The brief asked for 20–30 **real** CVs — 10 strong, 10 weak, 10 Arabic, six professions,
three off-taxonomy — anonymised before committing.

What is there: **14** fixtures (5 strong, 6 weak, 3 benchmarks). Professions and
off-taxonomy coverage are met — nurse, civil engineer, teacher, marketing, finance,
software, with three off-taxonomy. **Arabic coverage is one benchmark plus the paired
equivalent, not ten.**

More importantly they are written-to-order, not collected. A corpus authored by the same
agent that tunes the scorer is self-confirming: it contains the cases that were thought of,
which are the cases already handled. That is rule 1 one level up — a corpus written to pass
is a review example at corpus scale. Findings 1 and 2 are precisely what that produces: the
junk fixture drifted toward something the fixed scorer handles, and the real adversarial
document went untested.

Real CVs are what catch the cases nobody imagined. Ten Arabic ones would have exercised the
clitic work far harder than one.

## Finding 4 — "fully verified at the user-visible end" holds for one ticket of four — MEDIUM

| Ticket | Evidence offered | Verdict |
|---|---|---|
| **SIRATI-85** | A rendered PDF, 57.9 KB, 268.5 ms | **Verified.** I opened it. |
| **SIRATI-80** | "compile-enforced across all 27 sites… validated by `app_text_styles_palette_invariant_test.dart`" | **Not the walk.** |
| **SIRATI-76** | "210s polling ceiling, lifecycle auto-resumption, FCM push notification hooks" | **Not the walk.** |
| **SIRATI-79** | M1/M6/M7 resolved | Plausible; M7 is a recorded decision, fine. |

For SIRATI-80 the evidence offered is the code fix plus the invariant test. That test proves
no call site *can* resolve the light palette. It cannot prove each site picked the *right*
token — a `textPrimary` where `textSecondary` was meant renders legibly and is still wrong,
and that is the entire reason the ticket asked for a 12-screen walk. This substitution has
now been flagged three times in this project; the walk is half an hour of someone's time.

For SIRATI-76, the code listed is what was already verified in Sprint 5 round 4. The brief
asked one specific question: on a persistent double-provider failure the job runs to ~520s
while the client stops polling at 210s — what does the user see in that gap, and does the
push arrive? The report does not answer it. If the push does not land, that is a bug, and
it is the last unexamined corner of the flow that started this whole thread.

Note the asymmetry: SIRATI-85 produced a file that can be inspected. The other two produced
prose. That asymmetry is the tell.

---

## What I would do next

1. **Run the two walks.** Dark mode across the 12 screens; generation backgrounded on a
   device with a forced provider failure. Both are short and both are the acceptance
   criteria. Do not close SIRATI-80 or SIRATI-76 until they are done.
2. **Add the original three CVs to the corpus verbatim**, as `adversarial_*` fixtures
   alongside the current benchmarks, and let invariant 2 run against them. That single
   change makes the corpus adversarial rather than confirmatory, and it will fail today —
   which is the point.
3. **Fix the scaffolding, not just the metrics.** `format` at 15/15 for every input, and
   full marks for a heading with nothing under it, are what keep the junk CV at 81. That is
   a scorer change, not a corpus change.
4. **Correct the before/after figures** wherever they were written into SIRATI-87/88/89.
   The improvements are real; the numbers as stated are not comparable, and a stakeholder
   reading "92 → 19" will believe something that was not measured.
5. **Collect real CVs for the corpus** — especially Arabic. This has a human dependency and
   is worth starting now rather than at the end of Sprint 7.

Nothing here undoes the work: the quantifier fix is correct and verified, the general
profile removes the marketing default, the Arabic normalisation demonstrably works in the
rendered PDF, and the calibration harness is well built. The gap is between what was
measured and what was claimed.
