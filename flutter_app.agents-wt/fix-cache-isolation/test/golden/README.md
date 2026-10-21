# Golden tests (theme × direction)

Goldens live under `test/golden/goldens/` and cover a **4-way matrix** per subject:

`light_ltr`, `light_rtl`, `dark_ltr`, `dark_rtl`

Subjects:

1. Dashboard / home cluster
2. CV builder section chrome
3. CV live preview pane
4. ATS scanner / analysis chrome
5. `SubmitButton` enabled / loading / disabled
6. `AppTextFormField` idle / error / success
7. `ScoreBoosterCard`

Each subject is rendered with a fixed `MediaQuery` (390×844, `textScaler` 1.0, `disableAnimations: true`).

## Tolerance

`test/golden/tolerant_golden.dart` uses **0.005 (0.5% of pixels)**.

Baselines in this repo were generated on Windows. The GitHub `flutter-golden-tests` job runs on `windows-latest` so the comparison is same-OS. Typical same-OS Flutter engine noise is well below 0.5%; published Windows→macOS text rasterization diffs are also under 0.5%. The previous 2% budget (~19,000 pixels on these ~800×1200 images) was large enough to hide a footer line.

If you regenerate on another OS family, re-measure the actual `diffPercent` from `GoldenFileComparator.compareLists` on a no-op run and set the tolerance just above that floor — do not round up to a convenient 2%.

## Update baselines

After intentional visual changes (theme tokens, spacing, component chrome), regenerate on Windows (the CI runner OS):

```bash
flutter test test/golden --update-goldens
```

Commit the updated PNGs under `test/golden/goldens/`.

## CI

- The Ubuntu quality job runs `flutter test --coverage --exclude-tags golden` (fast, OS-agnostic).
- The **Flutter Golden Tests** job runs `flutter test --tags golden` on `windows-latest` and **fails the workflow on mismatch**.
- Codemagic preview jobs still run `flutter test --exclude-tags golden` **by design**: those jobs are macOS, and Windows-generated baselines fail there on text rasterization. Goldens gate on GitHub `windows-latest` only.

```bash
flutter test --tags golden
```
