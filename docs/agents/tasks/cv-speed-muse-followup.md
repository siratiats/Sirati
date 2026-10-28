FOLLOW-UP on your Redis/queue work (docs/agents/tasks/cv-speed-muse.md). A Grok review found three bugs.
Fix them. Same rules as before: only your files, do not commit. Shell: use `php vendor/bin/phpunit <file>`
with absolute paths.

1. HIGH - app/Services/HealthMonitor.php:144 reads the TAIL of the Redis list (`lrange($key, -1, -1)`), which
   is the newest job. Laravel RPUSHes and LPOPs (vendor/laravel/framework/src/Illuminate/Queue/LuaScripts.php),
   so the oldest pending job is index 0 (RedisQueue::creationTimeOfOldestPendingJob uses lindex(..., 0)). A job
   stuck at the head for 500s stays invisible while new jobs arrive and /up stays 200. Read index 0.
   Test: a queue of several jobs where ONLY the head is older than max_age_seconds must fail the check;
   a queue where only the tail is old must pass. Use any number of jobs, not one fixed example.

2. HIGH - double logging. Laravel's default Application::configure() turns on event discovery, which
   registers every public handle* method of classes in app/Listeners (vendor/.../Foundation/Events/DiscoverEvents.php).
   QueueTimingServiceProvider ALSO calls Event::listen for the same methods, so every job is logged twice and
   the second line has null wait_ms/run_ms. Remove the manual Event::listen calls (keep the singleton binding
   so start times are shared), or move the class out of app/Listeners - pick one, not both.
   Test: dispatch one job through the real event dispatcher and assert exactly ONE timing log line per event.

3. MEDIUM - app/Listeners/RecordQueueTiming.php:70 uses createdAt, the ORIGINAL push time. On a retry
   (GenerateCvContentJob backoff [10, 30]) wait_ms includes the previous run and the backoff. When the raw body's
   `attempts` is > 0 (or the job's attempts() > 1), log wait_ms as null and add `attempt` to the context.
   Test: for any attempt number > first, wait_ms is null; for the first attempt it is computed.

Run only: tests/Feature/HealthCheckTest.php, tests/Unit/QueueConfigTest.php, tests/Unit/QueueTimingListenerTest.php.

FINAL ANSWER: files changed, test results, LEARNINGS: lines.
