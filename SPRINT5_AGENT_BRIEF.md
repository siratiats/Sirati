# Sprint 5 — Implementation Brief

**For the implementing agent. Read this whole file before writing any code.**

Sprint 5 is active on board 36 (id 79), 9–23 Sept 2026.

**Goal:** make CV generation finish, make failure safe to export, make the output
structured — and make the flow fast enough that a user does not stare at a progress
screen for two minutes before seeing an error.

12 tickets. Eleven are engineering. One (SIRATI-70) is store-submission work that runs in
parallel and is **not** yours.

Two review documents at the repo root are the source for most of this and contain
evidence, line numbers and reproduction detail beyond what is summarised here. Read the
relevant section before starting each ticket:

- `AI_LAYER_AND_PERFORMANCE_REVIEW.md`
- `ATS_SCORING_REVIEW.md` (Sprint 5 does not implement this, but SIRATI-84/85 touch the
  same export path)
- `DARK_MODE_CONTRAST_AUDIT.md` (for SIRATI-80)

---

## Non-negotiables

These are the `AGENTS.md` workspace invariants. They are not style preferences; work that
violates them gets sent back.

1. **Test the invariant, not the review example.** If this brief names three broken call
   sites, the test asserts that *no* call site can be broken — not that those three are
   fixed. SIRATI-80 exists precisely because SIRATI-63 tested the example.
2. **Strict quality gates.** `flutter analyze` and the PHP test suite clean. Green
   analysis is the *start* of verification, not the end of it.
3. **Semantic and architectural validation** — not just "it compiles".
4. **Directionality and text boundaries** — every change is checked in Arabic and English,
   RTL and LTR.
6. **A deliverable is not done until something calls it, and the call chain is traced to
   the user-visible end.** This project has a repeated failure pattern: code built, tested
   in isolation, never connected. `normalizeExtracted`, the bidi utilities,
   `previewPdfResponse`, `AppLocalizations`, `detectBaseDirection`, the admin
   grant-premium endpoint — all shipped with no caller. Do not add to that list.
7. **Security gates fail closed.**

*(There is no rule 5 in AGENTS.md — the numbering skips it. Flagged separately; not your
problem for this sprint.)*

**Report honestly.** If a ticket is partially done, say which part. If something could not
be verified, say so rather than implying it was. "Completed" on a ticket whose acceptance
criteria were not exercised is the worst outcome available.

---

## Recommended order

The dependencies are real. Working out of order wastes effort.

### Wave 1 — the two-minute failure (do this first, it is the user's actual complaint)

| Ticket | Why first |
|---|---|
| **SIRATI-82** | One env value. Fixes a correctness bug that corrupts measurements of everything else. |
| **SIRATI-81** | The main latency fix. Cuts worst-case time-to-error from ~130s to under 10s. |
| **SIRATI-83** | Completes the retry story — stops the deterministic failure from burning the retry budget. |

Do 82 before 81, because with `retry_after` wrong you may be measuring double-runs.

### Wave 2 — generation correctness

| Ticket | Note |
|---|---|
| **SIRATI-76** | Timeout, backgrounding, progress reporting. Overlaps Wave 1 — read both before starting either. |
| **SIRATI-78** | Structured experience entries. Unblocks the real fix for the bad PDF. |
| **SIRATI-75** | Export safety. Depends on 78 for what "usable" means. |

### Wave 3 — the export itself

| Ticket | Note |
|---|---|
| **SIRATI-84** | **Measure before coding.** The answer may be a config change, not a code change. |
| **SIRATI-85** | Arabic font. Do after 78, so you are judging typography on correctly structured output. |
| **SIRATI-77** | Preview RTL. |

### Wave 4 — independent

| Ticket | Note |
|---|---|
| **SIRATI-80** | Dark mode. Fully independent of everything above — can be done in parallel by a second agent or whenever blocked. |
| **SIRATI-79** | Polish. Last. |

**Not yours:** SIRATI-70 (store listings, privacy labels, crash-reporting DSN).

---

## Per-ticket briefing

Full detail is in each Jira ticket. This section is the orientation plus the traps.

### SIRATI-82 — `retry_after` (90s) < job timeout (120s)

`config/queue.php:43` vs `GenerateCvContentJob::$timeout`. Laravel requires `retry_after`
to exceed the longest a job can run; it is 30s short, so any attempt past 90s is picked up
by a second worker while the first is still running — duplicate AI calls, a race on the
record, and phantom retries marking healthy jobs Failed.

Set `DB_QUEUE_RETRY_AFTER` above the longest job timeout in `.env.example` **and in
production**. Check `GenerateCvAdviceJob` too.

The test is the invariant: for every `ShouldQueue` class in `app/Jobs`, assert
`$timeout < retry_after` for its connection. Not a test for these two jobs.

### SIRATI-81 — no HTTP retry on AI calls

`OpenAiCvService.php:136-137`, `ClaudeCvService.php:109-110`,
`DeepInfraCvService.php:113-114` — all `->connectTimeout(...)->timeout(...)` with no
`->retry()`.

A transient 429 fails the whole generation attempt, and recovery falls to the job's
`tries=3` / `backoff=[10,30]`: 30 + 10 + 30 + 30 + 30 ≈ **130 seconds** and three
full-price API calls to survive something a one-second HTTP retry absorbs.

Retry at the HTTP layer for connection errors, 429 and 5xx, honouring `Retry-After`.
Reserve job-level `tries` for genuine failures.

**Trap:** all three services share the defect. A test for OpenAI only is a review-example
test — assert the retry policy per provider.

### SIRATI-83 — truncation burns all three retries

`GenerateCvSchema::MAX_TOKENS` and `AnalysisAdviceSchema::MAX_TOKENS` are 4096.
`ClaudeCvService.php:141-150` throws on `stop_reason === 'max_tokens'`;
`OpenAiCvService.php:164` only logs. Truncation is deterministic in input length, so it
fails identically on every retry — the user pays the full ~130s for a certain failure.

Classify truncation as non-retryable, handle it the same way in all three services, and
give the user a message that names CV length as the cause, in Arabic and English.

### SIRATI-76 — generation times out, stops when backgrounded, misreports progress

`cv_generator_screen.dart:101` — `_pollingPaused = state != AppLifecycleState.resumed;` is
why users are told to keep the app open. Polling at `:514`, timeout handling at `:553`
and `:558`.

Read SIRATI-81 first: some of what presents as a client timeout is the server's 130-second
retry cycle. Fixing the client to wait longer without fixing 81 is the wrong fix.

### SIRATI-78 — generator produces one text blob, not structured entries

`app/Cv/CvDocument.php` `fromLegacy()`:

```php
experience: $experienceBlob === ''
    ? []
    : [new ExperienceEntry(narrative: $text($experienceBlob))],
```

The entire experience section becomes **one** entry with a narrative string. This is why
the exported CV is one paragraph.

`resources/views/generated-cvs/templates/_sections.blade.php` already contains a **correct
entries renderer** — title left, `date_range` right with `direction: ltr; unicode-bidi:
embed`, subtitle, location, bullets, `page-break-inside: avoid`. It is never given
entries. You are not writing a renderer; you are feeding the one that exists.

Rule 6 applies hard here: trace it to a rendered PDF, not to a passing unit test.

### SIRATI-75 — export safety

Nothing unusable or internal may leave the app.
`resources/views/generated-cvs/templates/_footer.blade.php` renders
`ATS score: 83% · A` unconditionally whenever `$cv['score']['total'] !== null` — i.e. on
every export. An internal metric is going out on an employer-facing document.

### SIRATI-84 — PDF export performance

**Measure first, code second.** `CvTemplateRenderer::renderPdfBlob()` sets `tempDir` to
`storage_path('app/mpdf')` with `default_font => dejavusans`. mPDF caches font metrics
under `$tempDir/ttfontdata`. If that directory is absent, unwritable, or wiped on deploy,
mPDF re-parses a large full-Unicode font on **every export** — multi-second cost, invisible
in code.

Report the production state of that directory before changing anything. Then: cache the
rendered blob keyed on (document version, template, language), and pass CSS via
`HTMLParserMode::HEADER_CSS` instead of one `WriteHTML($html)` pass.

Give median and p95 render time before and after.

### SIRATI-85 — Arabic in DejaVu Sans

Same method: `'default_font' => 'dejavusans'` with `autoScriptToLang = false` and
`autoLangToFont = false`, so every glyph including Arabic comes from a font with minimal
Arabic coverage. Ship a real Arabic face (Amiri / Noto Naskh Arabic / Cairo), subset it,
register it in `fontdata`, select by language. Record the licence in the repo.

Verify by eye against a reference — "no exception thrown" is not verification of
typography.

### SIRATI-80 — `AppTextStyles` defaults to the light palette

`lib/shared/theme/app_theme.dart:126-180` — seven helpers, all
`final c = colors ?? SiratiColors.light;`. Light `textPrimary` is `0xFF171D1B`, so any
screen that omits the palette renders near-black on the dark surface.

**45** no-palette calls; **18** rescued by a trailing `.copyWith(color:)`; **27** live
defects across 12 files. The ticket has the per-file table. Two are shared widgets
(`app_list_tile.dart:88`, `job_title_picker_field.dart:210`) and clear several screens
each.

**The trap, and the reason this ticket exists:** SIRATI-63 "fixed" this by patching the
profile screen's call sites with `.copyWith(color: context.sirati.…)` and adding a
per-screen test. The `?? SiratiColors.light` default was never touched, so 27 sites in
twelve other files are still broken. **Do not patch call sites.** Make the palette a
required parameter so every unsafe site becomes a compile error and the bug becomes
unrepresentable.

Also delete `AppFormStyles.inputTheme` (`shared/widgets/form_fields.dart:67`) — same
shape, currently zero call sites, and the first widget that reaches for it gets light
fills, borders, labels, hints and error colours on a dark screen at once.

The guard is a repo-wide test: zero `AppTextStyles.*` calls without a palette, and zero
`SiratiColors.light`/`.dark` references outside `lib/shared/theme/`.

Note also: `job_news_screen.dart:482` passes a broken style **into**
`job_title_display.dart:50`'s `primaryStyle ?? AppTextStyles.titleMd()` fallback. Two
independent paths — fixing the widget default alone will not clear that screen.

### SIRATI-77 — preview renders English content RTL

### SIRATI-79 — polish: banner overlaps actions, headline case, inline Arabic

---

## Traps that will cost you a day if missed

1. **Any prompt or schema edit needs two version bumps.** The system prefix in
   `AnalysisAdviceSystemPrompt` is deliberately byte-identical to engage OpenAI prompt
   caching (target 1,400–1,800 tokens). Changing it requires bumping
   `AnalysisAdviceSystemPrompt::VERSION` **and** `CachedCvAiProvider::PROMPT_VERSION`
   (currently `'5'`). The second is the *only* cache-invalidation mechanism — skip it and
   stale advice is served from the old prompt until the TTL expires. Not expected in
   Sprint 5, but SIRATI-83 touches schema files.
2. **`retry_after` before measuring anything.** Do SIRATI-82 first or your latency
   numbers include double-runs.
3. **Patching call sites is not fixing SIRATI-80.** See above.
4. **The entries renderer already exists.** Do not write a second one for SIRATI-78.
5. **`.env.example` and production are two places.** SIRATI-82 and SIRATI-84 both have
   production-side steps that a green test suite will not catch.
6. **Generated CVs are scored by the same broken ATS scorer**
   (`GenerateCvContentJob:69` — `$scorer->score($markdown, ...)`). That is backlog work
   (SIRATI-86 to 91), **not** Sprint 5. Do not start fixing the scorer while doing 75 or
   78; note anything you find and move on.

---

## Definition of done for the sprint

- `flutter analyze` clean; PHP suite green.
- Every ticket's acceptance criteria exercised — not inferred from a passing build.
- The generation flow walked end to end **in both languages**, on a real device, with the
  app backgrounded at least once mid-generation.
- A generated CV exported to PDF in Arabic and in English, and both looked at.
- Dark mode walked across the 12 screens named in SIRATI-80.
- Before/after latency numbers for SIRATI-81 and SIRATI-84 stated in their tickets.
- Anything found but not fixed written up as a new ticket rather than left in a commit
  message.

## When you finish

Post a completion report listing, per ticket: what changed, which acceptance criteria you
actually exercised and how, what you could not verify, and anything you found along the
way that is not filed. That report goes to review — expect the load-bearing claims in it
to be checked against the source.
