# Sirati — Pre-Production Brief

**Scope:** The remaining work I'd want done before launch.
**Written:** 2026-07-27
**Environment:** Laravel Cloud, MySQL, queue worker enabled.

---

## What I checked and found already handled

Listed so nobody spends effort re-solving these:

- **Draft persistence** — `cv_generator_screen.dart` saves versioned drafts via `PreferenceStore`, flushes on background/pop, and offers a restore banner. Users don't lose typed input.
- **Graceful AI degradation** — when the AI call fails, `CvAnalysisController` still persists the deterministic ATS score with `ai_status = 'failed'` and `ai_error` recorded. An upload is never lost.
- **Refusal handling** — `AiRefusalException extends UnexpectedValueException`, so it is caught by the existing catch branches in both controllers rather than escaping as a 500.
- **Offline caching, skeletons, empty states, account deletion, privacy policy, signed PDF URLs** — all present.

---

## Priority

| # | Item | Why before launch |
|---|---|---|
| H | Queue the AI calls | Biggest UX risk on weak mobile networks |
| I | Error tracking | Without it you cannot see production failures at all |
| J | Three known rendering defects | One is visible on every Arabic CV |
| — | Scheduler decision | Blocks enabling smart notifications |
| — | Outstanding verifications | Carried from the production hardening brief |

---

## PROMPT H — Move AI calls to the queue

The largest item. Read the compatibility section carefully — there is a shipped app in production.

```
Move Sirati's CV analysis and CV generation AI calls off the HTTP request and onto
the queue, with client polling.

## Why

Both currently call OpenAI inline with a 30-second timeout. On a weak mobile
connection the user waits up to 30 seconds and may lose the request entirely.
The queue worker is confirmed running on Laravel Cloud.

## The schema already supports this

Do not add new status tables or columns. Both cv_analyses and generated_cvs
already have:

    ai_status  VARCHAR(30) DEFAULT 'not_configured'
    ai_error   TEXT NULL

and both are already exposed by CvAnalysisResource and GeneratedCvResource.
Existing values in use: 'not_configured', 'completed', 'failed'.

Add two more: 'queued' and 'processing'. Document all five in a PHP enum
(App\Enums\AiStatus) and replace the string literals across the controllers,
models, and resources so the set cannot drift.

## Flow

1. Controller does the synchronous work it already does — extract text, run
   AtsScoringService, persist the record — but sets ai_status = 'queued' and
   returns 201 immediately with the record.
2. Dispatch a job (App\Jobs\GenerateCvAdviceJob, App\Jobs\GenerateCvContentJob).
3. The job sets 'processing', calls the provider, then writes results with
   'completed', or 'failed' with ai_error.
4. Client polls the existing GET /cv-analyses/{analysis} and
   GET /generated-cvs/{generatedCv} endpoints, which already return ai_status.
   No new endpoints.

## BACKWARD COMPATIBILITY — do not skip this

There is a published app in the stores. Existing installs expect the AI result to
be present in the POST response. If they receive ai_status = 'queued' with a null
ai_feedback, they will render an empty result and users will think the app broke.

Gate the new behaviour on an explicit client opt-in header:

    X-Sirati-Async: 1

- Header present  → queue the job, return 201 with ai_status = 'queued'
- Header absent   → keep the CURRENT synchronous behaviour exactly as it is today

The updated Flutter app sends the header; older installs do not and continue to
work unchanged. Do not use the app version string for this — version comparison
logic is a common source of bugs and the header is unambiguous.

Add a feature test for each path asserting both behaviours from the same endpoint.

Plan to remove the synchronous branch only once store analytics show old installs
have drained. Leave a TODO naming that condition.

## Job configuration

- tries = 3, backoff = [10, 30]
- timeout = 120 (must exceed the provider's 30s HTTP timeout with margin)
- Do NOT retry on AiRefusalException. A refusal is deterministic — retrying burns
  money for the same answer. Catch it, set 'failed', and return without throwing.
- failed() must set ai_status = 'failed' and write ai_error. A job that dies
  without updating status leaves the record stuck on 'processing' forever and the
  client polling indefinitely.
- Queue these on a separate queue name ('ai') so a burst of CV work cannot starve
  push notification delivery. Confirm the Laravel Cloud worker is configured to
  consume it — if it only consumes 'default', these jobs will never run. Flag this
  in your report rather than assuming.

The response cache decorator sits in front of the provider, so a retry with
identical input costs nothing.

## Flutter

lib/widgets/loading/ai_progress_overlay.dart already provides staged status
lines, a cancel affordance and a long-wait timer. Reuse it — do not build a new
progress UI.

- Send X-Sirati-Async: 1 from lib/services/api_client.dart
- After POST returns 'queued', poll the GET endpoint every 2 seconds, backing off
  to 5 seconds after 30 seconds elapsed
- Stop on 'completed' or 'failed'; give up after 3 minutes with a clear Arabic
  message and a retry action
- The analysis record already exists server-side by then, so on give-up route the
  user to the result screen showing the deterministic ATS score with a note that
  AI advice is unavailable. They still get value — do not show a dead end.
- Cancel must stop polling but MUST NOT delete the record; the job may still
  complete and the result appears in history
- Handle app backgrounding: resume polling on return rather than hanging

Write Arabic strings as UTF-8 directly. Do not encode or escape them. After
editing, verify no line matches [Ø Ù â] — that signature means the file was
written with the wrong encoding and must be rewritten, not patched.

## Tests

Backend:
- With the header: 201 returns ai_status 'queued' and the job is dispatched (Bus::fake)
- Without the header: current synchronous behaviour, ai_status 'completed'
- The job writes 'completed' and populates ai_feedback on success
- The job writes 'failed' and ai_error on ConnectionException
- AiRefusalException sets 'failed' and does NOT retry (assert attempt count)
- failed() sets 'failed' — simulate a job dying mid-flight
- The deterministic ATS score is persisted before dispatch, so a never-running
  job still leaves a useful record

Flutter widget tests:
- Polling stops on 'completed' and on 'failed'
- Give-up path routes to the result screen with the deterministic score
- Cancel stops polling without deleting

Run `composer test`, `composer test:mysql`, and `flutter test`. Report all three.
```

---

## PROMPT I — Error tracking and observability

```
Add production error tracking to Sirati. There is currently no way to know a 500
happened unless a user reports it.

## Choose and wire a service

Sentry is the common choice for Laravel + Flutter and covers both from one
vendor. Any equivalent is fine, but it must cover BOTH the Laravel API and the
Flutter app — server-only tracking would miss the client-side failures that
matter most on poor networks.

Laravel: register the handler in bootstrap/app.php withExceptions().
Flutter: initialise in main.dart, wrapping runApp.

## Do not leak candidate data

This is the part that needs care. Sirati processes CVs — names, emails, phone
numbers, employment history. That must not end up in an error tracker.

- Set send_default_pii to false
- Scrub the request body on every AI and CV endpoint. Add resume_text,
  experience_input, education_input, summary_input, skills_input,
  certifications_input, draft, full_name, email, phone, linkedin, and location to
  the scrubbing list.
- Never attach the AI request or response payload to an exception report
- Attach only: user id, operation name, ai_status, model, and duration

Review App\Services\OpenAiCvService and ClaudeCvService: the existing Log::warning
calls include operation and model but not content. Keep it that way and add a
comment saying why, so a future change does not quietly start logging CV text.

## Instrument what matters

Beyond uncaught exceptions, report as handled events:
- ai_status transitions to 'failed', tagged with the operation and error class
- Queue job failures for the new AI jobs and the notification jobs
- Mail send failures from the queued VerifyEmailCode / PasswordResetCode failed()
  handlers, which currently only log
- 429s from the ai-heavy limiter, so you can see whether limits are set too tight

## Health check

bootstrap/app.php already registers health: '/up'. Extend it to verify database
connectivity and that the queue is being consumed — a /up that returns 200 while
the worker is dead is worse than no health check, because it creates false
confidence.

## Tests

- The scrubbing config excludes every listed field (assert against the config,
  not by making a network call)
- The health endpoint returns non-200 when the database is unreachable

Run `composer test` and report results.
```

---

## PROMPT J — Three known rendering defects

Small and independent. Ship whenever.

```
Fix three known defects in Sirati.

## 1. Arabic PDF footer renders reversed

Both templates hardcode Arabic in Blade:

    resources/views/generated-cvs/pdf.blade.php:74
    resources/views/generated-cvs/templates/modern-rtl.blade.php:79

        {{ $cv['language'] === 'en' ? 'ATS score' : 'نتيجة ATS' }}:

Hardcoded Arabic never passes through CvMarkdownRenderer::shapeText(), so it
reaches dompdf unshaped and renders reversed — 'نتيجة' prints as 'ةجيتن' on every
Arabic CV.

Fix: move all user-facing template labels into the view model built by
CvTemplateRenderer::viewModel(), where they go through the same shaping path as
every other value. Grep both templates for any remaining hardcoded Arabic and
move those too.

Add a test asserting no Blade template under resources/views/generated-cvs
contains an Arabic literal — that is the only durable guard against this class of
bug returning.

## 2. Em dash lost before a trailing year

An education line intended as:

    بكالوريوس علوم الحاسب — جامعة الملك سعود — 2020

renders as "...جامعة الملك سعود2020 —": the final em dash moves to the end and the
space collapses. This is bidi neutral resolution around a trailing number.

Fix by wrapping trailing Latin numerals in a directional isolate (U+2066 LRI …
U+2069 PDI) or, if dompdf mishandles isolates, U+200E LRM before the number.
Test both against a real render — do not assume which works.

Add a fixture covering a date range ("2020 — 2023"), a single year, and a
percentage inside an Arabic bullet.

## 3. Remaining mojibake string

flutter_app/lib/widgets/ai_cv_field.dart:262 contains:

    'ØªØ­Ù‚Ù‚ Ù…Ù† Ù‡Ø°Ù‡ Ø§Ù„ØªÙØ§ØµÙŠÙ„ ÙÙŠ Ø§Ù„Ù†Øµ Ø§Ù„Ù…Ø­Ø³Ù‘Ù†'

which should be 'تحقق من هذه التفاصيل في النص المحسّن'.

The string contains invisible C1 control characters, so exact-match find-and-
replace is unreliable. Fix with a byte-level transform on lines matching the
mojibake signature only — a blanket re-decode would corrupt correctly-encoded
Arabic elsewhere in the file.

Then add a CI check (or a test) that fails if any .dart or .php file contains
[Ø Ù â]. This has now occurred twice; a guard is overdue.

Run `composer test` and `flutter test`. Report both.
```

---

## Decisions needed from you

**The 15-minute planner schedule.** `bootstrap/app.php` runs `notifications:plan-daily` every fifteen minutes. The cadence is defensible given per-user preferred times and a 30-minute delivery window, but enabling `SMART_NOTIFICATIONS_ENABLED` now also switches on 96 full-user-base scans per day, each doing several queries per user. Before flipping the flag: run the planner manually against production with the flag on, measure query count and duration, then decide. Consider gating the schedule registration on the config flag so it does not register while disabled.

**Consolidate the schedule.** `fcm:clean-tokens` is in `routes/console.php`; the other two are in `bootstrap/app.php`. Both register, nothing is broken, but a split schedule is how a job gets lost.

**`HistoryScreen` vs `MyCvsScreen`.** Both are linked from `HomeScreen`. If they show overlapping content this will generate support questions. You know what each is meant to be — worth a look.

---

## Still outstanding

- **What did the Step 1 production diagnosis find?** Whether `user_fcm_tokens` exists, and whether the `migrations` row count matches the file count, determines which branch of the fix migration runs and whether other tables are missing.
- **Run `SELECT MAX(LENGTH(token)) FROM user_fcm_tokens;` before deploying that migration.** `->string('token', 512)->change()` errors under MySQL strict mode if any token is longer, and truncates silently without it.
- **Test results for the production hardening work were not reported.** That change alters production schema; both suites should be green, `test:mysql` included, before it deploys.
- **Smart notifications remain disabled** — no re-engagement mechanism at launch.
- **`experience_input` is still one 12,000-character textarea**, which caps template quality. Post-launch project.
