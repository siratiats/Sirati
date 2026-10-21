import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/storage/auth_token_store.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/settings/presentation/change_password_screen.dart';
import 'package:sirati/features/settings/presentation/delete_account_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/services/auth_api_service.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

/// Real AuthApiService over a recording HTTP client.
({AuthApiService auth, List<String> requests, MemoryAuthTokenStore tokens})
    _service({required int deleteStatus}) {
  final requests = <String>[];
  final client = MockClient((request) async {
    requests.add('${request.method} ${request.url.path}');
    if (request.url.path.endsWith('/auth/account')) {
      return http.Response(
        jsonEncode(deleteStatus == 200
            ? {'message': 'deleted'}
            : {
                'message': 'invalid',
                'errors': {
                  'password': ['كلمة المرور غير صحيحة.'],
                },
              }),
        deleteStatus,
        headers: {'content-type': 'application/json'},
      );
    }
    return http.Response('{}', 200,
        headers: {'content-type': 'application/json'});
  });
  final tokens = MemoryAuthTokenStore()..value = 'bearer-1';
  final auth = AuthApiService(
    apiClient: ApiClient(httpClient: client, tokenProvider: tokens.readToken),
    tokenStore: tokens,
  );
  return (auth: auth, requests: requests, tokens: tokens);
}

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  group('AuthApiService.deleteAccount', () {
    test('a wrong password leaves the session and push untouched', () async {
      final s = _service(deleteStatus: 422);
      await expectLater(
        s.auth.deleteAccount(password: 'nope'),
        throwsA(isA<ApiException>()),
      );
      expect(await s.tokens.readToken(), 'bearer-1');
      expect(s.requests.where((r) => r.contains('fcm-tokens')), isEmpty,
          reason: 'device push must not be unregistered before the server '
              'confirms');
    });

    test('success clears the session without a post-delete API call', () async {
      final s = _service(deleteStatus: 200);
      await s.auth.deleteAccount(password: 'right');
      expect(await s.tokens.readToken(), isNull);
      expect(s.requests, ['DELETE /api/auth/account'].map((r) => r).toList(),
          reason: 'any further call would use the revoked bearer and 401');
    });
  });

  group('screens', () {
    setUp(() => AppLocale.languageCode.value = 'en');

    Widget app(Widget home, AuthApiService auth, ThemeData theme) =>
        ProviderScope(
          overrides: [authApiServiceProvider.overrideWithValue(auth)],
          child: MaterialApp(
            theme: theme,
            locale: const Locale('en'),
            supportedLocales: AppLocalizations.supportedLocales,
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            home: home,
          ),
        );

    testWidgets('delete: Done + button open one confirmation', (tester) async {
      final s = _service(deleteStatus: 422);
      await tester.pumpWidget(app(
        const DeleteAccountScreen(),
        s.auth,
        AppTheme.lightFor(arabic: false),
      ));
      await tester.enterText(find.byType(TextField), 'secret');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pump();
      await tester.tap(find.text('Delete my account'), warnIfMissed: false);
      await tester.pumpAndSettle();
      expect(find.byType(AlertDialog), findsOneWidget);
    });

    testWidgets('change password: one request in flight', (tester) async {
      var calls = 0;
      final gate = Completer<void>();
      final auth = _GatedChange(() {
        calls++;
        return gate.future;
      });
      await tester.pumpWidget(app(
        const ChangePasswordScreen(),
        auth,
        AppTheme.lightFor(arabic: false),
      ));
      final fields = find.byType(TextField);
      await tester.enterText(fields.at(0), 'oldpass1');
      await tester.enterText(fields.at(1), 'newpass12');
      await tester.enterText(fields.at(2), 'newpass12');
      await tester.tap(find.text('Update password'));
      await tester.pump();
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pump();
      expect(calls, 1);
      gate.complete();
      await tester.pumpAndSettle();
    });

    testWidgets('change password: confirm re-checks when the new one changes',
        (tester) async {
      await tester.pumpWidget(app(
        const ChangePasswordScreen(),
        _GatedChange(() async {}),
        AppTheme.lightFor(arabic: false),
      ));
      final fields = find.byType(TextField);
      await tester.enterText(fields.at(0), 'oldpass1');
      await tester.enterText(fields.at(1), 'newpass12');
      await tester.enterText(fields.at(2), 'newpass1');
      await tester.tap(find.text('Update password'));
      await tester.pumpAndSettle();
      expect(find.text('Passwords do not match'), findsOneWidget);

      await tester.enterText(fields.at(1), 'newpass1');
      await tester.pump();
      expect(find.text('Passwords do not match'), findsNothing);
    });

    for (final (mode, theme) in [
      ('light', AppTheme.lightFor(arabic: false)),
      ('dark', AppTheme.darkFor(arabic: false)),
    ]) {
      for (final (name, screen) in [
        ('delete', const DeleteAccountScreen() as Widget),
        ('change', const ChangePasswordScreen()),
      ]) {
        testWidgets('$name account screen is AA in $mode', (tester) async {
          await tester.pumpWidget(
            app(screen, _service(deleteStatus: 200).auth, theme),
          );
          await tester.pump();
          expectAllTextAa('$name $mode');
          expect(find.byTooltip('Show password'), findsWidgets);
        });
      }
    }
  });
}

class _GatedChange extends AuthApiService {
  _GatedChange(this.onChange);
  final Future<void> Function() onChange;

  @override
  Future<String> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    await onChange();
    return 'ok';
  }
}
