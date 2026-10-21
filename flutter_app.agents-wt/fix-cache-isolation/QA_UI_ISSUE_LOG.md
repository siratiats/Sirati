# UI QA Issue Log

Use this log during tablet and phone QA to track regressions and polish issues.

## Severity Guide
- Critical: Blocks usage, causes crash, or blocks core flow.
- High: Major UX break or severe visual defect.
- Medium: Noticeable issue with workaround.
- Low: Minor visual polish issue.

## Status Flow
- Open
- In Progress
- Fixed
- Ready for QA
- Closed

## Suggested Labels
- ui
- responsive
- tablet
- rtl
- typography
- motion
- accessibility

## Issue Template Table

| ID | Severity | Screen/Area | Repro Steps | Expected | Actual | Owner | ETA | Status |
|---|---|---|---|---|---|---|---|---|
| UI-001 | Critical / High / Medium / Low | Home / My CVs / Education / Job News | 1) Open app 2) Go to screen 3) Perform action | Clear expected result | What happened instead | @owner | YYYY-MM-DD | Open |
| UI-002 | Critical / High / Medium / Low | Header | 1) Change language 2) Open screen 3) Observe title/subtitle | No clipping, clean alignment | Clipping or misalignment details | @owner | YYYY-MM-DD | Open |
| UI-003 | Critical / High / Medium / Low | Tab Navigation | 1) Rapidly switch tabs 20x | No overlap or flicker | Overlap/flicker seen on device | @owner | YYYY-MM-DD | Open |
| UI-004 | Critical / High / Medium / Low | Education Chips | 1) Open Education 2) Scroll chips 3) Rotate device | Smooth scroll, no crowding | Crowding/jump/clip details | @owner | YYYY-MM-DD | Open |
| UI-005 | Critical / High / Medium / Low | Job News Cards | 1) Open Job News 2) Review title/meta/date | Readable hierarchy | Specific readability issue | @owner | YYYY-MM-DD | Open |
| UI-006 | Critical / High / Medium / Low | My CVs Actions | 1) Open My CVs 2) Check Edit/Delete/Download | Labels/icons readable and aligned | Misalignment/size issue | @owner | YYYY-MM-DD | Open |
| UI-007 | Critical / High / Medium / Low | FAB Position | 1) Open Home 2) Scroll 3) Rotate | FAB aligned to content edge and no overlap | Overlap or edge drift | @owner | YYYY-MM-DD | Open |
| UI-008 | Critical / High / Medium / Low | RTL/LTR | 1) Switch EN/AR 2) Re-check all tabs | Proper mirroring/alignment | Directionality issue details | @owner | YYYY-MM-DD | Open |
| UI-009 | Critical / High / Medium / Low | Text Scale | 1) Set text scale to 1.2-1.3 2) Re-check screens | No clipping/collision | Clipping/collision details | @owner | YYYY-MM-DD | Open |
| UI-010 | Critical / High / Medium / Low | Landscape Tablet | 1) Rotate tablet 2) Re-check all tabs | Balanced spacing, no overflow | Overflow/spacing issue details | @owner | YYYY-MM-DD | Open |

## Fast Validation Commands

Run from Flutter project root:

```powershell
flutter clean
flutter pub get
flutter analyze
flutter test
flutter run -d <android_device_id_or_ip_port>
```

Optional web sanity run:

```powershell
flutter run -d chrome
```

## Release Gate
- Critical failures: 0
- High failures: 0
- Medium failures: acceptable only with owner and ETA
- Low failures: can ship with planned follow-up
