# Sprint 3 — Review Round 2: Verification and Finding Resolution

This document records the exact resolutions and verification evidence for all blocking (S1, S2, S3) and medium (M1–M4) findings identified in `SPRINT3_REVIEW_FINDINGS.md`.

---

## Executive Summary

| Finding | Severity | Status | Verification & Resolution |
|---|---|---|---|
| **S2 — Public PDF route authorization** | **Blocking** | **RESOLVED** | Added `SignedRecordAccess::authorize($request, $generatedCv)` to `GeneratedCvController::downloadPdf()`. `SignedRecordAccess::authorize` ignores `['template', 'language']`. `GeneratedCvResource` and `MobileContentController` now emit signed URLs for `template_pdf_url`. Sequential ID walking returns `403 Forbidden`. |
| **S1 — Entitlement resolution on export routes** | **Blocking** | **RESOLVED** | In `CvTemplateRenderer::downloadResponse()` and `GeneratedCvController::downloadPdf()`, user resolution falls back to `$generatedCv->user` when `$request->user()` is null (the mobile signed URL flow). Paying subscribers download their premium templates cleanly (`200 OK`), while free users are strictly blocked (`403 Forbidden`). |
| **S3 — ATS-safe text layer & PUA elimination** | **Blocking** | **RESOLVED** | Set `autoLangToFont = false` and `autoScriptToLang = false` in `CvTemplateRenderer`. This prevents mPDF from substituting `xbriyaz` (the source of PUA glyphs `U+E915`, `U+E940`, `U+E906`). Removed `$puaMap` completely from `ArabicPdfText::unshape()` in adherence to `AGENTS.md` Rule 1. `AtsParseBackTest` now asserts on **raw extraction** that zero PUA codepoints exist in the PDF text layer. |
| **M1 — `phpunit.xml` AI key isolation** | Medium | **RESOLVED** | Confirmed test hermeticity: `phpunit.xml` explicitly isolates the suite with `CV_AI_PROVIDER=openai`, `OPENAI_API_KEY=""`, `DEEPINFRA_API_KEY=""` to prevent live external network calls or host `.env` leakage. All 251 tests run 100% offline. |
| **M2 — `canPreviewTemplate()` gate semantics** | Medium | **RESOLVED** | Updated `EntitlementService::canPreviewTemplate(?User $user, CvTemplate $template): bool` to validate `$template->is_active`. Inactive templates are restricted from preview. |
| **M3 — PDF-level watermark stamp** | Medium | **RESOLVED** | Added native mPDF PDF-level watermark stamping via `$pdf->SetWatermarkText(...)`, `$pdf->showWatermarkText = true`, and `$pdf->watermarkTextAlpha = 0.15` in `CvTemplateRenderer::downloadResponse()`, preventing client DOM tampering. |
| **M4 — `autoLangToFont = true` font substitution** | Medium | **RESOLVED** | Explicitly set `autoLangToFont = false` and `autoScriptToLang = false` alongside `default_font: dejavusans`. Font choice remains deterministic and consistent. |

---

## Detailed Findings & Changes

### S2 — Authorization on Public PDF Routes

#### The Defect
`GeneratedCvController::downloadPdf()` had no authorization check, and neither the web route (`/generated-cvs/{id}/pdf`) nor the API route (`/api/generated-cvs/{id}/pdf`) enforced signatures. An unauthenticated caller could walk sequential IDs to extract personal data (name, email, phone, employment history).

#### The Fix
1. **Controller Enforcement** ([`GeneratedCvController.php`](file:///d:/Sirati/app/Http/Controllers/GeneratedCvController.php#L286-L295)):
   ```php
   public function downloadPdf(Request $request, GeneratedCv $generatedCv, CvTemplateRenderer $renderer)
   {
       SignedRecordAccess::authorize($request, $generatedCv);

       $user = $request->user() ?? $generatedCv->user;

       return $renderer->downloadResponse(
           $generatedCv,
           $request->query('template'),
           $user,
           $request->query('language'),
       );
   }
   ```
2. **Signature Parameter Preservation** ([`SignedRecordAccess.php`](file:///d:/Sirati/app/Support/SignedRecordAccess.php#L13-L17)):
   `SignedRecordAccess::authorize` now ignores both `template` and `language` query parameters:
   ```php
   if ($request->hasValidSignatureWhileIgnoring(['template', 'language']) ||
       $request->hasValidSignatureWhileIgnoring(['template', 'language'], false) ||
       $request->hasValidSignature(false) ||
       $request->hasValidSignature()) {
       return;
   }
   ```
3. **Signed Resource URLs** ([`GeneratedCvResource.php`](file:///d:/Sirati/app/Http/Resources/GeneratedCvResource.php#L48-L49), [`MobileContentController.php`](file:///d:/Sirati/app/Http/Controllers/MobileContentController.php#L86-L87)):
   `template_pdf_url` points to the signed path (`$publicBaseUrl.$signedPath`) instead of a bare unauthenticated URL.

#### Verification
Added in `tests/Feature/PremiumTemplateGatingTest.php`:
- `test_unsigned_requests_to_pdf_download_routes_are_blocked_with_403`:
  - `GET /api/generated-cvs/{id}/pdf` without signature $\to$ `403 Forbidden`
  - `GET /generated-cvs/{id}/pdf` without signature $\to$ `403 Forbidden`
  - Authenticated stranger `GET /generated-cvs/{id}/pdf` $\to$ `403 Forbidden`

---

### S1 — Entitlement Resolution for Export Routes

#### The Defect
`CvTemplateRenderer::downloadResponse()` checked `canExportTemplate($user, $template)`. When mobile accessed the signed URL (`api.generated-cvs.pdf`), `$user` was `null` (unauthenticated signed route), causing paying subscribers to receive `403 Forbidden`.

#### The Fix
1. **Fallback to Record Owner** ([`CvTemplateRenderer.php`](file:///d:/Sirati/app/Services/CvTemplateRenderer.php#L98-L105)):
   ```php
   $effectiveUser = $user ?? $generatedCv->user;

   if (! $watermark && $template->isPremium() && ! $this->entitlementService()->canExportTemplate($effectiveUser, $template)) {
       ...
       abort(403, 'هذا القالب متاح للمشتركين فقط. يرجى الترقية لتحميل السيرة الذاتية بهذا القالب.');
   }
   ```
2. **Controller Resolution**:
   `GeneratedCvController::downloadPdf()` passes `$request->user() ?? $generatedCv->user`.

#### Verification
Added in `tests/Feature/PremiumTemplateGatingTest.php`:
- `test_paying_user_can_download_premium_template_via_signed_url_without_auth_session`:
  - Signed URL for a premium user's CV with `&template=executive-leadership-brief` $\to$ `200 OK` (`application/pdf`).
- `test_free_user_download_of_premium_template_via_signed_url_is_blocked_with_403`:
  - Signed URL for a free user's CV with `&template=executive-leadership-brief` $\to$ `403 Forbidden`.
- `test_paying_user_can_download_premium_template_on_web_route_with_valid_signature`:
  - Signed web URL for a premium user's CV with `&template=executive-leadership-brief` $\to$ `200 OK`.

---

### S3 — ATS-Safe Text Layer & PUA Codepoint Elimination

#### The Defect
`CvTemplateRenderer` had `autoLangToFont = true`. When mPDF detected Arabic text, it substituted `xbriyaz` (`XB_Riyaz.ttf`), which mapped Arabic glyphs into the Unicode Private Use Area (`U+E915`, `U+E940`, `U+E906`). A hardcoded `$puaMap` was introduced in `ArabicPdfText::unshape()` to translate these 5 codepoints back into Arabic letters. This violated `AGENTS.md` Rule 1 (code calibrated to a test fixture) and failed in third-party ATS parsers reading the raw PDF text layer.

#### The Fix
1. **Root-Cause Font Substitution Elimination** ([`CvTemplateRenderer.php`](file:///d:/Sirati/app/Services/CvTemplateRenderer.php#L137-L139)):
   ```php
   $pdf->autoScriptToLang = false;
   $pdf->autoLangToFont = false;
   ```
   With auto-font switching disabled, mPDF retains `dejavusans`. DejaVu Sans emits standard Unicode Arabic Presentation Forms-B (`U+FE70`–`U+FEFF`) and basic Arabic (`U+0600`–`U+06FF`), completely eliminating PUA glyph generation (`PUA Count: 0`).
2. **Complete Removal of `$puaMap`** ([`ArabicPdfText.php`](file:///d:/Sirati/app/Support/ArabicPdfText.php#L37-L57)):
   Deleted the `$puaMap` array. `unshape()` relies purely on standard Unicode NFKC compatibility normalization (`Normalizer::FORM_KC`) for standard Unicode presentation form ranges (`[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]`).
3. **Strict Raw Extraction Assertions** ([`AtsParseBackTest.php`](file:///d:/Sirati/tests/Feature/AtsParseBackTest.php#L118-L132)):
   `AtsParseBackTest` now parses the raw PDF byte stream with `PdfParser` and asserts:
   - Zero PUA codepoints in the raw text:
     ```php
     $this->assertDoesNotMatchRegularExpression(
         '/[\x{E000}-\x{F8FF}]/u',
         $rawText,
         'PDF text layer contains Private Use Area (PUA) codepoints. All glyphs must map to standard Unicode.'
     );
     ```
   - Candidate contacts (`faisal.qahtani@example.com`, `+966509876543`) exist in raw text without normalization.
   - Standard Unicode NFKC eliminates all presentation forms without custom mappings.
   - `ArabicPdfText::normalizeExtracted` handles visual line reordering for RTL, recovering full candidate name (`فيصل`, `القحطاني`) and headings in linear reading order.

---

### M1–M4 — Medium Findings

1. **M1 (`phpunit.xml` AI keys)**: Confirmed test suite isolation. All 251 tests pass hermetically without network requests or dependency on host `.env`.
2. **M2 (`canPreviewTemplate`)** ([`EntitlementService.php`](file:///d:/Sirati/app/Services/EntitlementService.php#L26-L34)):
   Updated `canPreviewTemplate` to validate `$template->is_active`. Covered by `test_inactive_template_cannot_be_previewed`.
3. **M3 (PDF Watermark Stamp)** ([`CvTemplateRenderer.php`](file:///d:/Sirati/app/Services/CvTemplateRenderer.php#L141-L145)):
   Added server-rendered mPDF watermark stamping:
   ```php
   if ($watermark) {
       $pdf->SetWatermarkText($language === 'en' ? 'SIRATI PREVIEW' : 'معاينة سيرتي');
       $pdf->showWatermarkText = true;
       $pdf->watermarkTextAlpha = 0.15;
   }
   ```
4. **M4 (`autoLangToFont`)**: Resolved simultaneously with S3 by setting `autoLangToFont = false`.

---

## Round 2 Verification Findings & Final Sign-off Resolution

This section addresses the two residual items and two notes raised in `SPRINT3_REVIEW_ROUND2_VERIFICATION.md`.

### Residual 1 (Resolved) — Entitlement Gating Decoupled from Watermark Parameter

#### The Finding
`downloadResponse` accepted a `bool $watermark = false` parameter, and the gate checked `if (! $watermark && $template->isPremium() && ! $this->entitlementService()->canExportTemplate(...))`. If a caller passed `watermark: true`, it bypassed premium gating.

#### The Fix ([`CvTemplateRenderer.php`](file:///d:/Sirati/app/Services/CvTemplateRenderer.php#L92-L117))
1. **Removed `$watermark` parameter** from `downloadResponse()`:
   ```php
   public function downloadResponse(
       GeneratedCv $generatedCv,
       ?string $templateKey = null,
       ?User $user = null,
       ?string $languageOverride = null,
   )
   ```
2. **Strict entitlement check before any watermark evaluation**:
   ```php
   if ($template->isPremium() && ! $this->entitlementService()->canExportTemplate($effectiveUser, $template)) {
       if (request()?->wantsJson()) {
           return response()->json([
               'message' => 'هذا القالب متاح للمشتركين فقط. يرجى الترقية لتحميل السيرة الذاتية بهذا القالب.',
               'error' => 'premium_template_locked',
               'template' => $template->slug,
           ], 403);
       }
       abort(403, 'هذا القالب متاح للمشتركين فقط. يرجى الترقية لتحميل السيرة الذاتية بهذا القالب.');
   }
   ```
3. **Watermark derived strictly via policy**:
   ```php
   $watermark = $this->entitlementService()->shouldWatermark($effectiveUser, $template);
   ```
Entitlement and watermarking are now completely decoupled; caller input cannot disable the premium export gate.

---

### Residual 2 (Verified) — Visual Pixel Eyeball Verification of Arabic PDFs Post-Font Change

#### The Finding
Disabling `autoLangToFont = true` switches Arabic rendering from `xbriyaz` to `dejavusans`. While extraction tests proved the raw text layer is ATS-clean and free of PUA codepoints, extraction tests cannot detect disconnected Arabic letters, tofu/missing glyph boxes, or layout corruption.

#### Eyeball & Raster Verification Method
A standalone verification script (`render_and_rasterize.php`) was executed to render an authentic Arabic CV payload (with candidate profile, summary, work experiences, education, skills, and certifications) across all 5 seeded templates:
1. `ats-classic-professional`
2. `graduate-launchpad`
3. `executive-leadership-brief`
4. `sales-impact-performer`
5. `bilingual-global-professional`

Each generated PDF was rasterized at 150 DPI using Ghostscript 10.03.1 and ImageMagick 7.1.1 into high-resolution PNG images (`raster_verify_*.png`) and visually inspected.

#### Visual Inspection Observations
- **Cursive Shaping & Joining**: All Arabic letters (including complex ligatures like `لا`, `لأ`, `لإ`, `لآ`, initial, medial, and final forms) are 100% properly connected and shaped. No isolated or detached glyphs exist.
- **Glyph Coverage & Tofu**: DejaVu Sans provides complete Unicode coverage for all Arabic characters used in titles, descriptions, and metadata. Zero tofu/missing glyph boxes (`□`) or substitution artifacts.
- **RTL Alignment & Typography**: Margins, bullet points, headers, and section rules align precisely on the right. Numbers, percentages (`28%`, `35%`), phone numbers (`+966 50 987 6543`), email addresses, and mixed English terms (e.g. `DevOps`, `AWS`, `PMP`, `Scrum`) render in their natural reading order without flipping or visual distortion.
- **Watermark Stamp**: For watermarked renders, the diagonal `معاينة سيرتي` stamp renders subtly at 15% opacity across the page without obscuring underlying text or disrupting layout.

---

### Note 1 (Resolved) — Signed URL Expiration Alignment

#### The Finding
`GeneratedCvResource` and `MobileContentController` generated download URLs with a 30-minute expiration (`now()->addMinutes(30)`), which caused links to expire prematurely when mobile clients cached CV lists. In contrast, `SignedRecordAccess::temporaryUrl()` defaulted to 7 days.

#### The Fix ([`GeneratedCvResource.php`](file:///d:/Sirati/app/Http/Resources/GeneratedCvResource.php#L45), [`MobileContentController.php`](file:///d:/Sirati/app/Http/Controllers/MobileContentController.php#L83))
Aligned expiration to 7 days across all signed CV download endpoints:
```php
$expires = now()->addDays(7);
```

---

### Note 2 (Resolved) — Signature Validation Architecture & Host Binding

#### The Finding
`SignedRecordAccess::authorize` checks multiple signature forms. Clarify why both relative and absolute forms are evaluated.

#### The Architecture ([`SignedRecordAccess.php`](file:///d:/Sirati/app/Support/SignedRecordAccess.php#L11-L29))
- **Relative Signatures**: Mobile API resources generate relative signed paths (`URL::temporarySignedRoute(..., false)`) and prepend the public application URL (`config('app.url')`). This decouples signature validity from internal server hosts/reverse proxies. Validation checks `hasValidSignatureWhileIgnoring(['template', 'language'], false)`.
- **Absolute Signatures**: Web routes and web test suites generate absolute URLs including host and scheme via `SignedRecordAccess::temporaryUrl()`. Validation checks `hasValidSignatureWhileIgnoring(['template', 'language'])`.
- **Allowed Query Filters**: `['template', 'language']` query parameters can be adjusted by the client (e.g. switching templates or export languages) without invalidating the signed URL, because entitlement is strictly determined by the resolved CV owner, not the query parameters.
- Documented in code comments to prevent accidental regressions.

---

## Post-Sign-off Refinements & Architecture Decisions

### 1. Raster Verification Assets in Repository
The 5 rasterized 150-DPI PNG verification images have been copied into the repository under [`docs/verification/`](file:///d:/Sirati/docs/verification/):
- [`raster_verify_ats-classic-professional.png`](file:///d:/Sirati/docs/verification/raster_verify_ats-classic-professional.png)
- [`raster_verify_graduate-launchpad.png`](file:///d:/Sirati/docs/verification/raster_verify_graduate-launchpad.png)
- [`raster_verify_executive-leadership-brief.png`](file:///d:/Sirati/docs/verification/raster_verify_executive-leadership-brief.png)
- [`raster_verify_sales-impact-performer.png`](file:///d:/Sirati/docs/verification/raster_verify_sales-impact-performer.png)
- [`raster_verify_bilingual-global-professional.png`](file:///d:/Sirati/docs/verification/raster_verify_bilingual-global-professional.png)

These allow any reviewer or team member to inspect the DejaVu Sans Arabic cursive shaping, zero-tofu rendering, and RTL alignment directly inside the repository.

### 2. Elimination of Dead Watermark Code in Export
- In `CvTemplateRenderer::downloadResponse()`, because entitlement (`canExportTemplate`) is strictly required prior to generation, export downloads are never watermarked.
- Extracted `renderPdfBlob(GeneratedCv $generatedCv, CvTemplate $template, string $language, bool $watermark = false)` as the PDF rendering engine.
- `downloadResponse()` explicitly passes `watermark: false` to `renderPdfBlob()`, removing any misleading illusion that export downloads calculate a watermark.
- Added `previewPdfResponse()` gated by `canPreviewTemplate()`, which explicitly exercises the native mPDF watermark stamp (`SetWatermarkText`) for unentitled users previewing premium templates. Verified via `test_free_user_preview_pdf_response_includes_watermark_stamp`.

### 3. Signed URL 7-Day Window Decision Record
- **Context**: The signed URL TTL was expanded from 30 minutes to 7 days (`now()->addDays(7)`) to match `SignedRecordAccess::temporaryUrl()` and avoid dead links when mobile clients cache CV lists.
- **Trade-off Analysis**: A 7-day signed URL acts as a bearer token for candidate PII (name, email, phone, work history) if leaked. However:
  - The URL is cryptographically signed and scoped only to `/pdf` download.
  - Authenticated CV owners do not rely on the signature; `SignedRecordAccess::authorize` allows authenticated owners to download indefinitely regardless of signature expiration.
  - 7 days cleanly accommodates weekly client cache lifecycles without requiring background token refreshes.
  - If a tighter security posture is preferred by security compliance, reducing to 24 hours (`now()->addHours(24)`) can be configured without breaking API contracts.

### 4. Resolution of Footer Bidi Defect (`_footer.blade.php`)
- **The Defect**: In Arabic RTL context, the CV footer interpolated the ATS grade raw next to the Arabic label `نتيجة ATS: 94% · A+`. Under UAX #9 rules N1/N2, the neutral `+` trailing at the end of the LTR grade was resolved to the paragraph direction (RTL) and stranded at the opposite end of the line, rendering as `+ATS: 94% · A`.
- **The Resolution**:
  - Created shared partial [`resources/views/generated-cvs/templates/_footer.blade.php`](file:///d:/Sirati/resources/views/generated-cvs/templates/_footer.blade.php), wrapping both score total and grade in dedicated inline bidi embed spans:
    ```blade
    @if ($cv['score']['total'] !== null)
        <div class="footer">
            <span class="footer-label">{{ $cv['labels']['ats_score'] }}:</span>
            <span class="footer-metric" style="direction: ltr; unicode-bidi: embed; display: inline-block;">{{ $cv['score']['total'] }}%</span>
            @if (filled($cv['score']['grade']))
                <span class="footer-separator" style="color: #9ca3af; padding: 0 4px;">·</span>
                <span class="footer-metric" style="direction: ltr; unicode-bidi: embed; display: inline-block;">{{ $cv['score']['grade'] }}</span>
            @endif
        </div>
    @endif
    ```
  - Replaced inline footer code across all 8 Blade templates (`ats-classic-professional`, `bilingual-global-professional`, `executive-leadership-brief`, `graduate-launchpad`, `modern-rtl`, `sales-impact-performer`, `technical-specialist-matrix`, and `pdf.blade.php`) with `@include('generated-cvs.templates._footer')`.
  - Re-rendered and re-rasterized all 5 template PNGs in [`docs/verification/`](file:///d:/Sirati/docs/verification/), visually confirming the footer now renders flawlessly: `نتيجة ATS: 94% · A+`.
  - Added test `test_arabic_pdf_footer_isolates_grade_and_score_metrics_without_stranding_plus` in `CvPdfRenderingTest.php`, asserting that `+ATS` never appears in the extracted PDF text layer and that `A+` is preserved intact.

### 5. Preview PDF Route & Watermark Security Notes
- `previewPdfResponse()` currently serves as an engine method and test fixture. When wired to an HTTP route in future sprints, the controller must invoke `SignedRecordAccess::authorize($request, $cv)` to prevent unauthenticated access.
- For business IP protection, watermark opacity can be tuned between 0.20–0.25 if full-page watermarked PDF previews are offered to free users.

---

## Test Execution Results

### 1. Backend Test Suite
```bash
php artisan test
```
**Result**:
```
Tests:    252 passed (2962 assertions)
Duration: 33.31s
```

### 2. Flutter Code Quality & Analysis
```bash
cd flutter_app && flutter analyze
```
**Result**:
```
No issues found! (ran in 32.4s)
```

### 3. Flutter Test Suite
```bash
cd flutter_app && flutter test
```
**Result**:
```
00:28 +162: All tests passed!
```

---

## Quality Gate Checklist

- [x] **AGENTS.md Rule 1 (Invariant, Not Examples)**: Zero PUA codepoint mappings. Fonts and ToUnicode CMaps adhere strictly to Unicode standard; normalization tested on general invariants.
- [x] **Residual 1 Closed**: `downloadResponse` watermark parameter removed; gating strictly evaluates `canExportTemplate`; watermark derived from `shouldWatermark`.
- [x] **Residual 2 Eyeball Verified**: 5/5 Arabic templates rasterized and visually confirmed: 100% cursive joining, zero tofu boxes, correct RTL alignment, crisp mixed LTR tokens.
- [x] **Footer Bidi Defect Closed**: Created `_footer.blade.php` partial; isolated `A+` and metrics; verified via raster images and unit tests.
- [x] **Note 1 Closed**: Signed URL expiration aligned to 7 days (`now()->addDays(7)`) across mobile resources.
- [x] **Note 2 Documented**: Relative and absolute signature validation documented with rationale.
- [x] **Zero Tolerance Contrast & Strict Math**: Contrast assertions verified in Flutter theme tests.
- [x] **PDPL & Authorization Compliance**: All CV download endpoints secured by cryptographic signatures or authenticated record ownership.
- [x] **Revenue & Entitlement Protection**: Mobile signed URLs correctly resolve paying candidate subscriptions and forbid unauthorized premium downloads.
- [x] **Zero Analyzer Warnings & Green CI**: 0 warnings in `flutter analyze`, 162/162 Flutter tests passing, 252/252 PHP tests passing.


