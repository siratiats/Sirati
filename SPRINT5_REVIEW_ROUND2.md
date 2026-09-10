# Sprint 5 — Review Round 2

Verification of `SPRINT5_REVIEW_ROUND1_VERIFICATION.md` against source, files re-staged
this round. `device_bash` still unavailable; source review, suite not run.

**Three of the four findings are fully closed. Finding 1 is closed in design and has a
wiring bug in two of its three call sites** — the parameter that exists to enforce the new
budget is not passed where it matters most.

---

## Closed, verified

- **Finding 2.** `QueuedJobTimeouts::timeoutSeconds()` now throws when a job omits
  `$timeout`, with a message that names why ("the worker `--timeout` flag is not visible to
  this invariant"). `config/queue.php:22` adds `worker_timeout`, `.env.example` carries
  `QUEUE_WORKER_TIMEOUT=180` **and** the `php artisan queue:work --timeout=$QUEUE_WORKER_TIMEOUT`
  line so the value is actually used. `HealthMonitor` asserts `worker >= maxJob` and
  `retry_after > worker`. The framework default is no longer load-bearing.
- **Finding 3.** Job discovery is now a `RecursiveIteratorIterator` over `app/Jobs`, and
  `persistentConnectionNames()` derives from `require config_path('queue.php')` — every
  connection with a `retry_after` key. Reading the file rather than the runtime config is
  the right call: it keeps test doubles out without a hardcoded list. SQS still correctly
  excluded because it has no `retry_after`.
- **Finding 4.** `RENDER_VERSION = '2'` is in the blob cache key, and `updated_at` is
  keyed as `format('U.u')` so same-second writes no longer collide.
- **The SIRATI-81 before-figure correction** (~265s, not ~130s) is recorded.

`AiTimeouts` as a single source is a good shape, and `AiHttpRetry` switching from an
attempt count to a wall-clock deadline is the right fix — the `when` closure refusing to
start an attempt unless `remaining >= attemptTimeout` is exactly the mechanism that was
missing.

---

## Finding 5 — the budget guard is not wired at two of three call sites — MEDIUM

`AiHttpRetry::configure(PendingRequest $request, int $budgetSeconds = HTTP_BUDGET_SECONDS,
int $attemptTimeoutSeconds = 30)`.

Only `OpenAiCvService` passes them (`:152-153`, `AiTimeouts::HTTP_BUDGET_SECONDS` and its
own `$attemptTimeout`). Both other services call it with a **single argument**:

- `DeepInfraCvService.php:113-118` — `->timeout(config('services.deepinfra.timeout', 45))`,
  but `configure()` receives the default `attemptTimeoutSeconds = 30`.
- `ClaudeCvService.php:105-114` — same shape; correct only by coincidence, because
  `ANTHROPIC_TIMEOUT=30` happens to equal the default. Change that env value and Claude
  acquires the same bug silently.

**What it costs.** The guard admits a new attempt whenever `remaining >= 30`, so for
DeepInfra a failure at t=20s leaves remaining=30, the guard says yes, and the next attempt
runs up to **45s** — ending around t=70 against a stated 50s budget. Worst case per layer:

| Layer | Stated budget | Actual worst case |
|---|---|---|
| OpenAI primary (attempt 30) | 50s | ~55s (deadline + one 5s sleep) |
| DeepInfra fallback (attempt 45, guard 30) | 50s | **~70s** |
| One job attempt | 100s | **~125s** |

The top-level invariant survives — 125s is still inside the 180s job timeout — so nothing
is broken today. But the headroom is a quarter smaller than the constants say, and the
overrun is in the layer with the least margin.

Second, smaller: DeepInfra-as-fallback uses `HTTP_BUDGET_SECONDS`, not
`FALLBACK_BUDGET_SECONDS`. Both are 50 today, so the wiring error is invisible; it goes
live the moment either constant is tuned, which is the point of having two names.

**Fix:** pass both arguments at all three call sites, from each provider's own configured
timeout, and give DeepInfra `FALLBACK_BUDGET_SECONDS` on the fallback path.

## Finding 6 — the composition test asserts constants, not wiring — MEDIUM

`AiTimeoutsCompositionTest::test_one_provider_attempt_fits_inside_its_budget()` asserts
`config('services.deepinfra.timeout') <= FALLBACK_BUDGET_SECONDS`. That passes — 45 ≤ 50 —
while finding 5 is live, because the test never asks what `configure()` actually received.

This is the AGENTS.md rule 1 shape one level up: the invariant checks that the *values* are
consistent, not that the code is *using* them. Every constant in `AiTimeouts` can be
correct while a call site ignores all of them.

The test should exercise the wiring: fake each provider's HTTP layer, drive a 429 → hang
sequence, and assert the elapsed budget per provider — or, more cheaply, assert that each
service passes its own configured timeout into `configure()`. Given that finding 1 was the
highest-severity item in the sprint, its guard deserves a test that would fail if the guard
were disconnected.

## Finding 7 — the invariant still covers one job attempt, not the job — LOW, partially accepted

The stated invariant is `HTTP + fallback < job < client poll`, and the test's own message
says the client poll "must outlive **one full job attempt**". The job still has
`$tries = 3` and `$backoff = [10, 30]`, so a persistent failure runs:

```
125 + 10 + 125 + 30 + 125 ≈ 412s   to failed()
client poll                  210s
```

The client still stops ~200s before the record becomes Failed. This is much better than
round 1 (the mismatch was 180 vs ~400) and the verification document is straight about it
— "gone **for a single attempt**" — but the round-1 finding is presented as closed and this
part is not.

It may be fine to accept: SIRATI-76 added an FCM push on completion or terminal failure,
which is the right answer for work that outlives the foreground session. If that is the
decision, then say so in the invariant, and make the client's timeout copy match it — "we
will notify you when it is ready" rather than presenting 210s as an endpoint. Right now the
client presents a timeout while the server is still working, which is the same shape of
mismatch, just smaller.

---

## Still blocking sign-off — unchanged

Both are visual, both are the whole point of their ticket, and neither is affected by
anything in this round:

- **SIRATI-85** — Arabic typography against a reference.
- **SIRATI-80** — the dark-mode walk of the 12 screens.

And the generation E2E walk in both languages with the app backgrounded. Finding 7 gives
it something specific to watch for: a persistent provider failure will still time out the
client before the record turns Failed, so check what the user is shown in the gap and
whether the push arrives.

Production env remains outstanding and is now two values: `QUEUE_WORKER_TIMEOUT=180` and
`DB_QUEUE_RETRY_AFTER=240`. `/up` fails closed if either is missed, which is the right
outcome — but it means the deploy will report unhealthy until they are set, so set them in
the same change.

---

## Assessment

Findings 5 and 6 are one small commit together and neither blocks the sprint. Finding 7 is
a decision, not a bug. The engineering in this round is good: the wall-clock budget, the
throwing `timeoutSeconds()`, and deriving connections from the config file on disk are all
stronger than what I asked for. The gap is the familiar one for this codebase — a
well-designed mechanism that two of its three callers do not actually use.
