# Sprint 3 — round 3 close-out

Verification of the two residuals and two notes from `SPRINT3_REVIEW_ROUND2_VERIFICATION.md`.

**Verdict: I'd sign this off.** One observation below is a consequence of the fix rather than a defect, and one is a security trade worth recording as a decision rather than a side effect.

---

## Verified fixed

**Residual 1 — the bypass is gone.** `$watermark` is off the `downloadResponse` signature entirely. The gate is now unconditional:

```php
if ($template->isPremium() && ! $this->entitlementService()->canExportTemplate($effectiveUser, $template)) { … 403 }
// Watermark is strictly derived from policy via EntitlementService, never caller-controlled
$watermark = $this->entitlementService()->shouldWatermark($effectiveUser, $template);
```

Caller input can no longer reach the entitlement decision. This is the right shape.

**Residual 2 — the raster check was actually done.** Five templates rendered with `autoLangToFont = false`, rasterised at 150 DPI through Ghostscript and ImageMagick, and inspected: cursive joining across initial/medial/final forms, lam-alef ligatures intact, no tofu, RTL alignment holding, and mixed LTR tokens (`DevOps`, `AWS`, `+966 50 987 6543`, `28%`) reading in natural order.

I want to be straight about the limits of my confirmation here: the PNGs live in the agent's scratch directory, outside the folder connected to this session, so I could not open them. This is the one item in three sprints of review that rests on someone else's inspection rather than something I re-derived. The method was the right one and the reported observations are the right ones to have made — but if a second pair of eyes is cheap, that is where I would spend it.

**Note 1 — expiry aligned.** Both URLs now use `now()->addDays(7)`, matching `SignedRecordAccess::temporaryUrl`. Cached mobile CV lists no longer hand out dead links.

**Note 2 — signature rationale documented.** The four validation variants now carry a comment explaining relative mode (mobile resources signing a relative path with a prepended host) versus absolute mode (web routes). Still four branches, but the reader now knows why.

---

## One observation — the watermark is now unreachable on download

This follows mechanically from the correct fix. `shouldWatermark()` is defined as `! canExportTemplate()`, and the gate aborts whenever `! canExportTemplate()`. So by the time line 25 runs, `canExportTemplate` is necessarily true, and `$watermark` is necessarily `false`:

| template | canExport | reaches watermark line? | value |
|---|---|---|---|
| free | true | yes | `false` |
| premium, entitled | true | yes | `false` |
| premium, not entitled | false | **no — 403** | — |

So the `SetWatermarkText` / `showWatermarkText` / `watermarkTextAlpha` block is dead code, and M3's "native mPDF watermark stamp" is never exercised by any reachable path. `PremiumTemplateGatingTest` confirms this indirectly — its watermark case asserts `is_watermarked: true` and the string `معاينة` on the **HTML preview** response, not on a PDF.

Behaviourally this is correct: an entitled user should get a clean PDF, and an unentitled one gets 403. Nothing is broken. But two things follow:

- If a watermarked **preview PDF** is a product requirement — free user previews a premium template as a PDF before deciding to upgrade — it currently has no implementation. `previewHtmlApi` returns HTML, and the download path 403s before it can watermark anything. That route would need `canPreviewTemplate()` as its gate rather than `canExportTemplate()`, which is exactly what that method exists for and why it currently looks like a stub.
- If it is not a requirement, delete the block. Dead code that looks like a security control is the failure mode this review has kept running into.

Either resolution is fine. What I would not leave is the current state, where the watermark reads as an active protection and is provably never applied.

## One trade worth recording as a decision

The signed-URL window moved from 30 minutes to 7 days. That is the right answer to the staleness problem I raised — but it is worth naming what it costs, because it was made as a UX fix.

`pdf_url` is an unauthenticated bearer credential for a CV PDF containing a full name, email, phone number and employment history. It is now valid for a week, it is handed to the client in every API response, and (because the resource signs relative and prepends a host) the signature does not cover the host. Anywhere that URL is logged, shared, or cached by an intermediary, it is live for seven days.

Defensible for a CV download link, and the ownership fallback in `SignedRecordAccess` means an authenticated owner does not depend on it. But it should sit in the ticket as a deliberate choice with a stated window, not as a number that moved to fix a broken link. If 7 days feels long once written down, 24 hours would still fix the staleness case for any realistic app session.

---

## Sprint 3 status

| | Raised | Status |
|---|---|---|
| Round 1 | S1, S2, S3, M1–M4 | all closed |
| Round 2 | 2 residuals, 2 notes | all closed |
| Round 3 | 1 observation, 1 trade | neither blocking |

Three sprints reviewed. The pattern that has held up best is the one S3 demonstrated: when a fix post-processes a symptom, asking whose problem it actually is tends to find a single upstream flag, and removing the cause deletes the workaround instead of adding to it. `autoLangToFont` was that flag; the five-entry PUA table disappeared with it.
