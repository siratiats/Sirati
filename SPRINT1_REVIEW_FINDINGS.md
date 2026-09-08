# Sprint 1 — code review findings

Review of the 12 tickets in **In Review** per `SPRINT1_IN_REVIEW.md`. Scope: code correctness, design, and performance. Static review plus executed proofs (the Arabic bidi and contrast findings below were reproduced by running the actual algorithms, not inferred).

The four skipped tickets (58, 54, 53, 57) were left alone as instructed.

**Verdict:** the model layer, the router, and the token set are solid, well-documented work. But four findings undercut the acceptance criteria they were written against, and three of them are invisible to the tests that were added. The suite is green because it tests what was built, not what the AC asked for.

Severity: **H** = blocks sign-off · **M** = fix this sprint · **L** = backlog / design debt.

---

## H1 — The typed `CvDocument` never reaches the PDF

**SIRATI-30, SIRATI-47** · `app/Services/CvTemplateRenderer.php`, `resources/views/generated-cvs/**`

`renderHtml()` builds two parallel payloads and hands both to the view:

- `$pdfData` — from the **legacy scalar columns** (`$generatedCv->full_name`, `->email`, …) and `->generated_markdown`
- `$cv` — from `viewModel()`, the only thing derived from `CvDocument`

Grep across all 8 blade files, every `$cv[...]` reference:

```
42  $cv['direction']         16  $cv['score']['total']    8  $cv['language']
16  $cv['score']['grade']     8  $cv['labels']['ats_score']
 2  $cv['template']['colors']['accent']   1  ...['primary']
```

`$cv['candidate']`, `$cv['summary']` and `$cv['sections']` are referenced **zero times**. All rendered content comes from `$pdfData`.

So SIRATI-30's "schema usable by ATS + templates" and SIRATI-47's "content lives on `CvDocument`, not on the template" hold in the model and are false in the render path. This is precisely the *"any leftover untyped source of truth"* the handoff asks reviewers to watch for — it is in `CvTemplateRenderer`, not in `app/Cv/`.

Secondary cost: `viewModel()` does a full `CvDocument::fromArray()` hydration plus a complete `resolve()` (string joins over every section) on **every render**, and ~90% of the result is discarded.

**Fix:** either drive the templates from `$cv['candidate']` / `$cv['sections']` and delete `$pdfData`, or stop computing the unused branches of `viewModel()` and reclassify SIRATI-30 as model-layer only.

---

## H2 — The Flutter `CvDocument` mirror is lossy and exposes `toJson()`

**SIRATI-30, SIRATI-32** · `flutter_app/lib/models/cv_document.dart`

The Dart class models `personal` + `summary` and nothing else. It has no `experience`, `education`, `skills`, `languages`, `certifications`, `projects`, or `custom_sections`.

`toJson()` emits only `schema_version`, `export_language`, `personal`, `summary`. So:

```dart
CvDocument.fromJson(serverDoc).toJson()   // every content section is gone
```

Today it is read-only, so nothing is lost yet. But the class ships `toJson()` *and* `duplicate()`, which is an open invitation for the first screen that PUTs an edited document to wipe the CV body server-side. "Lossless JSON round-trip" is an AC on this ticket; the mirror does not satisfy it.

Also on this file: `missingTranslations` is read from `json['missing_translations']`, but in `GeneratedCvResource` that key is a **sibling** of `document`, not a member of it. If the model is constructed from `json['document']`, `hasTranslationGaps` is permanently `false`. Worth confirming at the call site.

**Fix:** either complete the mirror, or rename it (`CvDocumentHeader`) and drop `toJson()`/`duplicate()` so it can't be round-tripped.

---

## H3 — `ArabicPdfText::normalizeExtracted` corrupts LTR lines and Arabic digits

**SIRATI-46** · `app/Support/ArabicPdfText.php`

`restoreLine()` fires on any line containing **one** Arabic character, and unconditionally reverses the segment order. It has no direction or language parameter. Reproduced by running the shipped code:

| Input | Output |
|---|---|
| `Senior Engineer at شركة, Riyadh` | `, RiyadhةكرشSenior Engineer at ` |
| `بتك ٢٠٢٤` | `٤٢٠٢ كتب` |
| `normalizeExtracted(normalizeExtracted('بتك'))` | `بتك` — back to garbage |

Three distinct defects:

1. **LTR-base lines are scrambled.** An English CV containing an Arabic company or university name — routine in this market — gets its word order reversed. This is the ATS ingestion path, so it degrades the product's core scoring feature.
2. **Arabic-Indic digits (U+0660–0669) match `\p{Arabic}`** and get character-reversed. `٢٠٢٤` → `٤٢٠٢`. Numbers are LTR inside RTL runs and are painted LTR; they must not be reversed.
3. **`preg_split('//u')` splits by codepoint, not grapheme,** so combining harakat get reassigned to different base letters. `كَتَب` (fatha on ك and ت) comes back with the fatha on ب and ت.

Not idempotent, with no guard — any pipeline that normalizes twice produces mojibake.

Unit coverage for all of this is `ArabicPdfTextTest`: two assertions, one of which is a single 3-letter word.

**Fix:** take a `string $baseDirection` parameter (from `export_language`); only reverse when the paragraph is RTL-base; exclude `\p{Nd}` and Arabic-Indic digits from the reversal class; split with `\X` instead of `//u`; add an idempotence guard. Then add cases for mixed AR/EN, digits, and an LTR-base line.

Minor, same file: `unshape()` uses NFKC, which also rewrites unrelated Latin — `Sirati™` → `SiratiTM`, `½` → `1⁄2`. NFKC is right for presentation-form Arabic but it is a blunt instrument for the rest of the string.

---

## H4 — The light-mode primary button is 2.98:1

**SIRATI-17** · `flutter_app/lib/theme/app_contrast.dart`, `widgets/components/app_button.dart`

Computed from the shipped hex values:

| Pair | Light | Dark |
|---|---|---|
| `onPrimary` `#FFFFFF` on `primary` `#00A898` | **2.98** | 6.39 |

Two things let this through:

**(a) The threshold is wrong.** The suite holds this pair to 3.0 with the comment *"Filled buttons use 14pt bold (WCAG 'large text' → 3.0:1)"*, and the handoff repeats it to reviewers. But `AppButton` renders `AppTypography.labelLg` = `fontSize: 14` — and Flutter `fontSize` is **logical pixels**, not points. 14 px ≈ 10.5 pt. WCAG large text is 14 pt bold = **18.66 px** bold. The exemption does not apply; the pair needs 4.5:1.

**(b) The assertion has slack.** `ContrastPair.passes` is:

```dart
bool get passes => ratio + 0.05 >= minRatio;
```

That is not a float epsilon — 0.05 is real contrast headroom, and this pair is the only one in either palette that needs it. At 2.98 it fails even the relaxed 3.0 target and is green solely because of the `+ 0.05`.

Dark mode is fine. This is a light-mode-only defect on the app's most common control.

**Fix:** drop the `+ 0.05`, hold `onPrimary/primary` to 4.5, and darken the light-mode filled-button background toward `primaryDark` `#006A60` (6.50 on surface). Note this is the one point where I'd push back on the handoff's guidance to reviewers.

---

## Medium

**M1 — Crashlytics and Sentry receive unredacted exceptions.** `main.dart:_installCrashlyticsErrorHandlers` calls `recordFlutterFatalError(details)` with the raw `FlutterErrorDetails`. `AppLog`'s redaction only applies to its own `debugPrint`. Exception messages routinely embed the offending string (a `FormatException` on a parsed CV field carries the field). SIRATI-16's "no CV/PII in logs" holds for logcat and not for the crash reporters — which is the path that leaves the device. `options.sendDefaultPii = false` does not redact exception text.

**M2 — Up to 280 chars of CV text can reach production logcat.** `AppLog.redact()` truncates a long message *and keeps the first 280 characters*. `_emit` drops only `AppLogLevel.debug` in release; `info`/`warn`/`error` still call `debugPrint`, which prints in release builds. The `_piiKeys` allowlist protects the `data:` map, not the message string. The handoff acknowledges this ("free-text CV bodies are only truncated") — worth calling it what it is: a 280-character résumé leak.

**M3 — `resolveInitialRoute()` has side effects and is called from `build()`.** It is the `initialRoute:` argument of `MaterialApp`, nested inside two `ValueListenableBuilder`s. Every theme or locale change re-runs it, and it writes `AppRouter.pendingLocation` (including `AppRoutes.notFound`). Toggling the theme can resurrect a consumed deep link. Resolve it once before `runApp` and pass the value down.

**M4 — `CvListController` can notify after dispose.** The generation guard covers stale loads but not disposal: if the widget is disposed while `_loader()` is in flight, `notifyListeners()` throws *"used after being disposed"*. Add a `_disposed` flag set in an overridden `dispose()`. This is the reference implementation for SIRATI-12, so the bug will be copied into every new controller.

**M5 — `/analysis/:id` deep links drop the id.** `AppRouter.parse()` extracts `analysisId` correctly, then `_widgetFor` returns `const CvAnalysisScreen()` with no id. One of three deep-link types silently opens an empty screen.

**M6 — `GeneratedCvResource` hydrates the document twice per row.** `cvDocument()` re-parses the JSON blob into ~50 value objects on every call and is not memoized; the resource calls it twice (`document`, `missing_translations`). On the My CVs list that is 2N hydrations. `missingTranslations()` compounds it with `[...$paths, ...$entry->…]` inside loops — quadratic array copying. The resource also ships `document` **plus** every legacy scalar **plus** `generated_markdown` for every row, so the list endpoint downloads the full text of every CV. Memoize `cvDocument()`, use `array_push(...$x)`, and split a list resource from the detail resource.

**M7 — The root `builder:` rebuilds the whole app on keyboard insets.** `MediaQuery.of(context)` inside the root builder subscribes the entire tree to every MediaQuery change, `viewInsets` included, and the root `LayoutBuilder` adds a layout-driven rebuild on top. In the same rebuild, `AppTheme.lightFor(...)` / `darkFor(...)` construct full `ThemeData` (including a `TextTheme` built from tokens) from scratch, defeating theme equality caching and forcing `didChangeDependencies` on every `Theme.of` dependent. Use `MediaQuery.textScalerOf(context)` and memoize the four `(brightness, arabic)` `ThemeData` combinations.

**M8 — The textScaler clamp caps accessibility at 1.3 and floors it at 1.0.** WCAG 1.4.4 asks for 200%. The floor also forces users who chose *smaller* system text back up to 100%, and the clamp flattens Android 14's non-linear scaling curve. Compounding it, `AppButton` sets `maxLines: 1, overflow: ellipsis` on its label — so at 1.3 with Arabic, button labels truncate rather than wrap. SIRATI-18's AC is "OS text size **without clipping**"; the component library clips by design.

**M9 — The contrast suite tests pairs the components don't render.** `AppButton.destructive` paints `foregroundColor: c.onPrimary` on `c.error`. The suite tests `onError / error`. `onError` — added for this ticket — is used by no component. Also untested: `onSurfaceMuted / surface` (4.48 light), and `onSurfaceMuted / background` is held to 3.0 (4.19), so hint text sits below AA body contrast in light mode.

**M10 — `app_metrics_test` is weaker than its stated guarantee.** The raw-color regex is `Color\s*\(\s*0x`, so it misses `Colors.*`, `Color.fromRGBO`, and — the one that matters — `AppColors.*`, the light-only static palette that `app_theme.dart` still defines and re-exports to every component. The `EdgeInsets` scan matches only `.all` / `.symmetric`, missing `.only`, `.fromLTRB` (used in `app_sheet.dart`), and bare magic numbers: `SizedBox(width: 18, height: 18)`, `strokeWidth: 2.2`, `Icon(icon, size: 18)` all sit in `app_button.dart` today and pass. Relatedly, `AppSurfaceCard` uses the *old* `c.border` / `c.softShadow` rather than the new `outline` / `AppShadows.of` tokens the sprint introduced — and the scan cannot see it.

**M11 — Duplicated semantics in every core component.** `AppButton` wraps an `ElevatedButton` in `Semantics(button: true, label: label)`, and `AppInput` wraps a `TextField` in `Semantics(textField: true, label: …)` while *also* setting `InputDecoration.labelText`. Both produce nested semantics nodes; TalkBack and VoiceOver announce the label twice. Use `excludeSemantics: true` or `MergeSemantics`. `AppButton` also never announces its loading state.

---

## Low / design debt

**Data model**

- `CvDocument::fromArray()` hardcodes `schemaVersion: self::SCHEMA_VERSION`, discarding the stored value. There is no migration path and no rejection path for a future v2 — a v2 blob would be silently relabelled v1. Read the stored version and branch.
- `LocalizedText::fromArray()` puts a legacy plain string into `ar` unconditionally, regardless of `export_language`. An English legacy payload lands in the Arabic slot. Same bug in the Dart mirror.
- Round-trip is not byte-stable: `fromArray` trims but the constructor doesn't; `ExperienceEntry::texts()` silently drops empty bullets, shifting indices and invalidating the `missingTranslations` paths that reference them; `CustomSection::fromArray()` rewrites a blank key to `'custom'`, collapsing several keyless custom sections onto one identifier.
- `CvDocument::duplicate()` is a no-op on a `final readonly` class whose children are all readonly — it allocates a copy that can never differ.
- `mapList()` turns a malformed (non-array) section into `[]`, erasing the `null` vs `[]` distinction the class docblock establishes.

**Template switching**

- `TemplateSwitch::ALIASES` is a hardcoded `const`. A template declaring a section name outside the list produces a false *"this template will hide…"* warning; matching is case- and whitespace-sensitive; an empty `supported_sections` silently means "supports everything". This mapping belongs on the template record, not in code.
- `TemplateSwitchResult::warningMessage()` inlines AR/EN strings instead of using `lang/`.

**PDF pipeline**

- `CvMarkdownRenderer::protectLtrRuns()` — the idempotence guard `str_contains($run, LRM)` is **dead code**: the regex alternatives are all `[A-Za-z0-9…]`, and the LRM sits *outside* the matched run, so it can never fire. Running `shapeText` twice double-wraps (`<LRM><LRE><LRM><LRE>Python<PDF><PDF>`, reproduced). The marks also inflate Arabic text ~50% in bytes. And it uses the deprecated LRE/PDF embedding rather than LRI/PDI isolates.
- `shapeText` early-returns unless the **whole string** contains Arabic, so `email`, `phone` and `linkedin` are never processed. The LTR isolation the handoff credits to `shapeText` is actually delivered by the templates' `unicode-bidi: embed` CSS. The outcome is right; the stated mechanism isn't.
- `CvTemplateRenderer::resolve()` runs two uncached queries per render for static reference data.
- `Str::slug()` on a pure Arabic name returns an empty string → `sirati-cv--123.pdf`.
- `autoLangToFont = true` lets mPDF override the `dejavusans` default per script — possibly with a different Arabic face than the one visually verified. Worth confirming the rendered font is still DejaVu.
- The `downloadResponse` fallback re-render is not itself wrapped, so a failure in the *default* template escapes uncaught.
- `sanitizeChildren()` strips **all** attributes from allowed tags, so `colspan`/`rowspan` are lost from markdown tables.

**Routing / app shell**

- `AppRouter.pendingLocation` and `EntitlementStore.hasPremium` are mutable global statics with two writers and two consumers — race-prone and not resettable between tests.
- `onGenerateRoute` ignores `settings.arguments`, so any `pushNamed(..., arguments:)` silently loses them. Named routes are now the sanctioned API, so this will bite.
- `parse()` accepts any non-`sirati` scheme's path with no host validation (latent until App Links are declared).
- `handleIncoming` doesn't dedupe, so a redelivered OS deep link pushes a duplicate screen.
- `/premium` renders `PremiumGateScreen` from `_widgetFor` even when `hasPremium` is true — the guard has no granted branch.
- `resolveInitialRoute` and `onGenerateInitialRoutes` both parse the same route and both write `pendingLocation`; the former's return value is then discarded for deep routes.
- **Worth verifying:** if Splash routes to Login when signed out, nothing clears `pendingLocation` on that path — a deep link opened while logged out may be dropped or fire late.

**Theme / typography**

- `AppCrashView` hardcodes `AppTheme.lightFor(...)` and `SiratiColors.light.background` — the crash screen is light even in dark mode.
- `AppTypeToken.resolve()` hardcodes `fontFamily: 'IBM Plex Sans Arabic'` into every token. One family serves both scripts, and the family can't be overridden without editing the token. SIRATI-18's AC names Latin *and* Arabic.
- Two disagreeing definitions of "is Arabic": `AppTypography.isArabic` reads `Directionality`, `AppTheme.lightFor(arabic:)` reads the language code. Inside any locally-overridden `Directionality` (mixed-language content — exactly what this app does) they diverge and line-heights silently switch. `showAppDialog` adds a third, picking its default OK label from `Directionality`.
- `showAppBottomSheet` sets neither `isScrollControlled` nor `useSafeArea` — any sheet containing a text field is covered by the keyboard, and tall sheets cap at 50% height.
- `showAppDialog` captures `context.sirati` *before* `showDialog`, so its colors go stale if the theme changes while it's open.
- `AppRadius` and `AppTouchTarget` live in `app_typography.dart` while `AppSpacing` and `AppElevation` live in `app_theme.dart` — SIRATI-19's token set is split across two files.
- `AppThemeController.setMode` fires `AnalyticsService` calls neither awaited nor `unawaited()`-wrapped, so their errors escape to `PlatformDispatcher.onError`. `main.dart` is careful about this everywhere else.
- `AsyncState` exposes `isLoading` / `dataOrNull` / `errorOrNull` alongside the sealed hierarchy, which undercuts the "exhaustive `switch` is the API" decision it documents. `CvListController` also has no refresh-preserving-data path, so pull-to-refresh flashes the list empty.

---

## What I'd fix before sign-off

1. **H4** — one-line change to `passes`, one color change. Cheapest fix, real user impact, and it invalidates a claim currently written into the handoff.
2. **H3** — direction-aware `normalizeExtracted` plus digit and grapheme handling. This is the ATS path; the current two-assertion test does not cover the failure modes.
3. **H1** — decide whether templates consume the document or `viewModel()` stops computing what nobody reads. Right now the ticket's AC is met on paper only.
4. **H2** — complete or rename the Dart mirror before any screen writes through it.
5. **M1 + M2** together — the SIRATI-16 AC is "no CV/PII in logs", and both leaks are outside the redaction path that was tested.

**On the tests generally:** `flutter analyze` at 0 and the suites green are real, but three of the four H findings sit in code the tests exercise and pass. `app_metrics_test`, `app_contrast_test` and `ArabicPdfTextTest` are each calibrated to what was built rather than to the AC. That's the pattern worth fixing, more than any single line.
