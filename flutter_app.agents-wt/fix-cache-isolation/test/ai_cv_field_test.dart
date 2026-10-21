import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/services/cv_api_service.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/ai_cv_field.dart';
import 'package:sirati/shared/widgets/form_fields.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

void main() {
  testWidgets(
      'enhance action is disabled when text is under more than a line (single short line)',
      (tester) async {
    final controller = TextEditingController(text: 'short');
    addTearDown(controller.dispose);

    await tester.pumpWidget(_testApp(
      AiCvField(
        field: 'skills',
        controller: controller,
        english: true,
        isLoading: false,
        helperText: 'Example',
        onEnhance: () {},
        onDismissResult: () {},
        child: AppTextFormField(controller: controller),
      ),
    ));

    final button =
        tester.widget<SubmitButton>(find.byKey(const Key('enhance_skills')));
    expect(button.onPressed, isNull);

    // 11 characters - still single short line -> disabled
    controller.text = 'Laravel PHP';
    await tester.pump();
    final stillDisabled =
        tester.widget<SubmitButton>(find.byKey(const Key('enhance_skills')));
    expect(stillDisabled.onPressed, isNull);

    // 26 characters - still a single line -> disabled
    controller.text = 'Software Engineer at Corp';
    await tester.pump();
    final alsoDisabled =
        tester.widget<SubmitButton>(find.byKey(const Key('enhance_skills')));
    expect(alsoDisabled.onPressed, isNull);

    // 51 characters - exceeds line threshold -> enabled
    controller.text = 'Laravel, PHP, MySQL, REST APIs, Docker, Git, CI/CD';
    await tester.pump();
    final enabled =
        tester.widget<SubmitButton>(find.byKey(const Key('enhance_skills')));
    expect(enabled.onPressed, isNotNull);

    // Multi-line text with newline and meaningful length -> enabled
    controller.text = 'Flutter Developer\nBuilding iOS and Android apps';
    await tester.pump();
    final multilineEnabled =
        tester.widget<SubmitButton>(find.byKey(const Key('enhance_skills')));
    expect(multilineEnabled.onPressed, isNotNull);
  });

  for (final minimum in [null, 80]) {
    testWidgets('a disabled Enhance always says why (minimum: $minimum)',
        (tester) async {
      final controller = TextEditingController();
      addTearDown(controller.dispose);
      await tester.pumpWidget(_testApp(AiCvField(
        field: 'f',
        controller: controller,
        english: true,
        isLoading: false,
        minimumCharacters: minimum,
        helperText: 'Example',
        onEnhance: () {},
        onDismissResult: () {},
        child: AppTextFormField(controller: controller),
      )));
      for (final text in [
        '',
        '   ',
        'short',
        'a\nb',
        'x' * 39,
        'x' * 40,
        'x' * 79,
        'x' * 80,
        'Line one here\nLine two here',
      ]) {
        controller.text = text;
        await tester.pump();
        final disabled = tester
                .widget<SubmitButton>(find.byKey(const Key('enhance_f')))
                .onPressed ==
            null;
        expect(find.byKey(const Key('enhance_hint_f')),
            disabled ? findsOneWidget : findsNothing,
            reason: '"$text" (disabled: $disabled)');
      }
    });
  }

  testWidgets('loading overlay appears and missing facts render prominently',
      (tester) async {
    final controller = TextEditingController(text: 'Laravel and PHP');
    addTearDown(controller.dispose);

    await tester.pumpWidget(_testApp(
      AiCvField(
        field: 'skills',
        controller: controller,
        english: true,
        isLoading: true,
        helperText: 'Example',
        result: const {
          'changes_made': ['Grouped technical skills'],
          'missing_facts': ['Add years of experience'],
          'ats_keywords_added': ['Laravel'],
          'unverified_claims': [
            {'text': 'Acme Corp', 'kind': 'employer'},
          ],
        },
        onEnhance: () {},
        onDismissResult: () {},
        child: AppTextFormField(controller: controller),
      ),
    ));

    expect(find.byType(CircularProgressIndicator), findsWidgets);
    expect(find.byKey(const Key('missing_facts_skills')), findsOneWidget);
    expect(find.text('Add years of experience'), findsOneWidget);
    expect(find.byKey(const Key('unverified_claims_skills')), findsOneWidget);
    expect(find.text('Acme Corp'), findsOneWidget);
  });

  testWidgets('undo restores exact pre-enhance text', (tester) async {
    final api = _FakeCvApiService();
    await _openSkillsStep(tester, api);
    const original = 'Laravel, PHP, MySQL, REST APIs, Docker, Git, CI/CD';
    await _addSkills(tester, original);
    await _tapEnhance(tester, 'skills');

    api.complete('skills', {
      'enhanced_text': 'Laravel, PHP, REST APIs, Docker, Git, PostgreSQL',
      'changes_made': ['Improved structure'],
      'missing_facts': ['Add years of experience'],
      'ats_keywords_added': ['REST API'],
    });
    await tester.pumpAndSettle();
    expect(_skillsText(tester),
        'Laravel, PHP, REST APIs, Docker, Git, PostgreSQL');

    await tester.tap(find.text('Undo'));
    await tester.pump();
    expect(_skillsText(tester), original);
  });

  testWidgets('superseded field requests are discarded', (tester) async {
    final api = _FakeCvApiService();
    await _openSkillsStep(tester, api);
    final summary =
        _fieldWithHint('Briefly describe your experience and achievements...');
    const skillsText = 'Laravel, PHP, MySQL, REST APIs, Docker, Git, CI/CD';
    const summaryText =
        'Experienced backend developer focused on building scalable REST APIs and microservices architecture';
    await _addSkills(tester, skillsText);
    await tester.enterText(summary, summaryText);

    await _tapEnhance(tester, 'skills');
    await _tapEnhance(tester, 'summary');

    api.complete('skills', _result('stale skills response'));
    await tester.pump();
    expect(_skillsText(tester), skillsText);

    api.complete('summary', _result('current summary response'));
    await tester.pumpAndSettle();
    expect(tester.widget<TextField>(summary).controller!.text,
        'current summary response');
  });

  testWidgets('overlay clears when enhancement fails', (tester) async {
    final api = _FakeCvApiService();
    await _openSkillsStep(tester, api);
    await _addSkills(
        tester, 'Laravel, PHP, MySQL, REST APIs, Docker, Git, CI/CD');
    await _tapEnhance(tester, 'skills');
    expect(find.byType(CircularProgressIndicator), findsWidgets);

    api.fail('skills');
    await tester.pumpAndSettle();
    expect(find.byType(CircularProgressIndicator), findsNothing);
  });

  testWidgets('skills are required, and entered in one place only',
      (tester) async {
    await _openSkillsStep(tester, _FakeCvApiService());
    // A single editable skills input: the chip entry, not a second raw field.
    expect(_fieldWithHint('PHP, Laravel, API, SQL, Git, Agile, Docker'),
        findsNothing);

    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    expect(find.text('Core skills are required.'), findsOneWidget);
    expect(find.text('Skills & Summary'), findsOneWidget);

    await _addSkills(tester, 'Flutter');
    await tester.pumpAndSettle();
    expect(find.text('Core skills are required.'), findsNothing);
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    expect(find.text('Work Experience'), findsOneWidget);
  });

  group('AiCvField.isSufficientForEnhancement invariant tests', () {
    test('returns false for empty, blank or whitespace-only text', () {
      expect(AiCvField.isSufficientForEnhancement(''), isFalse);
      expect(AiCvField.isSufficientForEnhancement('   '), isFalse);
      expect(AiCvField.isSufficientForEnhancement('\n\n\t '), isFalse);
    });

    test('returns false for single short words or phrases under a line', () {
      expect(AiCvField.isSufficientForEnhancement('Developer'), isFalse);
      expect(AiCvField.isSufficientForEnhancement('مبرمج'), isFalse);
      expect(AiCvField.isSufficientForEnhancement('Laravel PHP'), isFalse);
      expect(
          AiCvField.isSufficientForEnhancement('مطور تطبيقات فلاتر'), isFalse);
      expect(AiCvField.isSufficientForEnhancement('Senior Software Engineer'),
          isFalse);
      expect(
          AiCvField.isSufficientForEnhancement(
              '123456789012345678901234567890123456789'),
          isFalse); // 39 chars
    });

    test(
        'returns true for text that spans more than a single line (>= 40 chars)',
        () {
      expect(
          AiCvField.isSufficientForEnhancement(
              '1234567890123456789012345678901234567890'),
          isTrue); // 40 chars
      expect(
          AiCvField.isSufficientForEnhancement(
              'Laravel, PHP, MySQL, REST APIs, Docker, Git, CI/CD'),
          isTrue);
      expect(
          AiCvField.isSufficientForEnhancement(
              'مطور واجهات وتطبيقات بخبرة تزيد عن 3 سنوات في بناء الأنظمة السحابية'),
          isTrue);
    });

    test('returns true for multi-line text with explicit line breaks', () {
      expect(
          AiCvField.isSufficientForEnhancement(
              'Backend Developer\nBuilding cloud APIs with high throughput'),
          isTrue);
      expect(
          AiCvField.isSufficientForEnhancement(
              'مطور فلاتر\nبناء وتطوير تطبيقات الهواتف الذكية'),
          isTrue);
    });

    test('rejects adversarial newlines with insufficient text', () {
      expect(AiCvField.isSufficientForEnhancement('a\nb'), isFalse);
      expect(AiCvField.isSufficientForEnhancement('dev\nphp'), isFalse);
      expect(AiCvField.isSufficientForEnhancement(' \n \n '), isFalse);
    });

    test('strictly obeys customMinimum when specified', () {
      const expMin = 80;
      expect(
          AiCvField.isSufficientForEnhancement(
              'Laravel, PHP, MySQL, REST APIs, Docker, Git, CI/CD',
              customMinimum: expMin),
          isFalse);
      expect(
          AiCvField.isSufficientForEnhancement(
              'Backend Developer at Global Tech (2021-2024): Designed and built resilient microservices handling over 5M daily requests.',
              customMinimum: expMin),
          isTrue);
    });
  });
}

Widget _testApp(Widget home) => MaterialApp(
      locale: const Locale('en'),
      theme: AppTheme.light,
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      home: home is CvGeneratorScreen
          ? home
          : Scaffold(body: SingleChildScrollView(child: home)),
    );

Future<void> _tapEnhance(WidgetTester tester, String field) async {
  tester.testTextInput.hide();
  await tester.pump();
  final button = tester.widget<SubmitButton>(find.byKey(Key('enhance_$field')));
  expect(button.onPressed, isNotNull);
  button.onPressed!();
  await tester.pump();
}

Future<void> _openSkillsStep(
  WidgetTester tester,
  _FakeCvApiService api,
) async {
  AppLocale.languageCode.value = 'en';
  await tester.pumpWidget(ProviderScope(
    overrides: [cvApiServiceProvider.overrideWithValue(api)],
    child: _testApp(const CvGeneratorScreen()),
  ));
  await tester.pump();
  await tester.enterText(_fieldWithHint('Salem Sayer'), 'Salem Sayer');
  await tester.enterText(
      _fieldWithHint('salem@example.com'), 'salem@example.com');
  await tester.enterText(
      _fieldWithHint('+966 5X XXX XXXX'), '+966 59 189 0300');
  await tester.enterText(
      _fieldWithHint('Laravel Backend Developer'), 'Backend Developer');
  await tester.tap(find.text('Next'));
  await tester.pumpAndSettle();
  expect(find.text('Skills & Summary'), findsOneWidget);
}

/// Skills are entered through the chip editor, the only skills input.
Future<void> _addSkills(WidgetTester tester, String csv) async {
  await tester.enterText(_fieldWithHint('Type a skill and press + ...'), csv);
  await tester.tap(find.widgetWithText(FilledButton, 'Add'));
  await tester.pump();
}

/// The skills as the chips show them, in order.
String _skillsText(WidgetTester tester) {
  final chips = <String>[];
  for (var i = 0;; i++) {
    final chip = find.byKey(ValueKey('selected_skill_$i'));
    if (chip.evaluate().isEmpty) break;
    chips.add(tester
        .widget<Text>(find.descendant(of: chip, matching: find.byType(Text)))
        .data!);
  }
  return chips.join(', ');
}

Finder _fieldWithHint(String hint) => find.byWidgetPredicate(
      (widget) => widget is TextField && widget.decoration?.hintText == hint,
    );

Map<String, dynamic> _result(String text) => {
      'enhanced_text': text,
      'changes_made': <String>[],
      'missing_facts': <String>[],
      'ats_keywords_added': <String>[],
      'unverified_claims': <Map<String, String>>[],
    };

class _FakeCvApiService extends CvApiService {
  final Map<String, Completer<Map<String, dynamic>>> _requests = {};

  @override
  Future<Map<String, dynamic>> enhanceCvField({
    required String field,
    required String draft,
    required String jobTitle,
    required String language,
  }) {
    return (_requests[field] = Completer<Map<String, dynamic>>()).future;
  }

  void complete(String field, Map<String, dynamic> result) {
    _requests[field]!.complete(result);
  }

  void fail(String field) {
    _requests[field]!.completeError(
      const ApiException('Request failed', type: ApiErrorType.server),
    );
  }
}
