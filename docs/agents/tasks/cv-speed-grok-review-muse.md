READ-ONLY REVIEW. Do not edit files, do not commit, do not run the app. You may run the tests named below.
Read AGENTS.md first.

CONTEXT: Muse implemented docs/agents/tasks/cv-speed-muse.md (Redis queue/cache switch, queue health check,
queue wait/run timing). The working tree ALSO holds other uncommitted work (Codex: PDF pre-render; Claude:
Anthropic timeouts). Review ONLY Muse's files:

- config/queue.php (redis block_for)
- .env.example (the commented "Production (Redis queue + cache)" block only)
- app/Services/HealthMonitor.php (queue-age part only; ignore the AiTimeouts line)
- app/Listeners/RecordQueueTiming.php
- app/Providers/QueueTimingServiceProvider.php, bootstrap/providers.php
- docs/deploy/redis-queue.md
- tests/Feature/HealthCheckTest.php, tests/Unit/QueueConfigTest.php, tests/Unit/QueueTimingListenerTest.php

Use `git diff -- <file>` for modified files; the others are new.

CHECK:
1. Double registration: Laravel event discovery scans app/Listeners. Is RecordQueueTiming registered both by
   discovery and by QueueTimingServiceProvider (so every job logs twice)? Check bootstrap/app.php withEvents.
2. wait_ms: which payload field gives the push time (verify in vendor/laravel/framework/src/Illuminate/Queue/Queue.php)?
   Is it correct for delayed jobs and for retries after backoff (the CV job has backoff [10, 30])? Missing field
   must not crash. Payload data (CV text, user data) must never reach the log context.
3. Health check on redis: does it measure the real oldest-job age or queue size correctly, and does it fail
   closed loudly when Redis cannot be read outside local (AGENTS.md rule 7), without breaking local/testing?
4. Timeouts: redis retry_after (240) > worker --timeout (190 in the doc) > AiTimeouts::JOB_SECONDS (180);
   same for the database connection. block_for env cast is safe for empty/0 values.
5. Deploy doc: switch-over order drains the database jobs table first, config:cache + queue:restart, at least
   2 workers, supervisor stopwaitsecs longer than the job timeout, failed_jobs stays in the database.
6. Tests follow AGENTS.md rules 1 and 3: they test the behaviour for arbitrary inputs, not one example, and do
   not regex-scan PHP source.

RUN ONLY: php artisan test tests/Feature/HealthCheckTest.php tests/Unit/QueueConfigTest.php tests/Unit/QueueTimingListenerTest.php

ANSWER (max ~40 lines): first line VERDICT: LGTM or VERDICT: CHANGES. Then findings ranked by severity, each
with file:line, the concrete failure scenario, and the fix in one sentence. Then the test result line.
