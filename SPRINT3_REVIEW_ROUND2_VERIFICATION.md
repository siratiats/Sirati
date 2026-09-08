# Sprint 3 — re-audit of the fixes (round 2)

Verification of the changes made against `SPRINT3_REVIEW_FINDINGS.md`. Same method: re-read the changed code, trace every path into the export boundary. Local shell still won't start here, so the reported 251 backend / 162 Flutter green is taken on trust; nothing below depends on it.

**Verdict: all three blocking findings are closed.** Two residuals, one of which is a latent design hazard worth fixing while the code is fresh, plus one verification gap the test suite structurally cannot cover.

---

## Verified fixed

**S2 — the public PDF routes are authorized now.** `downloadPdf()` calls `SignedRecordAccess::authorize($request, $generatedCv)`, which requires either a valid signature or a matching `user_id`, and aborts 403 otherwise. Both the API and web routes go through that same method, so the web route is covered too. `GeneratedCvResource` no longer publishes an unsigned URL — `template_pdf_url` now points at the same signed path as `pdf_url`. The id-walking hole is shut.

**S1 — the gate can see the owner.** `$user = $request->user() ?? $generatedCv->user;` in `downloadPdf`, mirrored by `$effectiveUser = $user ?? $generatedCv->user;` inside `downloadResponse`. A premium subscriber downloading through the signed URL now resolves to their own entitlement instead of `null`. The two new tests cover exactly the paths that were failing.

**S3 — the root cause was found, and the fix is at the source.** This is the part I want to credit properly. `autoLangToFont = true` was substituting `xbriyaz` (XB_Riyaz.ttf) for Arabic runs, and *that* font is what emitted Private Use Area codepoints. Disabling the substitution keeps DejaVu Sans, the PUA disappears at the origin, and the five-entry `$puaMap` is gone from `ArabicPdfText::unshape` entirely.

The test now asserts the property that actually matters, on the raw layer before any of our processing:

```php
$this->assertDoesNotMatchRegularExpression('/[\x{E000}-\x{F8FF}]/u', $rawText);
$this->assertStringContainsString('faisal.qahtani@example.com', $rawText);
$kcNormalized = Normalizer::normalize($rawText, Normalizer::FORM_KC);
$this->assertDoesNotMatchRegularExpression('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $kcNormalized);
```

That third assertion is the one that makes this real: it proves a third-party ATS applying standard NFKC gets canonical Arabic out of our text layer. The Arabic names are still asserted only after `normalizeExtracted`, and that is correct — the raw layer is in visual order because that is how mPDF paints RTL, which is the problem `normalizeExtracted` legitimately exists to solve. The PUA problem and the visual-order problem are now cleanly separated.

**M3 — the watermark is a real PDF stamp.** `SetWatermarkText` / `showWatermarkText` / `watermarkTextAlpha`, drawn by mPDF rather than a removable `<div>`.

---

## Residual 1 (medium) — the watermark flag doubles as an entitlement bypass

`downloadResponse` gained a `bool $watermark = false` parameter, and the gate is short-circuited by it:

```php
if (! $watermark && $template->isPremium() && ! $this->entitlementService()->canExportTemplate($effectiveUser, $template)) {
    … 403
}
```

So `downloadResponse($cv, $tpl, $user, $lang, watermark: true)` returns a full premium PDF to anyone, watermarked. No caller passes `true` today — I checked all four call sites — so this is unreachable rather than exploitable. But the hazard is that entitlement and watermarking are welded to one caller-supplied boolean, and the natural next feature ("let free users export a watermarked preview PDF") is implemented by flipping that flag at a route, which silently turns off premium gating for that route.

`EntitlementService::shouldWatermark()` already derives the right answer from the user and template. The clean shape is to let `downloadResponse` compute it — decide entitlement from the resolved user, then derive the watermark from that result — rather than accepting it as input. Two lines, and the bypass stops being possible to reintroduce.

## Residual 2 (medium) — nothing verifies the PDF still *looks* right after the font change

Disabling `autoLangToFont` changed which font renders Arabic across every template. The extraction tests prove the text layer is clean, and the presence of presentation forms in `$rawText` (which the NFKC assertion implies) is good evidence that shaping is still happening rather than the letters being emitted unjoined.

But no test looks at pixels, and a text-extraction test structurally cannot: a PDF whose Arabic renders as disconnected letters, with tofu boxes for missing glyphs, or with the wrong metrics, extracts identically to one that renders correctly. Sprint 1's handoff recorded raster verification via pdfium and ImageMagick — that check predates this font change and is exactly the check this change invalidates.

Worth re-running before ship: render one Arabic CV per template and eyeball the raster. Specifically whether DejaVu covers every glyph the previously-substituted font was chosen for.

---

## Two smaller notes

**Signed URLs expire in 30 minutes.** `GeneratedCvResource` builds both `pdf_url` and `template_pdf_url` with `now()->addMinutes(30)`. Any client that caches a CV list — which the mobile app does — will hand the user a dead download link half an hour later. `SignedRecordAccess::temporaryUrl()` exists with a 7-day default and is not used here; the two should agree on one policy.

**`SignedRecordAccess::authorize` tries four signature variants.** Absolute and relative, each with and without ignoring `template`/`language`. It works, but it reads as uncertainty about which form the URL was signed with rather than a decision. The resource signs relative (`URL::temporarySignedRoute(..., false)`) and prepends a host, so `hasValidSignature(false)` is the one that matters — and a consequence worth knowing is that the signature does not cover the host, so the same signature validates against any host serving the app. Not exploitable on its own; worth one line of comment so the next person does not tighten it by accident.

Ignoring `template` in the signature is fine, incidentally — I checked whether a free user could append `?template=<premium-slug>` to their own signed URL, and they cannot get past the gate, because entitlement is decided from the resolved owner rather than from anything in the URL. That is the right place for it.

---

## Sprint 3 status

| | Raised | Status |
|---|---|---|
| Round 1 | S1, S2, S3 + M1–M4 | all closed |
| Round 2 | 2 residuals, 2 notes | Residual 1 worth fixing now; Residual 2 is a verification step, not a code change |

I would sign this off once the watermark flag stops gating entitlement and someone has looked at a rendered Arabic PDF post-font-change. Neither is large.

The S3 fix is the best piece of work in this sprint and worth noting as the pattern: the first attempt post-processed the symptom, the review asked whose problem it actually was, and the second attempt found that a single mPDF flag was substituting the font. Removing the cause deleted the workaround, shrank the code, and made the test assert something that means what it says.
