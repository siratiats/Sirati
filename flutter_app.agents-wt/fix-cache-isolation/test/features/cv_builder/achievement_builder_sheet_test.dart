import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/cv_builder/presentation/widgets/achievement_builder_sheet.dart';
import 'package:sirati/shared/theme/app_theme.dart';

Widget _wrap(Widget child) {
  return MaterialApp(
    theme: AppTheme.light,
    home: Scaffold(body: child),
  );
}

void main() {
  testWidgets('AchievementBuilderSheet generates XYZ formula bullet point',
      (tester) async {
    tester.view.physicalSize = const Size(800, 1200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    String? insertedBullet;

    await tester.pumpWidget(_wrap(
      Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => AchievementBuilderSheet.show(
            context: context,
            english: true,
            onInsert: (b) => insertedBullet = b,
          ),
          child: const Text('Open Sheet'),
        ),
      ),
    ));

    await tester.tap(find.text('Open Sheet'));
    await tester.pumpAndSettle();

    expect(find.text('XYZ Achievement Builder'), findsOneWidget);

    // Find the 3 text fields
    final fields = find.byType(TextField);
    expect(fields, findsNWidgets(3));

    // Field 1: Action
    await tester.enterText(fields.at(0), 'Developed REST APIs');
    // Field 2: Metric
    await tester.enterText(fields.at(1), 'by 35%');
    // Field 3: Method
    await tester.enterText(fields.at(2), 'using Redis caching');
    await tester.pump();

    // Verify live preview is rendered
    expect(find.text('Live Achievement Preview:'), findsOneWidget);
    expect(
      find.text('• Developed REST APIs, by 35% using Redis caching.'),
      findsOneWidget,
    );

    // Tap insert
    await tester.tap(find.text('Insert into Experience'));
    await tester.pumpAndSettle();

    // Verify sheet closed and callback received the formatted string
    expect(find.text('XYZ Achievement Builder'), findsNothing);
    expect(
        insertedBullet, '• Developed REST APIs, by 35% using Redis caching.');
  });

  testWidgets('AchievementBuilderSheet applies quick preset on tap',
      (tester) async {
    tester.view.physicalSize = const Size(800, 1200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    String? insertedBullet;

    await tester.pumpWidget(_wrap(
      Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => AchievementBuilderSheet.show(
            context: context,
            english: false,
            onInsert: (b) => insertedBullet = b,
          ),
          child: const Text('Open Sheet'),
        ),
      ),
    ));

    await tester.tap(find.text('Open Sheet'));
    await tester.pumpAndSettle();

    expect(find.text('صانع الإنجازات المقاسة (معادلة XYZ)'), findsOneWidget);

    // Tap "تحسين الأداء التقني" preset
    await tester.tap(find.text('تحسين الأداء التقني'));
    await tester.pump();

    expect(find.text('معاينة الصياغة النهائية:'), findsOneWidget);

    // Tap insert
    await tester.tap(find.text('إدراج في الخبرة'));
    await tester.pumpAndSettle();

    expect(insertedBullet, isNotNull);
    expect(insertedBullet!.contains('بنسبة 35%'), isTrue);
    expect(insertedBullet!.startsWith('•'), isTrue);
  });

  group('starter verbs never stack', () {
    const rests = [
      '',
      'REST APIs and payment gateway',
      'واجهات برمجة التطبيقات'
    ];
    for (final (lang, verbs) in [
      ('en', achievementVerbsEn),
      ('ar', achievementVerbsAr),
    ]) {
      test('any tap sequence leaves exactly the last verb ($lang)', () {
        for (final rest in rests) {
          for (final first in verbs) {
            for (final second in verbs) {
              var text = rest;
              // Tap twice, then another: order and repeats must not matter.
              text = applyAchievementVerb(text, first, verbs);
              text = applyAchievementVerb(text, first, verbs);
              text = applyAchievementVerb(text, second, verbs);
              final expected = rest.isEmpty ? second : '$second $rest';
              expect(text, expected, reason: '$first → $second on "$rest"');
            }
          }
        }
      });

      test('a bare verb is not an achievement ($lang)', () {
        for (final v in verbs) {
          expect(isAchievementActionComplete(v, verbs), isFalse);
          expect(isAchievementActionComplete(' $v ', verbs), isFalse);
          expect(isAchievementActionComplete('$v X', verbs), isTrue);
        }
        expect(isAchievementActionComplete('   ', verbs), isFalse);
      });
    }

    test("text that merely starts with a verb's letters is kept", () {
      // "Developed" must not be stripped out of "Developers ...".
      expect(
        applyAchievementVerb(
            'Developers onboarding', 'Optimized', achievementVerbsEn),
        'Optimized Developers onboarding',
      );
    });
  });

  group('appendAchievement', () {
    test('adds each achievement once, whatever the spacing', () {
      const bullet = '• Cut costs by 20%.';
      expect(appendAchievement('', bullet), bullet);
      expect(appendAchievement('  ', ' $bullet '), bullet);
      final once = appendAchievement('Role — Co', bullet)!;
      expect(once, 'Role — Co\n$bullet');
      expect(appendAchievement(once, bullet), isNull);
      expect(appendAchievement('$once\n', '  $bullet'), isNull);
      expect(appendAchievement(once, '• Another.'), '$once\n• Another.');
    });
  });

  testWidgets('tapping verb chips replaces the verb in the field',
      (tester) async {
    tester.view.physicalSize = const Size(800, 1400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(_wrap(Builder(
      builder: (context) => ElevatedButton(
        onPressed: () => AchievementBuilderSheet.show(
            context: context, english: false, onInsert: (_) {}),
        child: const Text('Open Sheet'),
      ),
    )));
    await tester.tap(find.text('Open Sheet'));
    await tester.pumpAndSettle();

    final action = find.byType(TextField).first;
    for (final verb in ['طوّرت', 'طوّرت', 'حسّنت كفاءة']) {
      await tester.tap(find.widgetWithText(ActionChip, verb));
      await tester.pump();
    }
    expect(tester.widget<TextField>(action).controller!.text, 'حسّنت كفاءة');

    // A bare verb cannot be inserted.
    final insert = tester.widget<ButtonStyleButton>(find.ancestor(
      of: find.text('إدراج في الخبرة'),
      matching: find.byWidgetPredicate((w) => w is ButtonStyleButton),
    ));
    expect(insert.onPressed, isNull);
  });
}
