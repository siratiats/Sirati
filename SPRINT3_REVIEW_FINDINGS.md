# Sprint 3 — code review findings

Review of SIRATI-43, 44, 45, 48, 49, 41 and 15 as delivered. Same method as Sprints 1 and 2: read the code, trace the paths, verify claims against the source rather than the summary.

**Coverage note.** The local Linux workspace still fails to start here, so I could not run `flutter analyze` / `php artisan test` myself — the reported 162 + backend green is taken on trust. Nothing below depends on it. I read the backend export path, entitlement service, routes, templates and the two new feature tests closely; the Flutter export controller, file namer and live preview pane got a lighter pass.

**Verdict: not ready to sign off.** Three blocking findings. Two are in SIRATI-49 (the gate is wired to a user object that two of the three export routes never have), and one is in SIRATI-44 (the ATS-safety fix makes the PDF safe for *us*, not for an ATS).

---

## First, what genuinely landed

**Sprint 1's H1 is finally closed.** `_sections.blade.php` renders from `$cv['structured_sections']` — the typed `CvDocument` — rather than `generated_markdown`. That thread has been open since the first review; it is properly resolved now, and the declarative partial is the right shape.

Also good: `page-break-inside: avoid` on entries and `page-break-after: avoid` on headings (real ATS/print hygiene, and asserted), PDF document metadata via `SetTitle`/`SetAuthor`/`SetSubject`, explicit A4 margins, and `Str::slug(...) ?: 'candidate'` still guarding the Arabic filename case.

---

## S1 — the entitlement gate is blind on two of the three export routes, and 403s paying users

**SIRATI-49** · `CvTemplateRenderer::downloadResponse`, `routes/api.php:28`, `routes/web.php:28`

The gate itself is placed sensibly — at the renderer, so every caller inherits it:

```php
if ($template->isPremium() && ! $this->entitlementService()->canExportTemplate($user, $template)) { … 403 }
```

But it keys on the `?User $user` the caller passes, and the callers pass `$request->user()`. There are three routes into it:

| Route | Auth | `$request->user()` |
|---|---|---|
| `POST/GET /api/generated-cvs/{id}/download` | `auth:sanctum` | the user ✓ |
| `GET /api/generated-cvs/{id}/pdf` | **none** | `null` |
| `GET /generated-cvs/{id}/pdf` (web) | **none** | `null` |

`canExportTemplate(null, $premiumTemplate)` returns `false`. So on both unauthenticated routes every premium export 403s — **including a paying subscriber's**.

That is not a corner case. `GeneratedCvResource` hands the client both of those URLs on every single CV:

```php
'pdf_url'          => $publicBaseUrl.$signedPath,                     // api.generated-cvs.pdf
'template_pdf_url' => $publicBaseUrl."/generated-cvs/{$this->id}/pdf", // the web route
```

`pdf_url` is the one the mobile app uses. A premium user who buys a premium template and taps download gets 403.

`PremiumTemplateGatingTest` is green because all four of its cases go through `actingAs($user)` on the authenticated `/download` route. The two routes that actually break are never exercised.

**Fix:** the entitlement decision needs an owner, not a session. Resolve the user from the CV (`$generatedCv->user`) rather than the request, or require authentication on every export route and stop publishing unauthenticated PDF URLs.

## S2 — the public PDF routes have no authorization at all

**Pre-existing, but this sprint edited the method and added a different check while leaving this open.**

`downloadPdf()` never calls `authorizeApiAccess()`. And neither public route validates a signature:

```php
// routes/api.php
Route::get('/generated-cvs/{generatedCv}/pdf', [GeneratedCvController::class, 'downloadPdf'])
    ->name('api.generated-cvs.pdf');          // named for signing — no `signed` middleware

// routes/web.php
Route::get('/generated-cvs/{generatedCv}/pdf', [GeneratedCvController::class, 'downloadPdf'])
    ->name('generated-cvs.pdf');              // plainly public
```

`GeneratedCvResource` builds `pdf_url` with `URL::temporarySignedRoute(...)`, so the signature is *generated* — but never *checked*, because the route has no `signed` middleware. And `template_pdf_url` is the unsigned web route, published to every client.

So `GET /generated-cvs/{id}/pdf` with any id returns that CV's PDF — full name, email, phone number, employment history — to an unauthenticated caller walking sequential ids. For a CV product in a PDPL jurisdiction this is the finding I would fix first regardless of sprint scope.

**Fix:** add `->middleware('signed')` to the API route, add an ownership check to `downloadPdf`, and either drop `template_pdf_url` from the resource or point it at a signed route.

## S3 — the PUA map fixes the PDF for us, not for an ATS

**SIRATI-44** · `ArabicPdfText::unshape`

```php
$puaMap = [
    "\u{E915}" => 'ي',  "\u{E940}" => 'ك',  "\u{E906}" => 'ة',
    "\u{E918}" => 'ي',  "\u{E919}" => 'ي',
];
$text = strtr($text, $puaMap);
```

Three problems, in ascending order of seriousness.

**It is font-version-coupled.** Private Use Area codepoints have no standard meaning — U+E915 is whatever the vendored DejaVu Sans build's cmap happens to assign. A font update, a different font (and `autoLangToFont = true` is still on, so mPDF may substitute one per script), or an mPDF upgrade renumbers these and the map silently starts producing *wrong letters* rather than failing.

**It covers 5 of roughly 140 shaped forms.** Arabic has ~36 letters in up to four positional forms. If the pipeline emits PUA for `ي`, `ك` and `ة`, it emits PUA for others too. These five are the ones that appeared in the test fixture — the session's own scratch file was checking `'القحطان' . "\u{E915}"`, i.e. the trailing yeh of the fixture's surname. That is `AGENTS.md` rule 1 inverted: rather than a test calibrated to a reported example, this is *code* calibrated to a test fixture.

**Most importantly, it inverts the ticket.** SIRATI-44 is "ATS-safe templates with parse-back verification". Its value is that a *third-party* ATS can read the PDF. If the text layer contains PUA codepoints, that is a defect in the PDF: the embedded font subset lacks a correct `ToUnicode` CMap. An external ATS will read `القحطان` followed by an unmapped private glyph — it will never run our normalizer. Post-processing the extraction on our side proves only that we can undo our own damage.

`AtsParseBackTest` passes because it extracts, calls `normalizeExtracted`, and *then* asserts. Remove the normalizer call and the assertion on `القحطاني` fails — which is the actual state of the artefact a recruiter's ATS receives.

**Fix:** make mPDF emit a proper ToUnicode CMap so the text layer carries real Unicode (that is a font-embedding/subsetting configuration question, not a string-substitution one). Then assert parse-back on the **raw** extraction with no normalizer in the path — that is the assertion that means "ATS-safe". Keep `normalizeExtracted` for the visual-order inversion it was built for, which is a separate and legitimate concern.

---

## Medium

**M1 — `phpunit.xml` gained AI provider keys mid-run.** `CV_AI_PROVIDER=openai`, `OPENAI_API_KEY=""`, `DEEPINFRA_API_KEY=""` were added to the testing env. Pinning empty keys so tests cannot reach a live API is good hygiene and I would keep it. But the change landed between a failing `php artisan test` and a passing one, so it is worth confirming it isolated the suite rather than routing a failure into the "not configured" branch. This is the one change here I cannot verify without the diff.

**M2 — `canPreviewTemplate()` always returns `true` and ignores both parameters.** Harmless as a policy, but it reads as a gate and is not one. Either delete it and call the policy what it is, or give it the check it looks like it has.

**M3 — the watermark is a positioned `<div>` in the HTML, not a PDF-level stamp.** It renders on the preview, which satisfies the AC. Worth knowing it is defeated by anything that re-renders the HTML, and that `previewHtmlApi` returns the HTML itself — so a free user receives the full premium template markup with one removable element in it. If the watermark is meant to protect template value rather than just label a preview, it needs to be drawn by mPDF (`SetWatermarkText`) on a server-rendered PDF.

**M4 — `autoLangToFont = true` is still set** alongside `default_font: dejavusans`. I flagged this in Sprint 1 as "confirm the rendered font is still DejaVu"; it is now load-bearing, because the PUA map in S3 is only valid for DejaVu. If mPDF substitutes a different Arabic face for some run, the map is wrong for that run.

---

## Where I'd start

1. **S2** — unauthenticated access to arbitrary users' CV PDFs. Smallest fix here, largest consequence, and independent of the sprint's goals.
2. **S1** — premium users currently cannot download premium templates through the URL the app actually uses. This is the ticket's headline feature failing in production while its test is green.
3. **S3** — decide whether SIRATI-44 means "our extractor can read it" or "an ATS can read it". If the latter, the fix is in font embedding and the test must assert on raw extraction.

## On AGENTS.md

Rule 1 held on the Flutter side and slipped on the backend: the PUA map is code shaped to a fixture. Worth extending the rule so it reads in both directions — *neither tests nor code may be calibrated to a specific observed example; both must be derived from the invariant.*

The Sprint 2 note I suggested (test the acceptance criterion, not the implementation) would have caught S1 and S3 directly. SIRATI-49's AC says free users are blocked and premium users are not; no test covers a premium user on the route the app uses. SIRATI-44's AC says the PDF is ATS-safe; the test asserts only that our own normalizer can repair it.
