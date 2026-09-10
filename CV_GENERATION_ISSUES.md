# CV generation — issue list from the 2026-09-08 test run

Compiled from three artefacts: the exported PDF, the CV preview screen, and the generation progress dialog. Each item below is grounded in either the rendered output or the source, and says which.

---

## The headline: the bad PDF and the timeout are the same bug

The progress dialog hung on "تقييم التوافق مع ATS". The preview then showed:

> لا يزال الذكاء الاصطناعي يعمل. نسخة محلية من سيرتك جاهزة، ويمكنك إعادة محاولة التوليد لاحقاً.

**The AI never finished, so the app fell back to a local copy of the raw text you pasted — and that is what got exported.** The formatting was not mangled by the renderer; the renderer was handed unprocessed input. That is why the entire CV appears as one paragraph under EXPERIENCE, why the summary appears twice and education three times, and why an editorial note survived into the document.

So the fixes split cleanly: **make generation finish reliably**, and **make the fallback safe to export** — because a fallback that produces an unusable CV is worse than an error.

---

## S1 — Generation times out and the user is left with unusable output

**Evidence.** `cv_generator_screen.dart:514` calls `_apiService.pollGeneratedCv(...)`, and `poll.timedOut` at line 553/558 drives the banner. The dialog's own copy — *"يستغرق هذا وقتًا أطول من المعتاد"* — is the timeout path being hit.

**What to fix**
- The client poll timeout is shorter than a real AI round-trip under load. Raise it, and make the ceiling explicit rather than implicit.
- On timeout, do not silently present raw input as "your CV". Either keep the user on a waiting state, or label the fallback unmistakably: this is your unformatted draft, not a finished CV.
- **Block or clearly warn on export while `ai_status` is Queued/Failed.** Right now the download button produces a document that would embarrass the user in front of an employer. That is the single most damaging behaviour in this flow.

## S2 — "Please keep the app open" — polling stops when the app is backgrounded

**Evidence.** `cv_generator_screen.dart:101`:
```dart
_pollingPaused = state != AppLifecycleState.resumed;
```

The server-side job (`GenerateCvAdviceJob`) keeps running when the user leaves the app; only the client stops asking. So the instruction is real but the constraint is self-inflicted.

**What to fix.** The infrastructure to remove this already exists and is unused for this purpose: FCM tokens, `FirebaseNotificationService`, and notification preferences. Generation should survive backgrounding — notify on completion, resume polling on foreground, and drop the "keep the app open" instruction. Users background apps; a flow that punishes it will read as broken.

## S3 — English CV content renders right-to-left in the preview

**Evidence.** Screenshot 1: every line of the English CV is flush right, with sentence-final periods appearing at the left edge. And in source, `detectBaseDirection` has exactly **two** call sites — `form_fields.dart:682` and `app_input.dart:93`, both *input* widgets. `generated_cv_screen.dart` and `cv_live_preview_pane.dart` contain **zero** `Directionality` or `textDirection` references, so CV content inherits the app's ambient RTL.

**This is SIRATI-63 item 3, on a surface the fix did not reach.** Inputs were fixed. The PDF export was fixed and verified. The on-screen preview was not — it is not a form field, so wiring `detectBaseDirection` into `AppTextFormField` never touched it.

**What to fix.** Wrap rendered CV content in a `Directionality` resolved from the document's `exportLanguage` (or from `detectBaseDirection` on the content), not from the app locale. Add a widget test asserting an English document renders LTR under `Locale('ar')` — the mirror of the export-side assertion that already exists.

## S4 — The internal ATS score is printed on the exported CV

**Evidence.** `_footer.blade.php` renders unconditionally whenever `$cv['score']['total'] !== null`. The exported PDF carries `ATS score: 83% · A` on the final page.

That is Sirati's internal metric on a document the candidate sends to an employer. It advertises the tool and publishes a self-assessment no recruiter should see.

**What to fix.** Show it on the preview, never in the export. Pass a flag into the template and gate the partial on it.

*Noting for the record: this footer was reviewed three times during Sprint 3 for a bidi defect in how it rendered `A+`. The rendering was fixed and nobody asked whether it belonged in the file at all.*

## S5 — Editorial advice leaked into the CV body

**Evidence.** End of the experience block in the PDF:
> `تحسينات مطلوبة: - أضف رابط LinkedIn في الهيدر مباشرة`

("Required improvements: add LinkedIn link directly in the header.")

Whether that came from AI advice being concatenated into content or survived from the pasted input, it reached the export. A recruiter reads it as the candidate's own note-to-self.

**What to fix.** Advice and content must not share a field. Strip annotation-style lines at the render boundary as a backstop, and make sure `ai_feedback` can never be written into `experience_input` or the document body.

## S6 — No contact details in the header

**Evidence.** The exported header is name + headline only. `CvDocument::fromLegacy` reads `email`, `phone`, `linkedin`, `location` from the request, so the form either did not collect them or did not require them. Location and languages instead ended up buried inside the experience paragraph.

A CV a recruiter cannot reply to has failed at its only job. **Make email and phone required in the generator form**, and render the contact line whenever present.

## S7 — The generator form has no structured input, so experience can never be formatted

**Evidence.** `CvDocument::fromLegacy`:
```php
experience: $experienceBlob === ''
    ? []
    : [new ExperienceEntry(narrative: $text($experienceBlob))],
```

One textarea becomes **one** entry with only a narrative — no title, employer, dates or bullets. `_sections.blade.php` has a correct entries renderer (title left, dates right, bullets, `page-break-inside: avoid`) that never receives entries.

**This is the structural fix.** The Flutter CV *builder* already produces real `ExperienceEntry` objects and would render properly through this same template. The generator does not. Either give the generator the same structured inputs, or have it hand off to the builder after the AI pass.

---

## Medium — UX and polish

**M1 — The warning banner covers the action buttons.** In screenshot 1 the orange banner overlaps "تنزيل PDF" and "مشاركة". The user is told something went wrong and simultaneously blocked from the controls.

**M2 — The progress dialog misreports where the time goes.** The bar sits near-full while stuck, and the hanging step is labelled "تقييم التوافق مع ATS". ATS scoring is `AtsScoringService` — pure PHP, no network, effectively instant. The real wait is the AI advice call. The label points the user at the wrong thing.

**M3 — No elapsed time, no estimate, no way to be notified.** Combined with "keep the app open", the user has no basis to decide whether to wait.

**M4 — Page 2 of the PDF is ~90% empty**, with one orphaned education line at the top. A consequence of the unbreakable paragraph in S7, but worth its own check once structure lands.

**M5 — Duplicated sections.** Summary twice, education three times. Partly the raw-input fallback, partly that the form gives no guidance on what belongs in which box.

**M6 — Headline not title-cased.** "software engineer" renders lowercase in the header.

**M7 — Mixed Arabic inside an English CV.** The summary contains `ملخص مهني ,خبرات, مهارات` inline. Arguably intentional here since the summary is *about* bilingual ability, but inline RTL inside an English paragraph reads poorly and is worth a deliberate decision.

---

## Suggested ticket split

| Ticket | Items | Why grouped |
|---|---|---|
| **Generation reliability** | S1, S2, M2, M3 | One flow: make it finish, survive backgrounding, report honestly |
| **Export safety** | S4, S5, S6 | Three things that must never reach an employer-facing file. All small. |
| **Preview direction** | S3 | Completes SIRATI-63 on the surface it missed |
| **Structured generator input** | S7, M4, M5 | The real product fix; largest of the four |
| Polish | M1, M6, M7 | Cheap, do alongside |

**If only one thing ships first, make it S1's export guard.** Everything else degrades the product; exporting a raw-input document labelled as a finished CV actively harms the user in front of an employer.
