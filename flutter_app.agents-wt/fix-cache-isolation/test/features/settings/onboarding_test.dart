import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/settings/presentation/onboarding_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

const _titles = {
  'en': ['Welcome to Sirati', 'Instant ATS analysis', 'AI CV in minutes'],
  'ar': ['مرحباً بك في سيرتي', 'تحليل ATS فوري', 'سيرة بالذكاء في دقائق'],
};

void main() {
  const secureStorage =
      MethodChannel('plugins.it_nomads.com/flutter_secure_storage');

  setUp(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(secureStorage, (_) async => null);
  });
  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(secureStorage, null);
  });

  Widget app(String lang, {ThemeData? theme, VoidCallback? onFinished}) {
    AppLocale.languageCode.value = lang;
    return MaterialApp(
      theme: theme ?? AppTheme.lightFor(arabic: lang == 'ar'),
      locale: Locale(lang),
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      home: Scaffold(body: OnboardingScreen(onFinished: onFinished ?? () {})),
    );
  }

  for (final lang in ['en', 'ar']) {
    final rtl = lang == 'ar';

    testWidgets('$lang: swiping toward the reading direction advances',
        (tester) async {
      await tester.pumpWidget(app(lang));
      await tester.pumpAndSettle();
      final titles = _titles[lang]!;
      expect(find.text(titles[0]), findsOneWidget);

      // "Forward" is a leftward swipe in LTR and a rightward swipe in RTL.
      final forward = Offset(rtl ? 500 : -500, 0);
      for (var i = 1; i < titles.length; i++) {
        await tester.fling(find.byType(PageView), forward, 1500);
        await tester.pumpAndSettle();
        expect(find.text(titles[i]), findsOneWidget, reason: 'page $i');
      }
    });

    testWidgets('$lang: the next page enters from the reading-end side',
        (tester) async {
      await tester.pumpWidget(app(lang));
      await tester.pumpAndSettle();
      final pageView = find.byType(PageView);
      final width = tester.getSize(pageView).width;
      // Drag halfway toward "next" and see where page 2 sits.
      await tester.drag(pageView, Offset(rtl ? width / 2 : -width / 2, 0));
      await tester.pump();
      final p0 = tester.getCenter(find.text(_titles[lang]![0])).dx;
      final p1 = tester.getCenter(find.text(_titles[lang]![1])).dx;
      expect(p1 < p0, rtl, reason: 'RTL next page is to the left');
      await tester.pumpAndSettle();
    });
  }

  testWidgets('system Back steps back a page before leaving', (tester) async {
    await tester.pumpWidget(app('en'));
    await tester.pumpAndSettle();
    final titles = _titles['en']!;
    for (var i = 1; i < 3; i++) {
      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();
      expect(find.text(titles[i]), findsOneWidget);
    }
    for (var i = 1; i >= 0; i--) {
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.text(titles[i]), findsOneWidget, reason: 'back to $i');
    }
    final popScope = tester.widget(find.descendant(
      of: find.byType(OnboardingScreen),
      matching: find.byWidgetPredicate((w) => w is PopScope),
    )) as PopScope;
    expect(popScope.canPop, isTrue, reason: 'on page 1 Back leaves normally');
  });

  testWidgets('Skip has a full-size tap target and finishes', (tester) async {
    var finished = 0;
    await tester.pumpWidget(app('en', onFinished: () => finished++));
    await tester.pumpAndSettle();
    final skip = find.ancestor(
      of: find.text('Skip'),
      matching: find.byType(OutlinedButton),
    );
    final size = tester.getSize(skip);
    expect(size.height, greaterThanOrEqualTo(48));
    expect(size.width, greaterThanOrEqualTo(48));
    await tester.tap(skip);
    await tester.pumpAndSettle();
    expect(finished, 1);
  });

  for (final (mode, themeFor) in [
    ('light', AppTheme.lightFor),
    ('dark', AppTheme.darkFor),
  ]) {
    testWidgets('every page meets text AA and graphic 3:1 ($mode)',
        (tester) async {
      await tester.pumpWidget(
        app('en', theme: themeFor(arabic: false)),
      );
      await tester.pumpAndSettle();
      final background = Theme.of(tester.element(find.byType(OnboardingScreen)))
          .scaffoldBackgroundColor;

      for (var page = 0; page < 3; page++) {
        // Only the current page's text is visible.
        final onScreen = find.byWidgetPredicate(
          (w) => w is Text,
          skipOffstage: true,
        );
        expect(onScreen, findsWidgets);
        expectAllTextAa('onboarding $mode page $page');

        // Hero glyph against its circle, and the active dot against the
        // page background (non-text contrast, WCAG 1.4.11).
        for (final icon in tester.widgetList<Icon>(find.byType(Icon))) {
          final element = tester.element(find.byWidget(icon));
          final fill = paintedBackgroundsOf(element);
          for (final bg in fill) {
            if (icon.color == null) continue;
            expect(
                AppContrast.ratio(icon.color!, bg), greaterThanOrEqualTo(3.0),
                reason: '${icon.icon} on page $page ($mode)');
          }
        }
        final dots = tester
            .widgetList<AnimatedContainer>(find.byType(AnimatedContainer))
            .map((c) => (c.decoration as BoxDecoration?)?.color)
            .whereType<Color>()
            .where((c) => c.a == 1)
            .toList();
        expect(dots, isNotEmpty);
        for (final dot in dots) {
          expect(AppContrast.ratio(dot, background), greaterThanOrEqualTo(3.0),
              reason: 'active dot on page $page ($mode)');
        }

        if (page < 2) {
          await tester.tap(find.text('Next'));
          await tester.pumpAndSettle();
        }
      }
    });
  }
}
