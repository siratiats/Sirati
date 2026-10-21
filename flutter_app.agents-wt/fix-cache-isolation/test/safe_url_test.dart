import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/safe_url.dart';

void main() {
  test('accepts https and localhost http only', () {
    expect(
      parseSafeExternalUrl('https://jobs.example.com/apply'),
      isNotNull,
    );
    expect(parseSafeExternalUrl('http://localhost:8000/pdf'), isNotNull);
    expect(parseSafeExternalUrl('http://evil.example/phish'), isNull);
    expect(parseSafeExternalUrl('javascript:alert(1)'), isNull);
    expect(parseSafeExternalUrl(''), isNull);
    expect(parseSafeExternalUrl(null), isNull);
  });
}
