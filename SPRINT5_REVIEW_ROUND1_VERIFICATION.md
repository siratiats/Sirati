# Sprint 5 — Review Round 1 verification

Source review `SPRINT5_REVIEW_ROUND1.md` named four code findings. All four are fixed in tree. Visual passes (SIRATI-85, SIRATI-80 device walk) and the generation E2E walk remain unverified — those are not code.

---

## Finding 1 — HIGH — timeouts now compose

The HTTP retry attempt count could spend ~100s of a 120s job before DeepInfra ran, so the fallback was dead on the slow path and a persistent 429 could run to ~400s while the client gave up at 180s.

**Fix:** one source of truth in `app/Services/Ai/AiTimeouts.php`:

| Layer | Value |
|---|---|
| Primary HTTP wall-clock budget | 50s |
| DeepInfra fallback wall-clock budget | 50s |
| Job `$timeout` (`GenerateCv*`) | 180s |
| Client poll (`CvApiService`) | 210s |
| `retry_after` | 240s |
| `QUEUE_WORKER_TIMEOUT` | 180s |

Invariant, tested: **HTTP + fallback < job < client poll**.

`AiHttpRetry::configure()` takes a wall-clock budget and the provider's attempt timeout. It will not start another HTTP attempt unless remaining time ≥ that timeout, so a 30s OpenAI hang cannot consume DeepInfra's window.

**Tests:** `tests/Unit/AiTimeoutsCompositionTest.php`, wall-clock skip in `AiHttpRetryTest`.

**Corrected SIRATI-81 before-figure:** ~265s (job tries including DeepInfra fallback), not ~130s. Transient 429 still absorbed in the first HTTP attempt.

---

## Finding 2 — MEDIUM — every job declares `$timeout`

`SendPushNotificationJob`, `SendBulkNotificationJob`, `SendPlannedNotificationJob` now have `public int $timeout = 60`. `QueuedJobTimeouts::timeoutSeconds()` throws if a job omits it — the worker `--timeout` flag is no longer load-bearing for the invariant.

`QUEUE_WORKER_TIMEOUT` is in `.env.example`, `config/queue.php`, and phpunit. `/up` fails if it is below the longest job timeout or not strictly less than `retry_after`.

---

## Finding 3 — LOW — invariant edges

- Job discovery walks `app/Jobs` recursively.
- Persistent connections are derived from `config/queue.php` on disk (every connection that has a `retry_after` key). Runtime test doubles are not included. SQS stays excluded because it has no `retry_after`.

---

## Finding 4 — LOW — PDF cache key

`CvTemplateRenderer::RENDER_VERSION = '2'` is part of the blob cache key. Bump it on template, font, or renderer changes. `updated_at` is stored as `U.u` (unix + microseconds) so two writes in the same second do not collide.

---

## Still not code / still blocking sign-off

- SIRATI-85 visual Arabic typography vs a reference.
- SIRATI-80 dark-mode walk of the 12 screens.
- Generation E2E in both languages with the app backgrounded (now worth repeating: client 210s vs job 180s, so the 180s client-timeout-while-job-runs mismatch from finding 1 is gone for a single attempt).
- Production env: `QUEUE_WORKER_TIMEOUT=180`, `DB_QUEUE_RETRY_AFTER=240`.
