# Check ATS hand-off — hub → ATS scanner spec

## Summary

1. Today the hub's Check ATS opens the ATS input screen **empty**: `openCvHubAtsCheck` packages `cvId + markdown` into route arguments, but the pushed screen is `const CvAnalysisScreen()` and nothing ever reads those arguments.
2. The screen already knows how to scan a saved CV: `initialCvId` pre-selects it, `analysisSavedCvProvider(cvId)` fetches its markdown, and `_submit` sends that markdown as `resume_text` — the hub path uses none of this.
3. No function anywhere takes `(cvId, markdown)` together (**not found**); the pair must be glued together in `CvAnalysisScreen` (new `initialMarkdown`) and wired in `sirati_route_table.dart`.
4. No backend change is needed: `POST /cv-analyses` already accepts `resume_text`, and scanning a saved CV **by id** is **not found** server-side — the client already works around this by fetching the CV and posting its text.
5. The dialog that currently warns "your CV is not filled in automatically yet" (and the hub card body saying the same) becomes false after the fix and must go / be reworded.
6. Minimal change: 2 production Dart files + 2 ARB files (plus regenerated localizations), 2 test files extended, 0 backend files.
7. The existing hub test `ATS discloses the empty form and carries the source CV input` encodes the broken behaviour and must be replaced, not just extended.
8. Existing saved-CV tests (preselection, missing-CV-blocks-submit, saved-markdown-reaches-result) are the contract the hand-off must not break.
9. New tests assert on the pumped widget tree (screen properties, dropdown value, submitted text on a fake API) — no source regex.
10. Read-only spec: nothing was implemented, no `flutter`/`dart` was run; file:line references are from the `main` checkout at time of writing.

## 1. Where the hub's Check ATS action is built and what it does now

**Built** in the generated-CV hub screen:

- `flutter_app/lib/features/cv_builder/presentation/generated_cv_screen.dart:188` — `build` reads the nullable link: `final atsCheck = GeneratedCvHubLinks.atsCheck;`
- `flutter_app/lib/features/cv_builder/presentation/generated_cv_screen.dart:278-288` — the step is only rendered `if (atsCheck != null)`, as a `_NextStepCard` with `key: GeneratedCvScreen.atsStepKey` (declared `:50`), icon `Icons.fact_check_outlined`, title `l.cvHubCheckAts`, body `l.cvHubCheckAtsBody`, tag `l.cvHubRecommended`; `onTap: () => unawaited(atsCheck(context, _cv))` passes the whole on-screen `GeneratedCv`.
- `flutter_app/lib/features/cv_builder/presentation/generated_cv_screen.dart:414-417` — the link itself is a static nullable function (`static Future<void> Function(BuildContext, GeneratedCv)? atsCheck;`) so the cv_builder feature does not import the ATS feature; the step hides while unset.

**Wired** by the composition root:

- `flutter_app/lib/features/app/sirati_route_table.dart:36-39` — `registerSiratiRouteWidgets()` sets `GeneratedCvHubLinks.atsCheck = openCvHubAtsCheck;`

**What it does now** (`sirati_route_table.dart:50-78`, `openCvHubAtsCheck`):

1. Shows an `AlertDialog` titled `cvHubCheckAts` whose content is `cvHubAtsNewFormNotice` — in English: *"This opens a new ATS form. Your CV is not filled in automatically yet; paste its text or upload a file to continue."* (`flutter_app/lib/l10n/app_en.arb:644`; Arabic at `app_ar.arb:651`). Actions: `cvHubAtsStay` (pop `false`) / `cvHubAtsOpenNewForm` (pop `true`).
2. If the user proceeds, it pushes an imperative `MaterialPageRoute` (`:70-77`) whose `RouteSettings.arguments` is `CvHubAtsCheckArguments(cvId: cv.id, markdown: cv.generatedMarkdown)` — but whose builder is `(_) => const CvAnalysisScreen()`.
3. **The arguments are never read.** `CvAnalysisScreen` does not consult `ModalRoute.of(context).settings.arguments`, and its constructor has no markdown parameter (see §2). The user lands on the default ATS form (Saved-CV source, first saved CV auto-selected once the list loads) and must re-pick, paste, or upload — exactly what the dialog discloses. The carried `markdown` (`GeneratedCv.generatedMarkdown`, model field `flutter_app/lib/shared/models/generated_cv.dart:19`, parsed `:80`) is dead data today; only the existing test observes it (§5).

## 2. Route table entries and what the ATS input route already accepts

**Route-argument class (already exists):**

- `flutter_app/lib/features/app/sirati_route_table.dart:43-48` — `class CvHubAtsCheckArguments { const CvHubAtsCheckArguments({required this.cvId, required this.markdown}); final int cvId; final String markdown; }`, documented at `:41-42`: *"The current input screen still requires the user to paste or upload a CV."*

**Named-route table** (`flutter_app/lib/features/app/sirati_route_table.dart:80-146`, `_widgetFor`):

- There is **no named route for the ATS input screen** (**not found** in `AppRoutes`, `flutter_app/lib/core/routing/app_routes.dart:5-32`). The hub uses the imperative push above, not `AppRouter`.
- The only ATS-related entries are result routes: `sirati_route_table.dart:135-137` maps `/analysis/<id>` (`AppRoutes.analysisPrefix`/`analysis(id)`, `app_routes.dart:25,31`; parsed at `flutter_app/lib/core/routing/app_router.dart:220-223`) to `CvAnalysisLoaderScreen(analysisId:)`; `:138-140` maps a bare `/analysis/` prefix to `_SavedCvAnalysisEntry`.
- `sirati_route_table.dart:149-164` — `_SavedCvAnalysisEntry` builds `CvAnalysisScreen(savedCvs: …, onRetrySaved: …)` from `myCvsProvider`, **without** `initialCvId` and without looking at `parsed.arguments` (arguments do survive `AppRouter.onGenerateRoute`, `app_router.dart:145-148`, but this entry ignores them).
- Precedent for ID-carrying routes in the same table: `:117-119` maps `AppRoutes.jobMatch` arguments (`int`) to `JobMatchScreen(initialCvId:)`; `:128-134` maps `/cv/<id>` + a `GeneratedCv` argument to `GeneratedCvScreen`.

**What `CvAnalysisScreen` already accepts** (`flutter_app/lib/features/ats_scanner/presentation/cv_analysis_screen.dart:35-44`):

| parameter | type | effect |
|---|---|---|
| `savedCvs` | `AsyncValue<List<SavedAnalysisCv>>?` | Overrides the saved-CV list; null → screen watches `analysisSavedCvsProvider` itself (`:322-323`). |
| `onRetrySaved` | `VoidCallback?` | Retry hook for the saved list (`:363-369`). |
| `initialCvId` | `int?` | Pre-selects a saved CV: copied to `_selectedCvId` in `initState` (`:70`), re-applied in `build` (`:326-327`); a requested ID missing from the list yields a `null` selection — never a silent fallback (`:328-330`). |

**Not found:** any `initialMarkdown`, `initialCv`, or `CvHubAtsCheckArguments` parameter on the screen; any reading of `RouteSettings.arguments`/`ModalRoute` inside `cv_analysis_screen.dart`; any named ATS-input route constant. Direct callers already using `initialCvId`: `flutter_app/lib/features/dashboard/presentation/home/dashboard_cv_hero.dart:227` (hero Analyze button). Bare `const CvAnalysisScreen()` callers: `sirati_route_table.dart:76` (the bug), `dashboard_home_tab.dart:277`, `history_screen.dart:175`, `home_screen.dart:129`.

## 3. How the ATS input screen starts a scan (text vs file vs saved), and the (cvId, markdown) question

Source model: `enum AnalysisCvSource { saved, upload, paste }` (`cv_analysis_screen.dart:32`); default source is `saved` with `_selectedCvId = widget.initialCvId` (`:60,70`). The three source buttons carry `ValueKey(source)` and `Semantics(selected: …)` (`:418-450`).

**Saved CV (the path the hub should land on):**

- Dropdown `DropdownButtonFormField<int>` over `SavedAnalysisCv(id, title)` rows (`:478-498`; row type in `flutter_app/lib/features/ats_scanner/application/saved_analysis_cvs.dart:7-11`).
- Detail fetch: `analysisSavedCvProvider(selectedId)` — an `autoDispose.family<GeneratedCv, int>` that calls `cvApiServiceProvider.getGeneratedCv(id)` (`saved_analysis_cvs.dart:26-29`); watched in `build` only for the saved source (`cv_analysis_screen.dart:331-333`).
- Draft: `_draft` (`:88-102`) resolves `resumeText` to the fetched CV's `generatedMarkdown` for `saved`, to `_resumeTextController.text` for `paste`, to `''` for `upload`.
- Submit guard for `saved` (`:144-159`): selected ID must be present in the saved rows **and** the fetched markdown must be non-empty, otherwise an `atsInputSelectSourceRequired` snackbar and no request.

**Paste text:** `TextFormField` with `key: ValueKey('ats-input-resume-text')` bound to `_resumeTextController`, min 8 lines, non-empty validator (`:523-534`).

**Upload file:** `_pickFile` (`:104-132`) via `FilePicker` (custom `pdf`/`txt`, `AnalysisRules.upload` size/type gate from `flutter_app/lib/features/ats_scanner/domain/analysis_draft.dart:32-41`); `_multipartFile` (`:291-311`) builds the `http.MultipartFile` (`resume_file`) from bytes or path.

**Common submit pipeline** (`_submit`, `:134-289`): form validation → source/resume guard → `AnalysisDraft` fingerprint → idempotency key via `IdempotencyKeyRing.keyFor(draft)` (`analysis_draft.dart:85-92`) → `api.submitAnalysis(targetJobTitle: draft.jobTitle.trim(), jobDescription: draft.jobDescription, resumeText: draft.resumeText, resumeFile: resumeFile, idempotencyKey: …)` (`:195-201`) → `api.pollAnalysis(...)` (`:204-211`) → pushes `AnalysisResultScreen(analysis:)` (`:259-265`). The docked submit button is `SubmitButton(key: ValueKey('ats-input-docked-analyze'), onPressed: _submit)` (`:385-392`).

**Which existing function would take `(cvId, markdown)`? Not found** — no single function accepts the pair:

- `analysisSavedCvProvider(int id) → Future<GeneratedCv>` takes the `cvId` and *produces* the markdown (`saved_analysis_cvs.dart:26-29`).
- `CvApiService.submitAnalysis({targetJobTitle, resumeText, jobDescription?, resumeFile?, idempotencyKey?})` takes the markdown (as `resumeText`) but has **no CV-ID parameter** (`flutter_app/lib/shared/services/cv_api_service.dart:24-30`).
- `AnalysisDraft({jobTitle, jobDescription, resumeText, fileName, fileSize})` likewise carries text only (`analysis_draft.dart:47-53`).

So the pair must be joined in the screen: `cvId` drives selection/provenance, `markdown` becomes the draft's `resumeText` for that ID. The spec in §6 does exactly that and reuses everything downstream of `_draft` unchanged.

## 4. API client method and backend support

**Client:** `CvApiService.submitAnalysis` (`flutter_app/lib/shared/services/cv_api_service.dart:24-47`) — `postMultipart('/cv-analyses', fields: {target_job_title, resume_text (only if non-empty), job_description (only if non-empty)}, file: resumeFile, headers: {Idempotency-Key?, X-Sirati-Async: 1})`, returns `CvAnalysis.fromJson(response['data'])`. It sends **no** `generated_cv_id`. Fetching a saved CV by ID already exists client-side: `getGeneratedCv(id)` → `GET /generated-cvs/<id>` (`:211-214`).

**Backend routes** (`routes/api.php`, inside `auth:sanctum` + verified-email groups, `:38,51`):

- `POST /cv-analyses` → `CvAnalysisController@storeApi`, `throttle:ai-heavy` (`:72-74`); `GET /cv-analyses`, `GET /cv-analyses/{analysis}` (`:72,75`).
- `GET /generated-cvs/{generatedCv}` → `GeneratedCvController@showApi` (`:96`), owner-authorized at `app/Http/Controllers/GeneratedCvController.php:311-316` — this is what the saved-CV provider fetch uses.

**Backend controller** (`app/Http/Controllers/CvAnalysisController.php`):

- `storeApi` (`:63-86`): idempotency replay → `SubscriptionGate::assertCanAnalyze` → `createAnalysis(...)` → optional `GenerateCvAdviceJob` dispatch → 201 `CvAnalysisResource`.
- `createAnalysis` validation (`:110-115`) accepts exactly: `target_job_title` (required, ≤160), `job_description` (nullable, ≤4000), `resume_text` (nullable string), `resume_file` (nullable PDF/TXT ≤5120 KB); `:117-121` requires at least one of text/file. `CvTextExtractor::extract` (`app/Services/CvTextExtractor.php:19-21`) takes `resume_text` verbatim (≥80 chars after sanitize, `:40-44`), then classification, ATS scoring, and persistence follow (`CvAnalysisController.php:123-189`).

**Does the backend already support scanning a saved generated CV by ID? Not found.** No `generated_cv_id` (or similar) appears in the validation rules, the extractor, or the controller; the analysis record stores `resume_text`, never a CV reference (`:167-188`). The supported composition is the one the saved-CV UI already uses: `GET /generated-cvs/{id}` (owner-checked) → take `generated_markdown` → `POST /cv-analyses` with `resume_text`. The hub hand-off in §6 keeps that contract and needs **no backend or API-client change**; a true by-ID endpoint would be new server surface (validation + ownership lookup + tests) and is explicitly out of the minimal scope.

## 5. Existing tests the change must extend

**Hub (`flutter_app/test/features/cv_builder/generated_cv_hub_test.dart`):**

- `:287-303` — `testWidgets('ATS discloses the empty form and carries the source CV input')`: taps `atsStepKey`, expects the disclosure dialog (`cvHubAtsNewFormNotice`) and **no** `CvAnalysisScreen`, taps `cvHubAtsOpenNewForm`, then asserts the pushed route's `settings.arguments` is `CvHubAtsCheckArguments` with `cvId == _cv().id` and `markdown == _cv().generatedMarkdown`. This test *is* the current contract; the fix must replace it (see §6). Its harness (`_pump`, `:57-94`) already registers the real route table (`registerSiratiRouteWidgets()`, `:68`) and pumps the real hub, so the replacement test needs no new plumbing.
- Adjacent hand-off precedent in the same file: `:240-269` asserts `JobMatchScreen.initialCvId` and the selected option for arbitrary IDs `[7, 91, 503]`, and `:271-285` asserts an unavailable source CV never silently selects another — the ATS replacement should mirror this style.

**ATS input screen:**

- `flutter_app/test/features/dashboard/cv_analysis_home_acceptance_test.dart:321-368` — `'saved CV markdown and JD reach analysis result without upload'`: pumps `CvAnalysisScreen(savedCvs: AsyncData([SavedAnalysisCv(id: 71, …)]))`, submits, asserts `api.submittedText == 'CV 71 SQL خبرة مهنية'` (i.e. the fetched markdown), `submittedFile == null`, result screen shown.
- Same file, `:596-641` (inside the home-states test): hero Analyze opens `CvAnalysisScreen` with `initialCvId == latest.id`, the dropdown's `initialValue == latest.id`, and submit sends `latest.generatedMarkdown`.
- Same file, `:807-844` — `'missing requested CV blocks submission instead of selecting another'`: `CvAnalysisScreen(initialCvId: 999)` → dropdown `initialValue` is null, submit sends nothing, `atsInputSelectSourceRequired` text shown. **This must keep passing for the no-markdown case.**
- Same file, `:845-892` — docked Analyze stays fixed under scroll/keyboard; limits use Arabic wording.
- `flutter_app/test/features/ats_scanner/cv_analysis_screen_test.dart:29-70` — AA-contrast walk of the form, selected-file tile, upload-zone semantics. No source/submit behaviour; the place for a small prefilled-state test if the acceptance file is not touched.
- `flutter_app/test/async_cv_polling_test.dart:86-136` — paste-source submit helper (`_submitAnalysis`) driving poll outcomes (`:21-83`); unaffected, must stay green (paste path untouched).
- `flutter_app/test/cv_api_service_test.dart:65-72` — `submitAnalysis` multipart contract (`resume_text` field, async header). Unaffected; no client change is specified, so it must pass unmodified.

## 6. Minimal change and exact new tests

### Production changes (2 Dart files + 2 ARB files; generated localizations regenerated, not hand-edited)

1. **`flutter_app/lib/features/ats_scanner/presentation/cv_analysis_screen.dart`**
   - Add `this.initialMarkdown` (`final String? initialMarkdown;`) to the constructor (`:35-44`). Semantics: the markdown of the CV identified by `initialCvId`, carried by the caller; null/blank ⇒ today's behaviour exactly.
   - In `_draft` (`:88-102`): for the `saved` source, when `_selectedCvId == widget.initialCvId` and `initialMarkdown` is non-blank, use `initialMarkdown` instead of the provider's markdown. (Do not seed the paste controller; the saved-CV UX — dropdown, `atsInputSavedCvHint`, provenance — is the point of the hand-off.)
   - In the saved-source submit guard (`:144-159`): the carried markdown satisfies the "fetched markdown non-empty" leg for `initialCvId`; the "selected ID is in the saved rows" leg is unchanged, preserving the missing-CV invariant.
   - In `build` (`:331-333`): skip watching `analysisSavedCvProvider(selectedId)` when the carried markdown covers `selectedId`, so no loading/error/"text unavailable" state (`:504-512`) can appear for a hand-off that already has its text — and no redundant fetch is issued.
   - Nothing else: `_submit`, `_pickFile`, `_multipartFile`, idempotency, polling, and navigation are reused unchanged.
2. **`flutter_app/lib/features/app/sirati_route_table.dart`**
   - `openCvHubAtsCheck` (`:50-78`): delete the disclosure `AlertDialog`; push `CvAnalysisScreen(initialCvId: cv.id, initialMarkdown: cv.generatedMarkdown)` directly. Keep `RouteSettings(name:…, arguments: CvHubAtsCheckArguments(cvId: cv.id, markdown: cv.generatedMarkdown))` on the route so the arguments class and any route observers keep working. `CvHubAtsCheckArguments` (`:43-48`) stays; update its stale doc comment (`:41-42`).
3. **`flutter_app/lib/l10n/app_en.arb` / `app_ar.arb`** (then regenerate `lib/l10n/generated/`)
   - Reword `cvHubCheckAtsBody` (`app_en.arb:635`, `app_ar.arb:642`) — it currently says *"this CV is not prefilled yet"*, which becomes false.
   - Delete the now-unused dialog keys `cvHubAtsNewFormNotice`, `cvHubAtsStay`, `cvHubAtsOpenNewForm` (`app_en.arb:644-646`, `app_ar.arb:651-653`).

**Explicitly unchanged:** `generated_cv_screen.dart` (already passes `_cv`), `cv_api_service.dart`, `analysis_draft.dart`, `saved_analysis_cvs.dart`, `app_routes.dart`/`app_router.dart`, all backend PHP, `dashboard_cv_hero.dart`.

### New / replaced tests (all assertions on the pumped widget tree or fake-API state; no source regex)

Extend only the two existing files; no new test file, no new framework. Parameterize over at least two distinct markdowns (one Arabic, one mixed Latin/Arabic, different lengths) and, in the hub test, over arbitrary CV IDs — assert the invariant, not one fixture string.

1. **Replace** `generated_cv_hub_test.dart:287-303` (`ATS discloses the empty form and carries the source CV input`) with:
   - `'ATS opens prefilled with the source CV (id <id>)'` for IDs `[7, 91, 503]`: pump the hub via the existing `_pump`, tap `GeneratedCvScreen.atsStepKey`, then assert — `find.byType(AlertDialog)` finds nothing; `find.byType(CvAnalysisScreen)` finds one widget; `tester.widget<CvAnalysisScreen>(…).initialCvId == id` and `.initialMarkdown == <the pumped CV's markdown>`; the route's `settings.arguments` is still `CvHubAtsCheckArguments` with the same `cvId`/`markdown`; the Saved source is the selected source (the `Semantics` under `find.byKey(const ValueKey(AnalysisCvSource.saved))` has `properties.selected == true`); `find.byKey(const ValueKey('ats-input-resume-text'))` (paste field) finds nothing.
2. **Add** to `cv_analysis_home_acceptance_test.dart` (its `Api` fake already records `submittedText`/`submittedFile` at `:121-140`; add a `getGeneratedCv` call counter to it):
   - `'hub markdown reaches the analysis without a saved-CV refetch'`: pump `CvAnalysisScreen(initialCvId: 71, initialMarkdown: <distinct supplied markdown>, savedCvs: AsyncData([SavedAnalysisCv(id: 71, title: …)]))` with the fake returning a *different* markdown for ID 71; assert the dropdown's `initialValue == 71`; enter job title + JD (keys `ats-input-job-title` / `ats-input-job-description`), tap `ValueKey('ats-input-docked-analyze')`, then assert `api.submittedText == <supplied markdown>` (exact string), `api.submittedFile == null`, the fetch counter is 0, and `find.byType(AnalysisResultScreen)` finds one widget.
   - `'blank hub markdown falls back to the fetched saved CV'`: same pump with `initialMarkdown: '  '`; submit and assert `api.submittedText` equals the fake's fetched markdown for ID 71 and the fetch counter is ≥ 1 — proving the carried value is an accelerator, not a bypass of the saved-CV flow.
3. **Keep unmodified as regression contract:** `cv_analysis_home_acceptance_test.dart:807-844` (missing `initialCvId: 999` without markdown still blocks submission and selects nothing), `:321-368` (saved fetch path), `async_cv_polling_test.dart` (paste path), `cv_api_service_test.dart` (multipart contract). If (2)'s counter addition touches the shared `Api` fake, these tests must still pass without expectation changes.

### Acceptance criteria for the implementing change

- From the hub, one tap on Check ATS lands on `CvAnalysisScreen` with the hub CV pre-selected (Saved source), no dialog, no paste/upload required; entering only job title (JD optional) and tapping Analyze sends the hub CV's exact markdown as `resume_text`.
- A hand-off whose CV is missing from the saved list, with blank carried markdown, behaves exactly as today (blocked, no silent substitution).
- All tests named in §5 pass, with only the one hub test at `generated_cv_hub_test.dart:287` replaced and the two tests in §6.2 added.
