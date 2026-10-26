# Redis queue and cache (production)

Move the queue and cache from the database to Redis so queued CV jobs are
picked up immediately (`block_for` wakes a worker as soon as a job lands)
instead of being polled. Local development keeps the database defaults in
`.env.example`; only production sets the Redis values below.

## 1. Install Redis and phpredis

On the production server (Ubuntu/Debian, PHP 8.3):

```bash
sudo apt-get update
sudo apt-get install -y redis-server php8.3-redis
sudo systemctl enable --now redis-server
redis-cli ping   # expect: PONG
php -m | grep -i redis
```

Keep Redis bound to localhost (the default) unless workers run on other
hosts; never expose port 6379 publicly.

## 2. Environment values (production `.env`)

```dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_QUEUE_BLOCK_FOR=5
HEALTH_QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_QUEUE_RETRY_AFTER=240
```

Invariants:

- `REDIS_QUEUE_RETRY_AFTER` (240) and `DB_QUEUE_RETRY_AFTER` (240) must stay
  above the longest job timeout (`AiTimeouts::JOB_SECONDS` = 180s) and above
  `QUEUE_WORKER_TIMEOUT`, or a still-running job can be released to a second
  worker. `/up` (HealthMonitor) fails closed when this is violated.
- `REDIS_QUEUE_BLOCK_FOR` must be a positive integer (seconds a worker blocks
  waiting for a job). `config/queue.php` casts it with `(int)`, default `5`.
- `HEALTH_QUEUE_CONNECTION=redis` makes `/up` check the Redis queue: oldest
  pending job age and queue size via `LLEN` / `LRANGE` on `queues:default`,
  plus the existing worker heartbeat. If Redis cannot be read, `/up` fails
  loudly (500) rather than reporting healthy.

## 3. Worker command

Run workers with exactly:

```bash
php artisan queue:work redis --queue=default --sleep=1 --tries=3 --timeout=190 --max-time=3600
```

Run **at least 2 worker processes** so one long CV generation job (up to
180s) does not block other jobs behind it. `--max-time=3600` recycles each
worker hourly; Supervisor restarts it.

### Supervisor example

`/etc/supervisor/conf.d/sirati-worker.conf`:

```ini
[program:sirati-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sirati/artisan queue:work redis --queue=default --sleep=1 --tries=3 --timeout=190 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/sirati/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start sirati-worker:*
```

## 4. Switch-over order (database queue -> Redis)

1. Stop pushing new jobs: put the app in maintenance mode (or pause the
   producers) so no new rows are written to the database `jobs` table.
2. Let the database `jobs` table drain: keep a database worker running until
   `jobs` is empty (check `select count(*) from jobs;`).
3. Switch the env values in production `.env` to the Redis block in section 2.
4. Rebuild the config cache: `php artisan config:cache`.
5. Restart workers so they pick up the new connection:
   `php artisan queue:restart` (then start/restart the Supervisor programs
   in section 3 so the new workers use the Redis command).
6. Leave maintenance mode and verify: `GET /up` is 200, and queue timing log
   lines (`Queue job timing` with `wait_ms` / `run_ms`) appear in the log.

## 5. Failed jobs

Failed jobs stay in the database: `failed_jobs` uses the
`database-uuids` driver on the default DB connection regardless of the queue
connection. Inspect/retry them as before:

```bash
php artisan queue:failed
php artisan queue:retry <id>
```
