import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/settings/application/settings_controllers.dart';
import 'package:sirati/features/settings/presentation/settings_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/auth_session.dart';
import 'package:sirati/shared/services/auth_api_service.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';
import 'package:sirati/shared/services/session_cache.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

class _FakeGateway extends NotificationPrefsGateway {
  _FakeGateway();

  bool? server = true;
  bool local = true;
  Object? readError;
  Object? syncError;
  Object? deviceError;
  Completer<void>? deviceGate;
  final log = <String>[];

  @override
  Future<bool?> fetchServer() async {
    if (readError != null) throw readError!;
    return server;
  }

  @override
  Future<bool> readLocal() async {
    if (readError != null) throw readError!;
    return local;
  }

  @override
  Future<void> saveLocal(bool enabled) async => log.add('local:$enabled');

  @override
  Future<void> syncServer(bool enabled) async {
    log.add('server:$enabled');
    if (syncError != null) throw syncError!;
  }

  Future<void> _device(String what) async {
    log.add(what);
    await deviceGate?.future;
    if (deviceError != null) throw deviceError!;
  }

  @override
  Future<void> enableDevicePush() => _device('device:on');

  @override
  Future<void> disableDevicePush() => _device('device:off');
}

class _FakeAuth extends AuthApiService {
  Object? logoutError;
  int logouts = 0;

  @override
  Future<AuthUser?> me() async =>
      const AuthUser(id: 1, name: 'Sara', email: 'sara@x.io');

  @override
  Future<void> logout() async {
    logouts++;
    if (logoutError != null) throw logoutError!;
  }
}

void main() {
  group('NotificationPreferenceController', () {
    late _FakeGateway gateway;
    late ProviderContainer container;

    setUp(() {
      gateway = _FakeGateway();
      container = ProviderContainer(overrides: [
        notificationPrefsGatewayProvider.overrideWithValue(gateway),
      ]);
      container.listen(notificationPreferenceProvider, (_, __) {});
    });
    tearDown(() => container.dispose());

    Future<bool> load() =>
        container.read(notificationPreferenceProvider.future);
    NotificationPreferenceController ctrl() =>
        container.read(notificationPreferenceProvider.notifier);

    test('server value wins; local is the fallback; errors default to on',
        () async {
      for (final (server, local, readError, expected)
          in <(bool?, bool, Object?, bool)>[
        (true, false, null, true),
        (false, true, null, false),
        (null, false, null, false),
        (null, true, null, true),
        (false, false, StateError('keystore'), true),
      ]) {
        gateway
          ..server = server
          ..local = local
          ..readError = readError;
        container.invalidate(notificationPreferenceProvider);
        expect(await load(), expected,
            reason: 'server=$server local=$local error=$readError');
      }
    });

    test('turning off stops device push even when the server step fails',
        () async {
      await load();
      gateway.syncError = StateError('offline');
      expect(await ctrl().setEnabled(false), isFalse);
      expect(gateway.log, ['local:false', 'server:false', 'device:off']);
      expect(container.read(notificationPreferenceProvider).value, isFalse);
    });

    test('rapid toggles apply one at a time and end on the last value',
        () async {
      await load();
      gateway.deviceGate = Completer<void>();
      final first = ctrl().setEnabled(false);
      await Future<void>.delayed(Duration.zero);
      for (final v in [true, false, true, false]) {
        unawaited(ctrl().setEnabled(v));
      }
      gateway.deviceGate!.complete();
      await first;
      await Future<void>.delayed(Duration.zero);

      // First change, then only the final requested value (false == first,
      // so nothing more is applied).
      expect(gateway.log.where((e) => e.startsWith('device')), ['device:off']);
      expect(container.read(notificationPreferenceProvider).value, isFalse);
    });

    test('a change still finishes if the screen closes mid-apply', () async {
      await load();
      gateway.deviceGate = Completer<void>();
      final pending = ctrl().setEnabled(false);
      await Future<void>.delayed(Duration.zero);
      container.dispose();
      gateway.deviceGate!.complete();
      expect(await pending, isTrue);
      expect(gateway.log, contains('device:off'));
      container = ProviderContainer(); // for tearDown
    });
  });

  group('SettingsScreen', () {
    const secureStorage =
        MethodChannel('plugins.it_nomads.com/flutter_secure_storage');

    setUp(() {
      AppLocale.languageCode.value = 'en';
      SessionCache.instance.clear();
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(secureStorage, (_) async => null);
    });
    tearDown(() {
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(secureStorage, null);
    });

    Widget app(_FakeAuth auth, {ThemeData? theme}) => ProviderScope(
          overrides: [
            authApiServiceProvider.overrideWithValue(auth),
            notificationPrefsGatewayProvider.overrideWithValue(_FakeGateway()),
          ],
          child: MaterialApp(
            theme: theme ?? AppTheme.lightFor(arabic: false),
            locale: const Locale('en'),
            supportedLocales: AppLocalizations.supportedLocales,
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            home: const SettingsScreen(),
            onGenerateRoute: (settings) => MaterialPageRoute(
              settings: settings,
              builder: (_) => Scaffold(body: Text('route:${settings.name}')),
            ),
          ),
        );

    Future<void> confirmLogout(WidgetTester tester) async {
      final l10n = await AppLocalizations.delegate.load(const Locale('en'));
      await tester.scrollUntilVisible(find.text(l10n.logOut), 200);
      await tester.tap(find.text(l10n.logOut));
      await tester.pumpAndSettle();
      await tester.tap(find.descendant(
        of: find.byType(AlertDialog),
        matching: find.text(l10n.logOut),
      ));
      await tester.pumpAndSettle();
    }

    testWidgets('a failed local sign-out keeps the user here and says so',
        (tester) async {
      final auth = _FakeAuth()..logoutError = StateError('keystore locked');
      await tester.pumpWidget(app(auth));
      await tester.pumpAndSettle();
      await confirmLogout(tester);

      final l10n = await AppLocalizations.delegate.load(const Locale('en'));
      expect(find.text('route:${AppRoutes.login}'), findsNothing);
      expect(find.byType(SettingsScreen), findsOneWidget);
      expect(find.text(l10n.logOutFailed), findsOneWidget);
      expect(find.text(l10n.signedOutLocally), findsNothing);
    });

    testWidgets('a successful sign-out goes to Login', (tester) async {
      final auth = _FakeAuth();
      await tester.pumpWidget(app(auth));
      await tester.pumpAndSettle();
      await confirmLogout(tester);
      expect(auth.logouts, 1);
      expect(find.text('route:${AppRoutes.login}'), findsOneWidget);
    });

    for (final (mode, theme) in [
      ('light', AppTheme.lightFor(arabic: false)),
      ('dark', AppTheme.darkFor(arabic: false)),
    ]) {
      testWidgets('every settings text is AA in $mode mode', (tester) async {
        tester.view.physicalSize = const Size(1080, 4000);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.reset);
        await tester.pumpWidget(app(_FakeAuth(), theme: theme));
        await tester.pumpAndSettle();
        expectAllTextAa('settings $mode');
      });

      testWidgets('an on switch shows its thumb against the track ($mode)',
          (tester) async {
        tester.view.physicalSize = const Size(1080, 4000);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.reset);
        await tester.pumpWidget(app(_FakeAuth(), theme: theme));
        await tester.pumpAndSettle();
        expect(find.byType(Switch), findsWidgets);
        final context = tester.element(find.byType(Switch));
        final t = Theme.of(context);
        final cs = t.colorScheme;
        const on = {WidgetState.selected};
        for (final s in tester.widgetList<Switch>(find.byType(Switch))) {
          // Effective colours: widget, then theme, then the M3 defaults.
          final thumb = s.thumbColor?.resolve(on) ??
              t.switchTheme.thumbColor?.resolve(on) ??
              cs.onPrimary;
          final track = s.trackColor?.resolve(on) ??
              t.switchTheme.trackColor?.resolve(on) ??
              cs.primary;
          expect(AppContrast.ratio(thumb, track), greaterThanOrEqualTo(3.0),
              reason: 'switch thumb vs track ($mode)');
        }
      });
    }
  });
}
