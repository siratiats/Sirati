import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/auth/domain/social_sign_in.dart';
import 'package:sirati/features/auth/presentation/login_screen.dart';
import 'package:sirati/features/auth/presentation/register_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/auth_session.dart';
import 'package:sirati/shared/models/job_title.dart';
import 'package:sirati/shared/services/auth_api_service.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';
import 'package:sirati/shared/services/job_titles_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

class _GatedAuth extends AuthApiService {
  int calls = 0;
  final gate = Completer<void>();

  @override
  Future<AuthSession> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
    String? location,
    int? jobTitleId,
    String? jobTitleOther,
  }) async {
    calls++;
    await gate.future;
    throw StateError('stop here');
  }
}

Widget _subject(ThemeData theme, {AuthApiService? auth}) => ProviderScope(
      overrides: [
        jobTitlesProvider.overrideWith((ref) async => const <JobTitle>[]),
        if (auth != null) authApiServiceProvider.overrideWithValue(auth),
      ],
      child: MaterialApp(
        theme: theme,
        locale: const Locale('en'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        home: const RegisterScreen(),
      ),
    );

void main() {
  setUp(() => AppLocale.languageCode.value = 'en');

  for (final (mode, theme) in [
    ('light', AppTheme.lightFor(arabic: false)),
    ('dark', AppTheme.darkFor(arabic: false)),
  ]) {
    testWidgets('every register text is AA in $mode mode', (tester) async {
      tester.view.physicalSize = const Size(1080, 4000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(_subject(theme));
      await tester.pumpAndSettle();
      expectAllTextAa('register $mode');
    });
  }

  testWidgets('keyboard Done and the button submit only once in flight',
      (tester) async {
    tester.view.physicalSize = const Size(1080, 4000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    final auth = _GatedAuth();
    await tester.pumpWidget(
      _subject(AppTheme.lightFor(arabic: false), auth: auth),
    );
    await tester.pumpAndSettle();

    Finder field(String hint) => find.ancestor(
          of: find.text(hint),
          matching: find.byType(TextField),
        );
    const values = {
      'Enter your full name': 'Sara',
      'example@mail.com': 'sara@x.io',
      '05XXXXXXXX': '0501234567',
      'Riyadh, Saudi Arabia': 'Riyadh',
      '••••••••': 'secret123',
    };
    for (final MapEntry(key: hint, :value) in values.entries) {
      await tester.enterText(field(hint), value);
    }
    final password = find.byType(TextField).last;
    await tester
        .tap(find.text('I agree to Terms & Conditions', findRichText: true));
    await tester.pump();

    // Button, then keyboard Done twice while the first request is open.
    await tester.tap(find.text('Create Account'));
    await tester.pump();
    await tester.showKeyboard(password);
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pump();

    expect(auth.calls, 1);
    auth.gate.complete();
    await tester.pumpAndSettle();
  });

  testWidgets('no third-party sign-in controls while the flag is off',
      (tester) async {
    expect(socialSignInEnabled, isFalse);
    tester.view.physicalSize = const Size(1080, 4000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    for (final screen in const [RegisterScreen(), LoginScreen()]) {
      await tester.pumpWidget(ProviderScope(
        overrides: [
          jobTitlesProvider.overrideWith((ref) async => const <JobTitle>[]),
        ],
        child: MaterialApp(
          theme: AppTheme.lightFor(arabic: false),
          locale: const Locale('en'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          home: screen,
        ),
      ));
      await tester.pumpAndSettle();
      final name = screen.runtimeType;
      expect(find.text('Or continue with'), findsNothing, reason: '$name');
      expect(find.text('Google'), findsNothing, reason: '$name');
      expect(find.text('Apple'), findsNothing, reason: '$name');
      expect(find.byIcon(Icons.apple), findsNothing, reason: '$name');
    }
  });
}
