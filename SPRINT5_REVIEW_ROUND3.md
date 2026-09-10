# Sprint 5 — Review Round 3

Verification of the DeepInfra timeout / fallback change against source, files re-staged.
Source review; suite not run.

**The change is correct and the diagnosis behind it is right.** Round 2's findings 5 and 6
are closed. Three new things, one of which matters for the decision you are actually trying
to make about DeepInfra.

---

## Verified correct

- **Finding 5 closed.** Both remaining call sites now pass the budget and the attempt
  timeout: `DeepInfraCvService:151-152` (`FALLBACK_BUDGET_SECONDS`, `$attemptTimeout`) and
  `ClaudeCvService:116-117` (`HTTP_BUDGET_SECONDS`, `$attemptTimeout`). No call site relies
  on the defaults any more.
- **The budget arithmetic is exactly as claimed.** With budget 100 and attempt 90, the
  guard `remaining >= attemptTimeout` gives `100 - 90 = 10 >= 90` → false, so a full hang
  cannot start a second 90s wait; a 429 at t=1 leaves 99 ≥ 90 → true, so a fast failure
  still retries. That is precisely the behaviour you described.
- **`timeoutFor()` is correctly scoped** — `generate_cv` and `analysis_advice` get 90s,
  everything else stays on `DEEPINFRA_TIMEOUT=45`. Field enhance uses the fast model and is
  unaffected, as stated.
- **The depth guard is sound.** `AiProviderFallback` uses a static counter incremented
  around the secondary and decremented in `finally`, so it unwinds even when the secondary
  throws. `AiTruncationException` and `AiRefusalException` bypass the fallback entirely —
  correct, both are deterministic and a second provider would fail the same way.
- **Finding 6 improved.** The test now asserts `generate_timeout <= FALLBACK_BUDGET` and
  `FALLBACK_BUDGET > DEEPINFRA_GENERATE_TIMEOUT` with the right reason attached. It still
  never asserts what `configure()` actually receives, but with all three call sites now
  correct the exposure is much smaller.

---

## Finding 8 — the response cache now mislabels the provider on the common path — MEDIUM

`CachedCvAiProvider` is unchanged (its file predates this sprint). Its cache key is built
from `$this->provider` and `$this->model` — the **wrapper's** configured provider, not the
one that actually served the response:

```php
'cv_ai:'.$operation.':'.hash('sha256', implode('|', [$normalizedInput, $provider, $model, $promptVersion]))
```

With `CV_AI_PROVIDER=deepinfra`, a generation that DeepInfra hangs on and OpenAI completes
is stored for 24h under a `deepinfra | Qwen/Qwen2.5-72B-Instruct` key. Three consequences:

1. A repeat of the same input serves OpenAI's output **without DeepInfra being called at
   all**, labelled as DeepInfra.
2. `AiCallLog` records `provider = openai` for that call while the cache attributes it to
   DeepInfra. The two sources of truth disagree, and the log is the one that is right.
3. The file's own docblock warns about exactly this — *"switching CV_AI_PROVIDER would
   reuse entries written by the other vendor, which would silently make a Claude-vs-OpenAI
   bake-off compare OpenAI against its own cached output."* The fallback reintroduces it
   from the inside.

This existed before, when OpenAI → DeepInfra was the only direction and DeepInfra was cold.
It matters now because the arrangement is inverted: DeepInfra is the active provider, the
hang you diagnosed is common on `generate_cv`, and so **the fallback is expected to fire
routinely**. Mislabelled output stops being an edge case.

The practical cost is not a broken CV — the user gets a good one. It is that you cannot
answer "is DeepInfra good enough for generate_cv?" from your own data, which is the decision
this whole change is in service of.

**Fix:** key the cache on the provider and model that actually served the response — pass
them back from the fallback, or move the cache decorator below the fallback so each
provider caches under its own identity.

## Finding 9 — the composition invariant is test-time only, and the new knob is a production env — MEDIUM

`DEEPINFRA_GENERATE_TIMEOUT` is read at `config/services.php:53` and you have flagged it as
a production env value. The invariant that keeps it safe —
`generate_timeout <= FALLBACK_BUDGET_SECONDS` — is asserted **only in
`AiTimeoutsCompositionTest`**, against the test environment's config.

Set `DEEPINFRA_GENERATE_TIMEOUT=150` in production and nothing notices: the constant
`FALLBACK_BUDGET_SECONDS` stays 100, the test still passes locally, and one job attempt can
run 150 + ~55 = 205s against a 180s job timeout. The job is killed mid-flight, which is the
failure mode this whole thread has been about.

This is the same class as round-1 finding 2, which you fixed well: the worker `--timeout`
flag was invisible to the invariant, so you moved the check into `HealthMonitor` and `/up`
fails closed. The AI timeout stack now needs the same treatment — it has a production env
knob and only a test-time guard.

**Fix:** add the AI composition check to `assertHealthy()` alongside
`assertQueueRetryAfterExceedsJobTimeouts()`, reading the live config rather than the
constants. Then add `DEEPINFRA_GENERATE_TIMEOUT=90` to `.env.example` with its constraint
comment, the way `DB_QUEUE_RETRY_AFTER` and `QUEUE_WORKER_TIMEOUT` are documented — right
now it is the only knob in this stack that is not written down there.

## Finding 10 — the stated 150s is a floor; the real worst case is ~160s, and the client gap widened — LOW

The budget bounds when a new attempt may *start*, not when the last one ends, so each layer
can overrun by up to one sleep:

| Layer | Stated | Worst case |
|---|---|---|
| DeepInfra primary (budget 100, attempt 90) | 100s | ~105s (fail at t=10, sleep 5, 90s attempt) |
| OpenAI fallback (budget 50, attempt 30) | 50s | ~55s |
| **One job attempt** | **150s** | **~160s** |

Still inside the 180s job timeout, so the invariant holds — but the margin is 20s, not 30s,
and `DEEPINFRA_GENERATE_TIMEOUT` is the env value that eats it (finding 9).

Round 2's finding 7 also got wider rather than narrower. With `tries = 3` and
`backoff = [10, 30]`, a persistent double-provider failure now runs
`160 + 10 + 160 + 30 + 160 ≈ 520s` to `failed()`, against a 210s client poll. The client
stops ~310s before the record turns Failed, where in round 2 it was ~200s. That is the
accepted-with-push-notification decision from round 2, so it is not a new defect — but it is
drifting, and it deserves a number in the ticket rather than being left implicit.

## Minor

`config/services.php:59` still comments *"Production default is openai."* Production is now
DeepInfra. A stale comment on the line that decides which provider is hot will mislead the
next person reading this stack.

---

## On the change itself

The diagnosis is the valuable part: a 45s attempt against a 50s budget meant the guard could
never permit a retry, so a hang consumed the budget and the job failed with nothing tried.
Raising the attempt to 90s **and** the budget to 100s together is the right pair of moves —
raising only the timeout would have kept the guard permanently closed, and raising only the
budget would have let two 45s hangs stack.

Adding DeepInfra → OpenAI symmetry is also right, and the depth guard is the correct shape
for it. The one thing that symmetry brings with it is finding 8: with both directions live
and the active provider being the flaky one, the cache can no longer tell you which model
produced what.

**Blocking sign-off is unchanged and still not code:** the Arabic typography pass
(SIRATI-85) and the dark-mode walk (SIRATI-80). Findings 8 and 9 are worth doing before you
try to judge DeepInfra on production data — otherwise the data will not be able to answer
the question.
