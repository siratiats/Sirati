# Sprint 5 — Review Round 1

Verification of `SPRINT5_IMPLEMENTATION_WHAT_DONE.md` against source. Every claim below
was checked by reading the file it names in the tree as it stands now (files re-staged
this round; a stale copy of `GeneratedCvController.php` nearly produced a false finding on
the export guard, so it was re-staged before asserting).

`device_bash` is still unavailable on this machine, so this is a source review. I did not
run the suite; the reported 301 passed / analyze clean are taken as stated.

**First, the report itself.** It separates "exercised" from "could not verify" throughout,
names the production steps a green suite cannot cover, and lists three findings it chose
not to file. That is the right shape and it made this review faster. The findings below
are things the report does not say, not things it got wrong.

---

## Confirmed accurate

- **SIRATI-80 is fixed the right way.** `AppTextStyles.*` now takes a required positional
  `SiratiColors colors` with `arabic` moved to a named parameter; the `?? SiratiColors.light`
  default is gone from all seven helpers. `AppFormStyles.inputTheme` is deleted. The bug is
  unrepresentable, not merely absent — this is the opposite of what SIRATI-63 did.
- **The invariant test is a real invariant**, and its second assertion is the load-bearing
  one: zero `SiratiColors.light`/`.dark` outside `lib/shared/theme/` is what stops a lazy
  migration passing the light palette explicitly to satisfy the compiler. (The first
  assertion, matching empty parens, is now redundant with the compiler — harmless.)
- **SIRATI-83 truncation is genuinely terminal.** `OpenAiCvService` has
  `catch (AiTruncationException $e) { throw $e; }` ahead of the general `catch (Throwable)`
  in all four operations, so truncation does not fall through to DeepInfra. Both jobs catch
  it alongside `AiRefusalException` without rethrowing. `AiOutputTruncation` covers both
  `length` and `max_tokens`. The exception message carries the bilingual cause and the
  `cv_too_long` code.
- **SIRATI-75 export guard is wired.** `CvExportGuard::assertExportable()` is called in
  both `downloadPdf` (web, signed) and `downloadPdfApi`, and those are the only two PDF
  routes — `routes/api.php:32`, `routes/web.php:33`. The preview routes are HTML-only, so
  nothing unfinished reaches a PDF.
- **The footer gate is correct.** `_footer.blade.php` requires
  `! empty($cv['show_internal_score'])`, `viewModel()` sets it to `! $forExport`, and
  `renderPdfBlobUncached` calls `renderHtml(..., forExport: true)` on both the templated
  and the fallback path. The internal score cannot reach a PDF.
- **SIRATI-78 does not write a second renderer.** `CvDocument::fromLegacy()` delegates to
  `LegacySectionParser`, guarded by `hasStructuredExperience()` so builder-authored entries
  are preserved. Legacy rows re-parse on hydration via `cvDocument()`, so CVs generated
  before this change get entries too — that is the right call and the report does not claim
  credit for it.
- **SIRATI-84 measured before coding**, as asked. Font-cache directory is now created if
  missing, CSS goes through `HEADER_CSS` and body through `HTML_BODY`.
- **SIRATI-85** registers IBM Plex Sans Arabic in `fontdata` and selects it by language
  (`$defaultFont = $language === 'ar' ? 'ibmplexsansarabic' : 'dejavusans'`); English keeps
  DejaVu.
- **`PROMPT_VERSION` correctly not bumped** — no prompt or schema text changed. Right call,
  and good that it was checked rather than skipped silently.

---

## Findings

### 1. The three timeouts don't compose, and the DeepInfra fallback is now unreachable on the slow path — HIGH

This is the one to act on before closing SIRATI-81.

Measured from the constants in the tree:

| Layer | Budget |
|---|---|
| `AiHttpRetry::TIMES` = 3, sleeps ≤ `MAX_DELAY_MS` 5s | — |
| OpenAI attempt: `OPENAI_TIMEOUT=30` | 3×30 + 2×5 = **100s** |
| Fallback DeepInfra: `DEEPINFRA_TIMEOUT=45` | 3×45 + 2×5 = **145s** |
| **One job attempt, worst case** | **245s** |
| `GenerateCvContentJob::$timeout` | **120s** — kills the attempt mid-retry |
| Job `tries=3`, `backoff=[10,30]` | 120+10+120+30+120 = **400s** to `failed()` |
| `CvApiService.pollingTimeout` | **180s** |

Three consequences, none of them in the report's latency table:

**(a) The fallback is effectively dead when OpenAI fails slowly.** OpenAI's own retry chain
consumes 100s of the 120s job budget before the `catch (Throwable)` hands over, so DeepInfra
gets ~20s of its first 45s attempt and is killed. Before Sprint 5, OpenAI failed at 30s and
DeepInfra had a full run inside the same budget. The fallback exists precisely for
"OpenAI is unhealthy", and that is now the case it cannot serve.

**(b) The persistent-failure path got slower, not faster.** The table in the report covers
the transient case, which genuinely improved. For a persistent 429 — quota exhausted, a
provider incident, the case where users all hit it at once — time-to-error went from roughly
265s to roughly 400s.

*(My own SIRATI-81 ticket said ~130s for the before case. That number ignored the DeepInfra
fallback, which I had not traced when I wrote it. ~265s is the correct pre-Sprint-5 figure.
The ticket should be corrected.)*

**(c) The client gives up while the server is still working.** At 180s the app tells the
user it timed out; the job runs on until ~400s and then flips the record to Failed — or to
Completed, if a later attempt succeeds. The user is told one thing and the record says
another. SIRATI-76 raised the client to 180s against the old server budget, not this one.

Also: `AiHttpRetry`'s docblock says the wait is *"capped so the wait cannot exceed the job
timeout"*. That is true of the sleeps (≤10s total) and not of the total (≥100s). Worth
correcting so the next reader is not misled by it.

**Suggested fix:** give the retry a wall-clock budget rather than an attempt count — derive
it from the job timeout, leave headroom for the fallback, and stop retrying when the budget
is spent. Then set the client timeout from the server's real worst case rather than
independently. Whatever the shape, the three numbers should be derived from one source, and
a test should assert `HTTP budget + fallback budget < job timeout < client timeout`. That
is the invariant; the current values are three sensible local choices that do not compose.

### 2. The `retry_after` invariant cannot see the value that actually governs three of the five jobs — MEDIUM

`QueuedJobTimeouts::timeoutSeconds()` falls back to `(new WorkerOptions)->timeout` (60) when
a job declares no `$timeout`. Three of the five jobs declare none:
`SendBulkNotificationJob`, `SendPlannedNotificationJob`, `SendPushNotificationJob`.

What actually governs those in production is the `--timeout` flag on `queue:work`, which
nothing in the repo pins, documents or checks. Run the worker with `--timeout=300` and
`SendBulkNotificationJob` — a fan-out job, exactly the kind someone gives a long timeout —
can run 300s against `retry_after=180` and be double-released, while `/up` stays green
because the check computed max=120.

The health check is a good idea and the right call site. Close the gap: give every
`ShouldQueue` class an explicit `$timeout` so the framework default is never load-bearing,
and pin the worker `--timeout` somewhere the check can read (or at minimum document it
beside `DB_QUEUE_RETRY_AFTER` in `.env.example`).

### 3. Two hand-maintained edges inside the invariant — LOW

- `QueuedJobTimeouts::jobClasses()` uses `glob(app_path('Jobs/*.php'))`, non-recursive. A
  job in `app/Jobs/Notifications/` is invisible to both the health check and the test. Flat
  today; one directory away from silently untrue.
- `persistentConnectionNames()` hardcodes `['beanstalkd','database','redis']` and the
  docblock admits a new connection must be added by hand. Excluding SQS is correct (it has
  no `retry_after`), but the list should be derived from the config — every connection whose
  config has a `retry_after` key — rather than enumerated.

Both are small, and both are the specific failure mode AGENTS.md rule 1 is about: an
invariant that quietly stops covering new cases.

### 4. PDF blob cache survives deploys that change the output — LOW

`renderPdfBlob` keys on `(id, updated_at, template slug, language, ai_status)` with a 1h
TTL. Nothing in the key represents the *rendering code*: edit `_sections.blade.php`, change
the font registration, or fix the footer gate, and cached blobs from the previous deploy
keep serving for up to an hour — including on a rollback. Add a render version to the key
and bump it with any template, font or renderer change (`filemtime` of the template, or a
constant maintained like `PROMPT_VERSION`).

Minor second point: `(string) $generatedCv->updated_at` renders at second resolution, so two
updates inside the same second collide on the key.

---

## Unverified items — which actually block sign-off

The report lists these honestly. Ranking them by whether the ticket can close without them:

**Blocking.** These two tickets are entirely about how something looks, so a passing test
does not touch their acceptance criteria:

- **SIRATI-85** — Arabic typography compared against a reference. Registering a font proves
  the font loads, not that Arabic shapes correctly.
- **SIRATI-80** — the dark-mode walk across the 12 screens. The compiler proves no call site
  can pick the light palette; it does not prove each site picked the *right* token, and
  nothing catches a `textPrimary` that should have been `textSecondary`.

**Blocking for the sprint goal, not the ticket:** the end-to-end generation walk in both
languages with the app backgrounded mid-run. It is the whole point of SIRATI-76, and
finding 1 above predicts a specific behaviour there worth looking for — a client timeout at
180s while the job is still running.

**Acceptable to close on the current evidence:** SIRATI-77's missing widget test (small,
worth adding), SIRATI-79's M7 (correctly reported as not done), and the production env and
`ttfontdata` steps (ops, correctly flagged, but they do need doing — `/up` will now fail
closed if `DB_QUEUE_RETRY_AFTER` is missed, which is a good outcome).

---

## What I would do next

1. Fix finding 1 — it is a live regression on the failure path, and it changes what the
   device walk will show.
2. Do the two visual passes (SIRATI-85, SIRATI-80). They are cheap and they are the
   acceptance criteria.
3. Findings 2–4 in one small pass; none needs its own ticket unless you want the history.
4. Correct the before-figure in SIRATI-81 to ~265s, and update the two tickets that were
   supposed to carry before/after numbers — the report notes Jira was not updated because
   the Atlassian connection was unauthenticated. I can do that from here if you want.
