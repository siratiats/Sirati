# Sirati — CV Form Assist & Template Rendering Brief

**Scope:** AI-assisted form completion in the Flutter app, and fixing the PDF template output.
**Written:** 2026-07-27
**Decisions:** Flutter first · per-field enhance buttons · fix markdown rendering + use structured fields (stay on dompdf)

---

## Part 1 — Root cause of the "weak formatting"

It isn't a design problem. It's a bug: **the generated markdown is never converted to HTML.**

`CvTemplateRenderer::renderHtml()` passes `$generatedCv->generated_markdown` straight into the view, and both templates dump it as escaped plain text:

```blade
{{-- generated-cvs/pdf.blade.php (classic_rtl — the DEFAULT renderer) --}}
<div class="content">{{ $pdfData['content'] }}</div>
```

```css
.content { white-space: pre-wrap; }
```

So the AI returns `## الخبرة العملية` and `- طورت واجهات API` and the PDF literally prints `## الخبرة العملية` and `- طورت واجهات API`. Every heading marker, bullet dash, and `**bold**` renders as raw punctuation. `white-space: pre-wrap` preserves the line breaks, which is why it looks *almost* right and has probably been read as "needs better styling."

Three compounding problems on top:

**The default template has no contact block at all.** `pdf.blade.php` renders name, target job title, and the content blob. Email, phone, LinkedIn and location are collected in the form, stored on the model, exposed by `viewModel()` — and never printed. `modern-rtl.blade.php` does print them, so output differs drastically depending on which template a user picked.

**`viewModel()` builds rich structured data that both templates ignore.** `sections.skills`, `experience`, `education`, `certifications`, `summary`, and `score` are all assembled and then discarded in favour of the single markdown blob.

**Arabic glyph shaping runs at the wrong point in the pipeline.** `formatPdfText()` applies `ArPHP\I18N\Arabic::utf8Glyphs()` to the *raw markdown* before it reaches the view. That function converts Arabic to presentation forms and reorders for RTL — it is meant for final display text, not for a document still containing syntax. Parsing markdown after glyph shaping will not reliably work, because the shaping pass can reorder `##` and `-` markers relative to the text. **Ordering is the single most likely thing to break in this work.** Parse first, shape second, and shape only text nodes.

`league/commonmark` is already installed as a Laravel framework dependency, so `Str::markdown()` needs no new package.

---

## Part 2 — Root cause of the weak form

`GeneratedCvController` validates five free-text blobs:

| Field | Rule |
|---|---|
| `summary_input` | `nullable, max:2000` |
| `skills_input` | `required, max:3000` |
| `experience_input` | `required, min:80, max:12000` |
| `education_input` | `required, max:3000` |
| `certifications_input` | `nullable, max:3000` |

`experience_input` is one textarea accepting up to 12,000 characters. A user facing an empty box with a 80-character minimum has no idea what good looks like, so they type two vague lines — and the AI, correctly forbidden from inventing facts, has nothing to work with. Weak input is the upstream cause of weak output.

**The fix pattern already exists in your code.** `cv_generator_screen.dart` has `_enhanceJobDescription()` (line 552) wired to `AiFieldLoadingOverlay` and a `_aiRequestGen` counter for cancelling superseded requests. That pattern works and users already understand it. This brief extends it to the fields that actually feed the CV, rather than inventing new UX.

---

## Part 3 — Agent prompts

Run Prompt A first — better output makes the form work easier to evaluate. They touch different files and can be parallelised if you have two developers.

---

### PROMPT A — Fix template rendering

```
Fix Sirati's generated CV PDF output. The generated markdown is currently dumped as
escaped plain text, so headings and bullets print as literal '##' and '-'.

Do not change the AI prompts, the OpenAI service, or the form. Rendering only.

## 1. Markdown → HTML with a locked-down parser

Create App\Services\Cv\CvMarkdownRenderer.

Use league/commonmark (already installed via Laravel — add no new package).
Configure a CommonMarkConverter with:
    'html_input' => 'strip'
    'allow_unsafe_links' => false
    'max_nesting_level' => 20

This matters: the markdown is model output derived from user input, and the result
will be echoed with {!! !!} in a Blade template. 'strip' removes any raw HTML the
model emits. Do not skip this and do not use 'allow'.

Restrict the output to a CV-appropriate subset. After conversion, run the HTML
through a whitelist that permits only:
    h1 h2 h3 p ul ol li strong em br hr table thead tbody tr th td
Drop everything else, unwrapping its text content rather than deleting it, so no
candidate information is silently lost.

## 2. Fix the Arabic glyph-shaping ORDER — read this carefully

CvTemplateRenderer::formatPdfText() currently applies ArPHP utf8Glyphs() to the raw
markdown string BEFORE the view renders. utf8Glyphs() converts Arabic to presentation
forms and reorders for RTL display. Running it on text that still contains markdown
syntax will scramble the relationship between '##'/'-' markers and their text, so
parsing afterwards is unreliable.

Correct order:
  1. Parse markdown → HTML (structure now fixed and safe from reordering)
  2. Load the HTML into DOMDocument
  3. Walk TEXT NODES ONLY and apply utf8Glyphs() to each, for Arabic CVs only
  4. Serialise back to HTML

Never pass HTML tags or attributes through utf8Glyphs().

Load with DOMDocument::loadHTML and an explicit UTF-8 meta hint, or the encoding will
be mangled. Suppress libxml errors around the load and restore the previous state.

For English CVs (language === 'en'), skip shaping entirely — it is a no-op at best.

## 3. Rewrite both templates to use the structured data

CvTemplateRenderer::viewModel() already exposes candidate details, per-section inputs,
and score. Both templates currently ignore all of it. Use it.

Both resources/views/generated-cvs/pdf.blade.php (classic_rtl) and
resources/views/generated-cvs/templates/modern-rtl.blade.php get:

  a. A header block with full_name and target_job_title.
  b. A contact line: email, phone, linkedin, location — separated by a middot,
     omitting blanks with no stray separators.
     pdf.blade.php currently prints NO contact details at all. This is the single
     most visible defect in the default template.
  c. The rendered CV body via {!! $pdfData['contentHtml'] !!}
     Keep the escaped {{ }} form for every other field. Only the sanitized HTML
     from step 1 uses {!! !!}.
  d. A footer with the ATS score and grade when score.total is not null.

Contact values must go through the same shaping path as the body for Arabic CVs.
modern-rtl currently shapes name and job title but prints raw email/phone/location —
an Arabic location string renders incorrectly today.

## 4. CSS fixes for dompdf

- Remove 'white-space: pre-wrap' from .content. It is what made raw markdown look
  deliberate, and it will now break real HTML layout.
- Style h1/h2/h3, ul/ol/li, strong/em inside .content. dompdf applies almost no
  default styling, so unstyled headings render nearly identically to body text.
- Remove 'page-break-inside: avoid' from the main content section in
  modern-rtl.blade.php. A full CV exceeds one page, and the rule prevents sensible
  pagination. Keep it only on short blocks like the header.
- Add 'page-break-after: avoid' to headings so a section title cannot be stranded at
  the bottom of a page.
- Set explicit list padding for RTL: dompdf does not handle 'padding-inline-start'.
  Use padding-right for rtl and padding-left for ltr, chosen from $cv['direction'].

## 5. Tests

- Markdown headings and bullets become <h2>/<ul><li>, not literal '##'/'-'
- Raw HTML in the markdown (e.g. <script>alert(1)</script>) is stripped, not rendered
- javascript: links are not emitted
- An Arabic CV shapes text nodes but leaves tag names intact (assert the HTML still
  parses and still contains the expected element names)
- An English CV is not glyph-shaped
- pdf.blade.php output contains email, phone and location when present
- Contact separators do not appear when values are blank
- downloadResponse() still returns a non-empty application/pdf for both templates and
  both languages (4 combinations)
- Existing tests in tests/Feature/CvTemplateFeatureTest.php still pass

Run `composer test` and report results.
```

---

### PROMPT B — AI-assisted form completion (Flutter)

```
Add per-field AI assistance to Sirati's CV generator form in the Flutter app, so
users produce stronger input instead of facing empty textareas.

Follow the pattern ALREADY in lib/screens/cv_generator_screen.dart:
_enhanceJobDescription() (line ~552), AiFieldLoadingOverlay, and the _aiRequestGen
counter that cancels superseded requests. Do not invent a new interaction model.

## 1. Backend endpoint

New method on App\Contracts\CvAiProvider — implement in BOTH OpenAiCvService and
ClaudeCvService, since the interface is shared:

    enhanceCvField(string $field, string $draft, string $jobTitle, string $language): array

$field is one of: summary, skills, experience, education, certifications.

Route (inside the auth:sanctum + verified group, next to the existing
/generated-cvs/enhance-job-description):
    POST /api/generated-cvs/enhance-field

Validation:
    field  => required, in:summary,skills,experience,education,certifications
    draft  => required, string, min:10, max:12000
    job_title => required, string, max:160
    language => required, in:ar,en
Throttle it: 20,1.

## 2. Structured output schema

Add App\Services\Ai\Schemas\EnhanceCvFieldSchema following the existing schema
classes exactly — strict: true, additionalProperties: false, every property in
'required', nullables as union types. Register it in OperationSchemas with an
operation key of 'enhance_cv_field' and max_tokens 2048.

Schema:
    enhanced_text      string   — the rewritten field content
    changes_made       string[] — short Arabic notes on what improved and why
    missing_facts      string[] — specific facts the USER must supply (dates,
                                  employer names, metrics) that the model
                                  deliberately did NOT invent
    ats_keywords_added string[] — keywords introduced, all justified by the draft

missing_facts is the most important field in this feature. It converts the
do-not-invent constraint from an invisible limitation into visible, actionable
guidance. Surface it prominently in the UI (step 4).

## 3. Prompts — the hard constraint

Per-field system prompts, Arabic-first, each stating explicitly:

  Rewrite ONLY what the user wrote. Never add employers, job titles, dates,
  durations, metrics, percentages, certifications, institutions, or technologies
  that are not present in the draft. If a strong CV would normally include such a
  detail and it is absent, do NOT invent a placeholder or a plausible value —
  list it in missing_facts for the user to fill in.

This is stricter than it sounds and it is the whole safety property of the feature.
A user submitting a fabricated CV to an employer is a serious harm, and it would be
Sirati that fabricated it. Add an explicit test for this (step 6).

Field-specific guidance:
- experience: convert duties to achievements; strong Arabic action verbs
  (قدت، طورت، أدرت، رفعت، خفضت); keep each bullet one line; quantify ONLY where the
  draft already contains a number
- skills: group into technical / tools / soft; align to the target job title;
  drop generic filler ('العمل الجماعي' with no context)
- summary: 3-5 lines, role-focused, built strictly from other fields' facts
- education: normalise degree, institution, field, years into consistent order
- certifications: normalise issuer and name; never invent an issuing body

Reuse the Arabic register rules from
App\Services\Ai\Prompts\AnalysisAdviceSystemPrompt::registerSection() — formal MSA,
Arabic HR terms, technical terms left in English. Extract that section into a shared
trait or constant rather than copy-pasting it, so the two cannot drift.

## 4. Flutter UI

For _summaryCtrl, _skillsCtrl, _experienceCtrl, _educationCtrl, _certsCtrl:

- An "حسّن بالذكاء الاصطناعي" action on each field, styled like the existing job
  description enhance button (see line ~1148)
- Disable it until the field has >= 10 characters. The model cannot improve an
  empty box, and letting users tap it on empty input is how they conclude the
  feature is broken.
- Reuse AiFieldLoadingOverlay and the _aiRequestGen cancellation pattern
- On success: replace field text, then show a dismissible result card with
  changes_made, and missing_facts rendered as a visually distinct checklist —
  these are the user's to-dos, not a warning to dismiss
- ats_keywords_added as chips
- Undo: keep the pre-enhance value in memory and offer a single-step revert in the
  snackbar. Users will not trust a destructive rewrite of text they just typed
  without one.
- Follow existing patterns in widgets/form_fields.dart, widgets/app_snack_bar.dart,
  and utils/bidi_text.dart for RTL

## 5. Field guidance before AI is involved

Most of the quality gap is users not knowing what to write. Add, without any API call:
- Arabic helper text under each field with a concrete one-line example
- A character counter on _experienceCtrl showing progress toward the 80-character
  server minimum, so the validation error is never a surprise
- On focus of an empty _experienceCtrl, a dismissible hint showing the target shape:
  'المسمى — الشركة — الفترة، ثم إنجاز أو اثنان بأرقام'

Do this even if the AI work slips. It is free and it addresses the root cause.

## 6. Tests

Backend:
- Endpoint returns the schema shape for each of the 5 field types
- Validation rejects unknown field names and drafts under 10 characters
- Throttle returns 429 past the limit
- Unauthenticated and unverified users are rejected
- FABRICATION GUARD: with Http::fake() returning a response containing an employer
  and a date absent from the draft, assert the service surfaces them via
  missing_facts rather than silently accepting invented content in enhanced_text.
  Document what this test protects.
- Response-cache decorator handles the new operation (key includes field + language)

Flutter (widget tests):
- Enhance button disabled under 10 characters
- Overlay shows during the request and clears on error
- Superseded requests are discarded via _aiRequestGen
- Undo restores the exact pre-enhance text
- missing_facts renders when non-empty

Run `composer test` and `flutter test`. Report both.
```

---

## Part 4 — Sequence and expectations

1. **Prompt A**, verify PDFs by eye in both languages and both templates. This is where the visible quality jump is.
2. **Prompt B step 5** (helper text, counter, hint) — no API cost, ship independently.
3. **Prompt B** remainder.

Two things worth setting expectations on.

**Prompt A will not make the PDF beautiful, only correct.** It fixes structure — real headings, real bullets, contact details present, sane pagination. If you want visually distinct designs after that, it is a separate typography and layout pass, and worth doing once the rendering pipeline is trustworthy.

**Prompt B increases AI spend per CV.** Five enhanceable fields means up to five extra calls per session on top of generation. At `gpt-4.1-mini` rates each call is small, but check `ai:cost-report` after a week rather than assuming. The response cache from the earlier brief helps here — users re-enhancing an unchanged draft cost nothing.

## Open question

`experience_input` as a single 12,000-character textarea is the deeper constraint. Per-field enhancement improves the text inside the box, but the box itself is why the template cannot lay out roles individually — there is no structure to lay out. If output quality still disappoints after both prompts, the structured wizard (per-role entries with employer, dates, and bullets) is the real fix, and it would let templates render experience properly rather than as one prose block.
