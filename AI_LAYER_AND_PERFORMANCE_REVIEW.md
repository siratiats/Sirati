# Why the AI Layer Doesn't Catch the Scoring Defects — and the Generation/PDF Performance Path

Two reviews in one document, both requested after the ATS scoring review.

Files read: `Ai/Prompts/AnalysisAdviceSystemPrompt.php`, `Ai/Schemas/AnalysisAdviceSchema.php`,
`Ai/Schemas/GenerateCvSchema.php`, `Ai/CachedCvAiProvider.php`, `OpenAiCvService.php`,
`ClaudeCvService.php`, `DeepInfraCvService.php`, `Jobs/GenerateCvContentJob.php`,
`Jobs/GenerateCvAdviceJob.php`, `GeneratedCvController.php`, `CvTemplateRenderer.php`,
`config/queue.php`, `config/services.php`, `.env.example`.

---

# Part 1 — Why the AI doesn't care

You added AI expecting it to be a second opinion. It is not built as one. It is built as a
**presenter for the deterministic scorer**, and four separate design decisions make it
structurally incapable of noticing that the score is wrong.

## 1.1 It is explicitly forbidden from disagreeing

`AnalysisAdviceSystemPrompt::rubricSection()`:

> `Interpret the provided score JSON with this rubric. Do not re-score from scratch.`

That one sentence is the whole answer. The model receives the 92/A+ awarded to a
keyword-stuffed paragraph as **ground truth** and is instructed to explain it, not to
question it. It cannot tell the user "this score is inflated" because it has been told
that re-scoring is out of scope.

The instruction is defensible on its own terms — it keeps the number stable and stops the
model contradicting the UI. But it means the AI can never be the safety net for a scorer
bug. Whatever the scorer gets wrong, the AI ratifies.

## 1.2 It is fed the same wrong premise

`keywordBanksSection()` injects `AtsScoringService::jobKeywords()` verbatim into the
prompt — the same eight categories, the same generic terms. So when the nurse's CV is
classified `marketing` by `default => 'marketing'`, the model is handed the marketing
keyword bank and told:

> `Recommend missing keywords only when CV evidence supports them`

There is no mechanism by which it could recommend `BLS`, `ACLS`, `triage` or
`patient assessment` — those words are not in anything it was given. It is not being
lazy; it was never shown the right vocabulary.

## 1.3 The prompt actively amplifies the worst error

> `Prioritize fixes for the weakest high-weight criteria first.`

For the nurse, the weakest high-weight criterion is keywords at 6/30 — an artefact of
being scored against the wrong profession. So the prompt **directs** the model to spend
its `top_priorities` telling a working ICU nurse to add SEO and Google Ads. The rule is
sensible; applied to a broken input it makes the output worse than if there were no rule.

## 1.4 The 15 few-shot examples cover only the same eight categories

`FEW_SHOTS` spans software, marketing, data, sales, finance, hr, management, ecommerce —
by construction, "spread across the 8 AtsScoringService job categories". Zero healthcare,
education, engineering, construction, logistics, legal, hospitality. So even the style
guidance steers off-taxonomy users toward vocabulary from someone else's profession.

## 1.5 There is nowhere in the schema to put a disagreement

`AnalysisAdviceSchema` requires `executive_summary`, `top_priorities`,
`rewritten_summary`, `keyword_recommendations`, `bullet_improvements`, `warnings`. Only
`warnings` (a free-form string array) could carry "this CV appears keyword-stuffed" or
"the target role doesn't match any category we score well" — and nothing in the prompt
tells the model that `warnings` is for that. In practice it is used for content caveats.

So even a model that noticed has no structured place to say so, and no instruction to try.

## What to change

The cheapest meaningful fix is to give the AI **one job the scorer cannot do**, and a
field to put it in:

1. Add a `score_confidence` object to the schema: `{level, reason}` where the model may
   say the deterministic score is likely too high or too low, and why. Keep the displayed
   number as-is — this is a flag, not a re-score.
2. Add two explicit prompt instructions: flag CVs whose keyword density is high while
   achievements are absent (the stuffing case), and flag target roles that appear to fall
   outside the eight categories. Both are things the model can see and the scorer, by
   construction, cannot.
3. Stop injecting the keyword bank as the *only* vocabulary. Once the ATS review's item 1
   lands (job description in), pass the job description's own terms instead, and let the
   bank be the fallback.
4. Extend `FEW_SHOTS` to at least healthcare, education and engineering.

**Cost note the implementing agent must not miss:** the system prefix is deliberately
static and byte-identical to engage OpenAI prompt caching (target band 1,400–1,800
tokens). Any of the above changes the prefix, so it requires bumping
`AnalysisAdviceSystemPrompt::VERSION` **and** `CachedCvAiProvider::PROMPT_VERSION`
(currently `'5'`), and warm-up cache misses are expected for a day after deploy. That is
documented in the file; it is easy to skip and would silently serve stale advice.

---

# Part 2 — Generation and PDF performance

Grounded in yesterday's run: a long wait, then an error warning. The config explains it.

## 2.1 The retry stack costs ~130 seconds before it gives up — and mostly retries the wrong layer

`GenerateCvContentJob`: `$tries = 3`, `$backoff = [10, 30]`, `$timeout = 120`.
`OpenAiCvService`: `->connectTimeout(5)->timeout(30)` with **no `->retry()`**.

So a transient OpenAI 429 or 500 — the single most common failure, and one that usually
clears in under a second — fails the *entire generation attempt*, and recovery is handled
by re-queuing the whole job:

| | elapsed |
|---|---|
| attempt 1 fails | 30s |
| backoff | +10s |
| attempt 2 fails | +30s |
| backoff | +30s |
| attempt 3 fails | +30s |
| **user sees the error** | **~130s** |

Three full-price API calls and over two minutes of the user staring at a progress screen,
to recover from something that an HTTP-level `->retry(3, 500, throw: false)` honouring
`Retry-After` would have absorbed in about a second on the first attempt.

**Fix:** retry at the HTTP layer for 429/5xx/connection errors, and reserve the job-level
`tries` for genuine failures. This is the single biggest latency win in the flow.

## 2.2 `retry_after` (90s) is lower than the job timeout (120s)

`config/queue.php`: `'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90)`, and
`GenerateCvContentJob::$timeout = 120`.

Laravel's rule is that `retry_after` must always exceed the longest a job can run.
It doesn't. Any attempt that runs past 90 seconds is released back to the queue and picked
up by a second worker **while the first is still running it** — duplicate OpenAI calls,
duplicate cost, a race on the record, and `tries` consumed by phantom attempts so a
slow-but-healthy generation is marked Failed.

Given 2.1's timeline, an attempt crossing 90s is not hypothetical. Set
`DB_QUEUE_RETRY_AFTER` above the job timeout (e.g. 180) — a one-line env change, and the
cheapest fix in this document.

## 2.3 A truncated response fails all three tries deterministically

`max_tokens` is 4096 for both `generate_cv` and `analysis_advice`. `ClaudeCvService:141`
throws on `stop_reason === 'max_tokens'`; `OpenAiCvService:164` logs a warning on the same
condition.

Truncation is a function of input length, not luck. A CV long enough to overflow 4096
output tokens will overflow it on every retry — so the user pays the full ~130-second
retry cycle for a failure that was certain from the first attempt. Detect the truncation
signal and fail fast with a specific message ("your CV is too long to generate in one
pass") instead of retrying, or chunk the generation.

## 2.4 The response cache does nothing for real users

`CachedCvAiProvider::remember()` keys on `sha256(normalized full payload | provider |
model | prompt_version)` — and the payload for `generate_cv` includes the entire CV. Every
real CV is unique, so the hit rate on the critical path is effectively zero. With
`CACHE_STORE=database` it adds a DB read before and a DB write of a multi-KB JSON blob
after every generation.

It is not harmful and it does help exact retries, but it should not be counted as a
performance feature. If you want real cache value, key the *sub-operations* —
`enhance_cv_field` on (field, draft, job title, language) has genuinely repeating inputs.

## 2.5 Queue and cache both sit on the database

`.env.example`: `QUEUE_CONNECTION=database`, `CACHE_STORE=database`.

Fine for current volume, and worth naming now because it is the thing that will fail
first under launch load: every worker poll is a `SELECT … FOR UPDATE` against the jobs
table, and every AI response writes a large blob to the cache table. Under concurrency
this shows up to users as generation latency with no obvious cause. Redis for both is the
standard move, and `HEALTH_QUEUE_CONNECTION=redis` is already commented out in
`.env.example`, so the intent was there.

## 2.6 PDF: the font cache is the thing to check first

`CvTemplateRenderer::renderPdfBlob()` constructs `new Mpdf([... 'tempDir' =>
storage_path('app/mpdf'), 'default_font' => 'dejavusans' ...])` per request.

mPDF caches parsed font metrics under `$tempDir/ttfontdata`. DejaVu Sans is a large
full-Unicode face; if that directory is empty, unwritable, or wiped on each deploy,
**mPDF re-parses the font on every single PDF** — a well-documented multi-second cost per
export. This is the first thing to measure, because it is invisible in code and enormous
in wall-clock.

Check in production: does `storage/app/mpdf/ttfontdata/` exist, is it writable, is it
populated, and does it survive deploys? If it lives in an ephemeral container layer, move
it to persistent storage or warm it at build time.

## 2.7 DejaVu Sans is the wrong font for an Arabic-first CV product

`'default_font' => 'dejavusans'` with `autoScriptToLang = false` and
`autoLangToFont = false` — so every glyph, Arabic included, is rendered from DejaVu Sans.
DejaVu's Arabic coverage is minimal and its shaping quality is poor; this is a plausible
contributor to the formatting you saw in yesterday's export, independent of the structural
problems already filed as SIRATI-78.

Ship a proper Arabic face (Amiri, Noto Naskh Arabic, or Cairo) subset for CV use, register
it in mPDF's `fontdata`, and select by language. Subsetting also *reduces* PDF size and
render time, so this is a quality fix that pays for itself in performance.

## 2.8 Every download re-renders the PDF from scratch

`downloadPdf` / `downloadPdfApi` call `renderPdfBlob()` on each request. A generated CV
that has not changed since the last export produces a byte-identical PDF. Store the blob
keyed on (document version, template, language) and serve it directly; re-render only when
one of those changes. For a user who downloads, looks, and downloads again — the normal
pattern — this turns the second and third export into a file read.

## 2.9 Minor: `WriteHTML()` parses CSS on every render

`$pdf->WriteHTML($html)` on a complete document makes mPDF parse the template's CSS in the
same pass, every time. Passing the stylesheet once via `HTMLParserMode::HEADER_CSS` and
the body separately is mPDF's documented faster path. Small, but free.

## 2.10 Generated CVs inherit every ATS scoring defect

`GenerateCvContentJob:69` — `$scorer->score($markdown, $generatedCv->target_job_title)`.
The generated CV is scored by running the same regexes over the AI's markdown, with no job
description and no structured input, even though the structured form payload that produced
it is sitting right there in `form_payload`. Everything in `ATS_SCORING_REVIEW.md` applies
here too, and item 8 of that document (score the structured document, not the flattened
text) is easiest to land on exactly this path.

---

## Coverage note

`device_bash` remains unavailable on this machine, so files were staged into the review
container and read there. Part 1's findings are read directly from the prompt and schema
source. Part 2 is a **configuration and code review, not a measurement** — I have not
profiled a live generation or timed an mPDF render. The retry arithmetic in 2.1 follows
from the declared timeouts and backoff; the font-cache cost in 2.6 is a known mPDF
behaviour that needs one production check to confirm. Both should be measured before the
fixes are sized.
