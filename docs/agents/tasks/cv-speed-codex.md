Read AGENTS.md and docs/agents/PROJECT_MEMORY.md first.

GOAL: make CV PDF download fast. Today every first download runs mPDF inside the HTTP request
(GeneratedCvController::downloadPdfApi / downloadPdf -> CvTemplateRenderer::downloadResponse -> renderPdfBlob),
and the only cache is a 1h base64 blob in the database cache. Pre-render the PDF in the background after the
CV is ready, store it as a file, and serve the stored file on download.

Before editing, state your design in 3-5 bullets.

BACKEND (Laravel):
1. Fingerprint: add CvTemplateRenderer::pdfFingerprint(GeneratedCv, CvTemplate, string $language): string.
   Hash everything that changes the PDF bytes: the CV content the renderer reads (generated_markdown, the
   structured/resolved document, updated_at, ai_status), template slug + template config/updated_at,
   language, photo path + file size/mtime (same-path photo replacement must change it), and a RENDERER_VERSION
   constant. Replace pdfCacheKey usage with it.
2. Storage: private disk, path cv-pdfs/{generated_cv_id}/{fingerprint}.pdf. Never public. After writing a new
   file, delete older files for the same CV + template + language.
3. renderPdfBlob: if the file exists, return it; otherwise render (Cache::lock on the fingerprint so two
   requests don't render the same PDF twice), store the file, return it. Remove the database blob cache.
   Downloads must stay correct when no prerender ran (sync render fallback stays).
4. Remove the duplicated work in renderPdfBlobUncached: renderHtml and viewModel are both built (lines ~166
   and ~186). Build the view model once and reuse it. Keep Arabic OTL, LogicalTextMpdf and
   CvMarkdownRenderer::preparePdfHtml exactly as they are.
5. New app/Jobs/RenderCvPdfJob.php (ShouldQueue, afterCommit, unique per fingerprint, queue from
   config('cv_pdf.queue', 'default') in a new config/cv_pdf.php). Render the CV's default template + its
   language. Dispatch it from a GeneratedCv model observer (new file) when ai_status becomes completed or when
   CV content / template / photo changes while completed. Do NOT edit app/Jobs/GenerateCvContentJob.php.
6. Keep every check that runs before serving: SignedRecordAccess / authorizeApiAccess,
   CvExportGuard::assertExportable, premium template entitlement. A stored file is never served to a user who
   is not allowed to export it.
7. Log one line per download: cv id, cache hit/miss, render_ms (on miss), bytes.

FLUTTER (flutter_app, nested repo):
8. pdf_export_controller.dart / api_client.dart: the PDF download gets its own receive timeout (90s) instead of
   the global 45s; progress shows real download progress (onReceiveProgress) instead of a fixed 45%.
9. cv_api_service.dart polling: the 210s limit counts wall-clock time (Stopwatch), not only sleep time.
   Keep AiTimeouts::CLIENT_POLL_SECONDS in sync (do not change its value).

DO NOT TOUCH: app/Services/ClaudeCvService.php, app/Services/Ai/*, config/services.php, config/queue.php,
config/cache.php, .env*, app/Jobs/GenerateCvContentJob.php, routes baseline files. Do not commit.

TESTS (AGENTS.md rules 1 and 6: test the invariant, assert on the downloaded artefact):
- new tests/Feature/CvPdfArtifactTest.php: second download does not render again; the downloaded bytes are a
  valid PDF whose extracted text contains the CV name (Arabic and English CV); fingerprint changes for edited
  content, other template, other language, replaced photo at the same path; a user who loses export rights
  gets 403/422 even when a stored file exists; the observer dispatches RenderCvPdfJob once on completion.
- Run only: php artisan test tests/Feature/CvPdfArtifactTest.php tests/Feature/CvPdfRenderingTest.php
  tests/Feature/CvExportSafetyTest.php tests/Feature/CvTemplateFeatureTest.php tests/Feature/JobMatchExportTest.php
- Flutter: flutter test test/features/cv_export test/cv_api_service_test.dart

FINAL ANSWER: files changed, design bullets, test results, then LEARNINGS: lines.
