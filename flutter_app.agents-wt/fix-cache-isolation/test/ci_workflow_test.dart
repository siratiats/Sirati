import 'dart:io';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('SIRATI-15: Continuous Integration Pipeline Invariants', () {
    test(
        'CI workflow file exists and enforces format, fatal-infos analysis, and tests',
        () {
      final workflowFile = File('../.github/workflows/ci.yml');
      expect(
        workflowFile.existsSync(),
        isTrue,
        reason: '.github/workflows/ci.yml must exist at repository root',
      );

      final content = workflowFile.readAsStringSync();

      expect(content, contains('push:'));
      expect(content, contains('pull_request:'));

      expect(content,
          contains('dart format --output=none --set-exit-if-changed .'));

      expect(
          content, contains('flutter analyze --fatal-infos --fatal-warnings'));

      // Fast quality job stays OS-agnostic and skips goldens.
      expect(
          content, contains('flutter test --coverage --exclude-tags golden'));

      // SIRATI-29: a dedicated job must actually run goldens so mismatch fails CI.
      expect(content, contains('flutter test --tags golden'));
      expect(content, contains('runs-on: windows-latest'));
      expect(
        content,
        isNot(contains('echo "Flutter tests completed successfully."')),
      );

      expect(content, contains('timeout-minutes: 10'));
    });
  });
}
