# Sirati Flutter ΓÇö UI/UX audit, scope B (core journeys)

## 1. Color & contrast
- **U-01 (high)** `shared/widgets/form_fields.dart:41`
  - **Problem:** The input outline (`enabledBorder`, token `border`) is **1.33:1** in light mode (#D3E3DF on #FFFFFF) and **1.56:1** in dark mode (#31423E on #1A201D). The white field fill on the page background (#FAF7F2) is **1.07:1**. Fields in register, the CV wizard, ATS and Job Match are effectively invisible, which fails WCAG 1.4.11 (3:1 for UI components).
  - **Fix:** Add a token `inputOutline`: light #7D8D89 (**3.47:1**), dark #6B7E79 (**3.85:1**). Keep `border` for card dividers only. Users can then see where to tap and type.
- **U-02 (high)** `form_fields.dart:51-55`
  - **Problem:** `floatingLabelStyle` uses `primary`. In dark mode, #007F73 on #1A201D is **3.38:1**, and this is 13sp text, so it fails 4.5.
  - **Fix:** Use `primaryInk` (dark #2FC4B2 = **7.62:1**; light #006A60 = **6.50:1** on #FFFFFF). Do the same for `focusedBorder` (line 42).
- **U-03 (med)** `form_fields.dart:43`
  - **Problem:** `errorBorder` is red at 55% alpha, which renders as #E09390 on white (**2.40:1**) and #914D48 on dark (**2.65:1**). The invalid field is barely outlined.
  - **Fix:** Use solid `error` at 1.5dp (**5.11:1** on white). Add `Icons.error_outline_rounded` (18dp) as the error suffix icon.
- **U-04 (med)** `shared/theme/app_theme.dart:225,227`
  - **Problem:** Two dark-scheme pairs fail. `onSecondary` (#E6EAE7) on #E0A03A is **1.87:1**, and `onTertiary` (white) on #E8957A is **2.33:1**. Any Material component that falls back to these pairs will be unreadable.
  - **Fix:** Use #1A1200 (**8.19:1**) and #1A0504 (**8.44:1**).
- **U-05 (low)** `dashboard/presentation/home/dashboard_action_card.dart:44`
  - **Problem:** Both neutral cards (ATS and Job Match) use the same amber icon (#C97F1B on white, 3.21:1), so the two tools look identical.
  - **Fix:** Use `primaryInk` #006A60 for the ATS card. Keep amber for Job Match.
- **U-06 (low)** `ats_scanner/presentation/analysis_result/score_ring.dart:30`
  - **Problem:** The ring track is **1.33:1**, so a low score reads as an empty, unlabelled circle.
  - **Fix:** Use `borderStrong` #BBC9C6 for the track and show the band label under the number.

## 2. Typography
- **U-07 (med)** Several screens bypass the type tokens.
  - **Problem:** Off-scale sizes and an undeclared weight:

    | Where | Current |
    |---|---|
    | `dashboard_action_card.dart:128-133` | 17sp w800 |
    | `dashboard_home_tab.dart:236-240` | 15sp w800 |
    | nav labels, `dashboard_navigation_bar.dart:73-74` | 12.5 / 11.5sp |
    | error text, `form_fields.dart:62` | 12.5sp |
    | onboarding, `onboarding_screen.dart:315-342` | 23 / 26sp |

    Only weights 400/600/700 ship, so w800 falls back to 700. `app_typography.dart` already notes that 12.5sp renders badly in Arabic.
  - **Fix:**
    - Card titles ΓåÆ `titleLg` (18sp w700, Arabic line-height 1.52).
    - Section headers ΓåÆ `titleMd` (16sp w700).
    - Nav labels ΓåÆ `labelSm` (12sp w600) for both states, with selection shown by colour and the pill.
    - Error text ΓåÆ `bodySm` (13sp).
    - Onboarding title ΓåÆ `headlineLg` (24sp).

## 3. Icons & branding
- **U-08 (high)** `dashboard/presentation/home/dashboard_navigation_bar.dart:36-41,98`
  - **Problem:** One of the four nav slots is Education, which is "coming soon" and marked only by an 8dp dot with no screen-reader label. ATS and Job Match, the core tools, have no nav slot.
  - **Fix:**
    - Make the nav Home `home_rounded` ┬╖ My CVs `description_rounded` ┬╖ Analyze `fact_check_rounded` (ATS + Job Match hub) ┬╖ Jobs `work_rounded`.
    - Move Education to a Home card until it ships.
- **U-09 (low)** `shared/theme/app_theme.dart:432`
  - **Problem:** `SiratiMark` is drawn as a circle. The brand spec (┬º6) defines a squircle with corners at 26% of the side, using the #12384FΓåÆ#0E8F86 gradient.
  - **Fix:** Use `BorderRadius.circular(size*.26)`. This is an evolution of the current mark, not a rebrand.

## 4. Layout, hierarchy & CTAs
- **U-10 (high)** `dashboard/presentation/home/dashboard_home_tab.dart:156-221`
  - **Problem:** A new user first sees two stat cards both showing 0. Below them are three equal-weight action cards, each at least 174dp tall. On a 640dp-tall phone, the Job Match card falls below the fold.
  - **Fix:**
    - When `cvCount==0`: hide the stats and show one hero card, "╪º╪¿╪»╪ú ╪¿╪│┘è╪▒╪¬┘â ╪º┘ä╪ú┘ê┘ä┘ë", with a 54dp primary button.
    - Below it, a compact 2-up row (cards at most 96dp tall) for ATS and Job Match.
    - Returning users: a hero card "╪ó╪«╪▒ ╪│┘è╪▒╪⌐ ┬╖ ATS 72" with the CTA "╪¡╪│┘æ┘å ╪º┘ä╪»╪▒╪¼╪⌐".
- **U-11 (high)** `cv_builder/presentation/generated_cv_screen.dart:268-306`
  - **Problem:** After generation, the only actions are Share (plain text) and Download. The journey ends with no next step.
  - **Fix:**
    - Keep Download as the 54dp primary button.
    - Add 48dp tonal buttons "┘ü╪¡╪╡ ATS" (`fact_check_rounded`) and "╪╖╪º╪¿┘é ┘à╪╣ ┘ê╪╕┘è┘ü╪⌐" (`compare_arrows_rounded`), each opening with this CV preselected.
    - Make Share send the PDF.
- **U-12 (med)** `ats_scanner/presentation/cv_analysis_screen.dart:91-105`
  - **Problem:** ATS accepts only an uploaded file or pasted text. A user cannot pick a CV built in Sirati and must export it and upload it again.
  - **Fix:** Add a "╪│┘è╪▒╪⌐ ┘à╪¡┘ü┘ê╪╕╪⌐" source first, reusing the Job Match `_cvPicker`.
- **U-13 (med)** `cv_analysis_screen.dart:330-347`
  - **Problem:** The job description is collapsed as optional, though it drives the targeted score.
  - **Fix:**
    - Show it expanded with the helper "╪ú╪╢┘ü ╪º┘ä┘ê╪╡┘ü ┘ä┘å╪¬┘è╪¼╪⌐ ╪ú╪»┘é".
    - Add a 48dp "┘ä╪╡┘é" button (`content_paste_rounded`).
    - Add the character counter from `job_match_screen.dart:186`.
- **U-14 (med)** `ats_scanner/presentation/analysis_result_screen.dart:52-69`
  - **Problem:** The only primary CTA is "╪¬┘ê┘ä┘è╪» ╪│┘è╪▒╪⌐ ┘à╪¡╪│┘æ┘å╪⌐". Recommendations cannot be applied one by one.
  - **Fix:**
    - Add an "╪╖╪¿┘æ┘é" chip on each quick win and missing keyword.
    - Change the CTA to "╪¡╪│┘æ┘å ╪│┘è╪▒╪¬┘è (+N ┘å┘é╪º╪╖ ┘à╪¬┘ê┘é╪╣╪⌐)".

## 5. UX flow & copy
- **U-15 (high)** `dashboard/presentation/premium_gate_screen.dart:18-31`
  - **Problem:** The premium gate says "╪│╪¬┘Å┘ü╪¬╪¡ ┘ç╪░┘ç ╪º┘ä╪┤╪º╪┤╪⌐ ╪¿╪╣╪» ╪¬┘ü╪╣┘è┘ä ╪º┘ä╪º╪┤╪¬╪▒╪º┘â ╪º┘ä┘à┘à┘è╪▓" and its only button is "╪º┘ä╪╣┘ê╪»╪⌐ ┘ä┘ä╪▒╪ª┘è╪│┘è╪⌐". There is no benefit, no price and no way to upgrade.
  - **Fix:**
    - Reuse the Job Match gate pattern (`job_match_screen.dart:75-94`): three benefit bullets, a primary "╪º╪┤╪¬╪▒┘â ╪º┘ä╪ó┘å", and a secondary "╪º╪┤╪¬╪▒┘â╪¬ ΓÇö ╪¬╪¡╪»┘è╪½".
    - Lead with the outcome, e.g. "╪╖╪º╪¿┘é ╪│┘è╪▒╪¬┘â ┘à╪╣ ╪ú┘è ┘ê╪╕┘è┘ü╪⌐ ┘ê╪º╪¡╪╡┘ä ╪╣┘ä┘ë ╪¬╪╣╪»┘è┘ä╪º╪¬ ╪¼╪º┘ç╪▓╪⌐".
- **U-16 (med)** `auth/presentation/register_screen.dart:233-378`
  - **Problem:** Sign-up asks for 6 fields plus terms, and wizard step 1 (`personal_step.dart:35-105`) asks for name, email and phone again.
  - **Fix:**
    - Register asks only for name, email and password.
    - Wizard step 1 is prefilled from the profile.
    - Phone gets the +966 prefix and `AutofillHints.telephoneNumber`.
- **U-17 (med)** Missing autofill.
  - **Problem:** The register password has no `AutofillHints.newPassword` (`register_screen.dart:375-396`), and `personal_step.dart:43-124` has no autofill hints.
  - **Fix:**
    - Add name, email, telephoneNumber and url hints.
    - Wrap the forms in `AutofillGroup`.
    - Call `TextInput.finishAutofillContext()` on submit.
- **U-18 (low)** `register_screen.dart:425-437`
  - **Problem:** "╪º┘ä┘à╪¬╪º╪¿╪╣╪⌐ ╪¿╪»┘ê┘å ╪¡╪│╪º╪¿" is 12.5sp and gives no warning about what the user gives up.
  - **Fix:** Add the line "┘ä┘å ╪¬┘Å╪¡┘ü╪╕ ╪│┘è╪▒┘â" (`bodySm`, `textSecondary`).
- **U-19 (low)** Hard-coded bilingual strings sit outside the ARB files.
  - **Problem:** `cv_analysis_screen.dart:96-433`, `score_band_style.dart:29-40`, `ai_progress_overlay.dart:189-229`, and `generated_cv_loader_screen.dart:48` (Arabic only).
  - **Fix:**
    - Move them to the ARB files.
    - Soften "╪╢╪╣┘è┘ü - ┘è╪¡╪¬╪º╪¼ ╪Ñ╪╣╪º╪»╪⌐ ┘ç┘è┘â┘ä╪⌐" to "┘è╪¡╪¬╪º╪¼ ╪¬╪¡╪│┘è┘å╪º╪¬ ΓÇö ╪º╪¿╪»╪ú ╪¿╪º┘ä┘à┘é╪¬╪▒╪¡╪º╪¬ ╪ú╪»┘å╪º┘ç".
- **U-20 (low)** `job_match/presentation/widgets/job_match_widgets.dart:220-231`
  - **Problem:** The Job Match ring is always one colour with no band label, while the ATS ring is banded.
  - **Fix:** Reuse `ScoreBandStyle` so the same score means the same thing in both features.

## 6. States
- **U-21 (high)** `job_match/presentation/job_match_screen.dart:228-238,641`
  - **Problem:** A 10ΓÇô30 s Job Match submit shows only a spinner row (`_BusyRow`): no stages, no cancel, no long-wait message. Submit errors show a snackbar with no Retry (`job_match_screen.dart:365-374`).
  - **Fix:**
    - Use `AiProgressOverlay` with stages "┘é╪▒╪º╪í╪⌐ ╪º┘ä┘ê╪╡┘ü" ΓåÆ "┘à┘é╪º╪▒┘å╪⌐ ╪º┘ä┘à┘ç╪º╪▒╪º╪¬" ΓåÆ "╪Ñ╪╣╪»╪º╪» ╪º┘ä┘à┘é╪¬╪▒╪¡╪º╪¬", plus Cancel.
    - Show errors with `AppSnackBar.fromException(..., onRetry: _submit)`.
- **U-22 (med)** `shared/widgets/loading/ai_progress_overlay.dart:227-240`
  - **Problem:** The bar creeps toward 92% over 40 s with no elapsed time, so a 25 s wait looks stuck.
  - **Fix:** Show an elapsed counter "┘á:┘í┘¿" (`labelMd`) and the expectation "╪╣╪º╪»╪⌐┘ï ┘í┘ÑΓÇô┘ú┘á ╪½╪º┘å┘è╪⌐".
- **U-23 (med)** `job_match/presentation/job_match_result_screen.dart:36-38`
  - **Problem:** With no match, the screen body is blank (`SizedBox.shrink()`).
  - **Fix:** Show an empty state (`search_off_rounded`, "┘ä╪º ╪¬┘ê╪¼╪» ┘å╪¬┘è╪¼╪⌐") with the action "┘à╪╖╪º╪¿┘é╪⌐ ╪¼╪»┘è╪»╪⌐".
- **U-24 (med)** `cv_analysis_screen.dart:235-242`
  - **Problem:** A request-level ATS failure shows a snackbar with no Retry.
  - **Fix:** Pass `onRetry`, as `cv_generator_screen.dart:448-463` already does.
- **U-25 (low)** `cv_builder/presentation/my_cvs_screen.dart:81-105`
  - **Problem:** Delete is permanent.
  - **Fix:** Delete optimistically and show a 5 s "╪¬╪▒╪º╪¼╪╣" (undo) snackbar.

## 7. Accessibility & RTL
- **U-26 (med)** Touch targets below 48dp.
  - **Problem:**
    - My CVs download and delete buttons are 40├ù40 (`my_cvs/cv_document_card.dart:221-228`).
    - The Enhance button is 44dp (`personal_step.dart:161-177`).
    - The language option is about 40ΓÇô43dp (`personal_step.dart:267-276`).
    - Onboarding Skip is `Size(48,36)` (`onboarding_screen.dart:197`).
    - `LanguageToggle` is 42├ù42.
  - **Fix:** Make every hit area 48├ù48. Visuals can stay 40dp.
- **U-27 (med)** `score_ring.dart:18-73`
  - **Problem:** The ATS ring has no Semantics, so a screen reader reads fragments.
  - **Fix:** Add `Semantics(label:"╪»╪▒╪¼╪⌐ ATS", value:"72 ┘à┘å 100╪î ╪¼┘è╪» ╪¼╪»╪º┘ï", excludeSemantics:true)`, as the Job Match ring already does.
- **U-28 (low)** Direction and labels.
  - **Problem:** `splash/welcome_body.dart:86` uses a raw `Icons.arrow_forward`, which points the wrong way in Arabic. The register password toggle has no tooltip.
  - **Fix:** Use `DirectionalIcon`, and add the tooltip "╪Ñ╪╕┘ç╪º╪▒ ┘â┘ä┘à╪⌐ ╪º┘ä┘à╪▒┘ê╪▒".
- **U-29 (low)** `email_verification_screen.dart:199`
  - **Problem:** The padding is asymmetric left/right (`fromLTRB(8,8,12,0)`).
  - **Fix:** Use `EdgeInsetsDirectional.fromSTEB(12,8,8,0)`.

## Taps to first value
| Journey | Today | With fixes |
|---|---|---|
| New user to Home | 4 taps + 7 inputs | 4 taps + 3 inputs (U-16) |
| Home to finished CV | ~6 taps; journey dead-ends | 6 taps + next steps (U-11) |
| ATS on a Sirati-built CV | 8+ taps (export, leave app, upload) | 3 taps (U-11, U-12) |
| Job Match | 3 taps, no progress feedback | 3 taps with progress (U-21) |

## Already good ΓÇö do not change
1. Wizard autosave every 2 s, draft restore, and the exit dialog (`cv_builder/presentation/cv_generator_screen.dart:119-226`).
2. Job Match fact confirmation, partial apply, and applying to a copy of the CV (`job_match/presentation/widgets/job_match_suggestion_card.dart:111-141`).
3. Status colour containers use gated contrast pairs (`score_band_style.dart:16-27`), e.g. warning text on warning tint at 5.16:1.
4. Per-content text direction in Job Match, plus LTR email, phone and OTP fields with Arabic-digit OTP input (`email_verification_screen.dart:289-324`).
5. Arabic line-height tokens of 1.5ΓÇô1.65, and validation that waits until submit (`login_screen.dart:219-241`).

## SCREENS TO REDESIGN IN STITCH:
1. Home ΓÇö a state-aware hero (first CV, or improve the last score), with the tools in a compact 2-up row.
2. Nav shell ΓÇö an Analyze hub (ATS + Job Match) instead of the "coming soon" Education slot.
3. Generated CV ΓÇö a results hub: Download as primary, then ATS check, Job Match and Edit as next steps.
4. ATS input ΓÇö one screen with a saved/upload/paste CV source, the job description visible, and one CTA.
5. ATS result ΓÇö a banded score header and a list of fixes you can apply, with the projected gain on the CTA.
6. Premium gate ΓÇö a paywall that leads with benefits, offers a plan, and has a "subscribed ΓÇö refresh" path.

LEARNINGS: The project's preToolUse guard blocks `create` even in the Copilot session folder (`files/` and `plan.md`), so read-only reports have to be returned inline.
LEARNINGS: In light mode, `primary` #007F73 is actually 4.59:1 on the background and 4.90:1 on white. The "2.79:1" comment in `sirati_colors.dart` and the "~2.9:1" comment in `score_ring.dart` are stale. The dark `primary` (3.38:1 on the dark surface) is what fails.
LEARNINGS: The theme has no separate token for input outlines; `border` (#D3E3DF) is used for both card dividers and input outlines.


