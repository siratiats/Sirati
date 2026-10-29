READ-ONLY REVIEW. Do not edit files, do not commit, do not run the app. You may run the tests named below.
Read AGENTS.md and docs/agents/PROJECT_MEMORY.md first.

CONTEXT: an exported English CV was badly formatted (evidence, personal data, never copy it anywhere:
docs/feedback/cv-format-salem/p1.png, p2.png, text.txt). Claude fixed it. The working tree also holds other
uncommitted work (PDF pre-render, Redis queue, Claude timeouts) - ignore it. Review ONLY these changes:

- app/Support/Utf8Sanitizer.php - Latin ligatures U+FB00-FB06 expanded; sanitizeDeep() for arrays
- app/Cv/CvTextLayout.php (new) - unwrapLines (rejoin hard-wrapped pasted lines), narrativeRepeatsBullets,
  splitQualifications (one education entry per degree), splitIssuer ("Name — Issuer"), tidyDocument
- app/Models/GeneratedCv.php - setAttribute runs sanitizeDeep on document/form_payload and tidyDocument on document
- app/Cv/LegacySectionParser.php - splitBody, education() and certifications() use CvTextLayout
- app/Cv/CvDocument.php - displayDate(): ISO and MM/YYYY print as "Nov 2025" / "نوفمبر 2025"
- resources/views/generated-cvs/templates/ats-classic-professional.blade.php - entries may split across
  pages; only heading + first entry header are kept together (.keep / .entry-head); descriptions keep line
  breaks; education details on their own line
- app/Services/CvTemplateRenderer.php - ONLY the RENDERER_VERSION bump 7 -> 8
- database/migrations/2026_10_26_000001_tidy_pasted_cv_text.php - one pass over existing rows, quiet saves
- tests/Unit/CvTextLayoutTest.php, tests/Feature/CvPdfFormattingTest.php, tests/Feature/TidyPastedCvTextMigrationTest.php

Use `git diff -- <file>` for modified files; the others are new.

TRY TO BREAK IT (give a concrete input for every finding):
1. unwrapLines: a real separate bullet wrongly joined, or a wrap left split. Think of: bullets without
   periods that are 50+ chars, a line ending with "and"/comma that is a complete item, lines starting with
   "(", numbers, acronyms, Arabic text (no case), mixed Arabic/English, a heading like "Key achievements:".
2. narrativeRepeatsBullets / tidyDocument: a genuine description dropped; bilingual entries (ar + en bullets)
   misaligned; key-order or shape changes that break CvDocument::fromArray / toArray round trips; idempotence.
3. splitQualifications: detail lines that start with a degree word ("Associate member...", "MA" vs
   "Massachusetts", "BS" abbreviations) splitting wrongly; Arabic degree words; text before the first degree.
4. splitIssuer: certificate names that legitimately contain " — " or " – ".
5. Ingestion contract (AGENTS.md rule 4): is text normalized once at the boundary? Any write path that skips
   GeneratedCv::setAttribute (query builder updates, DB::table, mass updates, the duplicate/copy flows,
   GenerateCvContentJob overlay)? Is anything now normalized at render time instead?
6. Template / mPDF: can a heading still end a page? Can .keep on a long text/tags section push it whole to the
   next page and leave a large gap? RTL templates and the Arabic date cell (direction: ltr) with Arabic
   month names.
7. Migration: safe on a large production table (memory, chunkById while updating, events off, timestamps
   untouched), idempotent, and harmless for rows with null/empty document.
8. Tests: do they test invariants over arbitrary inputs (AGENTS.md rule 1), and assert on the downloaded PDF
   (rule 6)? Would they fail on the old code?

RUN ONLY: php artisan test tests/Unit/CvTextLayoutTest.php tests/Feature/CvPdfFormattingTest.php
tests/Feature/TidyPastedCvTextMigrationTest.php tests/Unit/LegacySectionParserTest.php tests/Feature/CvPdfRenderingTest.php

ANSWER (max ~50 lines): first line VERDICT: LGTM or VERDICT: CHANGES. Then findings ranked by severity, each
with file:line, the concrete input that breaks it, what happens, and the fix in one sentence. Then the test
result line. Then LEARNINGS: lines.
