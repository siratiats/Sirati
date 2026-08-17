# Sirati — Remediation Brief

**Scope:** Fixes for defects found reviewing the CV form assist + template rendering work.
**Written:** 2026-07-27
**Supersedes nothing.** Companion to `form-and-template-brief.md`.

> **Caveat on this whole document.** No test suite has been run against any of this work. The review sandbox has no PHP runtime, so every finding below is static code review. Run `composer test` and `flutter test` before and after each fix.

---

## Status

| # | Issue | Severity | Fix |
|---|---|---|---|
| 1 | 25 Arabic strings double-encoded in `cv_generator_screen.dart` | **Blocker** | Script (Fix 1) |
| 2 | `EnhanceCvFieldResultGuard` silently deletes valid user content | **High** | Prompt C |
| 3 | Contact fields forced RTL — emails/URLs may render reversed | Medium | Prompt D |
| 4 | `CvMarkdownRenderer` returns empty string on DOM lookup failure | Medium | Prompt D |
| 5 | Markdown links unwrapped, URLs lost | Low | Prompt D |

Issues 1 and 2 are release blockers for the Flutter form work. Issues 3–5 affect PDF output quality and can ship slightly later, but 3 is visible to every Arabic user.

---

## Fix 1 — Character encoding (do this first, by hand)

**What happened.** Every Arabic string literal added to `flutter_app/lib/screens/cv_generator_screen.dart` was written as mojibake — UTF-8 bytes encoded a second time as Latin-1:

```dart
actionLabel: english ? 'Undo' : 'ØªØ±Ø§Ø¬Ø¹',   // intended: 'تراجع'
```

**Scope.** 29 lines, all in that one file. Confirmed *not* a display artifact: the pre-existing Arabic on line 683 (`'تم تحسين الوصف الوظيفي.'`) is intact, and the newly created `widgets/ai_cv_field.dart` is clean. Only the strings this agent wrote into this file are affected.

**Impact.** Every new label, hint, placeholder, validation message and snackbar in the CV generator form renders as garbage for Arabic users — your primary audience.

**Why this needs a script rather than an agent prompt.** The corrupted text contains invisible C1 control characters (UTF-8 continuation bytes in the `0x80`–`0x9F` range become non-printing control codes when misread as Latin-1). They cannot be reliably reproduced by copying the rendered text, so exact-match find-and-replace fails unpredictably. 4 of the 29 were repaired this way; the remaining 25 need a byte-level transform.

**Do not** run a blanket re-decode across the whole file. That would corrupt the correctly-encoded Arabic on line 683. The script below only touches lines carrying the mojibake signature.

```powershell
$p = "D:\Sirati\flutter_app\lib\screens\cv_generator_screen.dart"
$cp1252 = [Text.Encoding]::GetEncoding(1252)
$lines = [IO.File]::ReadAllLines($p, [Text.Encoding]::UTF8)
for ($i = 0; $i -lt $lines.Length; $i++) {
  if ($lines[$i] -match '[\u00d8\u00d9\u00e2]') {
    $lines[$i] = [Text.Encoding]::UTF8.GetString($cp1252.GetBytes($lines[$i]))
  }
}
[IO.File]::WriteAllLines($p, $lines, (New-Object Text.UTF8Encoding $false))
```

The `\u00e2` in the pattern catches lines whose only corruption is an em dash (`â€"` ← `—`), which appear in the placeholder examples.

**Verify before committing:**

```powershell
git diff --stat
Select-String -Path $p -Pattern '[\u00d8\u00d9\u00e2]'   # must return nothing
```

Then open the file and read the Arabic. `git diff` should show ~25 changed lines and nothing else.

**Prevention.** Add this line to any future prompt that touches files containing Arabic:

> All files are UTF-8. Write Arabic string literals as UTF-8 directly. Do not encode, escape, or transliterate them. After editing, verify no line matches the mojibake signature `[Ø Ù â]` — if any does, the file was written with the wrong encoding and must be rewritten, not patched.

---

## PROMPT C — Make the fabrication guard advisory, not destructive

```
Rewrite App\Services\Ai\EnhanceCvFieldResultGuard so it reports unsupported facts
instead of deleting the user's text.

## The problem

The guard currently calls removeLinesContaining() to strip any line containing a
date or employer name absent from the draft. Two things make this unsafe:

1. The employer regex is far too greedy:

     /(?:\bat\s+|\bfor\s+|في\s+شركة\s+|لدى\s+شركة\s+)([\p{L}\p{N}&.\- ]{2,60})/ui

   The capture class includes spaces and periods, so it swallows up to 60
   characters after any 'at' or 'for'. Real example:

     draft:    "Managed delivery for the retail team"
     enhanced: "Managed delivery for the retail team across 3 regions"
     captured: "the retail team across 3 regions"  → not in draft → LINE DELETED

   The user's improved bullet vanishes and they are told to "supply the employer
   name (the retail team across 3 regions)". This will fire constantly on normal
   English CV text.

2. The date check does not normalize Arabic-Indic digits:

     preg_match_all(...); if (! str_contains($draft, $date))

   A draft written "٢٠٢٤" and output written "2024" is treated as fabricated.
   CachedCvAiProvider::normalizeArabicIndicDigits() already solves exactly this —
   extract it to a shared helper and use it here rather than duplicating it.

Deleting a user's text based on a greedy regex is a worse outcome than the
fabrication risk it guards against, especially now that the Flutter UI has Undo
and renders missing_facts prominently.

## Required behaviour

- NEVER modify enhanced_text. Return it exactly as the model produced it.
- Delete removeLinesContaining() entirely.
- Detected unsupported facts are appended to missing_facts as specific,
  actionable Arabic (or English) guidance naming the exact token.
- Add a fourth return key: unverified_claims — a list of
  {text: string, kind: 'date'|'employer'} so the UI can highlight the specific
  spans rather than showing a generic warning. Update EnhanceCvFieldSchema's
  documented return shape and the Flutter parsing to match.

## Tighten the detection

Dates: keep the existing pattern, but normalize BOTH draft and candidate through
the shared Arabic-Indic digit helper before comparing.

Employers: replace the greedy regex. Match only a capitalised proper-noun run of
1-4 words immediately after the trigger, stopping at a lowercase word, digit, or
punctuation:

    /(?:\bat|\bfor|في\s+شركة|لدى\s+شركة)\s+((?:\p{Lu}[\p{L}&.\-]*)(?:\s+\p{Lu}[\p{L}&.\-]*){0,3})/u

Arabic has no case distinction, so the Arabic triggers ('في شركة', 'لدى شركة')
are already explicit enough — for those, capture 1-4 words and stop at a comma,
period, or line break.

Accept that this will miss some fabricated employers. That is the correct
trade-off: a false negative shows the user unmodified text, a false positive
previously destroyed their work.

## Tests

Rewrite tests/Feature/EnhanceCvFieldTest.php::test_fabrication_guard_* and add:

- enhanced_text is returned BYTE-IDENTICAL to the model output in every case,
  including when unsupported facts are detected. Assert this explicitly — it is
  the property this rewrite exists to guarantee.
- "Managed delivery for the retail team across 3 regions" against draft
  "Managed delivery for the retail team" produces NO employer finding
  (regression test for the greedy-capture bug)
- Draft "٢٠٢٤" with output "2024" produces NO date finding
- Draft with no employer, output "Built APIs at Acme Corp" produces one employer
  finding of exactly "Acme Corp" — not a longer run
- unverified_claims carries the exact matched span and kind
- An Arabic draft/output pair behaves the same as the English pair

Run `composer test` and report results.
```

---

## PROMPT D — Renderer and glyph-shaping robustness

```
Three defects in Sirati's CV PDF rendering path. Behaviour fixes only — do not
restyle the templates.

## 1. Contact fields are forced into RTL bidi

CvTemplateRenderer::formatPdfText() routes every contact value through
CvMarkdownRenderer::shapeText(), which calls:

    $this->arabic->utf8Glyphs($text, 90, false, true)

Verified signature (vendor/khaled.alshamaa/ar-php/src/Arabic.php:2822):

    utf8Glyphs($text, $max_chars = 50, $hindo = true, $forcertl = false)

So the call sets max_chars=90, hindo=false, and forcertl=TRUE. Forcing RTL bidi
on a pure-Latin string such as "salem@example.com" or a LinkedIn URL risks
reordering it around the neutral '@', '.', '/' characters — a reversed email on
a CV is a serious defect, and it is currently applied to email, phone, linkedin,
and location on every Arabic CV.

Fix: only shape strings that actually contain Arabic.

    private function containsArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

In shapeText(), return $text unchanged when the language is 'ar' but the string
contains no Arabic characters. A Latin-only value needs no glyph joining and no
bidi pass.

Add a test asserting an email, a URL, and an ASCII phone number survive
shapeText(..., 'ar') byte-identical, and that a mixed Arabic/Latin location like
"الرياض, Saudi Arabia" is still shaped.

Then generate one Arabic PDF and READ the contact line before closing this out.
This is the one item here that static review cannot confirm.

## 2. Silent empty output on DOM lookup failure

CvMarkdownRenderer::render() does:

    $root = $document->getElementById('cv-markdown-root');
    if (! $root instanceof DOMElement) {
        return '';
    }

Returning '' produces a PDF with a header, a contact line, and NO CV BODY — no
exception, no log line, nothing for anyone to notice. DOMDocument::getElementById
depends on the parser having indexed the id attribute, which is not guaranteed
across libxml builds and document fragments.

Fix, in order:
  a. Fall back to locating the wrapper via getElementsByTagName('div')->item(0)
  b. If that also fails, log a warning with the generated_cv id and markdown
     length, then throw a RuntimeException

CvTemplateRenderer::downloadResponse() already catches Throwable around
renderHtml() and retries with the default template, so a throw degrades safely
rather than 500-ing the user. An empty body does not degrade — it silently ships
a broken CV.

Test: a markdown input that produces valid HTML never yields an empty
contentHtml; and a forced lookup failure throws rather than returning ''.

## 3. Markdown links lose their URLs

'a' is absent from CvMarkdownRenderer::ALLOWED_TAGS, so sanitizeChildren()
unwraps anchors to their text. "[LinkedIn](https://linkedin.com/in/x)" renders as
the word "LinkedIn" and the URL is gone.

dompdf's anchor support is weak and allowing href reintroduces link-injection
surface, so do NOT add 'a' to the whitelist. Instead, before sanitizing, rewrite
each anchor whose href differs from its text content into the form:

    text (href)

Apply this only to http and https URLs and to mailto: — drop any other scheme
silently, consistent with the existing allow_unsafe_links: false setting.

Tests: an http link renders as "text (url)"; a javascript: link emits neither the
scheme nor the URL; an anchor whose text already equals its href is not
duplicated.

Run `composer test` and report results.
```

---

## Verification checklist

Run in order. Do not skip step 1 — several later checks assume a clean baseline.

1. `composer test` and `flutter test` **before** any fix. Record what already fails, so new failures are attributable.
2. Apply Fix 1. Confirm `Select-String` finds no mojibake and `git diff` shows only string literals changed.
3. `flutter run` and open the CV generator. Read every Arabic label, hint and validation message on screen.
4. Prompt C. Confirm the byte-identical `enhanced_text` test passes.
5. Prompt D. Generate four PDFs — {ar, en} × {classic_rtl, modern_rtl}.
6. **Read the Arabic PDFs by eye.** Check the contact line for reversal, headings render as headings, bullets as bullets, and pagination across a CV longer than one page.
7. `composer test` and `flutter test` again. Compare against the step 1 baseline.

Step 6 is not optional. Arabic RTL rendering in dompdf is the part of this system least amenable to automated testing, and the contact-reversal risk in Prompt D item 1 can only be confirmed visually.

---

## Open decisions carried forward

**Notification priority reorder.** `DailyNotificationCandidateService::forUser()` was changed so `firstAnalysis` is evaluated fifth instead of first. A newly registered user who declared a job title now receives a job listing before the "analyze your CV" onboarding nudge. Defensible and documented in the code, but it is an activation trade-off, not a technical one. Left as the agent wrote it, pending your call.

**The bake-off may need rerunning.** If `ai:arabic-bake-off` was run before the cache-key fix, discard those results — with the response cache enabled, Claude requests could read OpenAI's cached entries, making the comparison meaningless.

**`experience_input` is still one 12,000-character textarea.** Per-field enhancement improves the text inside the box, but templates cannot lay out individual roles because there is no structure to lay out. If output quality still disappoints after these fixes, the structured wizard (per-role entries with employer, dates, bullets) is the real fix.

**Arabic few-shot examples.** Still the highest-leverage content in the system and still the thing that should not be delegated to an AI agent.
