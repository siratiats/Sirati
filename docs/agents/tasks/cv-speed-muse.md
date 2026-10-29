Read AGENTS.md and docs/agents/PROJECT_MEMORY.md first.

GOAL: let the server run its queue and cache on Redis so CV jobs are picked up at once, and measure how long
each job waits in the queue. Today QUEUE_CONNECTION=database and CACHE_STORE=database.

Shell note: inside your sandbox `php artisan test` cannot start; use `php vendor/bin/phpunit <file>` with
absolute paths.

TASKS:
1. config/queue.php: redis connection 'block_for' => env('REDIS_QUEUE_BLOCK_FOR', 5) (it is null now, which
   makes workers poll). Keep retry_after (240) larger than the longest job timeout (AiTimeouts::JOB_SECONDS =
   180); check the database connection's retry_after the same way.
2. .env.example: keep database as the local default, and add a commented production block:
   QUEUE_CONNECTION=redis, CACHE_STORE=redis, REDIS_QUEUE_BLOCK_FOR=5, HEALTH_QUEUE_CONNECTION=redis.
3. HealthMonitor queue check (app/Services/HealthMonitor.php, only the queue-age part, not the AI timeout
   part around line 126): it must work when the queue is redis (oldest job age / queue size), and it must
   fail loudly, not pass, if it cannot read the queue (AGENTS.md rule 7).
4. Queue wait timing: new app/Listeners/RecordQueueTiming.php listening to JobProcessing / JobProcessed /
   JobFailed. Log job class, queue, connection, wait_ms (now minus the payload's push time; check
   vendor/laravel/framework/src/Illuminate/Queue/Queue.php for the payload field, e.g. createdAt) and run_ms.
   Never log the payload data. Register it in a new app/Providers/QueueTimingServiceProvider.php added to
   bootstrap/providers.php (one line).
5. docs/deploy/redis-queue.md: install Redis + phpredis, the env values, the worker command
   (php artisan queue:work redis --queue=default --sleep=1 --tries=3 --timeout=190 --max-time=3600), how many
   worker processes to run (at least 2 so a long CV job does not block others), supervisor example, switch-over
   order (stop pushing -> let the database jobs table drain -> switch env -> php artisan config:cache ->
   php artisan queue:restart), and that failed_jobs stays in the database.

DO NOT TOUCH: app/Services/ClaudeCvService.php, app/Services/Ai/*, config/services.php,
app/Services/CvTemplateRenderer.php, app/Http/Controllers/GeneratedCvController.php, app/Jobs/*,
flutter_app/, .env (only .env.example). Do not commit.

TESTS (AGENTS.md rule 1: test the behaviour, not one example):
- new tests/Unit/QueueTimingListenerTest.php: wait_ms and run_ms computed for any push time, missing push
  time does not crash, payload data never appears in the log context.
- a test that redis block_for is a positive int and retry_after > AiTimeouts::JOB_SECONDS for every queue
  connection.
- the HealthMonitor queue test file that already exists, plus your new tests. Run only those.

FINAL ANSWER: files changed, test results, then LEARNINGS: lines.
