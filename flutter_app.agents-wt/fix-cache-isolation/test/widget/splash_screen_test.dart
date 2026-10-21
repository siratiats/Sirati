import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/dashboard/application/session_bootstrap.dart';
import 'package:sirati/features/dashboard/presentation/splash/splash_boot_body.dart';
import 'package:sirati/features/dashboard/presentation/splash/welcome_body.dart';
import 'package:sirati/features/dashboard/presentation/splash_screen.dart';
import 'package:sirati/features/settings/presentation/onboarding_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../support/contrast_probe.dart';

/// Bootstrap whose outcome the test controls; unresolved keeps the boot beat.
SessionBootstrap _bootstrap({
  Future<String?> Function()? readToken,
  bool onboarded = true,
}) =>
    SessionBootstrap(
      readToken: readToken ?? () => Completer<String?>().future,
      fetchUser: () async => null,
      onboardingCompleted: () async => onboarded,
    );

void main() {
  Widget buildSubject({
    ThemeData? theme,
    Locale locale = const Locale('ar'),
    bool disableAnimations = false,
    SessionBootstrap? bootstrap,
  }) {
    return ProviderScope(
      overrides: [
        sessionBootstrapProvider.overrideWithValue(bootstrap ?? _bootstrap()),
      ],
      child: MaterialApp(
        theme: theme ?? AppTheme.light,
        locale: locale,
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        home: MediaQuery(
          data: MediaQueryData(disableAnimations: disableAnimations),
          child: const SplashScreen(),
        ),
      ),
    );
  }

  group('SplashScreen Circular Reveal & Transitions (SIRATI-59)', () {
    testWidgets('Renders centered circular element in light theme (RTL)',
        (tester) async {
      await tester.pumpWidget(buildSubject(theme: AppTheme.light));
      await tester.pump();

      final circleFinder = find.byKey(const ValueKey('splash_circle'));
      expect(circleFinder, findsOneWidget);

      final Container container = tester.widget(circleFinder);
      final box = container.decoration as BoxDecoration;
      expect(box.shape, BoxShape.circle);

      final Size size = tester.getSize(circleFinder);
      expect(size.width, 220);
      expect(size.height, 220);

      final center = tester.getCenter(circleFinder);
      expect(center.dx, closeTo(400, 50));
      expect(center.dy, closeTo(300, 50));
    });

    testWidgets('Renders centered circular element in dark theme (LTR)',
        (tester) async {
      await tester.pumpWidget(
        buildSubject(theme: AppTheme.dark, locale: const Locale('en')),
      );
      await tester.pump();

      final circleFinder = find.byKey(const ValueKey('splash_circle'));
      expect(circleFinder, findsOneWidget);
      final Container container = tester.widget(circleFinder);
      expect((container.decoration as BoxDecoration).shape, BoxShape.circle);
    });

    testWidgets('Honours MediaQuery.disableAnimations fallback',
        (tester) async {
      await tester.pumpWidget(buildSubject(disableAnimations: true));
      await tester.pump();

      final circleFinder = find.byKey(const ValueKey('splash_circle'));
      expect(circleFinder, findsOneWidget);
      final Container container = tester.widget(circleFinder);
      expect((container.decoration as BoxDecoration).shape, BoxShape.circle);
    });

    testWidgets('second splash beat reveals the brand mark in the circle',
        (tester) async {
      await tester.pumpWidget(buildSubject(locale: const Locale('en')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 860));

      expect(find.byKey(const ValueKey('splash_circle')), findsOneWidget);
      expect(find.byType(SiratiMark), findsWidgets);
    });
  });

  group('SplashScreen routing', () {
    for (final onboarded in [true, false]) {
      testWidgets('signed out, onboarded=$onboarded', (tester) async {
        AppLocale.languageCode.value = 'en';
        await tester.pumpWidget(buildSubject(
          locale: const Locale('en'),
          bootstrap: _bootstrap(
            readToken: () async => null,
            onboarded: onboarded,
          ),
        ));
        await tester.pumpAndSettle();
        expect(find.byType(WelcomeBody),
            onboarded ? findsOneWidget : findsNothing);
        expect(
          find.byType(OnboardingScreen),
          onboarded ? findsNothing : findsOneWidget,
        );
      });
    }

    testWidgets('Sign In resolves once even when tapped repeatedly',
        (tester) async {
      AppLocale.languageCode.value = 'en';
      var tokenReads = 0;
      final gate = Completer<String?>();
      await tester.pumpWidget(buildSubject(
        locale: const Locale('en'),
        bootstrap: SessionBootstrap(
          readToken: () {
            tokenReads++;
            // First read (launch) is signed out; the sign-in read is held
            // open so repeated taps land while it's in flight.
            return tokenReads == 1 ? Future.value(null) : gate.future;
          },
          fetchUser: () async => null,
          onboardingCompleted: () async => true,
        ),
      ));
      await tester.pumpAndSettle();

      final signIn = find.text('Sign In');
      await tester.ensureVisible(signIn);
      for (var i = 0; i < 3; i++) {
        await tester.tap(signIn);
        await tester.pump();
      }
      expect(tokenReads, 2, reason: 'one launch read + one sign-in read');
    });
  });

  group('Splash text contrast', () {
    for (final (mode, theme) in [
      ('light', AppTheme.lightFor(arabic: false)),
      ('dark', AppTheme.darkFor(arabic: false)),
    ]) {
      testWidgets('welcome texts are AA in $mode mode', (tester) async {
        AppLocale.languageCode.value = 'en';
        await tester.pumpWidget(MaterialApp(
          theme: theme,
          locale: const Locale('en'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          home: Scaffold(
            body: WelcomeBody(
              onRegister: () {},
              onLogin: () {},
              onPrivacy: () {},
            ),
          ),
        ));
        await tester.pumpAndSettle();
        expectAllTextAa('welcome $mode');
      });

      testWidgets('boot texts are AA in $mode mode', (tester) async {
        AppLocale.languageCode.value = 'en';
        await tester.pumpWidget(MaterialApp(
          theme: theme,
          home: const MediaQuery(
            data: MediaQueryData(disableAnimations: true),
            child: Scaffold(body: SplashBootBody()),
          ),
        ));
        await tester.pump();
        expectAllTextAa('boot $mode');
      });
    }
  });
}
