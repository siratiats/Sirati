READ-ONLY CONSULT. Do not edit files, do not commit. You may run existing tests.
Read AGENTS.md and docs/agents/PROJECT_MEMORY.md first.

A user exported this English CV (template looks like the default "classic"/ATS one) and the formatting is
broken. Evidence (personal data, never copy it into code, tests or commits):
docs/feedback/cv-format-salem/cv.pdf, text.txt (extracted text), p1.png, p2.png (page images).

Most of the input was pasted from another PDF (hard line breaks, ligature characters).

ISSUES SEEN:
A. Page 1 is ~55% empty: the whole experience entry jumps to page 2, and the "PROFESSIONAL EXPERIENCE"
   heading is left alone at the bottom of page 1.
B. Experience entry repeats itself: an italic paragraph with the entire raw description (it even restates
   "Company | Location | Nov 2025 - Present"), then bullets with the same text again.
C. Bullets are split at the pasted hard line wraps: 16 fragment bullets such as "product",
   "categories, consistently improving ...", "Cost Per Acquisition (CPA) ...".
D. Date printed raw as "2025-11-01 - Present" (ISO) instead of "Nov 2025 - Present".
E. Education: three degrees merged into one run-on paragraph joined by "|"; only the first is bold.
F. Compatibility ligature code points U+FB00 / U+FB03 in the PDF text ("oﬀers", "traﬃc", "Oﬃce"), so ATS
   keyword matching fails. They come from the pasted input, not from mPDF (check that too).
G. Header subtitle is the raw target title "Ecommerce"; PDF metadata title/author keep the lowercase "salem".
H. Certifications: the whole line incl. issuer is bold.
I. Core competencies are generic soft skills while the summary is full of hard skills (GA4, GTM, Google Ads).

FOR EACH ISSUE answer: root cause with file:line (input ingestion / form payload -> document model ->
AI generation (did the AI output even get used here?) -> markdown -> CvTemplateRenderer / template view /
CvMarkdownRenderer::preparePdfHtml / mPDF CSS), the fix (where, what), and the invariant test that proves it
(AGENTS.md rule 1: arbitrary inputs, not this CV; rule 4: normalize text once at the ingestion boundary;
rule 6: assert on the exported PDF). Also say which fixes also need the Flutter wizard (flutter_app) or the
web form to change, and whether Arabic CVs are affected by the same causes.

Places to start: app/Services/CvTemplateRenderer.php (viewModel, bodyMarkdown, arrangeSections),
app/Services/Cv/CvMarkdownRenderer.php, resources/views for the CV templates, the CvDocument model/resolve,
app/Http/Controllers/GeneratedCvController.php (storeApi, generatedCvAttributes), GenerateCvContentJob,
app/Services/Ai/Prompts/CvMarkdownFormat.php, app/Services/Ai/GenerateCvResultGuard.php.

ANSWER (max ~80 lines): a table-like list A..I with cause (file:line), fix, test, size (S/M/L); then the
3 fixes you would do first and why; then LEARNINGS: lines.
