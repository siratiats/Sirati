import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/parsed_job_title.dart';
import 'package:sirati/features/jobs/presentation/widgets/job_title_display.dart';
import 'package:sirati/shared/theme/app_theme.dart';

void main() {
  group('ParsedJobTitle Invariants & Boundary Conditions', () {
    test('Arabic only returns arabic and null english', () {
      final parsed = ParsedJobTitle.parse('مطور برمجيات أول');
      expect(parsed.arabic, 'مطور برمجيات أول');
      expect(parsed.english, isNull);
      expect(parsed.primaryTitle, 'مطور برمجيات أول');
      expect(parsed.secondaryTitle, isNull);
      expect(parsed.isBilingual, isFalse);
    });

    test('English only returns null arabic and english', () {
      final parsed = ParsedJobTitle.parse('Senior Software Engineer');
      expect(parsed.arabic, isNull);
      expect(parsed.english, 'Senior Software Engineer');
      expect(parsed.primaryTitle, 'Senior Software Engineer');
      expect(parsed.secondaryTitle, isNull);
      expect(parsed.isBilingual, isFalse);
    });

    test('Both present: Arabic outside parentheses, English inside', () {
      final parsed =
          ParsedJobTitle.parse('مطور برمجيات أول (Senior Software Engineer)');
      expect(parsed.arabic, 'مطور برمجيات أول');
      expect(parsed.english, 'Senior Software Engineer');
      expect(parsed.primaryTitle, 'مطور برمجيات أول');
      expect(parsed.secondaryTitle, 'Senior Software Engineer');
      expect(parsed.isBilingual, isTrue);
    });

    test('Both present: English outside parentheses, Arabic inside (reverses to fixed order)',
        () {
      final parsed =
          ParsedJobTitle.parse('Senior Software Engineer (مطور برمجيات أول)');
      expect(parsed.arabic, 'مطور برمجيات أول');
      expect(parsed.english, 'Senior Software Engineer');
      expect(parsed.primaryTitle, 'مطور برمجيات أول');
      expect(parsed.secondaryTitle, 'Senior Software Engineer');
    });

    test('Both present: parenthesized English with trailing Arabic suffix', () {
      final parsed = ParsedJobTitle.parse(
          'مطور تطبيقات فلاتر (Flutter Mobile Developer) - عن بعد');
      expect(parsed.arabic, 'مطور تطبيقات فلاتر - عن بعد');
      expect(parsed.english, 'Flutter Mobile Developer');
      expect(parsed.primaryTitle, 'مطور تطبيقات فلاتر - عن بعد');
      expect(parsed.secondaryTitle, 'Flutter Mobile Developer');
    });

    test('Both present: hyphen separator', () {
      final parsed1 =
          ParsedJobTitle.parse('مهندس شبكات - Network Infrastructure Engineer');
      expect(parsed1.arabic, 'مهندس شبكات');
      expect(parsed1.english, 'Network Infrastructure Engineer');

      // Reverse order in input string must still pin Arabic as primary
      final parsed2 =
          ParsedJobTitle.parse('Network Infrastructure Engineer - مهندس شبكات');
      expect(parsed2.arabic, 'مهندس شبكات');
      expect(parsed2.english, 'Network Infrastructure Engineer');
    });

    test('Both present: pipe and slash separators with technical tokens', () {
      final parsedPipe = ParsedJobTitle.parse('مصمم واجهات | UI/UX Designer');
      expect(parsedPipe.arabic, 'مصمم واجهات');
      expect(parsedPipe.english, 'UI/UX Designer');

      final parsedSlash =
          ParsedJobTitle.parse('محاسب عام / General Accountant');
      expect(parsedSlash.arabic, 'محاسب عام');
      expect(parsedSlash.english, 'General Accountant');
    });

    test('Both present: square brackets', () {
      final parsed = ParsedJobTitle.parse('مدير مشاريع [Project Manager]');
      expect(parsed.arabic, 'مدير مشاريع');
      expect(parsed.english, 'Project Manager');
    });

    test('Both present: arbitrary un-delimited word transition', () {
      final parsed = ParsedJobTitle.parse('مهندس نظم Systems Engineer');
      expect(parsed.arabic, 'مهندس نظم');
      expect(parsed.english, 'Systems Engineer');
    });

    test('Adversarial & boundary cases: empty, null, whitespace, numbers', () {
      expect(ParsedJobTitle.parse(null).primaryTitle, isNull);
      expect(ParsedJobTitle.parse('').primaryTitle, isNull);
      expect(ParsedJobTitle.parse('   ').primaryTitle, isNull);

      final digits = ParsedJobTitle.parse('12345');
      expect(digits.primaryTitle, '12345');
    });
  });

  group('JobTitleDisplay Widget Layout & Fixed Order', () {
    Widget buildSubject(String title, {Widget? trailingBadge}) {
      return MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          body: Center(
            child: SizedBox(
              width: 350,
              child: JobTitleDisplay(
                title: title,
                trailingBadge: trailingBadge,
              ),
            ),
          ),
        ),
      );
    }

    testWidgets('(a) Both titles present: Arabic on top, English below in LTR',
        (tester) async {
      const rawTitle = 'مطور برمجيات أول (Senior Software Engineer)';
      await tester.pumpWidget(buildSubject(rawTitle));

      final arTextFinder = find.text('مطور برمجيات أول');
      final enTextFinder = find.text('Senior Software Engineer');

      expect(arTextFinder, findsOneWidget);
      expect(enTextFinder, findsOneWidget);

      // Verify vertical positioning: Arabic is strictly above English (top-to-bottom)
      final arTop = tester.getTopLeft(arTextFinder).dy;
      final enTop = tester.getTopLeft(enTextFinder).dy;
      expect(arTop, lessThan(enTop),
          reason: 'Arabic title must be rendered above English secondary title');

      // Verify text directions
      final Text arTextWidget = tester.widget(arTextFinder);
      final Text enTextWidget = tester.widget(enTextFinder);
      expect(arTextWidget.textDirection, TextDirection.rtl);
      expect(enTextWidget.textDirection, TextDirection.ltr);
    });

    testWidgets('(a.2) Input reversed: English (Arabic) still renders Arabic on top',
        (tester) async {
      const rawTitle = 'Senior Software Engineer (مطور برمجيات أول)';
      await tester.pumpWidget(buildSubject(rawTitle));

      final arTextFinder = find.text('مطور برمجيات أول');
      final enTextFinder = find.text('Senior Software Engineer');

      expect(arTextFinder, findsOneWidget);
      expect(enTextFinder, findsOneWidget);

      final arTop = tester.getTopLeft(arTextFinder).dy;
      final enTop = tester.getTopLeft(enTextFinder).dy;
      expect(arTop, lessThan(enTop),
          reason:
              'Fixed order invariant: Arabic is always on top even if English was first in input');

      final Text enTextWidget = tester.widget(enTextFinder);
      expect(enTextWidget.textDirection, TextDirection.ltr);
    });

    testWidgets('(b) Arabic only: renders Arabic title on top and no subtitle',
        (tester) async {
      const rawTitle = 'أخصائي توظيف واستقطاب كفاءات';
      await tester.pumpWidget(buildSubject(rawTitle));

      final arTextFinder = find.text(rawTitle);
      expect(arTextFinder, findsOneWidget);

      final Text arTextWidget = tester.widget(arTextFinder);
      expect(arTextWidget.textDirection, TextDirection.rtl);

      // Verify only 1 Text widget is rendered inside JobTitleDisplay
      final allTexts = tester.widgetList<Text>(find.descendant(
        of: find.byType(JobTitleDisplay),
        matching: find.byType(Text),
      ));
      expect(allTexts.length, 1);
    });

    testWidgets('(c) English only: renders English title on top with LTR',
        (tester) async {
      const rawTitle = 'Lead Cloud Solutions Architect';
      await tester.pumpWidget(buildSubject(rawTitle));

      final enTextFinder = find.text(rawTitle);
      expect(enTextFinder, findsOneWidget);

      final Text enTextWidget = tester.widget(enTextFinder);
      expect(enTextWidget.textDirection, TextDirection.ltr);

      // Verify only 1 Text widget is rendered inside JobTitleDisplay
      final allTexts = tester.widgetList<Text>(find.descendant(
        of: find.byType(JobTitleDisplay),
        matching: find.byType(Text),
      ));
      expect(allTexts.length, 1);
    });

    testWidgets('Trailing badge renders beside top primary title',
        (tester) async {
      await tester.pumpWidget(
        buildSubject(
          'مطور أنظمة (Systems Developer)',
          trailingBadge: const Text('NEW_BADGE'),
        ),
      );

      final badgeFinder = find.text('NEW_BADGE');
      final arTextFinder = find.text('مطور أنظمة');
      final enTextFinder = find.text('Systems Developer');

      expect(badgeFinder, findsOneWidget);
      final badgeTop = tester.getCenter(badgeFinder).dy;
      final arCenter = tester.getCenter(arTextFinder).dy;
      final enTop = tester.getTopLeft(enTextFinder).dy;

      // Badge is vertically aligned with the Arabic primary title row, above the English row
      expect((badgeTop - arCenter).abs(), lessThan(10));
      expect(badgeTop, lessThan(enTop));
    });
  });
}
