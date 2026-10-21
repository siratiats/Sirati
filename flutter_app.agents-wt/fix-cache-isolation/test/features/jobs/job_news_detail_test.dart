import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/core/routing/app_router.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/app/sirati_route_table.dart';
import 'package:sirati/features/jobs/domain/job_news_feed.dart';
import 'package:sirati/features/jobs/presentation/job_news_detail_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/cv_target_job.dart';
import 'package:sirati/shared/models/job_news.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

JobNews _job({
  List<String> skills = const ['PHP', 'Laravel', 'JavaScript'],
  List<String> matched = const ['php', 'Java'],
  int? score = 86,
}) =>
    JobNews.fromJson({
      'id': 1,
      'title': 'Backend Developer',
      'body': 'Build APIs with Laravel.',
      'category': 'tech',
      'is_verified': true,
      'is_tamheer': true,
      'skills_required': skills,
      'matched_skills': matched,
      'match_score': score,
      'match_reasons': ['Title matches your profile'],
      'company': 'Acme',
      'action_url': 'https://acme.example/jobs/1',
    });

void main() {
  group('isSkillMatched', () {
    test('case, spacing and exact phrase', () {
      for (final (skill, matched) in [
        ('PHP', 'php'),
        ('Node  JS', 'node js'),
        (' Laravel ', 'LARAVEL'),
        ('إدارة المشاريع', 'إدارة المشاريع'),
      ]) {
        expect(isSkillMatched(skill, [matched]), isTrue,
            reason: '$skill/$matched');
      }
    });

    test('a matched phrase counts inside a longer skill only as whole words',
        () {
      expect(isSkillMatched('React Native', ['React']), isTrue);
      expect(isSkillMatched('Microsoft Excel (Advanced)', ['excel']), isTrue);
      expect(isSkillMatched('C++ / C#', ['C#']), isTrue);
      expect(
          isSkillMatched('إدارة المشاريع الكبرى', ['إدارة المشاريع']), isTrue);

      expect(isSkillMatched('JavaScript', ['Java']), isFalse);
      expect(isSkillMatched('Reactive programming', ['React']), isFalse);
      expect(isSkillMatched('Excellent communication', ['Excel']), isFalse);
      expect(isSkillMatched('المشاريعية', ['المشاريع']), isFalse);
    });

    test('blank entries never match anything', () {
      for (final skill in ['PHP', 'Laravel', 'إدارة', '']) {
        expect(isSkillMatched(skill, ['', '   ']), isFalse, reason: skill);
      }
      expect(isSkillMatched('', ['php']), isFalse);
    });

    test('regex metacharacters in a skill are literal', () {
      expect(isSkillMatched('C++', ['C++']), isTrue);
      expect(isSkillMatched('Cxx', ['C++']), isFalse);
      expect(isSkillMatched('.NET Core', ['.NET']), isTrue);
      expect(isSkillMatched('XNET Core', ['.NET']), isFalse);
    });
  });

  test('displayMatchScore clamps to 0–100 and keeps null', () {
    expect(displayMatchScore(null), isNull);
    for (final (raw, shown) in [
      (-5, 0),
      (0, 0),
      (57, 57),
      (100, 100),
      (140, 100)
    ]) {
      expect(displayMatchScore(raw), shown);
    }
  });

  group('CvTargetJob', () {
    test('trims and clips to the generator limits', () {
      final job = CvTargetJob(
        title: '  ${'t' * 300}  ',
        description: '  ${'d' * 5000}  ',
      );
      expect(job.title.length, CvTargetJob.titleMax);
      expect(job.description.length, CvTargetJob.descriptionMax);
      final short = CvTargetJob(title: ' Dev ', description: ' JD ');
      expect((short.title, short.description), ('Dev', 'JD'));
    });

    test('never cuts a surrogate pair in half', () {
      // 😀 is two UTF-16 units; place one across the cut point.
      final title = '${'a' * (CvTargetJob.titleMax - 1)}😀tail';
      final clipped = CvTargetJob(title: title, description: '').title;
      expect(clipped.length, lessThanOrEqualTo(CvTargetJob.titleMax));
      final last = clipped.codeUnitAt(clipped.length - 1);
      expect(last >= 0xD800 && last <= 0xDBFF, isFalse);
    });
  });

  group('JobNewsDetailScreen', () {
    setUp(() => AppLocale.languageCode.value = 'en');

    Widget app(JobNews job, ThemeData theme, {RouteFactory? onRoute}) =>
        MaterialApp(
          theme: theme,
          locale: const Locale('en'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          home: JobNewsDetailScreen(item: job),
          onGenerateRoute: onRoute,
        );

    testWidgets('only truly matched skills are marked', (tester) async {
      await tester.pumpWidget(
        app(_job(), AppTheme.lightFor(arabic: false)),
      );
      await tester.pumpAndSettle();
      Finder check(String skill) => find.descendant(
            of: find.ancestor(of: find.text(skill), matching: find.byType(Row)),
            matching: find.byIcon(Icons.check_rounded),
          );
      expect(check('PHP'), findsOneWidget);
      expect(check('JavaScript'), findsNothing,
          reason: '"Java" must not mark JavaScript');
      expect(check('Laravel'), findsNothing);
    });

    testWidgets('Tailor carries this job into the CV generator',
        (tester) async {
      RouteSettings? pushed;
      await tester.pumpWidget(app(
        _job(),
        AppTheme.lightFor(arabic: false),
        onRoute: (settings) {
          pushed = settings;
          return MaterialPageRoute(builder: (_) => const Scaffold());
        },
      ));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
          find.text('Tailor My CV for this Job'), 200);
      await tester.tap(find.text('Tailor My CV for this Job'));
      await tester.pumpAndSettle();

      expect(pushed?.name, AppRoutes.createCv);
      final job = pushed?.arguments;
      expect(job, isA<CvTargetJob>());
      final target = job! as CvTargetJob;
      expect(target.title, 'Backend Developer');
      expect(target.description, 'Build APIs with Laravel.');
    });

    for (final (mode, theme) in [
      ('light', AppTheme.lightFor(arabic: false)),
      ('dark', AppTheme.darkFor(arabic: false)),
    ]) {
      for (final score in [40, 86]) {
        testWidgets('all text is AA in $mode (match $score)', (tester) async {
          tester.view.physicalSize = const Size(1080, 3600);
          tester.view.devicePixelRatio = 1;
          addTearDown(tester.view.reset);
          await tester.pumpWidget(app(_job(score: score), theme));
          await tester.pumpAndSettle();
          expectAllTextAa('job detail $mode $score');
        });
      }
    }
  });

  testWidgets('create-cv route pre-fills the generator from the job',
      (tester) async {
    AppLocale.languageCode.value = 'en';
    SharedPreferences.setMockInitialValues({});
    const storage =
        MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(storage, (_) async => null);
    addTearDown(() => TestDefaultBinaryMessengerBinding
        .instance.defaultBinaryMessenger
        .setMockMethodCallHandler(storage, null));
    registerSiratiRouteWidgets();

    final navigatorKey = GlobalKey<NavigatorState>();
    await tester.pumpWidget(ProviderScope(
      child: MaterialApp(
        navigatorKey: navigatorKey,
        theme: AppTheme.lightFor(arabic: false),
        locale: const Locale('en'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        onGenerateRoute: AppRouter.onGenerateRoute,
        home: const Scaffold(),
      ),
    ));
    unawaited(navigatorKey.currentState!.pushNamed(
      AppRoutes.createCv,
      arguments: CvTargetJob(
        title: 'Data Analyst',
        description: 'SQL, dashboards and stakeholder reporting.',
      ),
    ));
    await tester.pumpAndSettle();

    final titleField = find.byWidgetPredicate(
        (w) => w is TextField && w.controller?.text == 'Data Analyst');
    expect(titleField, findsOneWidget);
  });
}
