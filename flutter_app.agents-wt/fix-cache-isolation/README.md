# Sirati Flutter app

Arabic-first CV builder for iOS, Android, and web. This package is the mobile client for the Sirati Laravel API.

## Layout

```
lib/
  core/         # logging, flavors, network, storage, routing engine, utils
  shared/       # models, theme, reusable widgets, cross-feature services
  features/     # product areas (auth, cv_builder, cv_export, ats_scanner, …)
  l10n/         # ARB catalogs + generated AppLocalizations
  main.dart
  main_dev.dart
  main_staging.dart
  main_prod.dart
```

Compatibility barrels remain at the previous paths (`lib/screens/`, `lib/services/`, …) so existing `package:sirati/...` imports keep resolving.

## Layering

1. `core/` has **zero** dependencies on `shared/` or `features/`.
2. `shared/` may depend on `core/`, never on `features/`.
3. `features/` may depend on `core/` and `shared/`.
4. Feature **data/** and **controllers/** must not import another feature. Cross-feature navigation goes through `AppRouter` (see `lib/features/app/sirati_route_table.dart`) or shared contracts.
5. `features/app` (route table) and `features/dashboard` (app shell) are composition roots and may import other features' presentation widgets.
6. `features/localization/app_locale.dart` re-exports the locale kernel in `core/utils/app_locale.dart`.

Enforced by `test/architecture/layering_invariants_test.dart` using the Dart AST (not source regex).

## Flavors

| Entrypoint | Flavor | Banner |
|---|---|---|
| `lib/main_dev.dart` | `dev` | amber **DEV** |
| `lib/main_staging.dart` | `staging` | blue **STAGING** |
| `lib/main_prod.dart` | `prod` | none |
| `lib/main.dart` | `--dart-define=FLAVOR=dev\|staging\|prod` (default `prod`) | as above |

Secrets are never committed. Inject them at build time:

```bash
flutter run -t lib/main_dev.dart \
  --dart-define=FLAVOR=dev \
  --dart-define=SIRATI_API_BASE_URL=https://example.invalid/api \
  --dart-define=SENTRY_DSN=
```

## i18n

- `l10n.yaml` + `lib/l10n/app_ar.arb` (template) / `app_en.arb`
- `flutter gen-l10n` (also runs with `flutter pub get` because `generate: true`)
- Arabic six-form plurals live in the ARB catalogs

## Tests

```bash
flutter analyze
flutter test --exclude-tags golden
flutter test --tags golden
```

Golden baselines (theme × direction) are documented in `test/golden/README.md`. GitHub CI runs goldens on `windows-latest`.
