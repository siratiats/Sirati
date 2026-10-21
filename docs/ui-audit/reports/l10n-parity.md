# l10n parity — en/ar ARB across main, ui-wp1c, ui-wp2b

## Summary

1. Checkouts verified: `main` = 430 en / 430 ar keys, `agent/ui-wp1c` = 311 / 311, `agent/ui-wp2b` = 317 / 317 (real keys only; `@…` metadata excluded).
2. (1) Missing keys: 0 in every checkout — no key exists in one language but not the other.
3. (2) Placeholder mismatches: 0 genuine in every checkout — placeholder names and ICU types match; Arabic-only extra plural cases are locale grammar, not defects (listed per checkout).
4. (3) Empty Arabic: 0 everywhere. Identical-to-English Arabic: 15 flagged keys per checkout (after excluding placeholder/number-only values; raw identical = 18 on main, 16 on each branch).
5. (4) ui-wp1c vs ui-wp2b conflicting text for the same key: 0 keys in en and 0 in ar — the branches will not text-conflict on shared ARB keys.
6. (5) English still saying "Sirati": main 0; ui-wp1c 3 keys; ui-wp2b 3 keys (same 3 keys/values on both branches). Arabic brand (`سيرتي`) is untouched everywhere.
7. Merge note: branches differ only by key presence, not text — ui-wp1c-only `sharedLoading`, ui-wp2b-only 7 keys (both locales); those merge as additions, not conflicts.
8. Read-only check: ARB files were parsed and compared, nothing was edited and neither `flutter` nor `dart` was run (this report is the only file written).

## Method

- Parsed `lib/l10n/app_en.arb` and `app_ar.arb` as JSON in each checkout; compared non-`@` keys.
- Placeholders were parsed as ICU (selector name + `plural`/`select` type + case keys), so select-case labels and nested plural messages are not mistaken for placeholders. For `plural`, Arabic legitimately uses more cases (`=2`, `few`, `many`, …) than English; a case-inventory difference with the same placeholder name and type is reported as a note, not counted as a mismatch.
- Category (3) excludes Arabic values that are placeholder/number-only (e.g. `{score}%`); brand-only and number-only values are excluded per the task definition. All other identical values are listed, including `*En`-suffixed ones, with a note where they look intentional.
- Category (4) compares the same locale file (en↔en, ar↔ar) between the two worktrees for keys present in both.

## main — `D:\Sirati\flutter_app` (branch `main`, 430 keys per language)

| category | count | keys / details |
|---|---|---|
| (1) In en, missing in ar | 0 | — |
| (1) In ar, missing in en | 0 | — |
| (2) Placeholder mismatch ({name}, plural/select) | 0 | Plural case inventory differs only on `jobMatchApplySelected`, `jobMatchPaywallTrial`, `jobMatchQuotaRemaining`, `premiumGatePeriod` (Arabic adds `=2`/`few`/`many`; same `{count}`/`{days}`/`{months}` plural placeholder — not a defect). `jobMatchSectionName` select cases are identical in en and ar. |
| (3) Arabic empty | 0 | — |
| (3) Arabic identical to English (flagged) | 15 | Table below. Excluded as placeholder/number-only: `cvHubAtsScore` (`{score}%`), `jobMatchCharCount` (`{count} / {max}`), `premiumGatePrice` (`{price} {currency}`). |
| (5) English says "Sirati" (not "Siratie") | 0 | Already rebranded: `appTitle`, `welcomeToSirati`, `contactUsEmailSubject`, `premiumGateAppBar` all say "Siratie". |

Flagged identical Arabic values on main:

| key | Arabic value (= English) | note |
|---|---|---|
| companyEn | Company Name (English) | `*En` field label — likely intentional |
| companyEnHint | e.g. Saudi Aramco | `*EnHint` example — likely intentional |
| fullNameEn | Full Name (English) | `*En` field label — likely intentional |
| fullNameEnHint | e.g. Mohammed Al-Qahtani | `*EnHint` example — likely intentional |
| headlineEn | Professional Headline (English) | `*En` field label — likely intentional |
| headlineEnHint | e.g. Senior Software Engineer | `*EnHint` example — likely intentional |
| institutionEn | Institution (English) | `*En` field label — likely intentional |
| institutionEnHint | e.g. King Saud University | `*EnHint` example — likely intentional |
| jobTitleEn | Job Title (English) | `*En` field label — likely intentional |
| jobTitleEnHint | e.g. Senior Software Engineer | `*EnHint` example — likely intentional |
| languageEnglish | English | Language name in English — verify intent |
| locationEn | Location (English) | `*En` field label — likely intentional |
| locationEnHint | e.g. Riyadh, Saudi Arabia | `*EnHint` example — likely intentional |
| summaryEnHint | Results-driven software engineer with 5+ years of experience... | `*EnHint` example — likely intentional |
| summaryEnLabel | Professional Summary in English | `*En` field label — likely intentional |

## ui-wp1c — `D:\Sirati.agents-wt\ui-wp1c\flutter_app` (branch `agent/ui-wp1c`, 311 keys per language)

| category | count | keys / details |
|---|---|---|
| (1) In en, missing in ar | 0 | — |
| (1) In ar, missing in en | 0 | — |
| (2) Placeholder mismatch ({name}, plural/select) | 0 | Plural case inventory differs only on `jobMatchApplySelected`, `jobMatchPaywallTrial`, `jobMatchQuotaRemaining` (Arabic adds `=2`/`few`/`many`; same placeholder name/type — not a defect). `jobMatchSectionName` select cases are identical in en and ar. |
| (3) Arabic empty | 0 | — |
| (3) Arabic identical to English (flagged) | 15 | Same 15 keys as main (table below). Excluded as placeholder/number-only: `jobMatchCharCount` (`{count} / {max}`). |
| (5) English says "Sirati" (not "Siratie") | 3 | `appTitle` = "Sirati", `welcomeToSirati` = "Welcome to Sirati", `contactUsEmailSubject` = "Sirati Support Inquiry". |

Flagged identical Arabic values on ui-wp1c: `companyEn`, `companyEnHint`, `fullNameEn`, `fullNameEnHint`, `headlineEn`, `headlineEnHint`, `institutionEn`, `institutionEnHint`, `jobTitleEn`, `jobTitleEnHint`, `languageEnglish`, `locationEn`, `locationEnHint`, `summaryEnHint`, `summaryEnLabel` — values identical to the main table above (all `*En`/`*EnHint` field labels and examples, plus `languageEnglish` = "English"; likely intentional, verify).

## ui-wp2b — `D:\Sirati.agents-wt\ui-wp2b\flutter_app` (branch `agent/ui-wp2b`, 317 keys per language)

| category | count | keys / details |
|---|---|---|
| (1) In en, missing in ar | 0 | — |
| (1) In ar, missing in en | 0 | — |
| (2) Placeholder mismatch ({name}, plural/select) | 0 | Plural case inventory differs only on `jobMatchApplySelected`, `jobMatchPaywallTrial`, `jobMatchQuotaRemaining` (Arabic adds `=2`/`few`/`many`; same placeholder name/type — not a defect). `jobMatchSectionName` select cases are identical in en and ar. |
| (3) Arabic empty | 0 | — |
| (3) Arabic identical to English (flagged) | 15 | Same 15 keys as main. Excluded as placeholder/number-only: `jobMatchCharCount` (`{count} / {max}`). |
| (5) English says "Sirati" (not "Siratie") | 3 | `appTitle` = "Sirati", `welcomeToSirati` = "Welcome to Sirati", `contactUsEmailSubject` = "Sirati Support Inquiry". |

Flagged identical Arabic values on ui-wp2b: same 15 keys and values as the main table above (`companyEn` … `summaryEnLabel`, plus `languageEnglish` = "English"; likely intentional, verify).

## (4) ui-wp1c vs ui-wp2b — same key, different text (merge conflicts)

| locale file | keys in both with different text | wp1c-only keys (not a conflict) | wp2b-only keys (not a conflict) |
|---|---|---|---|
| app_en.arb | 0 | `sharedLoading` | `clearFilters`, `compatibilityScoreLabel`, `compatibilityScoreValue`, `hidePassword`, `noMatchingJobs`, `registerInterest`, `showPassword` |
| app_ar.arb | 0 | `sharedLoading` | `clearFilters`, `compatibilityScoreLabel`, `compatibilityScoreValue`, `hidePassword`, `noMatchingJobs`, `registerInterest`, `showPassword` |

No shared key carries different wording on the two branches in either language, so merging the ARB files should produce no text conflicts; the branch-only keys above arrive as pure additions. (This compares string values only, not `@…` metadata or generated Dart.)

## Other notes

- Metadata (`@…`) was excluded from all counts. The Arabic files carry one metadata entry the English files lack (`@appTitle`); the real `appTitle` key exists in both languages on all three checkouts, and Arabic `appTitle` is `سيرتي` everywhere.
- `@…` placeholder declarations (names/types) agree between en and ar on all three checkouts; only `description`/`example` wording differs, which is expected per locale.
- The 15 identical-value keys in category (3) are the same set on all three checkouts — they predate the branches. Most are labels/examples for English-language CV input fields, so they read as intentional; `languageEnglish` ("English" in the Arabic file) is the one to confirm with the product owner.
