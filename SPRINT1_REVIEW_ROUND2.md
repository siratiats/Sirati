# Sprint 1 — re-audit of the fixes (round 2)

Verification pass over the changes made against `SPRINT1_REVIEW_FINDINGS.md`. I re-read the changed files and re-ran my adversarial cases against the *new* code — the bidi and contrast results below are executed output, not inspection.

The local Linux workspace is still failing to start on this machine, so I could not re-run `php artisan test` / `flutter test` myself. The green results reported are taken at face value; everything below is independent of them.

**Verdict:** H2, H4 and most of the M-tier are genuinely fixed. H3 is ~80% fixed but the new direction heuristic introduces a fresh silent-failure path. H1 is half-closed and reported as complete. Seven new issues, two of them worth fixing before this ships.

---

## Confirmed fixed (verified, not taken on trust)

| Finding | Evidence |
|---|---|
| **H4** contrast | `passes` is now `ratio >= minRatio` — slack gone. Recomputed every pair from the new hex values: **all pass strictly at 4.5** in both palettes (`onPrimary/primary` light 6.50, dark 6.39). `destructive` now paints `c.onError`. `excludeSemantics: true` added to `AppButton`/`AppInput`. Label `maxLines: 2`. |
| **H2** Dart mirror | All eight section types present (`ExperienceEntry`, `EducationEntry`, `Skill`, `LanguageSkill`, `Certification`, `Project`, `CustomSection`, `PersonalDetails`) with nullable lists mirroring PHP. |
| **H3** partial | Arabic-Indic digits excluded from reversal (`بتك ٢٠٢٤` → `٢٠٢٤ كتب` ✓). Harakat stay anchored — `\X` grapheme split verified. `unshape()` now scoped to presentation-form ranges, so `Sirati™` and `½` survive. Idempotent. LTR-declared lines untouched. |
| **M3** | `resolveInitialRoute()` called once in `main()`, passed to `SiratiApp` as a parameter. Correct fix. |
| **M4** | `_disposed` flag + `dispose()` override, checked on both branches and before the final notify. Correct. |
| **M7** | Four `ThemeData` instances memoized as statics; `MediaQuery.of(context)` gone from the root builder. |
| **M8** | `MediaQuery.withClampedTextScaling(0.8, 2.0)` — full 200%. |
| **M10** | Scan now covers `.only`/`.fromLTRB`, `Colors.*`, `Color.fromRGBO`, `AppColors.*`, and legacy `c.border` / `c.softShadow`. This one is now a real guard. |
| Arabic filename | `Str::slug(...) ?: 'candidate'`. |

---

## New issues introduced by the fixes

### N1 (high) — the LTR heuristic silently skips Latin-heavy Arabic lines

`ArabicPdfText::restoreLine` decides direction by counting characters:

```php
if ($latinCount > $arabicCount) { $isLtrLine = true; }   // → skip the reversal
```

Executed:

```
visual line : Kubernetes Django Python ةربخ      (arabic=4, latin=22)
              → treated as LTR, left as-is
normalize   → Kubernetes Django Python خبرة
wanted      → خبرة Python Django Kubernetes
```

The letters inside the Arabic word get fixed; **the word order does not.** That line shape — a skills or tech-stack line on an Arabic CV — is exactly where Latin outnumbers Arabic. The old code over-applied the reversal; this one under-applies it, and fails silently either way.

The caller already knows the answer: `CvTemplateRenderer` has `$language` from `export_language`, and `normalizeExtracted` already takes `$baseDirection`. Trust the parameter and delete the character census, or at minimum only fall back to counting when the caller passed nothing.

### N2 (high) — `$normalizedCache` is an unbounded static that never evicts in production

```php
private static array $normalizedCache = [];
```

`clearCache()` is called from exactly one place in the repo: `ArabicPdfTextTest`. Measured: **5,000 entries after 5,000 distinct inputs, none evicted.** In a queue worker or Octane process this grows for the life of the process, holding an md5 per CV ever rendered.

It's also content-keyed global state in the ATS path, which means the function's answer depends on what the process normalized earlier. That's a worse property than the non-idempotence it was added to solve — and `isAlreadyLogical()` already provides idempotence on its own. Delete the cache.

### N3 (medium) — the `cvDocument()` memo is never invalidated

```php
private ?CvDocument $memoizedCvDocument = null;
```

Nothing clears it when `document` changes. `$cv->document = [...]` followed by `$cv->cvDocument()` returns the stale document, as does `refresh()` and `fill()`. The performance win is real; it needs a `setAttribute('document', …)` hook that nulls the memo, or a memo keyed on the attribute value.

### N4 (medium) — Sentry still receives the raw exception

Crashlytics now gets the sanitized copy, but the chain passes the **original** through:

```php
unawaited(FirebaseCrashlytics.instance.recordFlutterFatalError(sanitizedDetails));
previousFlutterOnError?.call(details);        // ← original, and Sentry is in this chain
…
return previousPlatformOnError?.call(error, stack) ?? false;   // ← original
```

`SentryFlutter.init` installs its handler before `appRunner`, so it is `previousFlutterOnError`. M1 named both reporters; one of the two is still open. Pass `sanitizedDetails` / `sanitizedError` down the chain.

### N5 (medium) — `Exception(sanitized)` collapses Crashlytics grouping

Crashlytics groups issues by exception type plus top frame. Every error now arrives as `_Exception` with the text as payload, so unrelated crashes merge into one issue (or fragment by message string). Redact the message and keep the original type — or leave the exception intact and put the redacted text in `reason:` / a custom key.

### N6 (medium) — `_nationalId` eats timestamps

```dart
static final _nationalId = RegExp(r'\b[12]\d{9}\b');
```

Any 10-digit number starting with 1 or 2. Executed:

```
epoch seconds 1788532429   →  epoch seconds [id]
cv_id 1234567890           →  cv_id [id]
```

Every epoch-seconds timestamp in every log line becomes `[id]`. Anchor it to a labelled context (`national_id`, `iqama`, `هوية`) rather than matching bare digit runs.

### N7 (low–medium) — the new content regexes over-redact, and the leak isn't fully closed

`_markdownHeadingOrBullet` matches any line beginning `- ` / `* ` / `#`; `_cvSectionHeadings` matches any line beginning with a section word followed by `:`. Executed:

```
'- retrying request 3 of 5'       →  '[cv-content-redacted]'
'languages: [ar, en]'             →  '[cv-content-redacted]'
'summary: upload failed with 502' →  '[cv-content-redacted]'
```

Those are ordinary diagnostics, and the app will now be materially harder to debug from logs. Meanwhile the original hole is only narrowed, not closed — plain prose with no heading or bullet still logs 280 characters verbatim, and `_emit` still `debugPrint`s at info/warn/error in release. Redaction by prose-shape guessing will keep losing this race; the durable fix is to stop passing free text as the log *message* at all and require it to go through the key-filtered `data:` map.

---

## Two claims in the summary that overstate what shipped

**"All 8 Blade templates now bind directly to `$cv['candidate']`"** — 7 of 8 still read `$pdfData['name']` / `['targetJobTitle']` / `['contacts']`. Only one template was converted. Behaviour is right, because `$pdfData` is now *derived* from `$cv['candidate']` in the renderer — but that makes the seven template edits unnecessary churn and leaves one file inconsistent with the rest. Either convert all eight or none.

**H1 is half-closed.** Header and contacts now come from the typed document. The body does not:

```php
$markdown = (string) $generatedCv->generated_markdown;
if (trim($markdown) === '' && $resolved !== null) {
    $markdown = $resolved->toMarkdown();
}
```

`generated_markdown` is still the primary body source; `toMarkdown()` is a blank-only fallback. `$cv['sections']` is still referenced **zero times** across all templates. That may well be the right call — the AI markdown *is* the generated content — but then say so and stop computing `$cv['sections']`, rather than recording H1 as closed. Also `contentHtml` and `content_html` are both set on the view model; drop one.

---

## One product decision that got made inside an accessibility fix

Light `primary` moved `#00A898` → `#006A60`, `primaryDark` → `#004D46`, and `teal` moved with it. That fixes the contrast, but it retires the brand teal as the light-mode primary fill — and there's a `Sirati Brand Identity.dc.html` in this repo, so that's a decision with an owner.

Two side effects worth knowing before you accept it:

- `primary` and `primaryDark` are now **1.50:1** apart. Any hover / pressed / disabled state derived from that pair is now nearly invisible.
- Every decorative use of `teal` shifted with it.

There is an alternative that keeps `#00A898` and still clears AA — put dark ink on it instead of white, which is what the dark palette already does:

| Ink on `#00A898` | Ratio |
|---|---|
| `#FFFFFF` (the old failing pair) | 2.98 |
| `#00332D` (dark mode's existing `onPrimary`) | **4.67** |
| `#002B26` | 5.14 |

My original note said "darken toward `primaryDark`", which is what was done — but I should have offered this second option at the time. Worth a deliberate choice rather than inheriting the one I happened to suggest.

---

## Suggested order

1. **N1** — delete the character census, trust `$baseDirection`. Silent wrong output on a common line shape.
2. **N2** — delete `$normalizedCache`. `isAlreadyLogical()` already covers idempotence.
3. **N4 + N5** — one small edit each; M1 isn't actually closed until Sentry gets the sanitized copy.
4. **N6** — anchor the national-ID pattern.
5. **N3** — invalidate the memo on `document` writes.
6. Decide the brand-teal question explicitly, then **N7** and the H1 / template-binding cleanup.
