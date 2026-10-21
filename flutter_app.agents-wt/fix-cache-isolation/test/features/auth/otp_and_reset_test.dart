import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/core/utils/latin_digits.dart';
import 'package:sirati/features/auth/presentation/forgot_password_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

TextEditingValue _type(String text, {int? max}) =>
    LatinDigitsInputFormatter(maxLength: max).formatEditUpdate(
      TextEditingValue.empty,
      TextEditingValue(
        text: text,
        selection: TextSelection.collapsed(offset: text.length),
      ),
    );

void main() {
  group('LatinDigitsInputFormatter', () {
    test('every digit form becomes ASCII; nothing else survives', () {
      const forms = {
        'ascii': '0123456789',
        'arabic-indic': '٠١٢٣٤٥٦٧٨٩',
        'extended': '۰۱۲۳۴۵۶۷۸۹',
      };
      for (final MapEntry(key: name, value: digits) in forms.entries) {
        expect(_type(digits).text, '0123456789', reason: name);
        expect(_type('a${digits}b -+.').text, '0123456789', reason: name);
      }
      expect(_type('code: ١٢ab٣').text, '123');
      expect(_type('').text, '');
    });

    test('caps at maxLength and keeps the caret at the end', () {
      for (var max = 1; max <= 8; max++) {
        final v = _type('٠١٢٣٤٥٦٧٨٩', max: max);
        expect(v.text.length, max);
        expect(v.selection.baseOffset, max);
      }
    });

    test('pasting a mixed-script code yields the same code', () {
      for (final pasted in [
        '٤٨٢٩١٥',
        '482915',
        '۴۸۲۹۱۵',
        '48 29 15',
        '٤٨-٢٩-١٥'
      ]) {
        expect(_type(pasted, max: 6).text, '482915', reason: pasted);
      }
    });
  });

  group('ForgotPasswordScreen', () {
    const secureStorage =
        MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
    setUp(() {
      AppLocale.languageCode.value = 'en';
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(secureStorage, (_) async => null);
    });
    tearDown(() {
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(secureStorage, null);
    });

    Widget app(ThemeData theme) => MaterialApp(
          theme: theme,
          locale: const Locale('en'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          home: const ForgotPasswordScreen(),
        );

    for (final (mode, theme) in [
      ('light', AppTheme.lightFor(arabic: false)),
      ('dark', AppTheme.darkFor(arabic: false)),
    ]) {
      testWidgets('request step is AA in $mode', (tester) async {
        await tester.pumpWidget(app(theme));
        await tester.pumpAndSettle();
        expectAllTextAa('forgot password $mode');
      });
    }
  });
}
