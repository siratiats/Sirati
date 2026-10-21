import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/dashboard/presentation/home_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';
import 'package:sirati/shared/services/notification_service.dart';
import 'package:sirati/shared/theme/app_theme.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const secureStorage =
      MethodChannel('plugins.it_nomads.com/flutter_secure_storage');

  setUp(() {
    AppLocale.languageCode.value = 'en';
    MobileContentService.invalidate();
    SharedPreferences.setMockInitialValues({});
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(secureStorage, (call) async => null);
  });

  tearDown(() {
    NotificationService.instance.onNotificationTap = null;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(secureStorage, null);
  });

  testWidgets('HomeScreen owns the tap handler only while mounted',
      (tester) async {
    final client = MockClient(
      (_) async => http.Response('{"data":{}}', 200,
          headers: {'content-type': 'application/json'}),
    );
    await http.runWithClient(() async {
      await tester.pumpWidget(ProviderScope(
        child: MaterialApp(
          theme: AppTheme.lightFor(arabic: false),
          locale: const Locale('en'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          home: const HomeScreen(),
        ),
      ));
      await tester.pump();
      expect(NotificationService.instance.onNotificationTap, isNotNull);

      // Logout replaces the shell; later taps must be buffered, not routed
      // through the disposed screen.
      await tester.pumpWidget(const SizedBox());
      expect(NotificationService.instance.onNotificationTap, isNull);
    }, () => client);
  });

  testWidgets('a newer shell keeps its handler when an older one disposes',
      (tester) async {
    final client = MockClient(
      (_) async => http.Response('{"data":{}}', 200,
          headers: {'content-type': 'application/json'}),
    );
    await http.runWithClient(() async {
      void other(Map<String, dynamic> _) {}
      await tester.pumpWidget(ProviderScope(
        child: MaterialApp(
          theme: AppTheme.lightFor(arabic: false),
          locale: const Locale('en'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          home: const HomeScreen(),
        ),
      ));
      await tester.pump();
      NotificationService.instance.onNotificationTap = other;

      await tester.pumpWidget(const SizedBox());
      expect(NotificationService.instance.onNotificationTap, other);
    }, () => client);
  });
}
