import 'package:flutter/material.dart';

/// Build flavor selected at compile time via `--dart-define=FLAVOR=...`
/// or by a dedicated entrypoint (`main_dev.dart`, `main_staging.dart`,
/// `main_prod.dart`).
enum AppFlavor {
  dev,
  staging,
  prod;

  bool get isProd => this == AppFlavor.prod;

  bool get showBanner => this != AppFlavor.prod;

  String get label => switch (this) {
        AppFlavor.dev => 'DEV',
        AppFlavor.staging => 'STAGING',
        AppFlavor.prod => 'PROD',
      };

  // Non-prod build markers; never themed, never shown in prod.
  static const _dev = Color(0xFFF9A825); // ignore: hardcoded_color -- marker
  static const _stg = Color(0xFF1565C0); // ignore: hardcoded_color -- marker
  static const _none = Color(0x00000000); // ignore: hardcoded_color -- marker

  /// Ribbon color for the on-device [FlavorBanner]. Prod never renders it.
  Color get bannerColor => switch (this) {
        AppFlavor.dev => _dev,
        AppFlavor.staging => _stg,
        AppFlavor.prod => _none,
      };

  static AppFlavor? _current;
  static bool _bootstrapped = false;

  static AppFlavor get current => _current ?? AppFlavor.prod;

  /// Pin the flavor from a dedicated entrypoint. Wins over `--dart-define`.
  static void bootstrap(AppFlavor flavor) {
    _current = flavor;
    _bootstrapped = true;
  }

  /// Read `FLAVOR` from `--dart-define`. No-op if [bootstrap] already ran.
  static void ensureInitialized() {
    if (_bootstrapped) return;
    const raw = String.fromEnvironment('FLAVOR', defaultValue: 'prod');
    _current = switch (raw.trim().toLowerCase()) {
      'dev' => AppFlavor.dev,
      'staging' => AppFlavor.staging,
      'prod' => AppFlavor.prod,
      _ => AppFlavor.prod,
    };
    _bootstrapped = true;
  }

  @visibleForTesting
  static void resetForTest([AppFlavor? flavor]) {
    _current = flavor;
    _bootstrapped = flavor != null;
  }
}
