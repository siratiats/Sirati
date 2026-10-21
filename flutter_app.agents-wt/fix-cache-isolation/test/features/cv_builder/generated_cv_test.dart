import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/cv_builder/application/my_cvs_controller.dart';
import 'package:sirati/features/cv_builder/domain/cv_markdown.dart';
import 'package:sirati/features/cv_builder/presentation/generated_cv_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/ai_status.dart';
import 'package:sirati/shared/models/generated_cv.dart';
import 'package:sirati/shared/services/cv_api_service.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

const _sample = '''
# Sara Ahmed
Email: sara@x.io | +966 50 123 4567

## Experience
**Backend Developer — Acme**
- Built **Laravel** APIs serving 1M requests/day
* Cut p95 latency by 40%
• Mentored 3 engineers
Software Engineer, 2019 - 2023

---
### المهارات
- PHP, Laravel
''';

GeneratedCv _cv({
  String status = AiStatus.completed,
  String markdown = _sample,
  String language = 'en',
  String? aiError,
}) =>
    GeneratedCv.fromJson({
      'id': 7,
      'full_name': 'Sara Ahmed',
      'target_job_title': 'backend developer',
      'language': language,
      'generated_markdown': markdown,
      'ai_status': status,
      'ai_error': aiError,
      'score_total': 72,
      'grade': 'B',
      'email': 'sara@x.io',
    });

void main() {
  group('parseCvMarkdown', () {
    final blocks = parseCvMarkdown(_sample);

    test('no markdown syntax reaches the UI', () {
      for (final b in blocks) {
        expect(b.text, isNot(contains('**')), reason: '$b');
        expect(b.text, isNot(startsWith('#')), reason: '$b');
        expect(b.text, isNot(matches(r'^[-*•]\s')), reason: '$b');
        expect(b.text.trim(), b.text, reason: '$b');
        expect(b.text, isNotEmpty, reason: '$b');
      }
    });

    test('recognises every block kind in order', () {
      expect(blocks, [
        const CvHeading('Sara Ahmed'),
        const CvParagraph('Email: sara@x.io | +966 50 123 4567',
            isContact: true),
        const CvHeading('Experience'),
        const CvStrongLine('Backend Developer — Acme'),
        const CvBullet('Built Laravel APIs serving 1M requests/day'),
        const CvBullet('Cut p95 latency by 40%'),
        const CvBullet('Mentored 3 engineers'),
        // A date range is not contact info; it keeps the ambient direction.
        const CvParagraph('Software Engineer, 2019 - 2023'),
        const CvHeading('المهارات'),
        const CvBullet('PHP, Laravel'),
      ]);
    });

    test('blank, rule-only and CRLF input never yields empty blocks', () {
      for (final md in ['', '   ', '---\n***\n___', '\r\n\r\n', '- \n## \n']) {
        expect(parseCvMarkdown(md), isEmpty, reason: md);
      }
      expect(parseCvMarkdown('a\r\nb'),
          const [CvParagraph('a'), CvParagraph('b')]);
    });
  });

  test('plain-text share has no markdown and keeps every line', () {
    final text = cvPlainText(
      fullName: 'Sara Ahmed',
      headline: 'Backend Developer',
      markdown: _sample,
    );
    expect(text, isNot(contains('**')));
    expect(text, isNot(contains('#')));
    expect(text, isNot(contains('---')));
    for (final b in parseCvMarkdown(_sample)) {
      expect(text, contains(b.text));
    }
    expect(text.split('\n').first, 'Sara Ahmed');
  });

  test('displayHeadline title-cases English only', () {
    expect(displayHeadline('  backend  developer ', language: 'en'),
        'Backend Developer');
    expect(displayHeadline('مطور خلفي', language: 'ar'), 'مطور خلفي');
    expect(displayHeadline('backend dev', language: 'ar'), 'backend dev');
    expect(displayHeadline('   ', language: 'en'), '');
  });

  test('ready-to-send excludes exactly pending and failed', () {
    for (final status in [
      AiStatus.completed,
      AiStatus.notConfigured,
      AiStatus.failed,
      'queued',
      'processing',
    ]) {
      expect(
        isCvReadyToSend(_cv(status: status)),
        !AiStatus.isPending(status) && status != AiStatus.failed,
        reason: status,
      );
    }
  });

  group('resolveCvPdfUrl', () {
    test('falls back to the stored link and never throws', () async {
      final api = _ThrowingApi();
      for (final id in [null, 0, -1, 7]) {
        expect(
          await resolveCvPdfUrl(api,
              id: id, storedUrl: 'https://x/cv.pdf', templateSlug: 'modern'),
          'https://x/cv.pdf?template=modern',
          reason: 'id=$id',
        );
      }
      expect(await resolveCvPdfUrl(api, id: 7, storedUrl: ''), '');
      // Only positive ids are fetched: 7 in the loop, and 7 again above.
      expect(api.fetches, 2);
    });
  });

  group('GeneratedCvScreen', () {
    setUp(() => AppLocale.languageCode.value = 'en');

    Widget app(GeneratedCv cv, ThemeData theme) => ProviderScope(
          child: MaterialApp(
            theme: theme,
            locale: const Locale('en'),
            supportedLocales: AppLocalizations.supportedLocales,
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            home: GeneratedCvScreen(generatedCv: cv),
          ),
        );

    for (final (mode, theme) in [
      ('light', AppTheme.lightFor(arabic: false)),
      ('dark', AppTheme.darkFor(arabic: false)),
    ]) {
      for (final (status, error) in [
        (AiStatus.completed, null),
        (AiStatus.failed, 'provider_timeout'),
      ]) {
        testWidgets('all text is AA in $mode ($status)', (tester) async {
          tester.view.physicalSize = const Size(1080, 4000);
          tester.view.devicePixelRatio = 1;
          addTearDown(tester.view.reset);
          await tester.pumpWidget(
            app(_cv(status: status, aiError: error), theme),
          );
          await tester.pumpAndSettle();
          expectAllTextAa('generated cv $mode $status');
        });
      }
    }

    testWidgets('Share is disabled when there is no content', (tester) async {
      await tester.pumpWidget(
        app(_cv(markdown: '  '), AppTheme.lightFor(arabic: false)),
      );
      await tester.pumpAndSettle();
      final share = tester.widget<ButtonStyleButton>(find.ancestor(
        of: find.text('Share'),
        matching: find.byWidgetPredicate((w) => w is ButtonStyleButton),
      ));
      expect(share.onPressed, isNull);
    });

    testWidgets('an edited CV replaces the one on screen', (tester) async {
      SharedPreferences.setMockInitialValues({});
      await tester.pumpWidget(
        app(_cv(markdown: '## Summary\n- Old line'),
            AppTheme.lightFor(arabic: false)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Old line'), findsOneWidget);

      await tester.tap(find.byTooltip('Edit CV'));
      await tester.pumpAndSettle();
      // The editor saves and hands the updated CV back.
      tester
          .state<NavigatorState>(find.byType(Navigator))
          .pop(_cv(markdown: '## Summary\n- New line'));
      await tester.pumpAndSettle();

      expect(find.text('New line'), findsOneWidget);
      expect(find.text('Old line'), findsNothing);
    });
  });
}

class _ThrowingApi extends CvApiService {
  int fetches = 0;

  @override
  Future<GeneratedCv> getGeneratedCv(int id) async {
    fetches++;
    throw const FormatException('bad link');
  }
}
