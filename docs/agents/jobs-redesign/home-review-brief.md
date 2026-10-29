# Review: Home redesign to the approved board (READ-ONLY, <= 400 words, ranked)

Do not edit files. Review Claude's uncommitted changes in `flutter_app/` (`git -C flutter_app diff` plus the new
untracked files under `lib/features/dashboard/presentation/home/`: `home_pressable_card.dart`, `home_start_card.dart`,
`home_service_tile.dart`, `home_latest_cv_section.dart`).

Board (approved "option 3 with changes"): profile row card (avatar, greeting, hint, chevron; whole row one 64dp target;
pressed = surface-2 + selected border; focus ring 2dp) with the bell as its own button; two start cards (80dp drawing,
green band "create a CV", navy band "check with ATS"); three service tiles (upload CV, target a job, Learning disabled
with a "soon" chip); latest jobs as compact cards (title, company, pin + city · time); "my latest CV" card with Edit and
ATS check, or an empty state with "create your CV"; honesty line; bottom nav order Home, My CVs, Jobs, Learning.
The job list card (`lib/features/jobs/presentation/widgets/job_news_card.dart`) now uses the same anatomy.

Find: bugs (navigation targets, state handling, refresh, RTL/LTR, text scale 2.0 overflow, focus/semantics, the nav
order change vs `DashboardTab.fromIndex` users and deep links), accessibility (48dp targets, labels, AA contrast incl.
the new `inkSurface` token), anything the board shows that is missing or different, and tests that only check examples.
Each finding: severity, file:line, how it fails, fix. End with VERDICT: APPROVE or CHANGES.
