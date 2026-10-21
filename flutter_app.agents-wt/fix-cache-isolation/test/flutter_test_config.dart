import 'dart:async';

import 'golden/tolerant_golden.dart';

/// Directory-level test config (picked up automatically by `flutter test`).
///
/// Also installed from golden test [setUpAll] — see [installTolerantGoldenComparator].
Future<void> testExecutable(FutureOr<void> Function() testMain) async {
  installTolerantGoldenComparator(
      precisionTolerance: defaultGoldenPrecisionTolerance);
  await testMain();
}
