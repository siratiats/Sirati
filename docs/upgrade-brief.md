# Sirati — Technical Upgrade Brief (v2)

**Scope:** Cut AI token cost via caching, add structured job titles for notification targeting, and settle the OpenAI-vs-Claude question with evidence instead of assumption.
**Written:** 2026-07-27 · **Revision:** v2 — supersedes v1
**Status:** Not started. No code changed yet.

> **What changed from v1 and why.** v1 assumed a Claude migration and treated it as Phase 1. Checking current pricing and API capabilities inverted that conclusion: `gpt-4.1-mini` is roughly **2.8× cheaper per analysis** than Claude Haiku 4.5, and OpenAI already offers the guaranteed-valid-JSON feature I had cited as the strongest reason to switch. The migration is now demoted to a time-boxed experiment gated on one question only — Arabic quality. Everything else here is vendor-independent and worth doing regardless of how that experiment lands.

---

## Part 1 — The cost case for staying on OpenAI

### Per-token pricing

| | Input | Output | Cached input | Cache write penalty |
|---|---|---|---|---|
| **gpt-4.1-mini** (current) | $0.40 / MTok | $1.60 / MTok | $0.10 / MTok | None on this generation |
| Claude Haiku 4.5 | $1.00 / MTok | $5.00 / MTok | $0.10 / MTok | 1.25× (5m) / 2× (1h) |

### Cost per CV analysis

Using a representative call — ~3,000 input tokens (CV text capped at 7,000 chars, plus score JSON and prompt) and ~800 output tokens:

| | Cost per analysis | Relative |
|---|---|---|
| **gpt-4.1-mini** | **$0.00248** | 1.0× |
| Claude Haiku 4.5 | $0.00700 | 2.8× |

Migrating to Haiku 4.5 would have **roughly tripled your inference bill**. v1 flagged this as "verify against your invoice"; the numbers now confirm it.

### Three more points that favour staying

**Cached reads cost the same on both — $0.10/MTok.** So caching does not differentiate the vendors. It just makes the cheaper base rate matter more.

**OpenAI's caching floor is 1,024 tokens; Haiku 4.5's is 4,096.** A 4× lower bar to qualify. On OpenAI a solid Arabic ATS rubric alone clears it; on Haiku you would have needed a much larger prompt built primarily to satisfy the cache minimum rather than because the content earned its place.

**OpenAI caching is automatic and carries no write premium on this model generation.** No `cache_control` markers, no breakpoint placement, no 4-breakpoint budget, no risk of paying 1.25× for writes that expire unread. The 1.25× write billing applies to GPT-5.6-and-later families, not `gpt-4.1-mini`. This removes the single largest failure mode described in v1 Phase 3 — the scenario where caching silently costs *more* than not caching.

### The correction you should hold me to

**v1 said structured outputs were "the single strongest technical argument for the migration." That was wrong.** OpenAI has had the equivalent since 2024: `response_format: {type: "json_schema", json_schema: {..., strict: true}}` uses constrained decoding and guarantees schema adherence, not merely valid JSON syntax.

Your code currently uses `response_format: {type: 'json_object'}` — **JSON mode**, the weaker of the two. It guarantees parseable JSON but *not* that your expected keys exist or have the right types. That is why `OpenAiCvService::requestJson()` still needs:

```php
throw new UnexpectedValueException('OpenAI returned a non-JSON response.');
```

You can close that gap today, on your current vendor, by switching one parameter. See Phase 2. This was the best argument for migrating, and it turns out to be an argument for a config change instead.

### What remains genuinely unresolved

**Arabic output quality.** I cannot settle this from documentation, and neither can any benchmark you will find — MENA-market CV advice in formal MSA is too narrow a task. It needs your own eval on your own CVs, judged by a native speaker. That is Phase 5, deliberately placed *after* the work that pays off either way. Do not let it block the cost savings.

---

## Part 2 — Where the savings actually come from

Ranked by size of saving, which is not the order you would guess:

| Lever | Saving | Effort | Vendor-dependent? |
|---|---|---|---|
| **Response-level cache** | **100% on duplicate calls** | ~40 lines | No |
| Prompt caching (static prefix) | ~15–20% on every call | Prompt redesign | No |
| Vendor choice | 0% (staying put) | — | — |

**The response cache dominates and it is barely any code.** A prompt cache hit costs 10% of base input price; a response cache hit costs *nothing*, because no request is made. Users tweak and re-analyse the same CV repeatedly, and every one of those is currently billed in full.

Prompt caching is worth doing but is a second-order effect. With a 1,500-token static prefix on `gpt-4.1-mini`, a cache hit saves $0.00045 per call — about 18% of a single analysis. Real, not transformative.

**The structural constraint from v1 still holds, at a lower bar.** OpenAI caches the longest previously-computed *prefix*, starting at 1,024 tokens and growing in 128-token increments. Your shared prefix today is just the ~60-token system prompt — far under the floor, so **you currently get no caching at all**. Static content must come first and grow past 1,024 tokens; variable content (CV text, score JSON, job title) must come last. Phase 3 does exactly this.

Note the TTL: caches clear after 5–10 minutes of inactivity and are always evicted within an hour. At low traffic most entries expire unread — which on OpenAI costs you nothing extra, but also means the benefit only materialises once request volume is dense enough. Phase 0 tells you whether it is.

---

## Part 3 — Prioritized phases

| # | Phase | Why this position | Risk |
|---|---|---|---|
| 0 | Instrumentation baseline | Decision gate for everything below. Half a day. | None |
| 1 | Response-level cache | Largest saving, least code, no vendor coupling. | Low |
| 2 | Structured outputs on OpenAI | Deletes a live failure path. One parameter. | Low |
| 3 | Prompt restructure → automatic caching | Cuts per-call cost ~18%; doubles as quality work. | Medium |
| 4 | Job title taxonomy at registration | Independent — parallelisable. | Low |
| 5 | Notification targeting on job title | Depends on Phase 4. Delivers the business goal. | Low |
| 6 | *(Optional)* Arabic quality bake-off | Only reason to revisit vendors. Time-boxed. | Medium |

Phases 4 and 5 share no files with 1–3 and can run concurrently with a second developer.

---

## Part 4 — Agent prompts

Hand these to the AI agent one at a time. Phases 2 and 3 touch the same file — do not run them concurrently.

---

### PHASE 0 — Instrumentation baseline

```
Add AI-call instrumentation to the Sirati Laravel app. Measurement only — do not
change any prompt text, model config, or controller behaviour.

1. Migration for an `ai_call_logs` table:
   id, provider (string), model (string), operation (string: analysis_advice |
   generate_cv | enhance_job_description), input_tokens, output_tokens,
   cached_tokens (unsigned int, default 0), duration_ms, was_response_cache_hit
   (bool, default false), user_id (nullable FK), created_at.
   Index on (operation, created_at).

   Note `cached_tokens` — OpenAI reports prompt-cache hits in
   usage.prompt_tokens_details.cached_tokens. Capture it now even though it will
   read 0 until Phase 3, so you get a clean before/after.

2. In App\Services\OpenAiCvService::requestJson(), write one row per call.
   Wrap in try/catch that reports but never rethrows — logging must not break a
   user request.

3. Artisan command `ai:cost-report {--days=30}` printing per operation: call count,
   total input/output/cached tokens, mean and p95 duration, response-cache hit rate,
   and estimated spend using gpt-4.1-mini rates ($0.40/MTok input, $1.60/MTok
   output, $0.10/MTok cached input). Console table format.

4. Feature test: a log row is written on success, and a logging failure does not
   propagate to the caller.

Follow existing code style — constructor property promotion, readonly properties,
strict return types.

Run `composer test` and report results. Do not commit.
```

**Run this in production for at least a week before Phase 3.** If volume turns out to be low, Phase 3's ~18% saving may not justify the prompt redesign, and you should skip straight to Phase 4.

---

### PHASE 1 — Response-level cache

Biggest saving, smallest change. Do this first.

```
Add response-level caching to Sirati's AI calls to eliminate duplicate API requests.

## 1. Extract an interface

Create App\Contracts\CvAiProvider with the three methods currently on
OpenAiCvService — identical signatures and return shapes:
  analysisAdvice(array $score, string $resumeText, string $jobTitle): array
  generateCv(array $data): array
  enhanceJobDescription(string $jobTitle, ?string $jobDescription, string $language): array

Make OpenAiCvService implement it. Change no behaviour. Update the type hints in
CvAnalysisController and GeneratedCvController to depend on the interface. Touch
nothing else in those controllers.

(This interface also makes the Phase 6 vendor bake-off possible without a rewrite.
That is a side benefit — the decorator below is the actual point.)

## 2. Caching decorator

App\Services\Ai\CachedCvAiProvider implements CvAiProvider and wraps another
CvAiProvider instance.

Cache key:
  'cv_ai:' . $operation . ':' . hash('sha256', $normalizedInput . '|' . $model
    . '|' . self::PROMPT_VERSION)

Normalization rules — these matter, get them right:
- Collapse whitespace runs to a single space, then trim.
- Use mb_* functions throughout. Do NOT use strtolower() or trim() on Arabic —
  they are not multibyte-safe and will corrupt the key.
- Do NOT strip Arabic diacritics. They are semantically meaningful in some CV
  terminology and removing them would collide distinct inputs.
- Normalize Arabic-Indic digits (٠-٩) to ASCII before hashing, so the same CV
  typed with either digit set hits the same entry.

PROMPT_VERSION is a class constant, bumped by hand whenever prompt text or schema
changes. This is the only thing preventing stale results from surviving a prompt
edit — document it prominently in the class docblock.

TTL: 7 days for analysisAdvice and generateCv, 24 hours for enhanceJobDescription.
Configurable via config/services.php.

## 3. Wiring

Register the decorator in AppServiceProvider so it wraps the concrete provider.
Gate on config('services.cv_ai.response_cache_enabled'), default true.

On a cache hit, write an ai_call_logs row with was_response_cache_hit = true and
zero tokens, so the Phase 0 report shows the saving.

## 4. Storage check

The cache store is already the `database` driver — no new infrastructure needed.
But generateCv returns a full CV markdown document. Inspect the value column type
on the existing cache table and widen it to LONGTEXT in a migration if it is not
already large enough. Verify by round-tripping a realistic ~20KB payload in a test.

## 5. Tests

- A second identical call does not reach the underlying provider (assert with a mock)
- Whitespace-only and case-only differences in Arabic input produce the same key
- Arabic-Indic and ASCII digits produce the same key
- Diacritic differences produce DIFFERENT keys (assert the non-stripping behaviour)
- Bumping PROMPT_VERSION produces a different key
- A different model in config produces a different key
- A cache miss falls through and stores the result
- A ~20KB payload round-trips through the cache intact

Run `composer test` and report results.
```

---

### PHASE 2 — Structured outputs on OpenAI

```
Replace JSON mode with OpenAI Structured Outputs in Sirati, eliminating the
malformed-response failure path. Stay on gpt-4.1-mini. Change no prompt text.

## The change

App\Services\OpenAiCvService::requestJson() currently sends:
    'response_format' => ['type' => 'json_object']

That is JSON mode: it guarantees parseable JSON but NOT that your expected keys
exist or have correct types. Replace with Structured Outputs:

    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => <operation name>,
            'strict' => true,
            'schema' => <schema>,
        ],
    ]

With strict: true the model uses constrained decoding and cannot emit output that
violates the schema.

## Schemas

Define one per operation in App\Services\Ai\Schemas, matching the keys the existing
code already reads:

- analysis_advice: executive_summary (string), top_priorities (string[]),
  rewritten_summary (string|null), keyword_recommendations (string[]),
  bullet_improvements (array of object{before: string|null, after: string,
  reason: string}), warnings (string[])
- generate_cv: cv_markdown, headline, professional_summary (strings), core_skills,
  improved_experience_bullets, ats_notes, missing_information (string[])
- enhance_job_description: enhanced_description (string), suggested_keywords,
  responsibilities, requirements (string[])

Strict-mode schema rules that WILL 400 your request if ignored:
- Every object needs "additionalProperties": false
- Every property must be listed in "required". Optional fields are not supported
  in strict mode — model nullability as a union type instead:
  "rewritten_summary": {"type": ["string", "null"]}
- Nested objects follow the same rules recursively

## Error handling

Structured Outputs can still fail to match in two cases — handle both:
- A refusal: the response carries a `refusal` field instead of `content`. Throw a
  distinct AiRefusalException. Do not retry.
- Truncation: finish_reason == 'length' means output was cut off and is invalid.
  Log and throw. Raise max_tokens to 4096 for analysis_advice and generate_cv,
  2048 for enhance_job_description.

Keep the existing UnexpectedValueException as a final defensive fallback, but it
should now be unreachable in practice. Add a log line if it ever fires — that would
indicate a genuine API contract change worth knowing about.

## Timeout

Raise services.openai.timeout from 15 to 30 seconds. Structured Outputs adds
one-time schema-processing latency the first time each new schema is used; a 15s
timeout risks intermittent failures on cold schemas.

## Tests

- Feature test per operation with Http::fake() returning a schema-valid body
- A refusal response throws AiRefusalException
- finish_reason 'length' throws
- Existing tests in tests/Feature/CvMvpTest.php still pass unchanged
- Assert every schema sets strict: true and additionalProperties: false

Run `composer test` and report results.
```

---

### PHASE 3 — Prompt restructure for automatic caching

Do not start until Phase 0 has produced a week of real data. If call volume is low, skip this.

```
Restructure Sirati's OpenAI prompts so the static prefix exceeds 1,024 tokens and
OpenAI's automatic prompt caching begins applying.

## Context

OpenAI caches the longest previously-computed prefix, starting at 1,024 tokens and
growing in 128-token increments. Caching is automatic — there are no cache_control
markers to place. But Sirati's shared prefix today is only the ~60-token system
prompt, so NO caching currently occurs. This phase makes the prefix large enough to
qualify.

The added content must earn its place on quality grounds, not merely pad the token
count. Everything below is content that improves output regardless of caching.

## 1. Build the static prefix

For analysis_advice, construct a system prompt containing, in this order:

  a. The existing role instruction and do-not-invent constraint, VERBATIM. It is
     doing real work — do not paraphrase it.
  b. The full ATS rubric: all 7 criteria from App\Services\AtsScoringService with
     max scores (format 15, keywords 30, structure 15, experience 20, education 10,
     summary 5, contact 5) and the Arabic labels already defined there. Explain what
     high and low scores mean for each.
     Read these FROM the service at runtime — do not hand-copy into a string where
     they can silently drift out of sync with the scorer.
  c. The 8 job categories and keyword banks from AtsScoringService::JOB_KEYWORDS,
     generated from the constant for the same reason.
  d. 12–15 few-shot examples of strong Arabic CV advice: weak bullet, improved
     bullet, reason. Spread across several of the 8 categories.
  e. Arabic register guidance: formal MSA; no transliterated English where an Arabic
     term exists; correct mixed RTL/LTR handling when technical terms stay in English.

Target 1,400–1,800 tokens — comfortably clear of the 1,024 floor with margin for the
128-token increment rounding. Verify the real count with tiktoken or the API's usage
response; do not estimate.

Store as a versioned PHP class, not an inline heredoc, so it can be diffed in review.

## 2. Ordering — this is the whole trick

Static content FIRST, variable content LAST:

  messages: [
    { role: 'system',  content: <static prefix, identical every request> },
    { role: 'user',    content: <CV text + score JSON + job title> },
  ]

Any per-request value that leaks into the system prompt — a timestamp, the user's
name, the job title — breaks the prefix match and drops the hit rate to zero. Add a
test asserting the system prompt is byte-identical across two different CV inputs.

## 3. Interaction with Phase 2

Changing the schema changes the request and can affect cache behaviour. Freeze the
Phase 2 schemas before enabling this, and treat any schema edit as a cache-affecting
deploy. Note it in the class docblock.

Also: bump CachedCvAiProvider::PROMPT_VERSION when the prefix changes, or Phase 1
will keep serving responses generated by the old prompt.

## 4. Verify it worked

Extend `ai:cost-report` with: cached token count, cache hit rate
(cached_tokens / input_tokens), and estimated saving at $0.10/MTok for cached input
versus $0.40/MTok uncached.

Acceptance criterion: if usage.prompt_tokens_details.cached_tokens is 0 across a full
day of real traffic, caching is not engaging. Check the prefix length first, then
check for per-request values contaminating the system prompt. Do not assume it is
working because the code looks right.

## Tests

- The system prompt is byte-identical across two different CV inputs
- The prefix exceeds 1,024 tokens (assert against a real tokenizer)
- Rubric values in the prompt match AtsScoringService's constants (guards drift)

Run `composer test` and report results.
```

---

### PHASE 4 — Job title taxonomy at registration

Independent of Phases 0–3. Parallelisable.

```
Add a structured job title to Sirati user registration, backed by a seeded taxonomy,
to enable notification targeting.

## 1. Schema

Migration for `job_titles`:
  id, slug (unique string), name_ar, name_en (strings),
  category (string — MUST be one of the 8 keys in AtsScoringService::JOB_KEYWORDS:
  ecommerce, marketing, software, data, management, finance, hr, sales),
  keywords (json, nullable), is_active (bool default true),
  sort_order (int default 0), timestamps.
  Index on (is_active, sort_order).

Migration adding to `users`:
  job_title_id (nullable FK to job_titles, nullOnDelete)
  job_title_other (nullable string 120)
  Both after `location`, matching the pattern in
  2026_07_16_000001_add_phone_location_to_users_table.php.

Why category reuses the existing 8 keys: AtsScoringService already buckets jobs into
these for keyword scoring. Sharing the vocabulary means registration, ATS scoring,
and notification matching stay consistent instead of drifting into three
incompatible taxonomies. Do not invent a new category set.

## 2. Seeder

JobTitleSeeder with 60-80 real titles across all 8 categories, weighted toward the
MENA/Gulf market. Arabic name primary, English secondary. 3-6 keywords each, used
for job matching in Phase 5.

Final row: slug 'other', name_ar 'أخرى', name_en 'Other', category 'management',
sort_order 999.

Idempotent — updateOrCreate on slug. Register in DatabaseSeeder.

## 3. Model and API

App\Models\JobTitle with scopeActive and a users() HasMany.
On User: belongsTo jobTitle(), and add 'job_title_id' + 'job_title_other' to $fillable.

New PUBLIC endpoint — must sit OUTSIDE the auth:sanctum group in routes/api.php,
alongside the other public /mobile/* routes, because it is needed before signup:
  GET /api/mobile/job-titles
Active titles ordered by sort_order then name_ar, via a JobTitleResource.
Server-side cache for 24 hours; this list changes almost never.

## 4. Registration validation

In MobileAuthController::register(), add:
  'job_title_id' => ['nullable', 'integer', 'exists:job_titles,id'],
  'job_title_other' => ['nullable', 'string', 'max:120'],

Then: if the selected job_title_id has slug 'other', job_title_other becomes
REQUIRED. Implement via a custom rule or after-validation hook. Error message in
Arabic, matching the existing style (see 'بيانات تسجيل الدخول غير صحيحة.').

Persist both in the User::create() call.

Also add both to updateProfile(). Without this, every pre-existing user is
permanently untargetable — and right now that is your entire user base.

## 5. Flutter

- Model lib/models/job_title.dart
- Fetch via lib/services/mobile_content_service.dart, cached through the existing
  disk_cache.dart (24h) so registration survives a cold or slow network
- Searchable dropdown on lib/screens/register_screen.dart, following existing
  widgets/form_fields.dart patterns and utils/bidi_text.dart RTL handling
- Selecting 'other' reveals a free-text field
- name_ar as label; name_en as subtitle when locale is English (see app_locale.dart)
- Same control on lib/screens/profile_screen.dart

## 6. Tests

- Registration succeeds with a valid job_title_id
- Registration succeeds with NO job title — the field is optional and existing app
  builds will not send it. This must not break them.
- 'other' without job_title_other returns 422
- GET /api/mobile/job-titles returns only active rows, correctly ordered
- Seeder is idempotent across two runs
- Every seeded category is a valid AtsScoringService::JOB_KEYWORDS key — assert
  programmatically so the two can never drift

Run `composer test` and report results.
```

---

### PHASE 5 — Notification targeting on job title

Depends on Phase 4.

```
Use the new user job title to target Sirati's smart notifications.

## 1. Close the new-user coverage gap

DailyNotificationCandidateService::matchingJob() currently derives target titles ONLY
from cv_analyses and generated_cvs, returning null when both are empty. A user who
registered but has not yet analysed a CV can never match a job — they fall through to
the generic dailyTip. That is exactly the cohort you most need to activate.

New title-resolution order:
  1. Titles from recent cv_analyses and generated_cvs — existing behaviour, unchanged.
     Behavioural signal beats declared preference.
  2. NEW: if empty, use the user's job_title relation — name_ar, name_en, keywords[]
  3. NEW: finally, job_title_other free text if set

Apply the same chain to relevantEducation(), which has the identical gap (it matches
target_role against analysis titles only).

## 2. Replace the token matching

Current logic:
  Str::of($title)->explode(' ')->filter(fn ($w) => mb_strlen($w) >= 3)->first()

This takes only the FIRST word longer than 3 characters. For 'مطور تطبيقات الجوال' it
matches on 'مطور' and surfaces any developer job. For 'Senior Data Analyst' it matches
'Senior' — near-meaningless, and it mismatches across every category.

Replace with matching against job_titles.keywords, scoring candidates by number of
keyword hits and selecting the best match rather than the first. Keep the existing
is_published / valid_from / valid_until filters exactly as they are.

Add a minimum score threshold: below it, return null and fall through to
relevantEducation rather than sending a weak match. A wrong job notification costs
you more retention than no notification.

## 3. Admin segmentation

Extend AdminController::notificationRecipientCount() and sendNotification() with an
optional job_title_ids[] filter, and a coarser category filter for broad campaigns.

Update resources/views/admin/notifications.blade.php with a multi-select, following
the existing partials patterns. The recipient-count preview must reflect the filter
before sending — that endpoint already exists for precisely this.

## 4. Scoring hint (optional — measure before keeping)

AtsScoringService::jobCategory() infers a category from free-text job titles. When
the user has an explicit job_title.category, that is authoritative and should win.

Thread through as an OPTIONAL parameter defaulting to current inference, so no
existing call site or test breaks. Verify against tests/Unit/AtsScoringServiceTest.php
that scores are unchanged when the hint is absent.

## 5. Tests

Extend tests/Feature/SmartNotificationsTest.php:
- A user with a job title but zero analyses now gets a matching_job candidate
  (core new behaviour — currently returns dailyTip)
- Analysis-derived titles still take precedence over the declared job title
- A user with neither still falls through to dailyTip
- Below-threshold matches return null rather than a poor match
- Admin recipient count respects the job title filter
- All existing SmartNotificationsTest cases still pass

SMART_NOTIFICATIONS_ENABLED defaults to false — tests must set it explicitly.

Run `composer test` and report results.
```

---

### PHASE 6 — Arabic quality bake-off *(optional)*

Only run this if you have a specific complaint about Arabic output quality. It is the sole remaining argument for changing vendors, and it costs money to answer.

```
Time-boxed experiment: does Claude produce better Arabic CV advice than
gpt-4.1-mini for Sirati's users? Cost is already settled — gpt-4.1-mini is ~2.8x
cheaper — so Claude must win clearly on quality to justify switching.

1. Implement App\Services\ClaudeCvService against the CvAiProvider interface from
   Phase 1. API differences from OpenAI:
   - POST https://api.anthropic.com/v1/messages
   - Headers: `x-api-key` (NOT Bearer), `anthropic-version: 2023-06-01`
   - max_tokens is REQUIRED
   - `system` is a top-level parameter, not a message role
   - Response text at content[0].text, not choices[0].message.content
   - Structured outputs use output_config.format, not response_format
   - Handle stop_reason 'refusal' (HTTP 200, still billed) and 'max_tokens'

2. Bind via a CV_AI_PROVIDER env switch. Default 'openai'.

3. Build the eval: 30 real anonymised Arabic CVs spanning several of the 8 job
   categories. Run both providers. Present outputs BLIND and in randomised order to
   a native Arabic speaker familiar with Gulf/MENA hiring norms.

   Score each on: factual accuracy (did it invent anything?), Arabic register and
   fluency, actionability of advice, and RTL/LTR handling of technical terms.

4. Decision rule, agreed before looking at results: switch only if Claude wins
   clearly on accuracy or fluency. A narrow or ambiguous win does not justify
   tripling per-call cost. If results are close, stay on OpenAI and delete the
   Claude driver — do not keep a second unused vendor integration alive.

Report the scored comparison. Do not switch the production default.
```

---

## Part 5 — Rollout sequence

1. **Phase 0 → production.** Wait one full week. Read the report before deciding anything else.
2. **Phase 1 → production.** Immediate saving, low risk, no vendor coupling.
3. **Phase 2 → production.** Removes a live failure path.
4. **Phases 4 + 5** in parallel — no shared files with 0–3.
5. **Phase 3** only if the Phase 0 report shows volume justifying it. Verify `cached_tokens > 0` within 24 hours of deploy; if it is still 0, the prefix is short or something per-request is contaminating the system prompt.
6. **Phase 6** only on a concrete Arabic quality complaint.

## Open items needing your decision

- **Who writes the Arabic few-shot examples in Phase 3?** Highest-leverage content in the system, and the one thing here that should not be delegated to an AI agent.
- **Will `smart_notifications.enabled` ever go true?** All of Phase 5 is dormant until it does. Confirm before spending the effort.
- **Characterization tests before Phase 2.** `AtsScoringService` has unit tests, but CV analysis and generation — your actual product — are thin in `CvMvpTest.php`. Phase 2 changes the response contract underneath that untested code. Worth adding tests first.

## Sources

- [Models overview — Claude Platform Docs](https://platform.claude.com/docs/en/about-claude/models/overview)
- [Prompt caching — Claude Platform Docs](https://platform.claude.com/docs/en/build-with-claude/prompt-caching)
- [Structured outputs — Claude Platform Docs](https://platform.claude.com/docs/en/build-with-claude/structured-outputs)
- [Prompt Caching in the API — OpenAI](https://openai.com/index/api-prompt-caching/)
- [Prompt caching — OpenAI API docs](https://developers.openai.com/api/docs/guides/prompt-caching)
- [Structured model outputs — OpenAI API docs](https://developers.openai.com/api/docs/guides/structured-outputs)
- [Introducing Structured Outputs in the API — OpenAI](https://openai.com/index/introducing-structured-outputs-in-the-api/)
- [GPT-4.1 Mini API pricing](https://pricepertoken.com/pricing-page/model/openai-gpt-4.1-mini)
