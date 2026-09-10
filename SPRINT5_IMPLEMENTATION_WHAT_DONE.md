# Sprint 5 — Implementation What Was Done

Handoff for reviewing the Sprint 5 code delivery. Theme from `SPRINT5_AGENT_BRIEF.md`: **make CV generation finish, make failure safe to export, make the output structured, and cut the two-minute failure.**

Sprint window: 9–23 Sept 2026. Board 36.

This file records what landed in source, which acceptance criteria were actually exercised, and what could not be verified. Load-bearing claims here are meant to be checked against the tree.

**Not this sprint's engineering work:** SIRATI-70 (store listings, privacy labels, crash-reporting DSN).

---

## 1. Quick verification

From `D:\Sirati`:

```bash
php artisan test
```

Last full run: **307 passed** (3654 assertions), after Review Round 1 fixes.

From `D:\Sirati\flutter_app`:

```bash
flutter analyze
flutter test test/ai_error_message_test.dart
flutter test test/app_text_styles_palette_invariant_test.dart
flutter test test/cv_api_service_test.dart
flutter test test/async_cv_polling_test.dart
```

Last `flutter analyze`: **no issues**.

Targeted PHP filters for this sprint:

```bash
php artisan test --filter="QueuedJobRetryAfterTest|AiHttpRetryTest|CvAiProviderResilienceTest|HealthCheckTest|AsyncCvAiTest|LegacySectionParserTest|CvExportSafetyTest|CvPdfRenderingTest|OpenAiStructuredOutputsTest|ClaudeCvServiceTest|DeepInfraCvServiceTest"
```

---

## 2. Ticket status

| Key | Wave | What landed | Behavioural DoD |
|---|---|---|---|
| **SIRATI-82** | 1 | `retry_after` 240; worker timeout pinned at 180; `/up` fails closed | Exercised in tests. **Production env not set from here.** |
| **SIRATI-81** | 1 | HTTP retry with wall-clock budget so DeepInfra still fits | Exercised with fakes. Live 429 not measured. |
| **SIRATI-83** | 1 | Truncation is non-retryable; AR/EN user copy | Exercised in PHP + Dart unit tests. No real over-long CV on device. |
| **SIRATI-76** | 2 | Polling survives backgrounding; 210s client timeout (> job 180s); honest progress copy; FCM dispatch | Tests pass. Device / killed-process / real push **not** walked. |
| **SIRATI-78** | 2 | Parser feeds existing `_sections.blade.php` entries renderer | HTML/PDF tests. Visual PDF pass **not** done. |
| **SIRATI-75** | 2 | No ATS score on export; block unusable/pending export; email+phone required | Exercised in HTTP + PDF parse tests. |
| **SIRATI-84** | 3 | Measured locally, then blob cache + `HEADER_CSS` | Local numbers below. Production `ttfontdata` unknown. |
| **SIRATI-85** | 3 | IBM Plex Sans Arabic for AR PDFs; OFL recorded | Tests render. Eye comparison vs reference **not** done. |
| **SIRATI-77** | 3 | Generated-CV preview `Directionality` from CV language | Analyze clean. No `Locale('ar')` + English document widget test. |
| **SIRATI-80** | 4 | Palette is a required `AppTextStyles` argument; `inputTheme` deleted | Analyze + invariant tests. Dark-mode screen walk **not** done. |
| **SIRATI-79** | 4 | SafeArea on actions; English headline title-case on display; timeout no longer pretends the draft is ready | Partial. Inline Arabic-in-English (M7) not isolated. |
| **SIRATI-70** | — | Out of scope | Not touched. |

Jira was not updated (Atlassian MCP unauthenticated).

---

## 3. Wave 1 — the two-minute failure

### SIRATI-82 — `retry_after` must exceed job timeout

Laravel re-releases a job after `retry_after`. GenerateCv jobs timeout at 120s; `retry_after` was 90s, so a slow healthy run could be picked up by a second worker.

**Landed**

- `config/queue.php`: database / redis / beanstalkd defaults **180**.
- `.env.example` and local `.env`: `DB_QUEUE_RETRY_AFTER=180`, `REDIS_QUEUE_RETRY_AFTER=180`.
- `phpunit.xml` pins the same values.
- `app/Support/QueuedJobTimeouts.php` — discovers every `ShouldQueue` class under `app/Jobs`.
- `HealthMonitor::assertHealthy()` fails `/up` when any shipped persistent connection has `retry_after <= max job timeout`.

**Call chain to the user-visible end:** a mis-set production env makes `/up` fail. That is the production catch a green suite cannot provide.

**Tests:** `tests/Unit/QueuedJobRetryAfterTest.php`, `tests/Feature/HealthCheckTest.php` (`retry_after` too low → 500).

**Production step still required:** set `DB_QUEUE_RETRY_AFTER=180` (and Redis if used) on the deployed environment.

### SIRATI-81 — HTTP-layer retry on AI calls

All three drivers used `connectTimeout` + `timeout` with no `->retry()`. A 429 burned the job's `tries=3` / `backoff=[10,30]` (~130s, three full-price calls).

**Landed**

- `app/Services/Ai/AiHttpRetry.php` — 3 attempts; retries connection errors, 429, and 5xx only; honours `Retry-After`; sleep capped at 5s so the wait cannot exceed the job timeout.
- Wired in `OpenAiCvService`, `ClaudeCvService`, `DeepInfraCvService`.

**Tests:** `tests/Unit/AiHttpRetryTest.php` (policy invariant, not OpenAI-only). `tests/Feature/CvAiProviderResilienceTest.php` discovers every concrete `CvAiProvider` except the cache decorator and requires a fixture for each — 429 then 200, connection then 200, 400 not retried.

**Latency (design, not live-provider measurement)**

| Path | Before | After |
|---|---|---|
| Transient 429 / 5xx / connection drop | ~130s, 3 job tries | absorbed on the first HTTP attempt (~0.5–5s) |
| Genuine application failure | job `tries` unchanged | unchanged |

### SIRATI-83 — truncation must not burn retries

`max_tokens` overflow is deterministic in input length. Claude threw; OpenAI logged and threw `UnexpectedValueException` (retried); DeepInfra did not classify it. OpenAI also fell through to DeepInfra on truncation.

**Landed**

- `app/Exceptions/AiTruncationException.php` — stored `ai_error` prefix `cv_too_long`, bilingual cause in the message.
- `app/Services/Ai/AiOutputTruncation.php` — `length` and `max_tokens` on all three drivers.
- `GenerateCvContentJob` / `GenerateCvAdviceJob` catch it like `AiRefusalException` (terminal).
- OpenAI does **not** fall back to DeepInfra on truncation.
- Flutter `lib/core/utils/ai_error_message.dart` maps the code to AR/EN copy that names CV length. Wired on generator, generated-CV banner, analysis, and analysis-result.

No prompt or schema edit, so `CachedCvAiProvider::PROMPT_VERSION` was **not** bumped.

**Tests:** per-provider truncation in `CvAiProviderResilienceTest`; job does not rethrow in `AsyncCvAiTest`; `flutter_app/test/ai_error_message_test.dart`.

---

## 4. Wave 2 — generation correctness

### SIRATI-76 — finish, survive backgrounding, report honestly

**Landed**

- Client poll no longer sets `_pollingPaused` when the app leaves `resumed` (generator, analysis, analysis-result).
- `CvApiService.pollingTimeout`: 60s → **180s**.
- Overlay copy: “You can switch away — generation keeps running” / Arabic equivalent. Generation final step is “Saving your CV”, not “Scoring ATS compatibility”.
- Timeout: stay on the form; do not navigate to a local draft labelled as ready.
- `GenerateCvContentJob` dispatches `SendPushNotificationJob` on complete or terminal failure. Dispatch is try/caught so a missing Firebase project cannot fail the CV job.

**Call chain:** user backgrounds the app → client keeps polling while the process lives → server job still runs → FCM is the path if the process is killed.

**Could not verify:** real device, background mid-generation, push actually arriving. iOS may still freeze timers when suspended.

### SIRATI-78 — structured experience entries

The generator stored one narrative blob; `_sections.blade.php` already had a correct entries renderer (title / `date_range` LTR / bullets / `page-break-inside: avoid`) that never received entries. No second renderer was written.

**Landed**

- `app/Cv/LegacySectionParser.php` — splits date-delimited jobs, bullets, editorial lines; extracts `##` markdown sections.
- `CvDocument::fromLegacy()` uses the parser instead of wrapping the blob as one `ExperienceEntry(narrative:)`.
- `CvDocument::overlayGeneratedMarkdown()` — generation job writes structured `document` after AI returns. Builder-authored titled entries are kept.
- `GenerateCvContentJob` persists `document` from the overlay.

**Tests:** `tests/Unit/LegacySectionParserTest.php` (EN + AR two-job blobs, editorial strip, overlay of unstructured prose). `tests/Feature/CvExportSafetyTest.php` asserts ≥2 `entry-item` and `page-break-inside: avoid` in HTML.

Byte-safe trim: `ltrim` with a Unicode comma mask ate the leading `D8` byte of Arabic letters. Trim is now a `/u` regex.

### SIRATI-75 — nothing unusable or internal leaves the app

**Landed**

- `_footer.blade.php` gated on `$cv['show_internal_score']`. Preview HTML may show the score; `renderPdfBlob` sets the flag false.
- `app/Cv/CvExportGuard.php` — queued/processing → 409; missing email/phone or failed unstructured draft → 422. Completed + contact is exportable.
- Wired on `downloadPdf` and `downloadPdfApi`.
- Generator API and Flutter form: email and phone **required**.

ATS scoring of generated CVs is still the broken scorer (`GenerateCvContentJob` → `AtsScoringService`). Backlog SIRATI-86–91. Not started.

---

## 5. Wave 3 — the export

### SIRATI-84 — measure, then cache / HEADER_CSS

**Measured first on this machine**

- `storage/app/mpdf/ttfontdata/` exists and is populated (DejaVu + IBM Plex metrics).
- Arabic classic template, this workspace:

| | median | worst of 5 |
|---|---|---|
| Cold (cache flushed) | 141ms | 276ms |
| Warm (cached blob) | 0.5ms | 1.6ms |

**Then coded**

- Blob cache key: `(id, updated_at, template slug, language, ai_status)`, TTL 1h.
- CSS extracted and written with `HTMLParserMode::HEADER_CSS`; body with `HTML_BODY`.
- `ttfontdata` directory created if missing.

**Production step still required:** confirm `storage/app/mpdf/ttfontdata` is writable and survives deploys. If it lives on an ephemeral layer, cold font parse returns.

### SIRATI-85 — Arabic face

`'default_font' => 'dejavusans'` with `autoScriptToLang = false` rendered Arabic from a weak face.

**Landed**

- IBM Plex Sans Arabic (already in `flutter_app/assets/fonts`, SIL OFL) copied to `resources/fonts/ibm-plex-sans-arabic/` with `LICENSE.txt`.
- Registered in mPDF `fontdata` (`useOTL`, kashida). Arabic exports use it; English stays DejaVu.

**Could not verify:** visual comparison against a reference. Passing PDF tests is not typography sign-off.

### SIRATI-77 — preview direction

Generated-CV markdown inherited the app locale, so English CVs in an Arabic UI rendered RTL.

**Landed:** `generated_cv_screen.dart` wraps the body in `Directionality` from `generatedCv.language` (en → LTR, ar → RTL, else `detectBaseDirection`).

**Could not verify:** widget test of an English document under `Locale('ar')`; on-device screenshot.

---

## 6. Wave 4

### SIRATI-80 — palette required (do not patch call sites)

SIRATI-63 patched profile call sites with `.copyWith(color:)` and left `?? SiratiColors.light` in `AppTextStyles`. 27 sites in 12 files stayed broken.

**Landed**

- `AppTextStyles.*` first argument is required `SiratiColors`. The light default is gone; omitting it is a compile error.
- `AppFormStyles.inputTheme` (light-hardcoded, zero callers) deleted.
- Existing call sites pass `context.sirati`, including `job_title_display` fallback **and** `job_news_screen` (two independent paths).
- Bootstrap overlay moved to `AppTheme.bootstrapSystemUiOverlayStyle()` so `main.dart` does not name `SiratiColors.light`.

**Tests:** `flutter_app/test/app_text_styles_palette_invariant_test.dart` — zero `AppTextStyles.*()` with no args under `lib/`; zero `SiratiColors.light`/`.dark` outside `lib/shared/theme/`. `flutter analyze` clean.

**Could not verify:** dark mode walked across the 12 screens named in the audit.

### SIRATI-79 — polish

**Landed:** generated-CV action row in `SafeArea`; English headline title-cased on **display only** (not persisted); timeout/error copy no longer calls a raw draft employer-ready.

**Not done:** M7 isolating inline Arabic inside an English paragraph.

---

## 7. Definition of done vs this run

| Criterion from the brief | Status |
|---|---|
| `flutter analyze` clean | Exercised — no issues |
| PHP suite green | Exercised — 301 passed |
| Generation walked E2E in AR and EN on a real device, backgrounded mid-run | **Could not verify** |
| Generated CV exported to PDF in AR and EN and looked at | Tests parsed PDFs. **No visual pass.** |
| Dark mode on the 12 SIRATI-80 screens | **Could not verify** |
| Before/after latency on SIRATI-81 and SIRATI-84 in the tickets | Numbers in this file. Jira not updated. 81 not measured against a live provider. 84 measured locally only. |

---

## 8. Found along the way, not filed as tickets

- `context.sirati` still does `Theme.extension<SiratiColors>() ?? SiratiColors.light` inside `lib/shared/theme/sirati_colors.dart`. AppTextStyles can no longer hit that default; a raw extension miss still can.
- Generated CVs are still scored by the broken ATS scorer. Backlog SIRATI-86–91.
- Production `DB_QUEUE_RETRY_AFTER` and persistent mPDF `ttfontdata` are ops steps a green suite will not catch.

---

## 9. Principal files

| Area | Paths |
|---|---|
| Queue / health | `config/queue.php`, `.env.example`, `app/Support/QueuedJobTimeouts.php`, `app/Services/HealthMonitor.php` |
| AI HTTP + truncation | `app/Services/Ai/AiHttpRetry.php`, `AiOutputTruncation.php`, `app/Exceptions/AiTruncationException.php`, three `*CvService.php`, both generate jobs |
| Structured CV / export | `app/Cv/LegacySectionParser.php`, `CvDocument.php`, `CvExportGuard.php`, `GenerateCvContentJob.php`, `GeneratedCvController.php`, `_footer.blade.php` |
| PDF | `app/Services/CvTemplateRenderer.php`, `resources/fonts/ibm-plex-sans-arabic/` |
| Flutter generation | `cv_generator_screen.dart`, `cv_api_service.dart`, `ai_progress_overlay.dart`, `ai_error_message.dart`, `generated_cv_screen.dart` |
| Dark text | `lib/shared/theme/app_theme.dart`, `form_fields.dart`, call sites under `lib/features/**` and `lib/shared/widgets/**` |
| Tests | `tests/Unit/{QueuedJobRetryAfter,AiHttpRetry,LegacySectionParser}Test.php`, `tests/Feature/{CvAiProviderResilience,CvExportSafety}Test.php`, `flutter_app/test/{ai_error_message,app_text_styles_palette_invariant}_test.dart` |
