# Sprint 4 — plan and backlog state

Prepared 2026-09-08 after the Sprint 4 sign-off. Theme: **make it shippable.**

Everything below is live in Jira. Sprint 4 candidates carry the label `sprint-4`:

```
project = SIRATI AND labels = "sprint-4" ORDER BY priority DESC
```

**Correction to what I said earlier.** I claimed sprints 2–4 were never created in Jira. That was wrong — I had queried a single issue that happened to sit in Sprint 1. Board 36 actually holds **Sprint 1 (id 43), Sprint 2 (id 44) and Sprint 3 (id 45), all closed**; Sprint 3 closed today at 06:28. So the next sprint is **Sprint 4**, which is what you corrected me on.

**What I could not do from here:** Sprint 4 does not exist yet on board 36 — I probed ids 46, 47 and 48 and all return "We could not find the sprint" (id 44 returns "can be assigned only active or future sprints", which confirms the probe works and that closed sprints are simply rejected). There is no sprint-creation API in the tools available to me. **Create *SIRATI Sprint 4* in the board UI**, then either drag in the `sprint-4` items or use the JQL above — or tell me once it exists and I will assign all eleven in one pass.

---

## Board hygiene — done

Six stories were shipped, reviewed and signed off but still sat in **To Do**, which is why the board could not answer "what is left". All moved to Done:

`SIRATI-11` scaffold · `SIRATI-14` flavors · `SIRATI-24` i18n · `SIRATI-25` RTL layout · `SIRATI-26` locale switching · `SIRATI-29` golden tests in CI

Seven placeholder tickets (`SIRATI-59`–`65`, "UI / UX 1–7") had **empty descriptions** — all their content was in comments from Salem alshammari. Each now has a real summary, a written description with file references and acceptance criteria, and the Arabic UI copy preserved verbatim.

## Sprint 4 — 11 items

### Do first — these stop you launching

| | Key | Item | Pri |
|---|---|---|---|
| 1 | **SIRATI-66** | Web routes: unauthenticated **and unthrottled** AI endpoints | Highest |
| 2 | **SIRATI-67** | Premium entitlement has no grant path — `is_premium` is never written | Highest |
| 3 | **SIRATI-63** | Per-field text direction (+ dark-mode contrast, digit locale) | Highest |

**66** — `routes/api.php` is thoroughly throttled; `routes/web.php` has **zero** `throttle` middleware, and `POST /analyze` and `POST /generate-cv` are public and both call the AI provider. A browser loop drains your provider budget with no account. Smallest fix, largest exposure.

**67** — `EntitlementService` reads `$user->isPremium()`, which reads `is_premium`, which **nothing in the codebase writes**. No purchase endpoint, no webhook, no admin toggle. On launch day every premium template 403s forever and there is no way to buy one. This is the largest gap in the product and the one most likely not to fit in a single sprint — if it does not, the decision in step 1 of that ticket (which billing model) is what has to land this sprint, because everything else follows from it.

**63** — item 3 of that ticket is not UI polish: with the UI in Arabic, direction is forced RTL, so a CV written in English comes out distorted. It is also nearly free — `BidiTextUtils.detectBaseDirection` exists with **zero callers**, and `AppTextFormField` already accepts and forwards `TextDirection?`. Wiring, not a feature.

### Then

| | Key | Item | Pri |
|---|---|---|---|
| 4 | **SIRATI-68** | `CvDocument` round-trip drops skill level, category and id | High |
| 5 | **SIRATI-70** | Store submission readiness — listings, privacy labels, crash DSN | High |
| 6 | **SIRATI-69** | Route `previewPdfResponse()` with authorization, or delete it | Medium |

**70 starts in parallel, not after.** App review has the longest lead time on the board and a rejection restarts it. The PDPL basics are already in place (`/privacy-policy` route, `DELETE /auth/account` with tests) — what is missing is the submission package.

### Cheap UI wins — hours each, ship them alongside

| Key | Item |
|---|---|
| **SIRATI-61** | Profile: single edit path, fixed display order (pairs with 63 — same widgets) |
| **SIRATI-62** | Settings: replace "replay intro" with "تواصل معنا" (mailto) |
| **SIRATI-60** | Bottom nav: remove the opaque selected-state overlay |
| **SIRATI-59** | Splash: circular reveal |
| **SIRATI-64** | Detail chrome, AI wording, education spacing, job title layout |

**If you only get three things done, make them 66, 67 and 63.**

## Backlog — filed, deliberately not in Sprint 4

| Key | Item | Why not now |
|---|---|---|
| **SIRATI-65** | Job discovery redesign (career paths, country/city, remote) | Multi-sprint feature mislabelled "UI / UX 7". Split into four before estimating. |
| **SIRATI-72** | Finish i18n — `my_cvs_screen`, `cv_generator_screen` | Both are bilingual, so debt not defect |
| **SIRATI-71** | Record the signed-URL TTL as a decision | Documentation of an existing choice |
| **SIRATI-73** | Test and repo hygiene from Sprint 1–4 reviews | Six small items, grouped so they stop recurring |

Pre-existing and untouched: `SIRATI-52` pricing, `SIRATI-55/56` marketing — planning work, not engineering. `SIRATI-53/54/57/58` still have no spec.

## Two decisions blocking work, not code

1. **Billing model** (SIRATI-67 step 1). Everything in monetization depends on it. Confirm whether App Store / Play billing is required for this category in Saudi, or whether a local provider is permitted.
2. **Is the public web funnel intentional?** (SIRATI-66 step 3). If try-before-signup is a product requirement it needs a cheap path — a daily ceiling, a captcha, or a canned sample — not the full AI call. If it is not, those routes go behind `auth` and the ticket shrinks to one line.

Two smaller ones, both on SIRATI-63 and SIRATI-64: Latin-digits-everywhere reverses a deliberate Arabic-Indic choice and needs a call on whether it applies to the exported PDF too; and dropping "بالذكاء الاصطناعي" is positioning, so it should match whatever SIRATI-55/56 settle on and change in the store listing at the same time.

## What the four sprints have taught the backlog

Every round has turned up the same shape: something built, tested in isolation, and never connected. `normalizeExtracted` had no caller. The bidi utilities had one. `previewPdfResponse` still has no route — it is item 6 above. `AppLocalizations` had no consumer. And now `detectBaseDirection` has zero call sites while the defect it exists to prevent is the highest-priority UI item on the board.

Three tickets in this sprint are that pattern, not new work: **63** (wire an existing function), **69** (route an existing method), **68** (call an existing map). Worth noticing that they are cheap *because* the engineering was already done well — and worth adding rule 6 to `AGENTS.md` so the next one gets connected in the sprint that builds it.
