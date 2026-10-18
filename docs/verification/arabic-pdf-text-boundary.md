# Arabic PDF text boundary verification

The production chain is `GeneratedCvController::downloadPdfApi` →
`CvTemplateRenderer::downloadResponse` → `LogicalTextMpdf`. Uploaded PDFs enter
through `CvTextExtractor::extractPdf`, which canonicalizes extracted Unicode
once. Database fields and the CV document remain logical and unchanged.

The baseline failures had two causes:

- `ArabicPdfText` expanded lam-alef glyphs before reversing their characters.
  For example, the visual glyph stream `ﻻ تﻻﻵا ةرادﻹ ﺔﻤﻈﻧﻷا` became
  `األنظمة إلدارة اآلالت ال` instead of `الأنظمة لإدارة الآلات لا`.
- Structured sections bypassed the Markdown renderer's Latin embeddings, so
  neutral symbols in `C++` and `C#` followed the RTL paragraph direction.
  Protecting only Markdown and candidate identity fields missed the exported
  summary, experience, and skills. FreeSans GSUB also introduced Latin `ff`/`ffi`
  ligatures that changed extracted English facts.

Export now protects all rendered HTML body text nodes. Arabic shaping remains
enabled; Latin GSUB substitutions are disabled. The mPDF adapter captures each
line before bidi reordering and writes standard PDF `ActualText` in logical
Unicode alongside the existing glyph commands. Empty inline objects (including
list markers) cannot consume the line's replacement text. Each marked sequence
stays on its page, including wrapped paragraphs and table/layout replays.

`normalizeExtracted` no longer reverses text or guesses its direction from
Arabic spelling. It unshapes presentation forms and removes explicit display
controls idempotently. The retained direction argument is backward compatible
at the call boundary; direction is now represented by the PDF renderer.
Untagged third-party visual PDFs need an explicit source-specific adapter.

Acceptance coverage uses the actual download bytes: 201 generated Arabic/Latin
lines compare exactly with their source runs across multiple pages, covering
every isolated/final lam-alef form, `+ # . / -`, digits, and several positions.
A 640-run paragraph additionally checks wrapping and page order. The download
is also uploaded through the production extractor, and stored attributes must
remain byte-for-byte unchanged. JobMatch export expectations are unchanged.

The adapter uses mPDF 8.x private service integration because that version has
no ActualText extension hook; real export tests guard this dependency on upgrade.

Visual check: Ghostscript `png16m`, 110 dpi, text/graphics antialiasing 4, first
page of `storage/app/testing/arabic-pdf-logical.pdf` produced by
`CvPdfRenderingTest::test_mixed_arabic_fields_export_logically_with_symbols_and_lam_alef`.
The raster was opened and inspected: joined Arabic, lam-alef, RTL alignment,
contact order, and intact `C++`, `C#`, `PostgreSQL`, `Node.js`, `CI/CD`, and
`TLS-1.3` throughout header and structured sections.

![Inspected Arabic CV export](arabic-pdf-logical.png)

The [PDF used for this raster](arabic-pdf-logical.pdf) is retained for review.

Validation: 318 JobMatch-filtered tests passed (5,330 assertions). The Arabic,
Markdown, PDF rendering, ATS parse-back, and ATS calibration files passed; all
nine template/export-safety tests passed with a temporary process-only APP_KEY
for the session-backed admin cases. That is 54 related tests in total. Pint and
`git diff --check` passed. No full-suite run was performed.
