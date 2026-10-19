# UI change list (deduped, verified by Muse Wonder: 47 confirmed, 22 partly, 0 wrong)
Source of truth: docs/ui-audit/reports/muse-verification.md (use its "Corrected recommendation" column; search by symbol, line numbers drifted).

## WP1 shared design system (Codex)   lib/shared/**
- Inputs: outline token 3:1 (A-U-09; reject B-U-06 fix), floating label/focus -> primaryInk (A-U-03, B-U-02), error border solid + icon (B-U-03), errors attached to fields with live region (A-U-18, C-U-03).
- Semantics: stop excludeSemantics on AppInput/AppButton/AppListTile (A-U-01 = C-U-01 + C-U-02); tap targets 48dp (A-U-19).
- Dark scheme pairs onSecondary/onTertiary (A-U-02, B-U-04); primary as text -> primaryInk everywhere in shared widgets (A-U-03).
- Disabled submit button (A-U-04), amber text (A-U-05 = C-U-07), snackbar action contrast (A-U-06), AI progress upcoming steps -> textSecondary + outline icon (A-U-07 = C-U-09), textHint dark (A-U-08).
- Typography: only weights 400/600/700 (A-U-10), sizes/line-heights from tokens (A-U-11); keep text scale clamp but fix overflows (A-U-20).
- Loading overlay copy honesty (A-U-16), splash dark (A-U-17), login compact header on short screens (A-U-14), card shadow consolidation (A-U-15).
## WP2 feature screens (Antigravity)   lib/features/**   (C findings + B-U-05)
- Jobs feed/detail: badge/label contrast (primaryInk, #8A5612 warning ink), 13sp minimum, wrapping at large text; History/Education reflow for 200% text; Settings/Profile semantics + tap targets; Home action cards visually distinct (B-U-05).
## WP3 UX flows and layouts (Copilot Opus, after Stitch mockups)
- Premium gate with real upgrade path (B-U-15), Job Match wait/retry/blank-result (B-U-21, B-U-23), Generated CV next steps (B-U-11), ATS using a Sirati CV (B-U-12), Home hero, nav hub.
## NEEDS USER DECISION (brand)
- Logo/wordmark/app icon evolution (A-U-12, A-U-13, B-U-09, C-U-19): one scope, decided once.
## Rejected / already fine
- B-U-06 borderStrong fix (1.71:1 fails); B-U-28 arrow mirroring (SubmitButton already mirrors).
