import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sirati/main.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    // Splash bootstrap reads secure storage; mock so tests never hang / throw.
    const channel =
        MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
      // No token / prefs → first-run onboarding path.
      return null;
    });
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
      const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
      null,
    );
  });

  testWidgets('Sirati cold start reaches onboarding or welcome',
      (tester) async {
    // Mirrors main.dart: the app always runs inside a ProviderScope.
    await tester.pumpWidget(const ProviderScope(child: SiratiApp()));

    // Bootstrap is async (token + onboarding flags). Avoid pumpAndSettle —
    // BrandedLoader uses a repeating animation that never settles on boot.
    var sawGate = false;
    for (var i = 0; i < 40; i++) {
      await tester.pump(const Duration(milliseconds: 50));
      final onboarding =
          find.text('مرحباً بك في سيرتي').evaluate().isNotEmpty ||
              find.text('Welcome to Sirati').evaluate().isNotEmpty;
      final welcome =
          find.text('اصنع سيرتك الذاتية باحترافية').evaluate().isNotEmpty ||
              find.text('Build your CV professionally').evaluate().isNotEmpty;
      if (onboarding || welcome) {
        sawGate = true;
        break;
      }
    }

    expect(sawGate, isTrue, reason: 'onboarding or welcome after bootstrap');
    expect(find.text('سيرتي'), findsWidgets);
  });
}
