import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/ats_scanner/presentation/analysis_result_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/cv_analysis.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

/// Score tab with one criterion per criterion band (weak / good / strong).
CvAnalysis _analysis(int score, {String source = 'category'}) =>
    CvAnalysis.fromJson({
      'keyword_source': source,
      'id': 1,
      'target_job_title': 'Backend Developer',
      'score_total': score,
      'grade': 'B',
      'job_match': score,
      'ai_status': 'completed',
      'strengths': ['Clear summary'],
      'weaknesses': [
        {'title': 'Few metrics', 'suggestion': 'Quantify outcomes'},
      ],
      'keywords_found': ['PHP', 'Laravel'],
      'keywords_missing': ['Docker'],
      'quick_wins': ['Add measurable outcomes'],
      'criteria': [
        {'label': 'Structure', 'score': 3, 'max': 10},
        {'label': 'Keywords', 'score': 7, 'max': 10},
        {'label': 'Impact', 'score': 9, 'max': 10},
      ],
    });

void main() {
  setUp(() => AppLocale.languageCode.value = 'en');

  for (final (mode, theme) in [
    ('light', AppTheme.lightFor(arabic: false)),
    ('dark', AppTheme.darkFor(arabic: false)),
  ]) {
    // One score per ScoreBand, plus both edges of each threshold.
    for (final (score, source) in [
      for (final s in [0, 49, 50, 64, 65, 79, 80, 100]) (s, 'category'),
      (72, 'job_description'),
    ]) {
      testWidgets('score tab is AA in $mode at $score ($source)',
          (tester) async {
        tester.view.physicalSize = const Size(1080, 4200);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.reset);
        await tester.pumpWidget(ProviderScope(
          child: MaterialApp(
            theme: theme,
            locale: const Locale('en'),
            supportedLocales: AppLocalizations.supportedLocales,
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            home: AnalysisResultScreen(
              analysis: _analysis(score, source: source),
            ),
          ),
        ));
        await tester.pumpAndSettle();
        expectAllTextAa('analysis result $mode $score $source');

        // Every tab, not just the first.
        final tabs = find.byType(Tab);
        for (var i = 1; i < tabs.evaluate().length; i++) {
          await tester.tap(tabs.at(i));
          await tester.pumpAndSettle();
          expectAllTextAa('analysis result $mode $score $source tab $i');
        }
      });
    }
  }
}
