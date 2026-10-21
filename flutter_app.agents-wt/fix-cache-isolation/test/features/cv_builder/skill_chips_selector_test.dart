import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/cv_builder/presentation/widgets/skill_chips_selector.dart';
import 'package:sirati/shared/models/job_title.dart';
import 'package:sirati/shared/theme/app_theme.dart';

Widget _wrap(Widget child) {
  return MaterialApp(
    theme: AppTheme.light,
    home: Scaffold(
      body: SingleChildScrollView(child: child),
    ),
  );
}

void main() {
  testWidgets(
      'SkillChipsSelector parses initial comma-separated skills and renders chips',
      (tester) async {
    final controller = TextEditingController(text: 'Flutter, Dart, Firebase');
    addTearDown(controller.dispose);

    await tester.pumpWidget(_wrap(
      SkillChipsSelector(
        controller: controller,
        targetJobTitle: 'Mobile Developer',
        english: true,
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Flutter'), findsWidgets);
    expect(find.text('Dart'), findsWidgets);
    expect(find.text('Firebase'), findsWidgets);
    expect(find.text('Selected Skills (3)'), findsOneWidget);
  });

  testWidgets('tapping delete icon on chip removes it and updates controller',
      (tester) async {
    final controller = TextEditingController(text: 'Flutter, Dart, Firebase');
    addTearDown(controller.dispose);

    await tester.pumpWidget(_wrap(
      SkillChipsSelector(
        controller: controller,
        targetJobTitle: 'Mobile Developer',
        english: true,
      ),
    ));
    await tester.pumpAndSettle();

    // Tap delete on Flutter (first chip)
    final closeIcon = find.descendant(
      of: find.byKey(const ValueKey('selected_skill_0')),
      matching: find.byIcon(Icons.close_rounded),
    );
    await tester.tap(closeIcon);
    await tester.pumpAndSettle();

    expect(controller.text, 'Dart, Firebase');
    expect(find.text('Selected Skills (2)'), findsOneWidget);
  });

  testWidgets('adding custom skill updates chips and controller',
      (tester) async {
    final controller = TextEditingController(text: 'Flutter');
    addTearDown(controller.dispose);

    await tester.pumpWidget(_wrap(
      SkillChipsSelector(
        controller: controller,
        targetJobTitle: 'Mobile Developer',
        english: true,
      ),
    ));
    await tester.pumpAndSettle();

    final inputField = find.byType(TextField);
    await tester.enterText(inputField, 'Docker');
    await tester.tap(find.widgetWithText(FilledButton, 'Add'));
    await tester.pumpAndSettle();

    expect(controller.text, 'Flutter, Docker');
    expect(find.text('Selected Skills (2)'), findsOneWidget);
    expect(find.text('Docker'), findsWidgets);
  });

  testWidgets('tapping suggested skill chip adds it to selected skills',
      (tester) async {
    final controller = TextEditingController(text: 'Flutter');
    addTearDown(controller.dispose);

    const jobTitle = JobTitle(
      id: 1,
      slug: 'flutter-developer',
      nameAr: 'مطور فلاتر',
      nameEn: 'Flutter Developer',
      category: 'software',
      keywords: ['Dart', 'Bloc', 'REST APIs'],
      sortOrder: 1,
    );

    await tester.pumpWidget(_wrap(
      SkillChipsSelector(
        controller: controller,
        targetJobTitle: 'Flutter Developer',
        english: true,
        allJobTitles: const [jobTitle],
      ),
    ));
    await tester.pumpAndSettle();

    // The suggested chip for Bloc should exist
    final suggestedBloc = find.byKey(const ValueKey('suggested_skill_Bloc'));
    expect(suggestedBloc, findsOneWidget);

    await tester.tap(suggestedBloc);
    await tester.pumpAndSettle();

    expect(controller.text, 'Flutter, Bloc');
    expect(find.text('Selected Skills (2)'), findsOneWidget);
  });

  testWidgets('external controller update reflects in chips (two-way sync)',
      (tester) async {
    final controller = TextEditingController(text: 'Initial');
    addTearDown(controller.dispose);

    await tester.pumpWidget(_wrap(
      SkillChipsSelector(
        controller: controller,
        targetJobTitle: 'Developer',
        english: true,
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Initial'), findsOneWidget);

    // Simulate AI enhancement or external text modification
    controller.text = 'Enhanced A, Enhanced B';
    await tester.pumpAndSettle();

    expect(find.text('Enhanced A'), findsOneWidget);
    expect(find.text('Enhanced B'), findsOneWidget);
    expect(find.text('Selected Skills (2)'), findsOneWidget);
  });

  group('suggestions follow the target stack', () {
    Future<Set<String>> suggestionsFor(WidgetTester tester, String title,
        {bool english = true}) async {
      final controller = TextEditingController();
      addTearDown(controller.dispose);
      await tester.pumpWidget(_wrap(SkillChipsSelector(
        controller: controller,
        targetJobTitle: title,
        english: english,
      )));
      await tester.pumpAndSettle();
      return tester
          .widgetList<ActionChip>(find.byType(ActionChip))
          .map((c) => (c.key! as ValueKey<String>)
              .value
              .replaceFirst('suggested_skill_', ''))
          .toSet();
    }

    const backendOnly = {'Laravel', 'PHP', 'Node.js', 'PostgreSQL', 'Redis'};

    for (final title in [
      'Senior Flutter Developer',
      'flutter dev',
      'Mobile Engineer',
      'Android Developer',
      'iOS Developer',
      'مطور فلاتر',
      'مطور تطبيقات جوال',
      'مهندس موبايل أول',
    ]) {
      testWidgets('mobile: $title', (tester) async {
        final got = await suggestionsFor(tester, title,
            english: !title.contains(RegExp('[؀-ۿ]')));
        expect(got, contains('Flutter'), reason: title);
        expect(got.intersection(backendOnly), isEmpty, reason: title);
      });
    }

    for (final title in [
      'Laravel Backend Developer',
      'Back-end Engineer',
      'PHP Developer',
      'مطور خلفي',
    ]) {
      testWidgets('backend: $title', (tester) async {
        final got = await suggestionsFor(tester, title);
        expect(got, contains('Laravel'), reason: title);
        expect(got, isNot(contains('Flutter')), reason: title);
      });
    }

    testWidgets('selected skills are never suggested again', (tester) async {
      final controller = TextEditingController(text: 'Flutter, dart');
      addTearDown(controller.dispose);
      await tester.pumpWidget(_wrap(SkillChipsSelector(
        controller: controller,
        targetJobTitle: 'Flutter Developer',
        english: true,
      )));
      await tester.pumpAndSettle();
      expect(
          find.byKey(const ValueKey('suggested_skill_Flutter')), findsNothing);
      expect(find.byKey(const ValueKey('suggested_skill_Dart')), findsNothing);
    });
  });

  testWidgets('onChanged fires for every user edit', (tester) async {
    final controller = TextEditingController(text: 'Flutter, Dart');
    addTearDown(controller.dispose);
    var calls = 0;
    await tester.pumpWidget(_wrap(SkillChipsSelector(
      controller: controller,
      targetJobTitle: 'Flutter Developer',
      english: true,
      onChanged: () => calls++,
    )));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('suggested_skill_Firebase')));
    await tester.pump();
    await tester.tap(find.descendant(
      of: find.byKey(const ValueKey('selected_skill_0')),
      matching: find.byIcon(Icons.close_rounded),
    ));
    await tester.pump();
    await tester.tap(find.text('Clear all'));
    await tester.pump();
    expect(calls, 3);
    expect(controller.text, isEmpty);
  });
}
