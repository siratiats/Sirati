# Sprint 4 — code review findings

Review of SIRATI-11, 14, 24, 25, 26, 29 and the Sprint 1 M2 carryover, against `SPRINT4_AGENT.md` and `AGENTS.md`.

**Coverage note.** The local Linux workspace still won't start here, so I could not run `flutter analyze` / `flutter test` / `php artisan test` myself — the reported green is taken on trust and nothing below depends on it. I read the logging, routing, flavor, layering, golden and i18n code closely, and sampled three feature screens for string adoption.

**Verdict: two blocking findings.** Both are the same shape — infrastructure built correctly, then not connected to anything. The engineering underneath is the best of the four sprints; the gap is adoption.

---

## What genuinely landed

**M2 is properly closed, and structurally.** This was my carryover from Sprint 1 and it got the API change it needed rather than another regex:

```dart
if (_useReleasePolicy) {
  // Release: only structured event identifiers. Free-text (including
  // résumé paragraphs) is dropped, not truncated-and-kept.
  safeMessage = isKnownEvent ? message : AppLogEvent.diagnostic.eventId;
}
…
if (out.length > _maxMessageChars) {
  return '[omitted ${out.length} chars]';
}
```

Two independent layers: in release, any message that isn't an allowlisted event id is replaced wholesale; and `redact()` now *omits* oversized input instead of keeping its first 280 characters. Keeping the legacy `info/warn/error(String)` signatures while gating them through the event allowlist was the right call — it closes the leak without a big-bang migration. M2 is done.

**The router dependency inversion is real.** `core/routing/app_router.dart` imports no screens at all. Features register themselves:

```dart
typedef AppRouteWidgetBuilder = Widget Function(ParsedRoute parsed);
static void registerWidgets(AppRouteWidgetBuilder builder) { _widgets = builder; }
```

`registerSiratiRouteWidgets()` is called at `main.dart:85`, before `resolveInitialRoute()` at 128 and `runApp` at 129, and again defensively inside `SiratiApp.build`. Ordering is correct, and cold-start deep links still resolve. That is an architectural fix, not a folder shuffle.

**The layering test uses real AST parsing.** `parseFile` from the `analyzer` package walking `UriBasedDirective`, with the docblock explicitly noting "Source regex is not used". That is AGENTS rule 3 satisfied properly, and a genuine step up from the source-regex guards of Sprints 1–3.

**The migration left no duplicated code.** Every legacy path is a one-line barrel (`lib/theme/app_theme.dart` is 41 bytes: `export '../shared/theme/app_theme.dart';`). No forked copies to drift apart.

**The Arabic plurals are correct.** All six CLDR forms — `zero`, `one`, `two`, `few`, `many`, `other` — with proper duals (`سيرتان ذاتيتان`, `تحليلان`). Both catalogs are key-for-key in sync, 26 each, `untranslated.json` empty. Most implementations get Arabic pluralization wrong; this one doesn't.

---

## H1 — the i18n system is built and nothing uses it

**SIRATI-24, SIRATI-26** · `lib/l10n/`, `lib/features/**`

SIRATI-24 is "i18n infrastructure **and string extraction**". The infrastructure is right. The extraction did not happen. Sampled three feature screens:

| File | `AppLocalizations` refs | hardcoded Arabic literals |
|---|---|---|
| `settings/presentation/settings_screen.dart` | **0** | 40 |
| `cv_builder/presentation/cv_builder_screen.dart` | **0** | 39 |
| `cv_builder/presentation/personal_details_editor.dart` | **0** | 36 |

Zero references — including in the settings screen, which *hosts the language toggle*. The catalog holds 26 keys, and they are almost all generic chrome (`cancel`, `save`, `next`, `back`, `retry`); the app's actual copy is still literals in the widgets.

**And one of those screens is worse than untranslated — it is monolingual.** `settings_screen.dart` at least gates its strings on locale (40 `english ? … : …` ternaries), so it switches languages the old way. `personal_details_editor.dart` has **zero** locale gates across 36 Arabic literals, including every validation message:

```
'يرجى إدخال رقم هاتف سعودي صحيح (مثال: +966501234567 أو +966112345678)'
'يرجى إدخال رقم هاتف كويتي صحيح'
'يرجى إدخال بريد إلكتروني صحيح (مثال: name@example.com)'
```

An English-language user filling in the CV builder gets Arabic-only validation errors. That is a live user-facing defect in a bilingual product, not just architectural debt — and it sits in the sprint whose whole purpose was localization.

It also undercuts SIRATI-26. "Instant locale toggle flips `MaterialApp.locale` and `Directionality` immediately" is true, and the layout does mirror — but the *text* does not change, because nothing reads from the localization delegate.

**What I would do:** treat the catalog as the deliverable, not the plumbing. Extract the validator and dialog strings first — they are the ones that are currently monolingual — then the settings screen's 40 ternaries, which are a mechanical conversion. A lint that fails on a bare Arabic string literal outside `lib/l10n/` would keep it from regressing, and unlike the source-regex guards this one is a legitimate lexical check.

## H2 — the golden tests never run in CI

**SIRATI-29** · `.github/workflows/ci.yml`, `test/ci_workflow_test.dart`

SIRATI-29's acceptance criterion is "CI failure on mismatch." Nothing in CI can fail on a golden mismatch:

```yaml
- name: Run Test Suite with Coverage
  run: flutter test --coverage --exclude-tags golden

- name: Check Golden Tests & Test Reporting
  if: always()
  run: |
    echo "Flutter tests completed successfully."
```

The step *named* for checking goldens is an `echo` that always succeeds. A workflow reader would reasonably conclude goldens are covered; they are excluded one step earlier.

The part that makes this stick is `ci_workflow_test.dart`:

```dart
expect(content, contains('flutter test --coverage --exclude-tags golden'));
```

The test asserts the exclusion string as an invariant — so the suite now actively enforces that goldens stay out of CI. Anyone who adds a golden job breaks that test. The gap is locked in.

24 golden baselines were generated and committed (4 screens × light/dark × LTR/RTL, plus component clusters). That work is real and useful locally. It just isn't a CI gate, which is the ticket.

**Fix:** either add a second job that runs `flutter test --tags golden` on a fixed runner image, or drop SIRATI-29's CI claim and record the goldens as a local pre-merge step. Then change the `ci_workflow_test` assertion to match whichever is true.

---

## Medium

**M1 — the 2% golden tolerance is unjustified and, as configured, moot.** `installTolerantGoldenComparator({double precisionTolerance = 0.02})` passes when `result.diffPercent <= 0.02` — 2% of *pixels*, which on these ~800×1200 baselines is roughly 19,000 pixels. That is enough to hide an entire footer line, a mis-aligned label, or a colour shift across a small component.

Cross-OS font rasterization noise is a real problem and *some* tolerance is legitimate engineering rather than a diluted assertion, so I would not call this an AGENTS rule 2 violation outright. But nothing in the repo records the measured noise floor, and typical Windows→macOS text rasterization diff is well under 0.5%. Two things follow: the number appears chosen rather than derived, and since goldens never run on a second OS in CI (H2), it currently protects against a delta that is never exercised. Measure the actual cross-OS diff once, set the tolerance just above it, and write the measurement into `test/golden/README.md`.

**M2 — the layering test has two blind spots.** The AST approach is right; the predicates are narrower than the rules they encode.

*Legacy barrels launder feature imports.* `_isPackage(uri, 'features/')` matches only `package:sirati/features/…`. But `lib/screens/home_screen.dart` is `export '../features/dashboard/presentation/home_screen.dart';` — so a file in `core/` or `shared/` that writes `import 'package:sirati/screens/home_screen.dart';` imports a feature through the shim and passes the check. Nothing does this today (the migrated files import `package:sirati/core/…` exclusively, no relative imports at all — I checked), so this is latent rather than live. But the barrels exist precisely so old import paths keep working, which is the condition under which someone writes one.

*Cross-feature checking covers a third of the tree.* The rule in the runbook is "Cross-feature direct imports are strictly prohibited", full stop. The test only inspects `data/` and `controllers/` subdirectories and exempts `app`, `dashboard` and `localization` wholesale — so `presentation/`, which is where cross-feature imports are most likely and most tempting, is unchecked.

Both are a few lines: resolve barrel targets before classifying, and drop the layer filter.

---

## Low

- `_widgetFor` returns `const SizedBox.shrink()` when no route table is registered. With two registration points that is unlikely to fire, but a blank screen is a silent failure; an `assert(_widgets != null)` would surface a missed registration in debug instead of rendering nothing.
- The runbook places `app_locale.dart` under `features/localization/`; it shipped in `core/utils/`. Harmless, but `core/` holding the locale controller while a `features/localization/` directory also exists is a small inconsistency in the layering story.
- `lib/l10n/generated/` is committed. Generated localization output is normally gitignored and produced by `flutter gen-l10n` in CI; committing it means a stale catalog and a stale generated class can disagree.

---

## Where I'd start

1. **H1** — the Arabic-only validators are a user-facing bug today, independent of the architecture question. Fix those first, then the extraction.
2. **H2** — decide whether goldens are a CI gate or a local step, and make the workflow and its test say the same thing. Right now the test enforces the opposite of the ticket.
3. **M1** then **M2** — both small, both about making a guard mean what it says.

## On AGENTS.md

Rule 3 is the success story of this sprint: the layering test does real AST analysis and says so in its own docblock. Rule 1 held in the logging work — `app_log_test.dart` tests the release-policy behaviour class rather than specific strings.

The failure mode that persisted is the one that has now appeared in all four sprints under different names: **a thing is built, tested in isolation, and never connected.** Sprint 1's `normalizeExtracted` had no caller. Sprint 2's bidi utilities had one. Sprint 3's `previewPdfResponse` had no route. Sprint 4's `AppLocalizations` has no consumer, and the golden suite has no CI job.

That suggests a rule of its own, and it is cheap to check:

> **6. A deliverable is not done until something calls it.**
> For any ticket that adds a utility, catalog, or test suite, name the production call site or CI job that consumes it in the Definition of Done. If there is none yet, the ticket is infrastructure — say so on the ticket rather than marking it complete.
