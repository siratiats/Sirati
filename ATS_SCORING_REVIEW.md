# ATS Scoring — Accuracy Review

Scope: `app/Services/AtsScoringService.php` (the whole scorer), `CvTextExtractor.php`,
`CvAnalysisController.php`, `Ai/Prompts/AnalysisAdviceSystemPrompt.php`, plus a grep for
job-description handling across `app/`.

Method: I ran the **real scorer**, unmodified, against three CVs in a PHP shim, and
verified every regex claim below by executing it. Numbers in this document are outputs,
not estimates.

---

## The headline result

| CV | Total | Grade | Job match | What it actually is |
|---|---|---|---|---|
| Registered Nurse, 7 yrs ICU, real metrics, degree + licences | **69** | **C** | 20% | A strong, hireable CV |
| Arabic software developer, real metrics, proper sections | **71** | **B** | 57% | A good CV |
| A paragraph of marketing buzzwords with no achievements, no employer, no dates | **92** | **A+** | 90% | Unhireable |

The third CV is one run-on sentence: *"I worked at a company from 2019 to 2025 and did
marketing and campaign and brand and content and social media and seo and sem and ppc and
google ads and meta ads and analytics."* It outscores both real CVs by more than twenty
points.

That is the whole problem in one line. **The scorer currently rewards the exact behaviour
that gets a CV thrown out by the human who reads it after the ATS.** A user who follows
the advice honestly will score lower than one who pastes a keyword list. Right now the
number is not just imprecise — it points the wrong way.

Everything below is ranked by how much accuracy each fix buys.

---

## 1. The score never sees the job. **Biggest win by a distance.**

`AtsScoringService::score(string $resumeText, string $jobTitle, ?string $categoryHint)`.

There is no job-description parameter anywhere in the scorer, the controller, or the API
request. The 30-point "keywords" block — the largest single block — is scored against
`JOB_KEYWORDS`, a hardcoded array of eight categories with about ten generic terms each.
So `job_match: 57%` does not mean "you match 57% of this job". It means "you contain 57%
of a static list someone typed into a PHP constant in 2025."

Every credible ATS tool works the other way round: paste the job posting, extract its
terms, score the CV against *those*. Without it the number cannot be accurate, no matter
how the weights are tuned.

**You already collect this.** `GeneratedCvController.php:171` validates
`'job_description' => ['nullable','string','max:4000']` and `GeneratedCv` persists
`job_description_input`. The CV *generator* takes a job description; the CV *analyser*,
where it actually determines the score, does not. Same codebase, one flow away.

The fix: add an optional job-description field to the analysis request; extract its terms
(noun phrases, tools, certifications, seniority words) with a cheap tokeniser plus a
stopword list — no AI needed for v1; score coverage of *those* terms; fall back to the
category list only when the user gives no description. Report two numbers, because they
answer different questions: **job match** (against the posting) and **CV strength**
(against the rubric). Today they are fused into one score that means neither.

This one change is worth more than every other item on this list combined.

---

## 2. Everyone outside eight categories is scored as a marketer

`jobCategory()` ends `default => 'marketing'`.

The eight categories are ecommerce, marketing, software, data, management, finance, hr,
sales. There is no healthcare, education, engineering, construction, logistics,
hospitality, legal, government, or oil & gas — which between them are a large share of the
Saudi job market, and the segments where a bilingual Arabic CV tool has the least
competition.

A nurse is therefore scored against `seo`, `ppc`, `google ads`, `meta ads`. The run above
returns keywords **6/30**, and the app tells a working ICU nurse her CV is missing
"marketing, campaign, brand, content, social media, seo".

The ceiling is provable. With zero list keywords present, the most she can earn is the
6-point title-match bonus (the 3-point early-mention bonus requires a *found* keyword, so
it is unreachable). **24 of 100 points are structurally unavailable — an off-taxonomy CV
can never score above 76, and can never earn an A**, no matter how good it is.

The fix, in order of preference: (a) item 1 makes the taxonomy a fallback rather than the
main path; (b) failing that, default to a neutral profile that scores only
transferable signals instead of guessing "marketing"; (c) expand the taxonomy — but note
that hand-typed keyword lists per profession is an infinite job, which is why (a) is the
real answer.

---

## 3. "Quantified achievements" counts phone numbers

`QUANT_PATTERNS` ends with `/\b\d{2,}\b/u` — any run of two or more digits.

Run on a CV with **zero** achievement metrics ("Responsible for filing and answering the
telephone"):

```
quantifiedCount = 9
matches: 966 | 55 | 123 | 4567 | 31952 | 34423 | 2019 | 2025 | 2018
```

A phone number, a PO box, a postal code and three employment years. That clears the
`>= 5` threshold and awards **11 of 11 points** — the single largest sub-item in the
rubric — and then the strengths list congratulates the user: *"تم رصد 9 مؤشرات رقمية،
وهذا يرفع قوة السيرة"*.

Conversely a CV with three genuine metrics and no phone number scores lower than one with
none and a phone number.

The fix: exclude four-digit year-like tokens, exclude anything on a line that contains an
email/phone/URL, and require the number to sit in the same bullet as an action verb or a
unit (%, SAR, hrs, users, ×). Then count *distinct bullets containing a metric*, not raw
regex hits — one bullet with three numbers is one achievement, not three.

---

## 4. Truncated stems are wrapped in `\b…\b`, so they can never match

```php
'certifications' => '/\b(certif|license|accreditat|credential|award|…)\b/iu',
'skills' => '/\b(skills?|competenc|expertise|technical|…)\b/iu',
```

`certif` followed by `\b` cannot match "Certifications" — there is no word boundary
between `f` and `i`. Verified:

| Heading | Result |
|---|---|
| `Certifications` | **miss** |
| `Certified` | **miss** |
| `certificate` | **miss** |
| `licenses` | **miss** |
| `credentials` | **miss** |
| `accreditation` | **miss** |
| `awards` | **miss** |
| `Competencies` / `Core Competencies` | **miss** |
| `license`, `Award`, `Skills` (exact) | match |

The stems were clearly written to catch inflections and the trailing `\b` defeats every
one of them. This is why the nurse scored **education 5/10**: her "Certifications"
heading, her BLS/ACLS and her Saudi Commission licence earned **zero** certification
points — while the junk CV's bare string "AWS certified" earned the full 5, because `aws`
happens to be a whole word on the separate `educationScore` list.

The fix is one character per stem: drop the trailing `\b` on truncated stems, or use
`(?:certif\w*)`.

---

## 5. Arabic section headings miss on the most idiomatic forms

Arabic characters *are* word characters under `/u` (PCRE2 UCP is on — I checked), so `\b`
behaves. The problem is that Arabic attaches its clitics — ال، و، ب، ل، لل — directly to
the word, and the patterns list bare surface forms. Verified against the shipped patterns:

| Heading (how Arabic CVs are actually written) | Result |
|---|---|
| `الخبرة العملية` | **miss** |
| `الملخص المهني` | **miss** |
| `المؤهلات العلمية` | **miss** |
| `الجامعة` / `البكالوريوس` | **miss** |
| `الكفاءات` | **miss** |
| `مهاراتي` | **miss** |
| `وطورت` / `وأدرت` (verb with و prefix) | **miss** |
| `الخبرات المهنية`, `المهارات التقنية`, `ملخص`, `نبذة` | match |

`الخبرات` is in the list so it matches; `الخبرة` — the singular, at least as common as a
heading — is not. `المؤهلات العلمية` is *the* standard Arabic education heading and is
absent entirely.

This is visible in the run above: the Arabic CV scored **summary 0/5** even though its
heading is literally `الملخص المهني`, and structure 12/15.

For an Arabic-first product in the Gulf this is the credibility problem. The fix is not
more hand-typed forms — it is normalise-then-stem: strip tatweel and diacritics,
normalise أ/إ/آ→ا and ة→ه, then match an optional clitic prefix, e.g.
`(?:^|[\s،:\-])(?:وال|بال|لل|ال|و|ب|ل)?خبر(?:ة|ات)`. Cover the standard heading
vocabulary — المؤهلات، السيرة الذاتية، الدورات التدريبية، المهام والمسؤوليات — and test
against real Arabic CVs, not invented ones.

---

## 6. Substring matching produces silent false positives

`keywordScore()` uses `str_contains($text, $keyword)` with no word boundary. Verified:

| CV text | Falsely matched keyword |
|---|---|
| "Therapist at rapid capital clinic" | `api` |
| "Descartes cartography" | `cart` |
| "Metabolic metadata" | `meta` |
| "Contented team leader" | `team`, `content` |

Every false hit inflates the score *and* removes a term from `keywords_missing`, so the
user is never told to add the keyword they don't actually have. Fix with word-boundary
matching plus an explicit inflection map (`analytics`/`analytical`, `manage`/`managed`/
`management`) rather than raw substring.

---

## 7. Format is a free 15/15 and tests nothing an ATS cares about

`formatScore()` starts at 3 and adds points for: ≥10 lines (+4), >300 characters (+3),
average line length under 120 (+3), and the presence of any line shorter than 8
characters (+2). That is 15/15 for essentially every CV, and all three test CVs above —
including the junk one — scored a perfect 15. A criterion that every input passes carries
no information; it is 15 points of padding that inflate every score by a flat amount.

None of it corresponds to why CVs actually fail ATS parsing: multi-column layouts, text
inside tables, contact details in the page header or footer, text rendered as images,
non-embedded fonts, section headings that are graphics.

You are already parsing the PDF with `smalot/pdfparser` in `CvTextExtractor` — the
information is right there and thrown away. Real signals available at near-zero cost:
extracted characters per page (a very low ratio means an image-based or graphics-heavy
CV), detected columns from text x-coordinates, whether an email appears in the first 300
characters of *extracted* text (not of the visual page), page count, and whether
extraction yielded anything at all.

Also: `CvTextExtractor` currently *throws a validation error* on a scanned PDF. A scanned
CV is not an invalid request — it is a CV that will score near-zero on ATS parseability,
which is the most valuable thing you could possibly tell that user. Score it and explain
it instead of rejecting it.

---

## 8. The builder path discards the structure it already has

`scoreDocument()` takes a fully structured `CvDocument` — sections, entries, dates,
bullets — calls `->resolve()->plainText`, and hands the flat string to the same regex
guesser used for pasted text.

For CVs built inside Sirati you *know* where the experience section is, how many entries
there are, what each date range is, and which lines are bullets. Re-deriving that with
`/\b(experience|work history|…)\b/` is strictly worse and can be wrong about a document
you generated yourself. Split the rubric: structural criteria (structure, contact,
education, format) read the model directly; only text-quality criteria (keywords, verbs,
metrics) work on the rendered text. This also removes the current oddity that the same CV
scores differently depending on whether it came through the builder or the uploader.

---

## 9. `job_match` is not a match percentage

`'job_match' => (int) round(($keywordScore / 30) * 100)` — and `$keywordScore` includes a
+6 title-match bonus and a +3 "keyword appears early" bonus. A CV with **zero** keyword
overlap that merely repeats the job title in its headline reports **20% job match**
(exactly what the nurse got). Once item 1 lands, job match should be computed only from
job-description term coverage, and shown with its evidence: which terms matched, which
did not, and where.

---

## 10. Nothing measures whether a change made the score better

There is no labelled corpus and no calibration test. `Ai/BakeOff/ArabicCvCorpus.php`
exists but serves the AI provider bake-off, not the scorer.

That means every weight in this file is unfalsifiable, and any fix from this list could
silently make things worse. Per AGENTS.md rule 1, the guard has to be the invariant, not
an example: assemble 20–30 real CVs (10 strong, 10 weak, 10 Arabic, spanning at least six
professions), label them, and assert *ordering* properties that must hold regardless of
tuning —

- a strong CV always outscores a weak one in the same profession
- the keyword-stuffed CV never outscores a real CV **(fails today)**
- an equivalent Arabic and English CV score within N points **(fails today)**
- an off-taxonomy CV can reach an A **(impossible today)**
- adding a phone number never raises the score **(fails today)**

Four of those five fail right now, which is a good sign the set is testing something real.
Build it *before* changing weights, or you will be tuning blind.

---

## What to do first

**Sprint-sized, in this order:**

1. **Job description in, scored against.** Add the field to the analysis request and the
   Flutter screen, extract terms, score coverage, keep the taxonomy as fallback, split
   the output into job match and CV strength. (Item 1, item 9.) *This is the product
   difference between "a number" and "a tool".*
2. **Stop rewarding stuffing.** Fix the quantifier patterns (item 3) and require
   achievements to carry a verb and a unit; cap keyword credit per term so repetition
   stops paying. Re-run the junk CV and require it to score below both real CVs — that
   single assertion is the acceptance criterion.
3. **Arabic normalisation and stemming** (item 5) plus the `\b` stem fix (item 4). Both
   are small, mechanical, and immediately visible to Arabic users.
4. **Neutral profile instead of `default => 'marketing'`** (item 2), so nobody is told to
   add SEO to a nursing CV.
5. **Calibration corpus** (item 10) — ideally before 2, so you can prove the change
   worked.

**Deferred but worth queuing:** real format signals from the PDF parse (item 7), the
structured-document scoring path (item 8), and substring→boundary matching (item 6, small
but touches the same code as item 1 so it may as well ride along).

One thing to decide as product, not engineering: whether the score is presented as
"your ATS score" or "your match for this job". They imply different promises, and right
now the app makes the first promise while computing something closer to neither. My read
is that the second is more defensible, more useful, and far harder for a competitor to
copy — but it only becomes possible once item 1 exists.

---

## Coverage note

`device_bash` remains unavailable on this machine ("Workspace unavailable" for the whole
session), so files were staged into the review container and analysed there. Every regex
and score figure in this document was produced by executing the shipped code or the
shipped patterns under PHP 8. I did not run your test suite, and I did not review the
Flutter analysis screens beyond what was needed to confirm no job-description field is
sent.
