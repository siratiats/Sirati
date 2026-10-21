import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'harness.dart';
import 'tolerant_golden.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    // Windows baselines vs Codemagic macOS: allow small rasterization noise.
    // Installed here (not only flutter_test_config) so CI always picks it up.
    installTolerantGoldenComparator(
        precisionTolerance: defaultGoldenPrecisionTolerance);

    // Load brand font so goldens match production typography.
    final loader = FontLoader('IBM Plex Sans Arabic')
      ..addFont(rootBundle.load('assets/fonts/IBMPlexSansArabic-Regular.ttf'))
      ..addFont(rootBundle.load('assets/fonts/IBMPlexSansArabic-SemiBold.ttf'))
      ..addFont(rootBundle.load('assets/fonts/IBMPlexSansArabic-Bold.ttf'));
    await loader.load();
  });

  const matrix = <({String tag, ThemeMode theme, TextDirection dir, bool en})>[
    (
      tag: 'light_ltr',
      theme: ThemeMode.light,
      dir: TextDirection.ltr,
      en: true
    ),
    (
      tag: 'light_rtl',
      theme: ThemeMode.light,
      dir: TextDirection.rtl,
      en: false
    ),
    (tag: 'dark_ltr', theme: ThemeMode.dark, dir: TextDirection.ltr, en: true),
    (tag: 'dark_rtl', theme: ThemeMode.dark, dir: TextDirection.rtl, en: false),
  ];

  for (final m in matrix) {
    testWidgets(
      'dashboard cluster ${m.tag}',
      (tester) async {
        await _pumpGolden(
          tester,
          name: 'dashboard_cluster',
          themeMode: m.theme,
          direction: m.dir,
          english: m.en,
          child: GoldenDashboardCluster(english: m.en),
        );
      },
      tags: <String>['golden'],
    );

    testWidgets(
      'submit button states ${m.tag}',
      (tester) async {
        await _pumpGolden(
          tester,
          name: 'submit_button_states',
          themeMode: m.theme,
          direction: m.dir,
          english: m.en,
          child: GoldenSubmitButtonStates(english: m.en),
        );
      },
      tags: <String>['golden'],
    );

    testWidgets(
      'form field states ${m.tag}',
      (tester) async {
        await _pumpGolden(
          tester,
          name: 'form_field_states',
          themeMode: m.theme,
          direction: m.dir,
          english: m.en,
          child: GoldenFormFieldStates(english: m.en),
        );
      },
      tags: <String>['golden'],
    );

    testWidgets(
      'score booster card ${m.tag}',
      (tester) async {
        await _pumpGolden(
          tester,
          name: 'score_booster_card',
          themeMode: m.theme,
          direction: m.dir,
          english: m.en,
          child: GoldenScoreBooster(english: m.en),
        );
      },
      tags: <String>['golden'],
    );

    testWidgets(
      'cv builder screen ${m.tag}',
      (tester) async {
        await _pumpGolden(
          tester,
          name: 'cv_builder_screen',
          themeMode: m.theme,
          direction: m.dir,
          english: m.en,
          child: GoldenCvBuilderScreen(english: m.en),
        );
      },
      tags: <String>['golden'],
    );

    testWidgets(
      'cv live preview screen ${m.tag}',
      (tester) async {
        await _pumpGolden(
          tester,
          name: 'cv_live_preview_screen',
          themeMode: m.theme,
          direction: m.dir,
          english: m.en,
          child: SizedBox(
            height: 640,
            child: GoldenCvLivePreviewScreen(english: m.en),
          ),
        );
      },
      tags: <String>['golden'],
    );

    testWidgets(
      'ats scanner screen ${m.tag}',
      (tester) async {
        await _pumpGolden(
          tester,
          name: 'ats_scanner_screen',
          themeMode: m.theme,
          direction: m.dir,
          english: m.en,
          child: GoldenAtsScannerScreen(english: m.en),
        );
      },
      tags: <String>['golden'],
    );
  }
}

String _tag(ThemeMode theme, TextDirection dir) {
  final t = theme == ThemeMode.dark ? 'dark' : 'light';
  final d = dir == TextDirection.rtl ? 'rtl' : 'ltr';
  return '${t}_$d';
}

Future<void> _pumpGolden(
  WidgetTester tester, {
  required String name,
  required ThemeMode themeMode,
  required TextDirection direction,
  required bool english,
  required Widget child,
}) async {
  await tester.binding.setSurfaceSize(goldenSurfaceSize);
  addTearDown(() async {
    await tester.binding.setSurfaceSize(null);
  });

  await tester.pumpWidget(
    goldenShell(
      themeMode: themeMode,
      textDirection: direction,
      english: english,
      child: SingleChildScrollView(child: child),
    ),
  );
  // Frames for form post-frame focus/blur; avoid pumpAndSettle (spinners).
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 16));
  await tester.pump(const Duration(milliseconds: 16));
  await tester.pump(const Duration(milliseconds: 50));

  await expectLater(
    find.byType(MaterialApp),
    matchesGoldenFile('goldens/${name}_${_tag(themeMode, direction)}.png'),
  );
}
