# Sprint 5 — Review Round 4 / Code Sign-off

Verification of the round-3 fixes against source, files re-staged. Source review; suite not
run (80 passing taken as stated).

**All three round-3 findings are closed, correctly. No further code findings that block.**
Three low notes below, none of which need doing before ship.

---

## Verified

**Finding 8 — cache attribution.** `CachedCvAiProvider::remember()` now clears
`AiCallContext`, runs the callback, and pulls back the vendor and model that actually
served. When they differ from the wrapper's configured provider, the write goes under the
serving vendor's identity (`:213-224`); lookup stays on the configured key (`:195`). That
gives exactly the behaviour described: a DeepInfra miss retries DeepInfra rather than
serving OpenAI's CV labelled as DeepInfra, and same-vendor model variants (EN Llama vs AR
Qwen) still store under the configured key so English generate does not miss on every
repeat. `logCacheHit` recording `$this->provider` is now correct, because an entry is only
readable when the configured vendor produced it.

**Finding 9 — live enforcement.** `HealthMonitor::assertAiTimeoutsFitInsideJobTimeout()` is
called from `assertHealthy()` and reads live config, not constants: `DEEPINFRA_GENERATE_TIMEOUT`
and `DEEPINFRA_TIMEOUT` against `FALLBACK_BUDGET_SECONDS`, `OPENAI_TIMEOUT` and
`ANTHROPIC_TIMEOUT` against `HTTP_BUDGET_SECONDS`, and the computed worst case against
`JOB_SECONDS`. `DEEPINFRA_GENERATE_TIMEOUT=150` is a 503, as claimed. Same pattern as the
round-1 `retry_after` fix, which is the right consistency.

**Finding 10 — numbers in the stack.** `AiTimeouts::layerWorstCaseSeconds()` derives the
overrun correctly rather than hardcoding it: the last retry may start at
`budget − attempt`, add one sleep, then run `attempt`, ending at `budget + 5`. That is the
derivation, not an estimate. `worstCaseJobAttemptSeconds()` reads live env, and the ~160s
and ~520s figures with the ~310s client gap are in the class docblock where the next reader
will find them. `.env.example:30` carries `DEEPINFRA_GENERATE_TIMEOUT=90`.

**`config/services.php`** no longer claims openai is the production default, and explains
why the `env()` default stays openai — so phpunit does not hit DeepInfra. That reasoning is
worth having written down.

---

## Low notes — none blocking

**1. `ClaudeCvService` does not call `AiCallContext::record()`.** DeepInfra records at
`:251` and OpenAI at `:208`; Claude records nothing. Harmless today: Claude is never a
fallback target, and when it is the configured provider the null path stores under the
configured key, which is correct. But the mechanism is "every provider states its
identity", and one of three does not. The day Claude gains a fallback or becomes one,
attribution silently reverts to the pre-fix behaviour and no test fails. One line.

**2. The cross-vendor cache entry is write-only, by design.** When OpenAI serves a DeepInfra
miss, the entry is stored under an `openai` key that the configured lookup never reads. That
is the correct trade — attribution over hit rate — and the docblock says so. The consequence
worth being deliberate about: an input that *reliably* hangs DeepInfra costs the full
~90s + OpenAI **every single time**, forever, with no learning. Given the failure you
diagnosed is input-shaped (long CVs on Qwen/Llama), that is a real user-facing cost. A
short-TTL negative marker — "DeepInfra hung on this input, go straight to OpenAI" — would
fix it without touching attribution. Worth a backlog ticket, not a fix now.

**3. `/up` now does directory-walking, reflection and a file `require` per request.**
`assertHealthy()` → `QueuedJobTimeouts::maxTimeoutSeconds()` → `jobClasses()` runs a
`RecursiveIteratorIterator` over `app/Jobs` plus a `ReflectionClass` per job, and
`persistentConnectionNames()` does `require config_path('queue.php')`, on every hit. Health
endpoints get polled by load balancers and uptime monitors every few seconds. The AI
timeout check is pure config reads and costs nothing; it is the queue side that walks. A
static memo per process removes it. Small, but it is the kind of thing that only shows up
under production polling.

---

## Code sign-off

The AI timeout stack is in good shape and better than what I asked for at each round. What
stands out across the four rounds: the wall-clock budget replacing an attempt count, the
throwing `timeoutSeconds()`, deriving connections from the config file rather than a list,
moving enforcement into `/up` rather than leaving it in tests, and now deriving the overrun
arithmetic instead of quoting my number back. Each of those is the general fix rather than
the specific one.

Two corrections of my own from this thread, for the record: my SIRATI-81 "~130s before"
figure ignored the DeepInfra fallback (~265s was right), and my round-2 worst case was the
one the stack now computes properly.

**Still blocking sprint sign-off, and unchanged since round 1 — neither is code:**

- **SIRATI-85** — Arabic typography compared against a reference. Registering IBM Plex Sans
  Arabic proves the font loads, not that Arabic shapes, ligates and positions correctly.
- **SIRATI-80** — the dark-mode walk of the 12 screens. The compiler proves no call site can
  pick the light palette; it cannot prove each site picked the right *token*.

Plus the end-to-end generation walk in both languages with the app backgrounded, which is
the acceptance criterion for SIRATI-76 and now has something specific to look for: on a
persistent double-provider failure the client stops at 210s while the job runs to ~520s, so
check what the user sees in that gap and whether the push arrives.

Everything else in Sprint 5 is verified in source and can close.
