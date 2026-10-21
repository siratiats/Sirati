import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/features/dashboard/application/dashboard_controller.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';
import 'package:sirati/shared/services/mobile_content_service_provider.dart';
import 'package:sirati/shared/services/session_cache.dart';

class _FakeContent extends MobileContentService {
  final forces = <bool>[];
  Object? failWith;
  Map<String, dynamic> payload = {};

  @override
  Future<Map<String, dynamic>> dashboard(
    bool english, {
    bool force = false,
  }) async {
    forces.add(force);
    final error = failWith;
    if (error != null) throw error;
    return payload;
  }
}

void main() {
  late _FakeContent content;
  late ProviderContainer container;

  setUp(() {
    SessionCache.instance.unreadCount = 0;
    content = _FakeContent();
    container = ProviderContainer(
      overrides: [mobileContentServiceProvider.overrideWithValue(content)],
    );
  });

  tearDown(() => container.dispose());

  Future<void> load() {
    container.listen(dashboardProvider(true), (_, __) {});
    return container.read(dashboardProvider(true).future);
  }

  test('first load may use cache; every later load bypasses it', () async {
    await load();
    for (var i = 0; i < 3; i++) {
      final next = container.refresh(dashboardProvider(true).future);
      await next;
    }
    expect(content.forces, [false, true, true, true]);
  });

  test('server unread count updates the session badge; absence keeps it',
      () async {
    SessionCache.instance.unreadCount = 4;
    content.payload = {};
    await load();
    expect(SessionCache.instance.unreadCount, 4);

    content.payload = {'unread_notifications': 9};
    final next = container.refresh(dashboardProvider(true).future);
    await next;
    expect(SessionCache.instance.unreadCount, 9);
  });

  test('a failure surfaces as an error and is not auto-retried', () async {
    content.failWith = const ApiException('offline');
    await expectLater(load(), throwsA(isA<ApiException>()));
    await Future<void>.delayed(const Duration(milliseconds: 50));
    expect(container.read(dashboardProvider(true)).hasError, isTrue);
    expect(content.forces.length, 1);
  });
}
