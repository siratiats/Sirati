import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  final stored = <String, String>{};

  setUp(() {
    AppLocale.resetForTest();
    stored.clear();
    const channel =
        MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
      switch (call.method) {
        case 'read':
          final key = (call.arguments as Map)['key'] as String?;
          return stored[key];
        case 'write':
          final args = call.arguments as Map;
          stored[args['key'] as String] = args['value'] as String;
          return null;
        case 'delete':
          final key = (call.arguments as Map)['key'] as String?;
          stored.remove(key);
          return null;
        default:
          return null;
      }
    });
  });

  tearDown(() {
    AppLocale.resetForTest();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
      const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
      null,
    );
  });

  group('resolveLanguageCode', () {
    test('URL lang wins over storage and device', () {
      expect(
        AppLocale.resolveLanguageCode(
          urlLang: 'en',
          storedLang: 'ar',
          deviceLocales: const [Locale('ar', 'SA')],
        ),
        'en',
      );
    });

    test('stored preference wins over device', () {
      expect(
        AppLocale.resolveLanguageCode(
          storedLang: 'en',
          deviceLocales: const [Locale('ar', 'SA')],
        ),
        'en',
      );
    });

    test('Gulf country codes default to Arabic regardless of language tag', () {
      for (final country in gulfCountryCodes) {
        expect(
          AppLocale.resolveLanguageCode(
            deviceLocales: [Locale('en', country)],
          ),
          'ar',
          reason: 'en-$country is a Gulf device',
        );
        expect(
          AppLocale.resolveLanguageCode(
            deviceLocales: [Locale('fr', country)],
          ),
          'ar',
          reason: 'fr-$country is still a Gulf device',
        );
      }
    });

    test('Arabic language tag defaults to Arabic for non-Gulf countries', () {
      expect(
        AppLocale.resolveLanguageCode(
          deviceLocales: const [Locale('ar', 'EG')],
        ),
        'ar',
      );
    });

    test('English devices outside the Gulf default to English', () {
      expect(
        AppLocale.resolveLanguageCode(
          deviceLocales: const [Locale('en', 'US')],
        ),
        'en',
      );
      expect(
        AppLocale.resolveLanguageCode(
          deviceLocales: const [Locale('en', 'GB')],
        ),
        'en',
      );
    });

    test('unrelated locales fall back to the Arabic product default', () {
      expect(
        AppLocale.resolveLanguageCode(
          deviceLocales: const [Locale('fr', 'FR'), Locale('de', 'DE')],
        ),
        'ar',
      );
      expect(
        AppLocale.resolveLanguageCode(deviceLocales: const []),
        'ar',
      );
    });
  });

  test('bootstrap persists nothing and applies stored language', () async {
    stored['sirati_lang'] = 'en';
    await AppLocale.bootstrap(deviceLocales: const [Locale('ar', 'SA')]);
    expect(AppLocale.languageCode.value, 'en');
  });

  test('setLanguage persists ar/en', () async {
    await AppLocale.setLanguage('en');
    expect(stored['sirati_lang'], 'en');
    expect(AppLocale.languageCode.value, 'en');
    await AppLocale.setLanguage('ar');
    expect(stored['sirati_lang'], 'ar');
  });

  testWidgets(
      'toggling locale flips Directionality and preserves in-progress draft text',
      (tester) async {
    AppLocale.resetForTest(language: 'ar');
    final navKey = GlobalKey<NavigatorState>();

    await tester.pumpWidget(
      ValueListenableBuilder<String>(
        valueListenable: AppLocale.languageCode,
        builder: (context, language, _) {
          return MaterialApp(
            navigatorKey: navKey,
            locale: language == 'en'
                ? const Locale('en', 'US')
                : const Locale('ar', 'SA'),
            supportedLocales: const [
              Locale('ar', 'SA'),
              Locale('en', 'US'),
            ],
            localizationsDelegates: const [
              AppLocalizations.delegate,
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            builder: (context, child) {
              return Directionality(
                textDirection:
                    language == 'en' ? TextDirection.ltr : TextDirection.rtl,
                child: child ?? const SizedBox.shrink(),
              );
            },
            home: const Scaffold(
              body: TextField(
                key: Key('draft'),
              ),
            ),
          );
        },
      ),
    );

    const draft = 'مسودة مهندس برمجيات Laravel';
    await tester.enterText(find.byKey(const Key('draft')), draft);
    expect(find.text(draft), findsOneWidget);

    final rtlDirection =
        tester.widget<Directionality>(find.byType(Directionality).first);
    expect(rtlDirection.textDirection, TextDirection.rtl);

    await AppLocale.setLanguage('en');
    await tester.pump();
    await tester.pump();

    expect(find.text(draft), findsOneWidget);
    final ltrDirection =
        tester.widget<Directionality>(find.byType(Directionality).first);
    expect(ltrDirection.textDirection, TextDirection.ltr);
    expect(stored['sirati_lang'], 'en');
  });
}
