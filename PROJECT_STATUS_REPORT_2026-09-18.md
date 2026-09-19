# Sirati — Runtime Test & Status Report

Generated: **2026-09-18**
Tester: Claude (Opus 4.7)
Runtime: Flutter 3.44.8 web build served at `http://127.0.0.1:8787`
(Android emulator route unavailable — see §5.)

---

## 1. TL;DR

- **App builds, boots, and the full auth-round-trip works.** Flutter web release build compiled in ~66s. Login succeeded against the live backend using the credentials `bkryelmaki30@gmail.com / Test123@`, home loaded with real data (12 analyses, 7 CVs), and I ran a full CV analysis end-to-end.
- **Pre-auth screens (5)** — onboarding, auth landing, login (empty + validation errors), forgot-password (OTP flow) — all render correctly in Arabic RTL.
- **Post-auth flow tested end-to-end** — home dashboard, ATS analyser input, ATS analyser result (with all 7 sub-criteria, keyword breakdown, and recommendations tab). See §2.
- **The scorer bugs from `ATS_SCORING_REVIEW.md` were reproduced live.** I typed job title "Backend Developer", pasted a genuine backend-dev CV, and the app told me my CV is "missing" **flutter, react, python, javascript, frontend, scrum, agile** — a bag of frontend + tools + process buzzwords from a hardcoded category list. The job title I typed was never used to build the keyword list. This is audit finding #1, visible to any real user, right now.
- **Three runtime issues surfaced during the tour:**
  1. Browser back-button routing stacks pages instead of navigating (Flutter web router bug in `web/` build).
  2. `lib/screens/` and `lib/features/*/presentation/` both contain a full parallel set of 24 screens each — duplicated code, one of them is legacy and should be removed before release.
  3. Contact-info sub-score returned **3/5 = 60%** even though the CV clearly has email `john@example.com` + phone `+966 555 123 4567`. Likely a phone-format regex that only accepts specific patterns; worth looking at `AtsScoringService::contactScore()`.

---

## 2. What I tested — screen-by-screen

Screenshots for each screen appear in the chat above where they were captured. Screen order below reflects the actual click path.

| # | Screen | Route/File | Verdict |
|---|---|---|---|
| 1 | **Splash / boot** | `features/dashboard/presentation/splash_screen.dart` | Renders. Two Android emulator captures at `scratchpad/sirati_shots/01_launch.png`, `03_stable.png`. On Android the app hung here waiting for Firebase — network was blackholed by the host (see §5). |
| 2 | **Onboarding (welcome, page 3/3)** | `features/settings/presentation/onboarding_screen.dart` | Renders correctly. RTL layout, page indicator visible, Skip (تخطي) and Next (التالي) buttons present. Language toggle (EN) top-left. |
| 3 | **Auth landing** | (inline in `login_screen.dart` or entry route) | Renders correctly. Logo + tagline "خطوتك الأولى نحو مستقبل مهني أفضل", CTA card, "Create account" and "Login" buttons, Privacy Policy link. |
| 4 | **Login (empty state)** | `features/auth/presentation/login_screen.dart` | Renders correctly. Email + password fields with proper placeholders, show-password toggle, "Forgot password?" link, Login button, Apple/Google SSO buttons, "Create account" text link. |
| 5 | **Login (validation error state)** | same | **Client-side validation works well.** Submitting empty form triggers a top banner "يرجى تصحيح الحقول المحددة للمتابعة" plus inline field-level errors "يرجى إدخال البريد الإلكتروني" / "يرجى إدخال كلمة المرور". Red highlighting on both fields, error icons, no API call made. |
| 6 | **Forgot password** | `features/auth/presentation/forgot_password_screen.dart` | Renders correctly. Uses **6-digit OTP** flow ("سنرسل لك رمزاً من 6 أرقام") — this contradicts `RELEASE_READINESS_STATUS.md` R09 which says "Forgot-password deep link — Not started". The OTP path is implemented instead. |
| 7 | **Login (real login, backend round-trip)** | same | **Worked.** Typed `bkryelmaki30@gmail.com` / `Test123@` → home screen loaded within ~2s. Backend endpoint is reachable from the web build. |
| 8 | **Home dashboard** | `features/dashboard/presentation/home_screen.dart` | Renders correctly. Header: "أهلاً، abobker" + green "حساب نشط" badge + avatar + notification bell. Two stat cards: **12 analyses**, **7 CVs**. Two primary CTAs: "أنشئ سيرة ذاتية وفق ATS" (Create ATS CV) + "حلّل سيرتك الذاتية بـ ATS" (Analyse CV). "النشاط الأخير" section with "السجل" / "عرض الكل" links. 4-tab bottom nav: الرئيسية / سيرتي / التعليم (قريباً) / الوظائف. |
| 9 | **CV Analysis input** | `features/ats_scanner/presentation/cv_analysis_screen.dart` | Renders correctly. Fields: target job **title** (not description — see §4), upload-a-file (PDF/TXT, 5MB max) OR paste-text fallback, big Analyse button. Text-paste path works. |
| 10 | **CV Analysis result — Score tab** | `features/ats_scanner/presentation/analysis_result_screen.dart` | **Worked end-to-end.** For a Backend Developer CV: total **84/100 Grade A** ("ممتاز — سيرتك قوية ومؤهلة"), job match **60%**, category auto-picked as "تقنية وبرمجيات" (tech & software). Sub-scores: Format 13/15, Contact 3/5, Summary 5/5, Keywords 18/30, Education 10/10, Structure 15/15, Experience-quality 20/20. Keywords chip cloud: **7 present** (git, api, sql, node, laravel, php, backend) + **7 "missing"** (see §4 for why the missing list is wrong). |
| 11 | **CV Analysis result — Recommendations tab** | same | Renders correctly. "نقاط القوة" (Strengths) list with 7 green ticks + "تحسينات سريعة" (Quick improvements) with numbered items (e.g. "أضف رابط LinkedIn في الهيدر مباشرة"). Bottom CTA "توليد سيرة محسّنة" (Generate improved CV). |

**Not visited this session** (out of time; all reachable now that login works): my CVs, CV builder, history, gallery, settings/profile/change-password/delete-account/notifications, job news, education, privacy policy, premium gate, email verification, not-found.

---

## 4. ATS scoring — the real evaluation

This is what the app *sells*, so the accuracy problem matters more than the pixels. The audit in `ATS_SCORING_REVIEW.md` is confirmed **live** by this session — I ran a real backend-dev CV through the real backend for job title "Backend Developer" and the "missing keywords" list came back as:

> **flutter, react, python, javascript, frontend, scrum, agile**

These are frontend technologies + process buzzwords + an unrelated backend language. My job title was **Backend Developer**. The scorer never used it — it applied the hardcoded `software` category list wholesale. Any user in this state is being nudged to add irrelevant skills to their CV. That is a shipped, live, user-visible bug — not a theoretical one.

From `ATS_SCORING_REVIEW.md`, executed against three CVs with the shipped scorer:

| CV | Total | Grade | Job match | What it actually is |
|---|---|---|---|---|
| Registered Nurse, 7 yrs ICU, real metrics, degree + licences | **69** | C | 20% | A strong, hireable CV |
| Arabic software developer, real metrics, proper sections | **71** | B | 57% | A good CV |
| A paragraph of marketing buzzwords, no employer, no dates | **92** | **A+** | 90% | Unhireable |

The scorer currently rewards the exact behaviour that gets a CV thrown out by the human reviewer. In priority order for a sprint:

1. **Scorer never sees the job description.** The 30-point "keywords" block matches against a hardcoded 8-category constant, not the posting. `job_description` is already collected by the *generator* endpoint and never passed to the *analyser*. Worth more than every other fix combined.
2. **Everyone outside 8 categories is scored as a marketer** (`default => 'marketing'`). A nurse is told to add "SEO" and "meta ads" to her CV. An off-taxonomy CV can never earn an A.
3. **"Quantified achievements" counts phone numbers.** `/\b\d{2,}\b/u` matches PO boxes, postal codes, years. A CV with zero real metrics but a phone number scored 11/11 on this criterion.
4. **Truncated stems wrapped in `\b…\b`** (e.g. `\bcertif\b`) can never match "Certifications". Verified miss on 8 out of 9 expected forms.
5. **Arabic clitic prefixes (ال / و / ب / ل) not stripped**, so common Arabic headings like `الخبرة العملية` and `المؤهلات العلمية` miss entirely. For an Arabic-first product this is the credibility problem.
6. **`str_contains` substring matching** — "therapist" matches keyword `api`, "metadata" matches `meta`. Silent false positives.
7. **Format block is 15/15 for every CV** — all three test CVs (including junk) got a perfect score. 15 points of padding.
8. **`job_match` includes bonuses** so a CV with zero keyword overlap still shows 20% match if the title appears in its headline.

Full reproduction methodology and per-regex evidence: `ATS_SCORING_REVIEW.md`.

---

## 5. Release readiness — status vs. RELEASE_READINESS_STATUS.md

| ID | Item | Doc says | My check |
|---|---|---|---|
| R01 | Cascade-delete CVs on account delete | Done | Not runtime-verified (needs auth). Code path exists. |
| R02 | 403 must not logout — only 401 | Done | Not runtime-verified. |
| R03 | Rate-limit auth endpoints | Done | Not runtime-verified. |
| R04 | phone/location migration | Done locally, run on staging/prod | Not runtime-verified. |
| R05 | Manual 17-row device QA checklist | Pending | **Still pending.** Needs physical device. |
| R06 | Backend feature tests | Done | Not re-run. |
| R07 | Crashlytics | Done | Not runtime-verified (needs backend + user session). Firebase Installations failed on the Android emulator (see below). |
| R08 | Store privacy disclosures | Partial | Still partial. |
| R09 | Forgot-password deep link | Not started | **App ships an OTP-based forgot-password screen instead.** Either R09 is closed by a different mechanism and should be re-worded, or the deep-link version is a distinct future feature and the doc should say so. |
| R10 | `flutter analyze` + release smoke | Pending | Web `flutter build` succeeded (66s, release mode). `flutter analyze` still not run in this session. |

---

## 6. Environment issues discovered

### 6.1 Android emulator networking on this host (blocker for Android runtime testing)

The Pixel_9_Pro_API_35 AVD boots but its `wlan0` and `eth0` interfaces get an IP with **no default gateway**, so no packet leaves the guest. Symptoms:

- `ip route` shows only link-scope routes (`10.0.2.0/24 dev wlan0`), no `default via 10.0.2.2`.
- `dhcp.wlan0.gateway`, `net.dns1`, etc. all empty.
- Ping to `8.8.8.8` returns "Network is unreachable".
- On the app: Firebase Installations throws `Firebase Installations Service is unavailable`, Google Play Services fails DNS on `play.googleapis.com`, and the Sirati splash never advances.

Interventions attempted this session — none fixed it: `-dns-server 8.8.8.8`, `-feature -VirtioWifi`, `wsl --shutdown`, killed all Docker processes, stopped `hns` and `vmcompute` services (elevated). Host itself has internet fine (`ping 8.8.8.8` from PowerShell OK). Root cause is inside QEMU's user-mode NAT ("slirp") — likely another kernel-mode network driver on this Windows box (Npcap, WinDivert, an antivirus filter driver, or a leftover Hyper-V virtual switch). Recommend either (a) `Get-NetAdapter | Where Virtual` and inspect, (b) reinstall the Android emulator package, or (c) test on a physical device.

### 6.2 Browser-back stacks pages in Flutter web build

Calling `history.back()` twice from the forgot-password screen produced a **visually stacked composite** of three routes (forgot-password + login + auth landing all rendered on top of each other) instead of navigating. This is either a Flutter web CanvasKit renderer bug or the app's router not disposing previous routes on `didPopRoute`. Worth checking whether the same manifests on Android hardware back — if yes, this is a release-blocker. If web-only, it needs a doc note ("browser back is unsupported; use in-app navigation").

### 6.3 Duplicated screen tree — `lib/screens/` vs `lib/features/*/presentation/`

`find lib -name "*screen*.dart"` returns 58 files. After deduplication there are 28 unique screens; roughly 24 of them exist in **both** `lib/screens/foo.dart` **and** `lib/features/<domain>/presentation/foo.dart`. Either the feature-based structure is a mid-migration and `lib/screens/` should be deleted (recommended), or the old structure is the live one and `lib/features/*/presentation/` is dead. Either way, both trees compiling is a footgun — a fix landing in one won't reach users if the router still imports the other. Grep `app_router.dart` to see which side is actually routed.

---

## 7. What was fixed in this session (SIRATI-90 / SIRATI-91)

Audit finding #1 (scorer ignores the job description) and #9 (`job_match` includes bonuses) shipped as a single change. Files touched:

| File | Change |
|---|---|
| `database/migrations/2026_09_18_000001_add_job_description_input_to_cv_analyses_table.php` | New: adds nullable `job_description_input` text column to `cv_analyses`, mirroring the existing column on `generated_cvs`. **Migrated.** |
| `app/Models/CvAnalysis.php` | Added `job_description_input` to `$fillable`. |
| `app/Services/AtsScoringService.php` | `score()` and `scoreDocument()` now take an optional `?string $jobDescription`. New method `extractJobTerms()`: tokenises the JD (English + Arabic-normalised), drops stopwords + boilerplate, ranks unigrams + repeated bigrams, caps at 25 terms. When a JD is provided, keyword matching runs against those terms instead of the hardcoded category list; the category is the fallback. `job_match` is now pure coverage (0–100 of terms actually matched), stripped of the +6 title-match / +3 early-mention bonuses that still contribute to `total`. New response field `keyword_source` = `job_description` or `category`. |
| `app/Http/Controllers/CvAnalysisController.php` | Validates `job_description` (nullable, string, max 4000), passes to scorer, persists on the analysis row. |
| `app/Http/Resources/CvAnalysisResource.php` | Echoes `job_description_input` back to the client. |
| `flutter_app/lib/shared/services/cv_api_service.dart` | `submitAnalysis()` accepts optional `jobDescription` and sends it as a multipart field when non-empty. |
| `flutter_app/lib/features/ats_scanner/presentation/cv_analysis_screen.dart` | New "Job description (optional)" card between the title card and the upload card. 5-line textarea with 4000-char client-side limit and a one-line hint about why to paste it. |
| `tests/Unit/AtsScoringServiceTest.php` | Three new tests: term extractor returns ranked terms + drops boilerplate; JD-provided path returns `keyword_source: 'job_description'` and finds the CV's actual matches; missing-keywords list contains JD terms, not category terms. |

Verification:
- `php vendor/bin/phpunit --filter "Ats\|CvAnalysis"` → **24 tests, 283 assertions, all passing** (the existing calibration corpus still holds).
- `flutter analyze` on the two changed Dart files → **No issues found.**

Not addressed in this pass (still in the audit): #7 real PDF format signals, #8 structured-document scoring path, #10 calibration corpus expansion. Those can be follow-ups.

## 8. Recommended next steps, ranked

1. ~~**Fix ATS accuracy items 1 & 9**~~ — **Done this session.** See §7.
2. ~~**Investigate contact-info sub-score 3/5**~~ — **Not a bug.** `contactScore()` gives 2 for email + 1 for phone + 2 for LinkedIn URL. My test CV had no LinkedIn URL, so 3/5 is expected. Add a LinkedIn URL to hit 5/5.
3. **QA the new JD field on device.** Rebuild the app, submit an analysis with a real posting pasted in, confirm the "missing keywords" list now reflects the posting's own terms.
4. **Resolve the `lib/screens/` vs `lib/features/…` duplication** before any more feature work — every fix costs 2× and half of them land in the dead tree. Grep `app_router.dart` to see which side is actually routed, delete the other.
5. **Diagnose the browser-back stacking** (§6.2). Test on Android hardware back; if it repeats, block release.
6. **Have me run a follow-up session** to cover the 12 remaining authenticated screens (my CVs, CV builder, settings sub-pages, history, gallery, jobs, education). Est. 30 minutes once emulator or web-build is up.
7. **Fix Android emulator networking on this host** (§6.1) so future iterations can test the native build directly — or standardise on a physical device for QA.
8. **Full `flutter analyze` + a release smoke pass** to close R10 (I only analysed the two files I touched).
9. **Clarify R09** in the release readiness doc — the OTP-based forgot-password already ships; the deep-link version is a separate feature (or delete R09).
10. **Follow up on remaining audit items** #7 (real PDF format signals from the parser), #8 (skip regex re-derivation for builder-generated CVs), #10 (grow the calibration corpus so future changes are guarded).

---

## Appendix A — What was executed

```
D:/Sirati/flutter_app$ flutter pub get           # ok
D:/Sirati/flutter_app$ flutter run -d web-server \
    --web-hostname 127.0.0.1 --web-port 8787 --release
    # Compiled in 66.6s; served at http://127.0.0.1:8787
```

Interaction with the app was done through the built-in browser pane, driving Flutter's semantic layer (`role="button"` DOM proxies exposed after clicking `flt-semantics-placeholder`).
