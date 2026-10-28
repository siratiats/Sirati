# Notifications inbox redesign (Stitch Concept A: grouped timeline)

Goal: redesign the in-app notifications inbox to match `design/notifications/concept-a-grouped.html` (+ `.png`), with the empty state from `design/notifications/empty-state.html`. Layout, spacing, chips, icon tiles, unread tint, RTL as in the design, built from the app's own tokens/widgets (`context.sirati`, `AppTextStyles`, `AppSpacing`, `DirectionalIcon`, `MotionReveal`, `PressScale`). Do NOT commit `design/` (reference only).

Files: `lib/features/settings/presentation/notifications_screen.dart`, `lib/features/settings/domain/notification_item.dart`, new widgets/helpers under `lib/features/settings/` as needed, `lib/l10n/app_ar.arb` + `app_en.arb` (append new keys at the END of each file only), `test/features/settings/notifications_test.dart` (+ new test files under `test/features/settings/`). Do not touch `lib/shared/widgets/empty_state.dart`, login/auth files, or anything outside these.

## Real data (backend `GET /mobile/notifications`, latest 50)
Each row: `id, type, title, body, action_type, action_url, is_read, created_label, created_at, read_at`. The app currently ignores `type`; parse it. Types actually sent today:
- `cv_ready`, `cv_failed` (CV generation; url `my-cvs/<id>`), `cv` (planner: `create-cv`, `my-cvs`)
- `job_match` (url `job-news/<id>`), `job` (planner: `job-news/<id>`)
- `analysis` (url `analysis/<id>`)
- `education` (url `education/<id>`), `tip` (url `cv-analysis` or `home`)
- `info` or any free text (admin campaigns, any url or none)
Unknown/missing type -> derive category from `target` (the existing resolver), else General.

## Decisions
1. Category enum: cv, jobs, analysis, learning (education + tip), general. Each has an icon (Material: description / work / insights / school / notifications) and a tinted 44px rounded-square tile. `cv_failed` is in the CV category but uses an error-tinted tile + error icon. Tile/icon colours from existing theme tokens; must pass AA (graphics >= 3:1) in light AND dark.
2. Filter chips row (horizontal scroll): All (with unread count badge), Unread, then one chip per category that has at least one item, in the order CV, Jobs, Analysis, Learning, General. Client-side filtering. A filter with no items shows a small inline "nothing here" message, not the full empty state.
3. Date groups by local `createdAt`: Today, Yesterday, This week (last 7 days), Earlier; missing `createdAt` -> Earlier. Group header: label on the start side; on the end side "N new" if the group has unread items, else "N notifications" (ICU plural, Arabic plural forms correct).
4. Row: icon tile, title (bold if unread), body max 2 lines with ellipsis, time (`notificationTimeLabel`, keep it). Unread: light primary-tinted background + border + dot with the existing "Unread" semantics label. Tap behaviour unchanged (`_open`, mark read, navigate via target).
5. Inline action button only for unread `cv_ready`: "View CV" -> same navigation as tapping.
6. "Read all" stays in the app bar (existing logic). Keep pull-to-refresh, loading and error states.
7. Empty state (no notifications at all): bell illustration built from widgets (no new image assets), existing empty title/body keys, plus a secondary button "Scan your CV now" opening the ATS scanner (find the existing route used for the ATS/cv-analysis input).
8. Out of scope: swipe-to-delete/archive (no backend endpoint), bottom nav changes, the job-alerts toggle shown in the empty-state mock, the "swipe" hint.
9. All strings via ARB (display-only, nothing persisted). Arabic copy: اليوم، أمس، هذا الأسبوع، سابقاً، غير مقروء، السيرة الذاتية، الوظائف، التحليل، التعلّم، عام، عرض السيرة، فحص سيرتك الآن.

## Tests (AGENTS.md rules: invariants, strict contrast, widget-pump level, no source regex)
- Category mapping: every known type, unknown type falling back via target, missing type and empty url -> general.
- Grouping with an injected clock: boundaries (midnight, exactly 7 days, future dates, null dates), order preserved within groups, group counts/new counts.
- Filters: each chip shows exactly its items; chips for absent categories are hidden; unread count badge.
- Pump the screen in ar (RTL) and en, light and dark: `expectAllTextAa` passes; tiles/dots >= 3:1.
- Empty state CTA navigates; inline "View CV" only on unread cv_ready.
Run only: `flutter test test/features/settings/` and `flutter test test/dark_mode_12_screen_walk_test.dart test/localization/` and `flutter analyze lib/features/settings`. Run `flutter gen-l10n` (or pub get) if generated l10n is missing; do not commit `lib/l10n/generated`. Do not update goldens unless one covering this screen fails because of the intended change (say which).

Before editing, state your design in 3-5 bullets. End with a short summary and `LEARNINGS:` lines. Commit on the current branch when done.
