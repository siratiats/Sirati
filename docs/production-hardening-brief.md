# Sirati — Production Hardening Brief

**Scope:** Production schema integrity, AI cost controls, mail delivery, and test/production parity.
**Written:** 2026-07-27
**Environment:** Laravel Cloud, MySQL. Queue worker confirmed enabled.

---

## Context and constraints

Three facts shape everything below.

**Production is MySQL; the test suite is SQLite.** `phpunit.xml` sets `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`. Every green test run to date has validated against a different database engine than the one serving users. Step 1 exists because of a defect this masks.

**The queue worker is running.** This makes Prompt G safe. Queueing mail without a worker would silently stop every verification email — the jobs would enqueue and never process. Do not apply Prompt G to any environment where the worker is not confirmed running.

**The scheduler is a separate toggle on Laravel Cloud.** Confirm it independently. If it is off, `fcm:clean-tokens` has never run, and `notifications:plan-daily` will not run when smart notifications are eventually enabled.

Order matters: Step 1 is diagnostic and blocks Prompt E. Prompts F and G are independent of both.

---

## Step 1 — Diagnose the production schema (do this first, by hand)

`database/migrations/2026_07_02_111500_create_user_fcm_tokens_table.php:14` declares:

```php
$table->string('token', 4096)->unique();
```

MySQL/InnoDB caps an index key at **3072 bytes**. With `utf8mb4` at 4 bytes per character, `VARCHAR(4096)` needs 16,384 bytes — about 5× the limit. MySQL should reject this with:

```
SQLSTATE[42000]: Syntax error or access violation: 1071
Specified key was too long; max key length is 3072 bytes
```

SQLite enforces no such limit, so the test suite never caught it.

**Before writing any code, establish what production actually looks like.** Run against the production database:

```sql
SHOW TABLES LIKE 'user_fcm_tokens';
SHOW CREATE TABLE user_fcm_tokens;
SELECT COUNT(*) FROM user_fcm_tokens;
SELECT migration FROM migrations WHERE migration LIKE '%user_fcm_tokens%';
```

Interpret as follows:

| Finding | Meaning | Action |
|---|---|---|
| Table missing, migration row missing | Deploy failed at this migration; later migrations may also be missing | Check the full `migrations` table against `database/migrations/`. Push notifications have never worked. |
| Table missing, migration row present | Migration recorded but not applied — schema and history have diverged | Manual reconciliation needed before Prompt E |
| Table exists with a shorter column or no unique index | Someone patched it by hand | Prompt E must match production, not the migration file |
| Table exists as declared | Unexpected — investigate the MySQL version and row format before proceeding | Do not assume; confirm |

Also confirm every migration has applied:

```sql
SELECT COUNT(*) FROM migrations;
```

Compare against the file count in `database/migrations/`. A mismatch means the deploy stopped partway and other tables may be missing too.

**Record the findings before continuing.** Prompt E depends on them.

---

## PROMPT E — Fix the FCM token index

Do not run this until Step 1 is complete. Paste the findings into the prompt where indicated.

```
Fix the user_fcm_tokens unique index so it is valid on MySQL/InnoDB.

## Production state (from Step 1 diagnosis — fill this in)

    [ paste SHOW CREATE TABLE output and row count here ]

## The problem

The original migration declares:

    $table->string('token', 4096)->unique();

MySQL/InnoDB limits an index key to 3072 bytes; utf8mb4 VARCHAR(4096) requires
16,384. SQLite ignores this, which is why the test suite passes.

FCM registration tokens are roughly 150-300 characters, so 4096 was
over-provisioned regardless.

## Required change

Write a NEW migration. Do not edit the existing one — it may already be recorded
in the production migrations table, and editing applied migrations puts schema
and history permanently out of sync.

New shape:
  - token        VARCHAR(512), no index. Comfortably fits real FCM tokens with
                 headroom, and no index means no length limit.
  - token_hash   CHAR(64), UNIQUE. SHA-256 of the token. 64 ASCII characters =
                 64 bytes, far under the 3072 limit, and gives exact
                 deduplication rather than a prefix approximation.

The migration must be defensive, because production state is uncertain:
  - Use Schema::hasTable() and Schema::hasColumn() guards throughout
  - If the table does not exist, create it complete with the corrected shape,
    including every column and index from the original migration (user_id FK
    nullable + nullOnDelete, device_id indexed, platform, app_version,
    is_active, last_seen_at, timestamps, and the composite indexes on
    [user_id, is_active] and [device_id, is_active])
  - If it exists, add token_hash, backfill it from existing tokens in chunks,
    then add the unique index
  - Drop the old unique index on token only if it is actually present
  - down() must reverse cleanly

Backfill in chunks of 500 using the query builder, not Eloquent — this may run
against a large table and must not exhaust memory.

## Application changes

App\Models\UserFcmToken: set token_hash automatically whenever token is set. Use
a mutator or a saving() hook so no call site can forget it.

App\Http\Controllers\FcmTokenController: look up and dedupe by token_hash rather
than token. Check every other query against this table and update it too.

Add a guard so a token longer than 512 characters is rejected with a clear
validation error rather than being silently truncated by MySQL.

## Tests

- store() persists token_hash as the sha256 of the token
- Re-registering the same token updates the existing row rather than inserting
  a duplicate
- Two different tokens produce two rows
- A token over 512 characters is rejected with a validation error
- destroy() removes the correct row when matching by hash
- The migration is idempotent: running it against both an empty database and one
  already containing the corrected shape succeeds

Run `composer test` and report results.
```

---

## PROMPT F — Rate limit the AI endpoints

Independent of Step 1. This is the smallest change with the largest cost exposure.

```
Add rate limiting to Sirati's AI-backed API endpoints. They are currently
unlimited for any verified user.

## Current state

routes/api.php, inside the auth:sanctum + verified group:

    POST /cv-analyses                          — no throttle
    POST /generated-cvs                        — no throttle
    POST /cv-analyses/{analysis}/generated-cv  — no throttle
    POST /generated-cvs/enhance-job-description — no throttle
    POST /generated-cvs/enhance-field          — throttle:20,1  (only one covered)

For contrast, auth routes are throttled at 3-5 per minute and /mobile/activity at
30 per minute. Each unthrottled call above is a paid OpenAI request; the CV
routes additionally run a dompdf render. A single account can loop these without
limit.

## Named rate limiters

Define these in bootstrap/app.php (or a service provider, matching this project's
existing structure) using RateLimiter::for():

  'ai-heavy'  — CV analysis and CV generation.
                Per authenticated user: 10 per hour AND 40 per day.
                Use two stacked Limit instances so both apply.

  'ai-light'  — the enhance endpoints.
                Per authenticated user: 20 per minute AND 200 per day.

Key limiters by $request->user()?->id, falling back to IP. These routes are
already behind auth:sanctum, so the user branch is the normal path; the IP
fallback is defence in depth, not the main case.

Apply:
  ai-heavy → POST /cv-analyses
             POST /generated-cvs
             POST /cv-analyses/{analysis}/generated-cv
  ai-light → POST /generated-cvs/enhance-job-description
             POST /generated-cvs/enhance-field  (replaces the inline throttle:20,1)

## Response handling

Return 429 with an Arabic message matching the existing style (see the login
failure message 'بيانات تسجيل الدخول غير صحيحة.'), and include Retry-After.
Distinguish the two cases so the message is actually useful:
  - hourly/per-minute limit hit → "try again shortly"
  - daily limit hit → "you have reached today's limit"

Flutter: handle 429 in lib/services/api_client.dart and surface the server
message through AppSnackBar rather than a generic failure. Users hitting a limit
must understand it is a quota, not a crash.

## Set the numbers deliberately

These figures assume normal use is a handful of CVs per session. Before shipping,
check `ai:cost-report --days=30` for actual per-user call counts and adjust so the
limit sits well above real usage. A limit that fires on legitimate users is worse
than none — it teaches them the app is broken.

## Tests

- The 11th ai-heavy request within an hour returns 429
- The 41st within a day returns 429 even if hourly windows reset
- Limits are per user, not global: user A exhausting their quota does not affect
  user B
- The 429 body carries the Arabic message and Retry-After
- Requests below the limit are unaffected
- Existing tests in CvMvpTest.php still pass — raise limits in the test
  environment if any test loops these endpoints

Run `composer test` and report results.
```

---

## PROMPT G — Queue mail and add MySQL test parity

Safe to apply: the queue worker is confirmed running on Laravel Cloud.

```
Two production reliability fixes for Sirati.

## 1. Queue the transactional mail

App\Notifications\VerifyEmailCode and App\Notifications\PasswordResetCode extend
Notification, not ShouldQueue. Every registration, login-with-unverified-email,
resend, and password reset therefore blocks on an SMTP handshake to
mail.privateemail.com inside the HTTP request.

Change both to implement ShouldQueue and use the Queueable trait.

The queue worker IS running on Laravel Cloud — verify this remains true before
deploying, because queueing mail without a worker silently stops all verification
emails.

Then review App\Services\EmailVerificationService and PasswordResetService: the
registration path currently wraps send() in try/catch specifically so SMTP
failure cannot roll back account creation. Once queued, send() no longer performs
network I/O, so that catch will stop firing for transport errors — they surface as
failed jobs instead. Keep the try/catch (it still guards other failures) but:
  - Add failed() handlers to both notifications that log the user id and reason
  - Confirm the failed_jobs table exists and is being written to
  - Set explicit tries and backoff so a transient SMTP outage retries rather than
    dropping a user's verification code

This is the important part: a dropped verification email means a user who cannot
use the app at all. Silent job failure is worse than the current synchronous
failure, which at least gets reported.

## 2. Run the test suite against MySQL

phpunit.xml uses DB_CONNECTION=sqlite with an in-memory database. Production is
MySQL on Laravel Cloud. This masked the user_fcm_tokens index-length defect and
will mask others — index limits, native JSON column validation, and utf8mb4
collation behaviour for Arabic all differ between the two engines.

Add phpunit.mysql.xml mirroring phpunit.xml but pointing at a MySQL connection,
and a composer script:

    "test:mysql": "@php artisan test -c phpunit.mysql.xml"

Document in the README how to start a matching MySQL locally (Docker one-liner is
fine) and confirm the version matches the Laravel Cloud instance. Do not guess the
version — check the Laravel Cloud dashboard.

Do not replace the SQLite suite. It is fast and good for the inner loop. The MySQL
run is for pre-deploy verification.

Then run the full suite against MySQL and report EVERY failure. Some are expected:
the point of this task is to surface them. Do not fix application behaviour to make
a test pass without first determining whether the test or the application is wrong
— a MySQL-only failure usually means production has a real defect.

Run both `composer test` and `composer test:mysql`, and report both.
```

---

## Configuration changes (no code)

**Move cache and sessions off the primary database.** `CACHE_STORE`, `SESSION_DRIVER`, and `QUEUE_CONNECTION` are all `database`. On MySQL this works, but the response cache written in the earlier AI work stores full CV markdown payloads on every generation, adding write load to the database serving user queries. Laravel Cloud offers managed Redis — point `CACHE_STORE` and `SESSION_DRIVER` at it.

Keep `QUEUE_CONNECTION=database` for now. It is working, the worker is running, and changing the queue backend at the same time as everything else makes failures harder to attribute.

**Confirm the scheduler is enabled.** Separate from the queue worker in Laravel Cloud. If off, `fcm:clean-tokens` has never run, and `notifications:plan-daily` will not run when smart notifications are enabled.

**Add error tracking.** There is currently no way to know a production 500 happened unless a user reports it. Everything in this brief becomes considerably harder to verify without it.

---

## Sequence

1. Step 1 diagnosis. Do not skip — Prompt E depends on the findings, and a partial migration history would change the whole plan.
2. **Prompt F** — independent, smallest, protects a live bill. Ship first.
3. **Prompt E** — once production state is known.
4. **Prompt G part 2** (MySQL parity) — before the next deploy, so E and F are verified against the right engine.
5. **Prompt G part 1** (queued mail).
6. Redis for cache and sessions.

## Still open from earlier reviews

- One mojibake string remains at `flutter_app/lib/widgets/ai_cv_field.dart:262`
- Hardcoded Arabic in both PDF templates bypasses glyph shaping, so the footer renders reversed (`pdf.blade.php:74`, `modern-rtl.blade.php:79`)
- Education line drops its em dash separator before a trailing year
- `tmp/` holds 31 working files and is not in `.gitignore`
- Smart notifications remain disabled and `notifications:plan-daily` is unscheduled
- The `firstAnalysis` notification priority reorder is still awaiting a product decision
