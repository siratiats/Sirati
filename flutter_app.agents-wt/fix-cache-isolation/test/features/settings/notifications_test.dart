import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/dashboard/presentation/education_detail_screen.dart';
import 'package:sirati/features/settings/application/settings_controllers.dart';
import 'package:sirati/features/settings/domain/notification_item.dart';
import 'package:sirati/features/settings/presentation/notifications_screen.dart';
import 'package:sirati/features/settings/presentation/privacy_policy_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';
import 'package:sirati/shared/services/mobile_content_service_provider.dart';
import 'package:sirati/shared/services/notification_service.dart';
import 'package:sirati/shared/services/session_cache.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

Map<String, dynamic> _row(int id,
        {bool read = false, String? type, String? url}) =>
    {
      'id': id,
      'title': 'Note $id',
      'body': 'Body $id',
      'is_read': read,
      'created_at': '2026-09-20T10:00:00Z',
      'action_type': type,
      'action_url': url,
    };

class _FakeContent extends MobileContentService {
  _FakeContent(this.rows);
  List<Map<String, dynamic>> rows;
  Object? markError;
  final marked = <Object>[];

  @override
  Future<Map<String, dynamic>> notifications({bool force = false}) async =>
      {'items': rows};

  @override
  Future<Map<String, dynamic>> markNotificationRead(int id) async {
    marked.add(id);
    if (markError != null) throw markError!;
    return {};
  }

  @override
  Future<void> markAllNotificationsRead() async {
    marked.add('all');
    if (markError != null) throw markError!;
  }
}

void main() {
  group('NotificationItem', () {
    test('only url/screen actions with a target open something', () {
      for (final (type, url, opens) in <(String?, String?, bool)>[
        ('url', 'https://x.io', true),
        ('screen', 'jobs', true),
        ('screen', '', false),
        ('notifications', 'x', false),
        (null, 'jobs', false),
        ('', '', false),
      ]) {
        expect(
          NotificationItem.fromJson(_row(1, type: type, url: url))
              .opensSomething,
          opens,
          reason: '$type $url',
        );
      }
    });

    test('non-object rows are dropped', () {
      expect(
        NotificationItem.listFrom({
          'items': [_row(1), null, 'x', 3, _row(2)],
        }).map((n) => n.id),
        [1, 2],
      );
      expect(NotificationItem.listFrom({'items': 'nope'}), isEmpty);
    });
  });

  group('NotificationsInboxController', () {
    late _FakeContent content;
    late ProviderContainer c;
    setUp(() {
      SessionCache.instance.unreadCount = 0;
      content = _FakeContent([_row(1), _row(2), _row(3, read: true)]);
      c = ProviderContainer(
        overrides: [mobileContentServiceProvider.overrideWithValue(content)],
      );
      c.listen(notificationsInboxProvider, (_, __) {});
    });
    tearDown(() => c.dispose());

    Future<List<NotificationItem>> load() =>
        c.read(notificationsInboxProvider.future);
    NotificationsInboxController ctrl() =>
        c.read(notificationsInboxProvider.notifier);
    int unreadShown() => c
        .read(notificationsInboxProvider)
        .value!
        .where((i) => !i.isRead)
        .length;

    test('mark read updates the row and the badge together', () async {
      final items = await load();
      expect(await ctrl().markRead(items[0]), isTrue);
      expect(unreadShown(), 1);
      expect(SessionCache.instance.unreadCount, 1);
      expect(await ctrl().markAllRead(), isTrue);
      expect(unreadShown(), 0);
      expect(SessionCache.instance.unreadCount, 0);
    });

    test('already-read rows are not sent again', () async {
      final items = await load();
      await ctrl().markRead(items[2]);
      expect(content.marked, isEmpty);
    });

    test('a failed request restores the row and the badge', () async {
      final items = await load();
      content.markError = const ApiException('offline');
      expect(await ctrl().markRead(items[0]), isFalse);
      expect(unreadShown(), 2);
      expect(SessionCache.instance.unreadCount, 2);
      expect(await ctrl().markAllRead(), isFalse);
      expect(unreadShown(), 2);
    });
  });

  group('NotificationsScreen', () {
    setUp(() {
      AppLocale.languageCode.value = 'en';
      NotificationService.instance.onNotificationTap = null;
    });
    tearDown(() => NotificationService.instance.onNotificationTap = null);

    Widget app(_FakeContent content, {ThemeData? theme}) => ProviderScope(
          overrides: [mobileContentServiceProvider.overrideWithValue(content)],
          child: MaterialApp(
            theme: theme ?? AppTheme.lightFor(arabic: false),
            locale: const Locale('en'),
            supportedLocales: AppLocalizations.supportedLocales,
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            home: const Scaffold(body: Center(child: Text('home'))),
            routes: {'/inbox': (_) => const NotificationsScreen()},
          ),
        );

    Future<void> openInbox(WidgetTester tester) async {
      unawaited(tester
          .state<NavigatorState>(find.byType(Navigator))
          .pushNamed('/inbox'));
      await tester.pumpAndSettle();
    }

    testWidgets('tapping an actionable item routes like a push',
        (tester) async {
      final routed = <Map<String, dynamic>>[];
      NotificationService.instance.onNotificationTap = routed.add;
      final content = _FakeContent([
        _row(1, type: 'screen', url: 'jobs'),
        _row(2, type: 'url', url: 'https://x.io'),
        _row(3),
      ]);
      await tester.pumpWidget(app(content));
      await openInbox(tester);

      await tester.tap(find.text('Note 2'));
      await tester.pumpAndSettle();
      expect(routed.last, {'action_type': 'url', 'action_url': 'https://x.io'});
      expect(find.byType(NotificationsScreen), findsOneWidget,
          reason: 'url actions keep the inbox open');

      await tester.tap(find.text('Note 3'));
      await tester.pumpAndSettle();
      expect(routed, hasLength(1), reason: 'no action: mark read only');

      await tester.tap(find.text('Note 1'));
      await tester.pumpAndSettle();
      expect(routed.last['action_url'], 'jobs');
      expect(find.byType(NotificationsScreen), findsNothing,
          reason: 'screen actions leave the inbox so the target is visible');
      expect(content.marked, containsAll([1, 2, 3]));
    });

    for (final (mode, theme) in [
      ('light', AppTheme.lightFor(arabic: false)),
      ('dark', AppTheme.darkFor(arabic: false)),
    ]) {
      testWidgets('inbox, privacy and learning detail are AA in $mode',
          (tester) async {
        tester.view.physicalSize = const Size(1080, 4000);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.reset);

        await tester.pumpWidget(app(
          _FakeContent(
              [_row(1), _row(2, read: true, type: 'screen', url: 'jobs')]),
          theme: theme,
        ));
        await openInbox(tester);
        expectAllTextAa('inbox $mode');

        for (final screen in [
          const PrivacyPolicyScreen(),
          const EducationDetailScreen(fallback: {
            'title': 'Interview basics',
            'body': 'Prepare with the STAR method.',
            'badge': 'New',
            'target_role': 'Analyst',
            'duration': '15 min',
          }),
        ]) {
          await tester.pumpWidget(ProviderScope(
            // Fresh scope per screen: overrides differ from the inbox's.
            key: UniqueKey(),
            child: MaterialApp(
              theme: theme,
              locale: const Locale('en'),
              supportedLocales: AppLocalizations.supportedLocales,
              localizationsDelegates: AppLocalizations.localizationsDelegates,
              home: screen,
            ),
          ));
          await tester.pumpAndSettle();
          expectAllTextAa('${screen.runtimeType} $mode');
        }
      });
    }
  });
}
