# Sirati — UI + Typography Enhancement Suggestions

Generated: 2026-09-18
Based on live testing of 11 screens (5 pre-auth, 6 authenticated) in the Flutter web build. Ranked so a team can pick the top 5 for a sprint without reading the rest.

The app is in good visual shape overall — Arabic RTL is handled properly, colour palette is coherent, spacing tokens are consistent, animations are subtle. Findings below are refinements, not overhauls.

---

## 1. Typography scale audit

The existing scale in [`lib/shared/theme/app_typography.dart`](flutter_app/lib/shared/theme/app_typography.dart) is well-designed:

| Token | Size | Weight | Arabic line-height |
|---|---|---|---|
| displayLg | 32 | 700 | 1.38 |
| displayMd | 28 | 700 | 1.40 |
| headlineLg | 24 | 700 | 1.48 |
| headlineMd | 20 | 700 | 1.50 |
| titleLg | 18 | 700 | 1.52 |
| titleMd | 16 | 700 | 1.55 |
| titleSm | 14 | 700 | 1.52 |
| bodyLg | 16 | 400 | 1.65 |
| bodyMd | 14 | 400 | 1.65 |
| bodySm | 12.5 | 400 | 1.60 |
| labelLg | 14 | 700 | 1.42 |
| labelMd | 12 | 600 | 1.45 |
| labelSm | 11 | 600 | 1.40 |

**What's right**: separate Arabic/Latin line-heights (Arabic needs more room — you're doing this), one shared family (IBM Plex Sans Arabic) so mixed-script strings don't look chimeric, and no size < 11 in the token set.

**What to change**:

1. **Bump `bodySm` from 12.5 → 13**. 12.5 is an unusual half-pixel size that will hint inconsistently on Chrome/Safari and looks under-set at 100% zoom on high-DPI Android. 13 is standard, keeps hierarchy vs bodyMd (14), and reads more clearly for Arabic diacritics.
2. **Bump `labelSm` from 11 → 12**. 11px Arabic is at the readability floor — the "الحد الأقصى 5 ميجابايت" microcopy inside the upload zone I saw is right at the edge. Material 3's own `labelSmall` is 11 for Latin; for a bilingual app that renders Arabic 40% of the time, 12 is the safe minimum.
3. **Add a `bodyXs = 11/w400` token** only for legal/attribution text where 11 is deliberate. Right now anywhere that needs "smaller than bodySm" reaches for `labelSm` which is bold — wrong weight for reading.
4. **Cap `heightArabic` at 1.55 for headlines**. Currently `headlineLg` uses 1.48 and `displayLg` 1.38; but `titleLg` is 1.52 and `titleMd` 1.55 — the *smaller* text has *taller* line-height than the *larger* text, which visually makes the smaller titles look breezier than the big display type. Flip the pattern: display 1.45, headline 1.50, title 1.55, body 1.65.

---

## 2. Screen-specific findings, ranked

### P0 — worth doing this sprint

1. **Show which keyword set the score was built from.** The backend now returns `keyword_source: 'job_description' | 'category'` (SIRATI-90). Add a small pill on the result screen next to the "معيار التقييم" chip:
   - When `job_description`: "من وصف الوظيفة" — green
   - When `category`: "قائمة عامة — أضف وصف الوظيفة لتقييم أدق" — soft-orange, tappable, opens the analysis form with focus on the JD field
   Without this, a user looking at "missing keywords: flutter, react, python" for a backend job has no way to know why the list is off.

2. **Make the CV textarea auto-grow (or default to 12 lines instead of 8).** A real resume is 400–1000 words; typing/pasting into an 8-line viewport with internal scrolling is where users bail. The upload path exists but text paste is genuinely the fallback when a PDF fails — so make it comfortable. `keyboardType: TextInputType.multiline` + `minLines: 12, maxLines: null` handles both.

3. **Collapse the new "Job description (optional)" card when empty.** Right now it's always expanded, adding ~180px to the form and pushing the Analyze button below the fold on iPhone SE. Collapse to a `+ إضافة وصف الوظيفة (اختياري)` chip that expands on tap; auto-expand if the user has ever pasted a JD before (localStorage flag).

4. **"التعليم (قريباً)"** — the coming-soon parenthetical stretches the bottom-nav slot, making it visibly wider than its neighbours and mis-aligned with its icon. Two options:
   - Best: change the label to just "التعليم" and add a small "قريباً" badge above the icon (top-right corner in RTL).
   - Fine: swap for a `Coming Soon` bottom sheet on tap and drop the parenthetical entirely.

### P1 — nice this quarter

5. **Home CTA hierarchy is over-tilted toward "Create CV".** The "أنشئ سيرة ذاتية وفق ATS" card is a big filled green block; "حلّل سيرتك الذاتية بـ ATS" is roughly half the height with a soft-yellow secondary button. But analysing is a returning-user action (users re-analyse after edits), while creating is a one-time action. Make them equal: two full-width cards stacked, same height, same button prominence. Let the icon + colour do the differentiation.

6. **Forgot-password screen is 70% empty.** After the "أدخل بريدك…" text + email field + button, there's a large white void. Fill it with either:
   - A trust cue: "سنرسل الرمز خلال 60 ثانية. تحقق من مجلد الرسائل غير المرغوب فيها إذا لم يصل." — helps users not spam-tap the button.
   - An illustration matching the auth-landing document icon, sized ~120×120px, centred, opacity 0.15.
   Empty screens read as "under construction" even when they're not.

7. **Login: "نسيت كلمة المرور؟" link is under-weighted.** Currently 12px muted text right-aligned under the password field. It's the single most-tapped auxiliary action on this screen — bump to `bodyMd` (14) and use the primary green colour, not muted. It's OK for a link to look like a link.

8. **Login bottom: "أنشئ حساباً" needs to look tappable.** The link is same size as the surrounding label and differs only in colour. On dark-mode / high-contrast users' setups this fails. Add underline, or wrap in a `TextButton` with visible padding.

9. **File upload zone microcopy is too small.** "الحد الأقصى 5 ميجابايت" at 11–12px inside a large card looks like a footnote. Move it *outside* the card as helper text under the card (16px gap) at `bodySm`, so the card interior is just: icon + primary CTA label.

10. **"أو" (OR) divider** — beautiful pill design between upload and paste. **Keep**. This is a nit but the pill's border colour (`context.sirati.border`) is nearly invisible on the cream background. Bump to `border × 1.4` alpha or use `borderStrong` so the outline reads at a glance.

### P2 — quality passes

11. **Missing-keywords chip cloud has no upper limit.** When the JD is long (25 terms extracted, ~15 missing), the cloud pushes the "Generate improved CV" CTA down two screens. Cap visible chips at 8 with a "عرض الكل (15)" toggle.

12. **Score circle would earn its keep with an entrance animation.** The 84/100 number is the emotional moment of the whole app. A 400ms `AnimatedBuilder` counting 0→84 with an ease-out curve, plus the ring stroke sweeping in, is 20 lines of code and lands like a slot-machine reveal. Users screenshot this.

13. **"معيار التقييم: تقنية وبرمجيات" chip** — clear content, but the icon (a sliders icon) doesn't relate to "category". Swap for `Icons.category_outlined` or `Icons.tune_outlined` (already used in Material's category patterns).

14. **Recommendations tab: "أضف رابط LinkedIn في الهيدر مباشرة"** — good copy. The numbered `1` badge is orange on light-yellow which is 3.2:1 contrast (below AA 4.5:1). Darken the orange one shade.

15. **Splash screen sits ~2 seconds after boot** waiting for Firebase Installations to resolve. If the user is offline, it hangs indefinitely (this is what killed the Android emulator run). Add a 4-second timeout after which the splash proceeds to auth landing regardless — the app doesn't actually need Firebase to render the login screen.

### P3 — polish

16. **Login-screen validation errors** appear both as a top banner *and* as inline field errors. The banner ("يرجى تصحيح الحقول المحددة للمتابعة") duplicates what the inline errors say. Keep inline errors, drop the banner — or keep banner, drop inline. Not both.

17. **The "EN" language toggle** looks like a badge, not a control. Add a subtle chevron `˅` next to it, or ripple on tap, so users know it's tappable.

18. **Onboarding page dots** — 3 dots, active dot is a wider pill. Standard. But in RTL the "swipe direction" convention flips: swipe LEFT should go to the NEXT page, and the active dot should advance from RIGHT to LEFT. Test that the dot animation direction matches Arabic reading order.

19. **Bottom nav** — active state is icon + label both in primary green with a small dot indicator. Beautiful. But inactive labels are ~12px in a bold weight — this makes ALL 4 tabs look "active-ish". Make inactive labels 11px + w500 (or w400 if Medium isn't in pubspec.yaml — the code comment warns about that).

20. **Stat cards** ("التحليلات 13" / "السير الذاتية 7") — the numbers are huge (`displayStat` size). Consider adding a tiny delta indicator when the count changed since last visit: "13 (+1)" in `bodySm` green. Free dopamine hit.

---

## 3. Cross-cutting UI concerns

- **Router regression** (documented earlier): browser-back stacks pages. Not a font/typography issue but visible during my session. Blocks a web release, doesn't necessarily block mobile.
- **Flutter web canvas re-render artifacts** on viewport resize — cosmetic in dev, invisible to end users on a fixed-size device. No fix needed; note for future testing tooling.
- **RTL date rendering** — `BidiText.isolateLtr` is used correctly on the uploaded filename. Extend it to any bare year-range strings ("2022 to 2025") that appear in Arabic UI so they don't get reversed.

---

## 4. If you can only do FIVE things

1. Wire `keyword_source` to a badge on the result screen (P0 #1) — completes the SIRATI-90 change.
2. Collapse the new JD card by default (P0 #3) — protects mobile fold.
3. Make CV textarea comfortable (P0 #2) — kills a real drop-off point.
4. Bump `bodySm` 12.5 → 13 and `labelSm` 11 → 12 (Typography #1, #2) — one file, one commit, whole app benefits.
5. Fix the "التعليم (قريباً)" bottom-nav slot (P0 #4) — first thing every returning user sees.
