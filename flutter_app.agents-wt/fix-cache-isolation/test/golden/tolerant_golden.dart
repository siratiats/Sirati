import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';

/// Cross-OS golden comparator (Windows baselines vs Codemagic macOS).
///
/// Install once in [setUpAll] so it does not depend on [flutter_test_config]
/// being present in the clone.
/// Default 0.5% of pixels. Same-OS engine noise is typically far below this;
/// cross-OS text rasterization is documented as well under 0.5%. A 2% budget
/// (~19k pixels on these baselines) could hide a footer line.
const defaultGoldenPrecisionTolerance = 0.005;

void installTolerantGoldenComparator(
    {double precisionTolerance = defaultGoldenPrecisionTolerance}) {
  final current = goldenFileComparator;
  if (current is! LocalFileComparator) return;
  if (current is _TolerantGoldenFileComparator) return;

  goldenFileComparator = _TolerantGoldenFileComparator(
    Uri.parse('${current.basedir}test.dart'),
    precisionTolerance: precisionTolerance,
  );
}

/// Passes if images match exactly **or** differ by at most [precisionTolerance]
/// (0–1 fraction of pixels). Default is [defaultGoldenPrecisionTolerance].
class _TolerantGoldenFileComparator extends LocalFileComparator {
  _TolerantGoldenFileComparator(
    super.testFile, {
    required double precisionTolerance,
  })  : assert(
          0 <= precisionTolerance && precisionTolerance <= 1,
          'precisionTolerance must be between 0 and 1',
        ),
        _precisionTolerance = precisionTolerance;

  final double _precisionTolerance;

  @override
  Future<bool> compare(Uint8List imageBytes, Uri golden) async {
    final result = await GoldenFileComparator.compareLists(
      imageBytes,
      await getGoldenBytes(golden),
    );

    final passed = result.passed || result.diffPercent <= _precisionTolerance;
    if (passed) {
      result.dispose();
      return true;
    }

    final error = await generateFailureOutput(result, golden, basedir);
    result.dispose();
    throw FlutterError(error);
  }
}
