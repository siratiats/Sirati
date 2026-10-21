import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/auth/application/login_controller.dart';
import 'package:sirati/features/auth/presentation/login_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/auth_session.dart';
import 'package:sirati/shared/services/auth_api_service.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

class _FakeAuth extends AuthApiService {
  final calls = <(String, String)>[];
  Completer<void>? gate;
  Object? error;
  bool verified = true;

  @override
  Future<AuthSession> login({
    required String email,
    required String password,
  }) async {
    calls.add((email, password));
    await gate?.future;
    final e = error;
    if (e != null) throw e;
    return AuthSession(
      token: 't',
      tokenType: 'Bearer',
      user: AuthUser(id: 1, name: 'S', email: email, emailVerified: verified),
    );
  }
}

void main() {
  group('loginPasswordError', () {
    test('only an empty password is rejected — never a length rule', () {
      expect(loginPasswordError(null), isNotNull);
      expect(loginPasswordError(''), isNotNull);
      for (var n = 1; n <= 12; n++) {
        expect(loginPasswordError('x' * n), isNull, reason: 'length $n');
      }
      expect(loginPasswordError(' '), isNull, reason: 'spaces are valid');
    });
  });

  group('LoginController', () {
    late _FakeAuth auth;
    late ProviderContainer container;
    setUp(() {
      auth = _FakeAuth();
      container = ProviderContainer(
        overrides: [authApiServiceProvider.overrideWithValue(auth)],
      );
      container.listen(loginControllerProvider, (_, __) {});
    });
    tearDown(() => container.dispose());

    LoginController ctrl() => container.read(loginControllerProvider.notifier);

    test('trims the email, never the password', () async {
      await ctrl().signIn(email: '  a@b.io ', password: ' pw ');
      expect(auth.calls.single, ('a@b.io', ' pw '));
    });

    test('verification status is reported for routing', () async {
      for (final verified in [true, false]) {
        auth.verified = verified;
        final outcome = await ctrl().signIn(email: 'a@b.io', password: 'p');
        expect(
          outcome,
          isA<LoginSucceeded>()
              .having((o) => o.emailVerified, 'verified', verified),
        );
      }
    });

    test('one sign-in at a time', () async {
      auth.gate = Completer<void>();
      final first = ctrl().signIn(email: 'a@b.io', password: 'p');
      for (var i = 0; i < 3; i++) {
        expect(await ctrl().signIn(email: 'a@b.io', password: 'p'),
            isA<LoginIgnored>());
      }
      auth.gate!.complete();
      expect(await first, isA<LoginSucceeded>());
      expect(auth.calls, hasLength(1));
    });

    test('failures are classified and release the flag', () async {
      for (final (error, type) in <(Object, Type)>[
        (const ApiException('bad', statusCode: 422), LoginRejected),
        (StateError('x'), LoginFailedUnexpectedly),
      ]) {
        auth.error = error;
        final outcome = await ctrl().signIn(email: 'a@b.io', password: 'p');
        expect(outcome.runtimeType, type);
        expect(container.read(loginControllerProvider), isFalse);
      }
    });
  });

  group('LoginScreen', () {
    const secureStorage =
        MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
    setUp(() {
      AppLocale.languageCode.value = 'en';
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(secureStorage, (_) async => null);
    });
    tearDown(() {
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(secureStorage, null);
    });

    Widget app(AuthApiService auth, {ThemeData? theme}) => ProviderScope(
          overrides: [authApiServiceProvider.overrideWithValue(auth)],
          child: MaterialApp(
            theme: theme ?? AppTheme.lightFor(arabic: false),
            locale: const Locale('en'),
            supportedLocales: AppLocalizations.supportedLocales,
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            home: const LoginScreen(),
          ),
        );

    Future<void> fill(WidgetTester tester, String password) async {
      await tester.enterText(find.byType(TextField).at(0), 'a@b.io');
      await tester.enterText(find.byType(TextField).at(1), password);
    }

    testWidgets('a short password is sent to the server, not blocked',
        (tester) async {
      final auth = _FakeAuth()..error = const ApiException('wrong');
      await tester.pumpWidget(app(auth));
      await tester.pumpAndSettle();
      await fill(tester, 'abc');
      await tester.tap(find.text('Sign in'));
      await tester.pumpAndSettle();
      expect(auth.calls, [('a@b.io', 'abc')]);
    });

    testWidgets('keyboard Send while signing in does not send again',
        (tester) async {
      final auth = _FakeAuth()
        ..gate = Completer<void>()
        ..error = const ApiException('stop');
      await tester.pumpWidget(app(auth));
      await tester.pumpAndSettle();
      await fill(tester, 'secret');
      await tester.tap(find.text('Sign in'));
      await tester.pump();
      await tester.showKeyboard(find.byType(TextField).at(1));
      await tester.testTextInput.receiveAction(TextInputAction.send);
      await tester.testTextInput.receiveAction(TextInputAction.send);
      await tester.pump();
      expect(auth.calls, hasLength(1));
      auth.gate!.complete();
      await tester.pumpAndSettle();
    });

    testWidgets('the server\'s rejection is shown to the user', (tester) async {
      // The live backend's reply to wrong credentials: 422 + field error.
      const message = 'بيانات تسجيل الدخول غير صحيحة.';
      for (final (status, body) in [
        (422, '{"message":"$message","errors":{"email":["$message"]}}'),
        (401, '{"message":"$message"}'),
        (429, '{"message":"Too Many Attempts."}'),
        (500, '<html>oops</html>'),
      ]) {
        await http.runWithClient(() async {
          await tester.pumpWidget(app(AuthApiService()));
          await tester.pumpAndSettle();
          await fill(tester, 'wrong-pass');
          await tester.tap(find.text('Sign in'));
          await tester.pumpAndSettle();
        },
            () => MockClient((_) async => http.Response(body, status,
                headers: {'content-type': 'application/json'})));
        final snack = find.byType(SnackBar);
        expect(snack, findsOneWidget, reason: 'HTTP $status: no feedback');
        final shown = tester
            .widgetList<Text>(find.descendant(of: snack, matching: find.byType(Text)))
            .map((t) => t.data ?? '')
            .join(' ');
        expect(shown.trim(), isNotEmpty, reason: 'HTTP $status');
        if (status == 422 || status == 401) {
          expect(shown, contains(message), reason: 'HTTP $status');
        }
        ScaffoldMessenger.of(tester.element(find.byType(Scaffold).first))
            .removeCurrentSnackBar();
        await tester.pumpWidget(const SizedBox());
      }
    });

    testWidgets('password visibility toggle is labelled', (tester) async {
      await tester.pumpWidget(app(_FakeAuth()));
      await tester.pumpAndSettle();
      expect(find.byTooltip('Show password'), findsOneWidget);
      await tester.tap(find.byTooltip('Show password'));
      await tester.pump();
      expect(find.byTooltip('Hide password'), findsOneWidget);
    });

    for (final (mode, theme) in [
      ('light', AppTheme.lightFor(arabic: false)),
      ('dark', AppTheme.darkFor(arabic: false)),
    ]) {
      testWidgets('every login text is AA in $mode', (tester) async {
        tester.view.physicalSize = const Size(1080, 2600);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.reset);
        await tester.pumpWidget(app(_FakeAuth(), theme: theme));
        await tester.pumpAndSettle();
        expectAllTextAa('login $mode');
      });
    }
  });
}
