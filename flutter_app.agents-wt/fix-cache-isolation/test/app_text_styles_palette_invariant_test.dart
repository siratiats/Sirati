import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Repo-wide guard for SIRATI-80: the light-palette default must stay
/// unrepresentable. This is not a test of the reviewed call sites.
void main() {
  final libRoot = Directory('lib');

  test('every AppTextStyles call passes a palette argument', () {
    final pattern = RegExp(r'AppTextStyles\.\w+\(\s*\)');
    final offenders = <String>[];

    for (final file in libRoot
        .listSync(recursive: true)
        .whereType<File>()
        .where((file) => file.path.endsWith('.dart'))) {
      final contents = file.readAsStringSync();
      if (pattern.hasMatch(contents)) {
        offenders.add(file.path);
      }
    }

    expect(offenders, isEmpty,
        reason: 'AppTextStyles.*( ) with no palette: $offenders');
  });

  test('SiratiColors.light and .dark stay inside lib/shared/theme/', () {
    final pattern = RegExp(r'SiratiColors\.(light|dark)\b');
    final offenders = <String>[];

    for (final file in libRoot
        .listSync(recursive: true)
        .whereType<File>()
        .where((file) => file.path.endsWith('.dart'))) {
      final normalized = file.path.replaceAll('\\', '/');
      if (normalized.contains('lib/shared/theme/')) {
        continue;
      }
      final contents = file.readAsStringSync();
      if (pattern.hasMatch(contents)) {
        offenders.add(file.path);
      }
    }

    expect(offenders, isEmpty,
        reason: 'SiratiColors.light/dark outside theme: $offenders');
  });
}
