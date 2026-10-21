import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/shared/widgets/components/app_input.dart';
import 'package:sirati/shared/widgets/form_fields.dart';

void main() {
  group('SIRATI-63 Per-Field Text Direction Dynamic Detection', () {
    for (final locale in [const Locale('ar'), const Locale('en')]) {
      final ambientDirection =
          locale.languageCode == 'ar' ? TextDirection.rtl : TextDirection.ltr;

      testWidgets(
          'AppTextFormField: in Locale(${locale.languageCode}), empty field uses ambient direction, typing Latin switches to LTR, typing Arabic switches to RTL',
          (tester) async {
        final controller = TextEditingController();

        await tester.pumpWidget(
          MaterialApp(
            locale: locale,
            supportedLocales: const [Locale('ar'), Locale('en')],
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            home: Scaffold(
              body: AppTextFormField(
                controller: controller,
                hintText: 'Job Title',
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();

        // 1. Empty field: falls back to ambient locale direction
        TextField textField = tester.widget(find.byType(TextField));
        expect(textField.textDirection, ambientDirection,
            reason: 'Empty field must fall back to ambient directionality');

        // 2. Entering English: Software Engineer -> yields TextDirection.ltr
        await tester.enterText(find.byType(TextField), 'Software Engineer');
        await tester.pump();

        textField = tester.widget(find.byType(TextField));
        expect(textField.textDirection, TextDirection.ltr,
            reason: 'Latin input must yield TextDirection.ltr regardless of locale');

        // 3. Entering Arabic: مهندس برمجيات -> yields TextDirection.rtl
        await tester.enterText(find.byType(TextField), 'مهندس برمجيات');
        await tester.pump();

        textField = tester.widget(find.byType(TextField));
        expect(textField.textDirection, TextDirection.rtl,
            reason: 'Arabic input must yield TextDirection.rtl regardless of locale');

        // 4. Clearing text: falls back to ambient locale direction
        await tester.enterText(find.byType(TextField), '');
        await tester.pump();

        textField = tester.widget(find.byType(TextField));
        expect(textField.textDirection, ambientDirection,
            reason: 'Clearing text must restore ambient direction');
      });

      testWidgets(
          'AppInput: in Locale(${locale.languageCode}), empty field uses ambient direction, typing Latin switches to LTR, typing Arabic switches to RTL',
          (tester) async {
        final controller = TextEditingController();

        await tester.pumpWidget(
          MaterialApp(
            locale: locale,
            supportedLocales: const [Locale('ar'), Locale('en')],
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            home: Scaffold(
              body: AppInput(
                controller: controller,
                hint: 'Job Title',
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();

        // 1. Empty field: falls back to ambient locale direction
        TextField textField = tester.widget(find.byType(TextField));
        expect(textField.textDirection, ambientDirection,
            reason: 'Empty field in AppInput must fall back to ambient direction');

        // 2. Entering English: Software Engineer -> yields TextDirection.ltr
        await tester.enterText(find.byType(TextField), 'Software Engineer');
        await tester.pump();

        textField = tester.widget(find.byType(TextField));
        expect(textField.textDirection, TextDirection.ltr,
            reason: 'Latin input in AppInput must yield TextDirection.ltr');

        // 3. Entering Arabic: مهندس برمجيات -> yields TextDirection.rtl
        await tester.enterText(find.byType(TextField), 'مهندس برمجيات');
        await tester.pump();

        textField = tester.widget(find.byType(TextField));
        expect(textField.textDirection, TextDirection.rtl,
            reason: 'Arabic input in AppInput must yield TextDirection.rtl');
      });
    }
  });
}
