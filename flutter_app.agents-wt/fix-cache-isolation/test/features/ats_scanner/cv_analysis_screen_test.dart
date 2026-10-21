import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/ats_scanner/presentation/cv_analysis/analysis_form_sections.dart';
import 'package:sirati/features/ats_scanner/presentation/cv_analysis_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

Widget _app(ThemeData theme, Widget home) => ProviderScope(
      child: MaterialApp(
        theme: theme,
        locale: const Locale('en'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        home: home,
      ),
    );

void main() {
  setUp(() => AppLocale.languageCode.value = 'en');

  for (final (mode, theme) in [
    ('light', AppTheme.lightFor(arabic: false)),
    ('dark', AppTheme.darkFor(arabic: false)),
  ]) {
    testWidgets('analysis form texts are AA in $mode mode', (tester) async {
      tester.view.physicalSize = const Size(1080, 4000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(_app(theme, const CvAnalysisScreen()));
      await tester.pumpAndSettle();
      expectAllTextAa('analysis form $mode');
    });

    testWidgets('selected-file tile is AA in $mode mode', (tester) async {
      await tester.pumpWidget(_app(
        theme,
        Scaffold(
          body: AppCard(
            child: SelectedFileTile(
              fileName: 'Sara_Backend_CV_2026.pdf',
              onRemove: () {},
            ),
          ),
        ),
      ));
      await tester.pump();
      expectAllTextAa('file tile $mode');
    });
  }

  testWidgets('upload zone is announced as a button', (tester) async {
    final handle = tester.ensureSemantics();
    var taps = 0;
    await tester.pumpWidget(_app(
      AppTheme.lightFor(arabic: false),
      Scaffold(body: UploadDropZone(onTap: () => taps++)),
    ));
    expect(
      tester.getSemantics(find.byType(UploadDropZone)),
      isSemantics(isButton: true, hasTapAction: true),
    );
    await tester.tap(find.byType(UploadDropZone));
    expect(taps, 1);
    handle.dispose();
  });
}
