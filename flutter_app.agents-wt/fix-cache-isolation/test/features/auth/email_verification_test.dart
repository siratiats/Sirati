import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/core/utils/latin_digits.dart';
import 'package:sirati/features/auth/presentation/email_verification_screen.dart';
import 'package:sirati/features/auth/presentation/login_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/auth_session.dart';
import 'package:sirati/shared/services/auth_api_service.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

import '../../support/contrast_probe.dart';

class _FakeAuth extends AuthApiService {
  final codes = <String>[];
  Completer<void>? gate;
  Object? verifyError = const ApiException('wrong code');
  Object? logoutError;
  int logouts = 0;

  @override
  Future<AuthUser> verifyEmail({required String code}) async {
    codes.add(code);
    await gate?.future;
    if (verifyError != null) throw verifyError!;
    return const AuthUser(
      id: 1,
      name: 'S',
      email: 'sara@x.io',
      emailVerified: true,
    );
  }

  @override
  Future<void> logout() async {
    logouts++;
    if (logoutError != null) throw logoutError!;
  }
}

Widget _app(_FakeAuth auth, {String lang = 'en', ThemeData? theme}) {
  AppLocale.languageCode.value = lang;
  return ProviderScope(
    overrides: [authApiServiceProvider.overrideWithValue(auth)],
    child: MaterialApp(
      theme: theme ?? AppTheme.lightFor(arabic: lang == 'ar'),
      locale: Locale(lang),
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      home: const EmailVerificationScreen(email: 'sara@x.io'),
    ),
  );
}

Finder _codeField() => find.byWidgetPredicate((w) =>
    w is TextField &&
    (w.inputFormatters ?? const []).any((f) => f is LatinDigitsInputFormatter));

void main() {
  testWidgets('an Arabic-keyboard code auto-submits once, as ASCII',
      (tester) async {
    final auth = _FakeAuth();
    await tester.pumpWidget(_app(auth, lang: 'ar'));
    await tester.pump();
    await tester.enterText(_codeField(), '٤٨٢٩١٥٧');
    await tester.pumpAndSettle();
    expect(auth.codes, ['482915']);
  });

  testWidgets('auto-submit, Done and the button send one request in flight',
      (tester) async {
    final auth = _FakeAuth()..gate = Completer<void>();
    await tester.pumpWidget(_app(auth));
    await tester.pump();
    await tester.enterText(_codeField(), '123456');
    await tester.pump();
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.tap(find.byType(SubmitButton));
    await tester.pump();
    expect(auth.codes, ['123456']);
    auth.gate!.complete();
    await tester.pumpAndSettle();
  });

  testWidgets('system Back signs out and returns to Login', (tester) async {
    final auth = _FakeAuth();
    await tester.pumpWidget(_app(auth));
    await tester.pump();
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(auth.logouts, 1);
    expect(find.byType(LoginScreen), findsOneWidget);
  });

  testWidgets('a failed sign-out keeps the user here and says so',
      (tester) async {
    final auth = _FakeAuth()..logoutError = StateError('keystore');
    await tester.pumpWidget(_app(auth));
    await tester.pump();
    await tester.tap(find.byTooltip('Back to sign in'));
    await tester.pumpAndSettle();
    expect(find.byType(LoginScreen), findsNothing);
    expect(find.byType(EmailVerificationScreen), findsOneWidget);
    expect(find.textContaining("Couldn't sign out"), findsOneWidget);

    // Retrying is possible after a failure.
    auth.logoutError = null;
    await tester.tap(find.byTooltip('Back to sign in'));
    await tester.pumpAndSettle();
    expect(find.byType(LoginScreen), findsOneWidget);
  });

  for (final (mode, theme) in [
    ('light', AppTheme.lightFor(arabic: false)),
    ('dark', AppTheme.darkFor(arabic: false)),
  ]) {
    testWidgets('all text is AA in $mode', (tester) async {
      await tester.pumpWidget(_app(_FakeAuth(), theme: theme));
      await tester.pump();
      expectAllTextAa('verify email $mode');
    });
  }
}
