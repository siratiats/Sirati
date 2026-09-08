# Sprint 3 — raster verification, done independently

Putting the PNGs in `docs/verification/` closed the one gap I flagged: I opened them myself rather than relying on someone else's inspection. Two templates examined in full (`ats-classic-professional`, `executive-leadership-brief`).

**Sign-off stands.** The reported findings were accurate, and I found one defect in the render that the first pass missed.

---

## Confirmed by direct inspection

The font change is clean. Everything the round-2 report claimed holds up:

- **Cursive joining is correct throughout.** `فيصل بن ناصر القحطاني`, `الملخص المهني`, `الخبرة المهنية`, `مدير برامج التحول الرقمي` — initial, medial and final forms all connect. No isolated letterforms anywhere.
- **Ligatures render.** Lam-alef in `الأعمال`, `الأولى`, `الاستراتيجية`.
- **No tofu.** Zero missing-glyph boxes across both pages, including hamza forms (`أ`, `إ`, `ؤ`) and taa marbuta.
- **RTL layout holds.** Headings and body flush right, date column flush left, rules and bullets on the correct side.
- **Mixed LTR tokens read correctly** — `DevOps`, `Agile & Scrum`, `AWS`, `(PMP)`, `(PMI)`, `faisal.qahtani@example.com`, `+966509876543`, `linkedin.com/in/faisal-qahtani`, and the numerals `40`, `35%`, `2021 - 2024`, `2030`. None reversed, none broken across the direction boundary.

Disabling `autoLangToFont` did not cost anything visually. That was the real risk and it is cleared.

---

## One defect the raster does show — the ATS footer

Both templates render the footer as:

```
+ATS: 94% · A
```

The `+` from the grade **`A+`** has detached from the `A` and jumped to the opposite end of the line. It should read `نتيجة ATS: 94% · A+`.

This is textbook UAX #9: `+` is a neutral character, it sits at the end of an LTR run (`A`) against the RTL paragraph boundary, so rules N1/N2 resolve it to the paragraph direction and it gets placed at the far side of the line, orphaned from the letter it belongs to.

It is also the Sprint 1 finding resurfacing in a new spot. `CvMarkdownRenderer::shapeText()` early-returns unless the *whole string* contains Arabic, so a pure-Latin value like `A+` never receives LTR isolation. The contact line survives because the templates wrap it in `.contact-item { direction: ltr; unicode-bidi: embed; }` — the footer has no equivalent span, and the score and grade are interpolated raw:

```blade
{{ $cv['labels']['ats_score'] }}: {{ $cv['score']['total'] }} @if (filled($cv['score']['grade'])) · {{ $cv['score']['grade'] }} @endif
```

**Fix:** isolate the grade token — either wrap it in `<span class="contact-item">`-style CSS like the contacts, or run it through the isolation the sprint already built (`⁦ … ⁩`). One line in each of the eight templates, or better, one shared footer partial the way `_sections.blade.php` consolidated the body.

Cosmetic, but it appears in the footer of every exported CV, and it is precisely the defect class three sprints of bidi work were aimed at. Worth fixing before ship.

---

## The dead watermark is properly resolved

`renderPdfBlob()` is extracted, `downloadResponse()` calls it with `watermark: false` explicitly, and `previewPdfResponse()` exercises the mPDF stamp with a test behind it. The block is no longer dead code pretending to be a control.

**One thing to note before it ships:** `previewPdfResponse()` has no route yet and performs no ownership or signature check of its own — it gates only on `canPreviewTemplate()`, which is `is_active`. That is fine as written, because `downloadPdf()` does its authorization in the controller. But whoever routes this must add `SignedRecordAccess::authorize($request, $generatedCv)` alongside it, or S2 comes straight back: an unauthenticated, full-fidelity PDF of any CV, watermarked.

There is also a product question worth answering deliberately rather than discovering later: once routed, any free user can obtain a complete premium-template PDF with a 15%-alpha diagonal stamp. If the watermark is meant to label a preview, that is fine. If it is meant to protect template value, 15% alpha on an otherwise perfect PDF is thin.

The TTL decision is recorded with its rationale and its downgrade path. Good.

---

## Sprint 3 final status

| | Raised | Status |
|---|---|---|
| Round 1 | S1, S2, S3, M1–M4 | closed |
| Round 2 | 2 residuals, 2 notes | closed |
| Round 3 | 1 observation, 1 trade | closed |
| Raster pass | footer bidi defect | open — small |

Everything else is done. The footer `A+` is the only thing I would hold for, and it is a one-line change.

Worth saying: moving those PNGs into the repo is what made this possible. Three sprints of review turned up exactly one claim I could not check, and putting the evidence where the reviewer can reach it turned that into a finding. That is a habit worth keeping — verification artefacts belong in the repo next to the thing they verify.

---

## Addendum — footer fix verified in the re-rendered raster

I opened the regenerated PNGs. The footer now reads:

```
before:   +ATS: 94% · A       ← the + stranded at the far end
after:     ATS: 94% · A+      ← bound to the A
```

Verified on `ats-classic-professional` and `sales-impact-performer`, so the shared `_footer.blade.php` applied consistently rather than one template being patched. No double `%` from the partial's added sign, and nothing else in the layout moved.

**Sprint 3 is closed.** Every finding across three review passes plus the raster pass is resolved.

One small note on the new test, not worth reopening anything:

```php
$this->assertStringNotContainsString('+ATS', $extracted);
$this->assertStringContainsString('A+', $extracted);
```

The first assertion is written against the exact symptom string from my report — it catches a `+` stranded immediately before the label, and nothing else. If a neutral strands somewhere different (ahead of the score, or after a label change), it passes while the bug is present. The second assertion is the actual invariant and does hold generally; it is the one carrying the weight. `AGENTS.md` rule 1 is about exactly this distinction, and it is worth keeping in view: the negative assertion describes what went wrong once, the positive one describes what must always be true.

---

## Addendum 2 — the generalized test now fails on correct output

Generalizing across `A+`, `B+`, `C-` was the right move, and the two positive assertions are exactly the invariant:

```php
$this->assertStringContainsString($grade, $extracted);      // grade intact
$this->assertStringContainsString("{$score}%", $extracted); // score intact
```

The third one is over-broad:

```php
$this->assertDoesNotMatchRegularExpression('/(?:^|\s)[+\-](?=\S)/u', $extracted);
```

It matches **any** `+` or `-` that follows whitespace and precedes a non-space — which includes content that is entirely correct. Executed against real rendered output:

| Text | Result |
|---|---|
| `+ATS: 94% · A` — the actual bug | matches ✓ correctly caught |
| `…example.com \|+966509876543 \|linkedin…` — ats-classic contact line | clean |
| `…example.com ♦ +966509876543 ♦ linkedin…` — **executive-leadership-brief contact line** | **matches — fails on correct output** |
| `خفض التكلفة -15% خلال عام` — a negative delta in a bullet | **matches — fails on correct output** |
| `الهاتف\n+966509876543` — phone on its own line | **matches — fails on correct output** |

The test passes today only because it pins `ats-classic-professional`, whose contact separator is `|` with no space before the `+`. `executive-leadership-brief` renders `♦ +966…` and would fail immediately. A candidate writing "reduced cost by -15%" would fail it too.

That is worse than the symptom-calibrated version it replaced. A test that misses a bug costs you the bug; a test that fails on correct output costs you the test, because the next person to hit it weakens the assertion rather than investigating.

Two ways to keep the invariant without the false positives:

1. **Scope it.** Pull the footer line out of the extraction and run the negative check on that line only, rather than the whole document.
2. **Count instead of pattern-match.** Assert the extracted text contains exactly as many `+` characters as the input did. That is invariant, has no false positives, and catches a stranded modifier anywhere — including places a positional regex would miss.

Option 2 is the stronger one, and it generalizes to any neutral, not just `+` and `-`.

Nothing else changes: the two positive assertions carry the real guarantee and they hold across all three grades.
