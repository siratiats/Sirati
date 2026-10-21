# Sirati tests

## Unit / widget tests

```bash
flutter test
```

## Golden tests (theme × direction)

Goldens live under `test/golden/goldens/` and cover:

- Dashboard stat + action card cluster
- `SubmitButton` enabled / loading / disabled
- `AppTextFormField` idle / error / success
- `ScoreBoosterCard`

Each subject is rendered in a **4-way matrix**: light+LTR, light+RTL, dark+LTR, dark+RTL — with a fixed `MediaQuery` (390×844, `textScaler` 1.0, `disableAnimations: true`).

### Update baselines

After intentional visual changes (theme tokens, spacing, component chrome):

```bash
flutter test test/golden --update-goldens
```

Or all tests with golden updates:

```bash
flutter test --update-goldens
```

Commit the updated PNGs under `test/golden/goldens/`.

### CI note

Golden pixel comparison is sensitive to OS/engine rasterization (Windows baselines vs Codemagic macOS).

- Tests are tagged `golden`.
- Codemagic runs `flutter test --exclude-tags golden` so preview builds stay green.
- Locally, `test/golden/tolerant_golden.dart` allows ~2% pixel noise when you do run goldens.

Run goldens (with tolerance):

```bash
flutter test test/golden
```

After intentional visual changes, regenerate on the **same OS family as production CI** when possible:

```bash
flutter test test/golden --update-goldens
```

Then commit the PNGs under `test/golden/goldens/`.
