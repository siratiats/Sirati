# Sprint 4 — verification of the reported completion

Independent re-derivation of the five invariant checks, read from the working tree rather than taken from the report.

**Verdict: all five verify.** Two of them are implemented more carefully than the report described. One new finding, below, is a config-dependent authentication bypass on the RevenueCat webhook and should be fixed before the endpoint is live.

---

## The five checks

**Check 1 — guests never reach a synchronous LLM call. Verified, and the bug I expected isn't there.**

`CvAnalysisController::store` computes `$isGuest = $request->user() === null`, passes `skipAi: $isGuest` and `queueAi: ! $isGuest`, and `createAnalysis` gates the provider on `if (! $skipAi && $openAi->isConfigured())`. Guests get `$scorer->score()` only.

What I specifically went looking for was the failure this project keeps producing: an authenticated web request marked `Queued` with nothing dispatching the job. It is not there — `store()` dispatches `GenerateCvAdviceJob` at line 52, mirroring `storeApi` at line 71. Both call sites present, both reachable.

`routes/web.php` wraps the public routes in `throttle:60,1` and adds `throttle:ai-heavy` to `POST /analyze` and `POST /generate-cv` specifically.

**Check 2 — prune command scheduled. Verified.** `bootstrap/app.php:88` registers `analyses:prune-guests` inside `withSchedule`, alongside two pre-existing scheduled commands — so the scheduler is already wired and running in this app, which is the part that usually isn't.

**Check 3 — webhook idempotency and mass-assignment. Verified.** `subscription_webhook_events.event_id` is `unique()`. `User::$fillable` contains only `name, email, phone, location, job_title_id, job_title_other, password` — every subscription field is out. The migration backfills legacy `is_premium = true` rows.

**Check 4 — round-trip identity. Verified, and it is the strong form.**
```php
$this->assertEquals($document, $rehydrated);
$this->assertSame($array, $rehydrated->toArray());
$this->assertEquals($document, CvDocument::fromArray($rehydrated->toArray()));
```
`assertSame` on the array compares key order and types, not just contents, and the fixture populates all eight model types including `Skill`'s `id`, `name`, `level`, `category`. That is an invariant, not a field checklist — it will catch the next model that grows a field.

**Check 5 — dynamic text direction. Verified.** `detectBaseDirection` now has two real call sites: `form_fields.dart:682` (inside `buildField`, where `AppTextFormField`'s builder funnels through — line 397 is plumbing, not a second uncovered path) and `app_input.dart:93`.

Seven hardcoded `TextDirection.ltr` remain across the three editors. I checked each: they are email, phone, LinkedIn and project-URL fields. That is correct and deliberate — an email field must not flip RTL on the first Arabic character. The free-text fields pass `null` and resolve dynamically.

---

## New finding — the RevenueCat webhook fails open when unconfigured

`routes/api.php:14` puts `POST /webhooks/revenuecat` outside the `auth:sanctum` group at line 35. Correct for a webhook: its only protection is the shared secret. But the check is conditional on the secret existing:

```php
$secret = config('services.revenuecat.webhook_secret');
if ($secret !== null && $secret !== '') {
    // …compare headers, 401 on mismatch
}
// falls through and processes the payload when no secret is configured
```

**If `REVENUECAT_WEBHOOK_SECRET` is unset or empty in production, the endpoint is completely unauthenticated.** Anyone who can POST JSON to it can mint an `INITIAL_PURCHASE` event for any `app_user_id` and grant themselves — or anyone — premium. A missing environment variable becomes a self-service entitlement grant, silently, with no error anywhere.

This is the same shape as findings from three previous sprints: a control that reads as a gate and is one condition away from not being one. The difference is that this one is gated on deployment config rather than on code, which makes it *less* likely to be noticed.

**Fix, two lines:**

1. **Fail closed outside local.** If the secret is empty and `app()->environment()` is not `local`/`testing`, return 503 rather than processing. An unconfigured webhook should be broken loudly, not permissive.
2. **Use `hash_equals()`** instead of `===` for the comparison. The current `$authHeader === 'Bearer '.$secret` is a timing side channel on the shared secret. Minor beside point 1, but it is the same edit.

Add a test that a request with no secret configured, in a non-local environment, is rejected. That is the assertion that keeps it closed.

---

## Two residuals, neither blocking

**`isPremium()` retains a fallback the report did not show.** The report quoted the clean form. The actual code is:

```php
if ($this->premium_until !== null) {
    return $this->premium_until->isFuture();
}
return (bool) $this->is_premium;
```

I went looking for the trapdoor — a webhook that nulls `premium_until` on expiry, letting a stale `is_premium` silently re-grant. **It does not exist.** `revokeSubscription()` sets `is_premium => false` *and* `premium_until => now()->subMinute()`, so both paths close together. The fallback is a deliberate transition net and it is correctly paired.

What remains is small: the `is_premium` column still exists and is still read. Anything writing it outside the model — a seeder, a manual `UPDATE`, `DB::table()` — grants unexpiring premium that the webhook cannot revoke. Once the backfill is confirmed in production, drop the column and delete the fallback in the same migration. One follow-up ticket.

**Legacy grants became permanent.** The backfill sets `premium_until = '2099-12-31'` for existing `is_premium = true` rows. That is a reasonable default for manually-granted accounts, but it is a decision that was made silently. Confirm those rows are test data; if any are real users, decide whether "forever" is what you meant.

---

## Status

| Check | Result |
|---|---|
| 1 — guests never hit synchronous AI | verified; job dispatch present on both paths |
| 2 — prune command scheduled | verified |
| 3 — webhook idempotency, fillable, backfill | verified |
| 4 — round-trip identity | verified; strict `assertSame`, all 8 models |
| 5 — dynamic bidi direction | verified; 7 remaining pins are all correct |
| **New** — webhook fails open without a secret | **open, fix before the endpoint is live** |
| Residual — `is_premium` column and fallback | open, follow-up |
| Residual — legacy grants set to 2099 | confirm the rows are test data |

Ten of eleven tickets stand. SIRATI-70 correctly moved back to In Progress rather than being closed on a document — that was the right call and it is the first time in this project a ticket has been held open for an acceptance criterion an agent could not satisfy.

## On the process

Two things worth keeping.

The report's summary of `isPremium()` did not match the code. Not misleading in effect — the real implementation is *more* careful than the summary, which is the harmless direction — but it is the reason a review reads source instead of reports, and it is worth the implementing agent quoting actual code rather than intended code.

And the pattern finally broke in the right place. Every deliverable in this sprint has a caller: `detectBaseDirection` has two, the prune command has a schedule entry, the round-trip test asserts an invariant that generalises, and the guest path has a test proving zero AI calls. The one gap left is a control that depends on an environment variable being set — which is the next layer down from "no caller", and worth adding to rule 6 as its own clause: **a gate that can be disabled by a missing config value is not a gate until it fails closed.**
