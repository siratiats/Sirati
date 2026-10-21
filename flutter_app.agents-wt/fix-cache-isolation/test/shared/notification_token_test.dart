import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/core/storage/auth_token_store.dart';
import 'package:sirati/features/auth/data/auth_session_guard.dart';
import 'package:sirati/shared/services/auth_api_service.dart';
import 'package:sirati/shared/services/notification_service.dart';

/// Device token that rotates when deleted, like FCM.
class _FakeTokens extends FcmTokenSource {
  int _generation = 1;
  int deletes = 0;
  bool failDelete = false;

  @override
  Future<String?> getToken() async => 'fcm-$_generation';

  @override
  Future<void> deleteToken() async {
    deletes++;
    if (failDelete) throw StateError('offline');
    _generation++;
  }
}

final _secure = <String, String>{};
final _requests = <String>[];

void _mockSecureStorage() {
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
      .setMockMethodCallHandler(
    const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
    (call) async {
      final args = (call.arguments as Map?)?.cast<String, Object?>() ?? {};
      final key = args['key'] as String?;
      switch (call.method) {
        case 'read':
          return _secure[key];
        case 'write':
          _secure[key!] = args['value']! as String;
        case 'delete':
          _secure.remove(key);
        case 'readAll':
          return Map<String, String>.from(_secure);
        case 'deleteAll':
          _secure.clear();
      }
      return null;
    },
  );
}

/// Every HTTP request in [body], including ones made through clients the
/// services build themselves.
Future<T> _recording<T>(Future<T> Function() body, {int status = 200}) =>
    http.runWithClient(
        body,
        () => MockClient((request) async {
              final payload = request.body.isEmpty ? '' : ' ${request.body}';
              _requests.add('${request.method} ${request.url.path}$payload');
              return http.Response('{}', status,
                  headers: {'content-type': 'application/json'});
            }));

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  late _FakeTokens tokens;

  setUp(() {
    _mockSecureStorage();
    SharedPreferences.setMockInitialValues({});
    _secure
      ..clear()
      ..['sirati_auth_token'] = 'bearer-A';
    _requests.clear();
    tokens = _FakeTokens();
    NotificationService.instance.configureForTest(
      tokens: tokens,
      clientFactory: () =>
          ApiClient(tokenProvider: const AuthTokenStore().readToken),
    );
  });

  List<String> fcmPosts() =>
      _requests.where((r) => r.startsWith('POST /api/fcm-tokens')).toList();

  test('re-registering within 5 minutes is de-duplicated', () async {
    await _recording(() async {
      await NotificationService.instance.registerToken();
      await NotificationService.instance.registerToken();
    });
    expect(fcmPosts(), hasLength(1));
  });

  test('after forgetDevice the next account registers at once, new token',
      () async {
    await _recording(() async {
      await NotificationService.instance.registerToken();
      await NotificationService.instance.forgetDevice();
      _secure['sirati_auth_token'] = 'bearer-B'; // next account signs in
      await NotificationService.instance.registerToken();
    });
    final posts = fcmPosts();
    expect(posts, hasLength(2),
        reason: 'the new account must not be skipped by de-duplication');
    expect(posts[0], contains('fcm-1'));
    expect(posts[1], contains('fcm-2'),
        reason: 'the old account\'s token was rotated away');
    expect(tokens.deletes, 1);
  });

  test('even if the token cannot rotate, the next account still registers',
      () async {
    tokens.failDelete = true;
    await _recording(() async {
      await NotificationService.instance.registerToken();
      await NotificationService.instance.forgetDevice();
      _secure['sirati_auth_token'] = 'bearer-B';
      await NotificationService.instance.registerToken();
    });
    final posts = fcmPosts();
    expect(posts, hasLength(2),
        reason: 'same token, new account: the server must re-link it');
    expect(posts.every((p) => p.contains('fcm-1')), isTrue);
  });

  test('logout unregisters the account\'s own token, then rotates it',
      () async {
    final auth = AuthApiService(tokenStore: const AuthTokenStore());
    await _recording(() async {
      await auth.logout();
      // Remote cleanup is fire-and-forget; let it run.
      await Future<void>.delayed(const Duration(milliseconds: 50));
    });
    final deletes =
        _requests.where((r) => r.startsWith('DELETE /api/fcm-tokens'));
    expect(deletes.single, contains('fcm-1'),
        reason: 'must remove the row for the token this account used');
    expect(tokens.deletes, 1);
    expect(await const AuthTokenStore().readToken(), isNull);
  });

  test('session expiry rotates the token even when unregister gets a 401',
      () async {
    AuthSessionGuard.resetForTest();
    addTearDown(AuthSessionGuard.resetForTest);
    await _recording(
      () => AuthSessionGuard.handleAuthExpired(
        requestToken: 'bearer-A',
        showMessage: false,
      ),
      status: 401,
    );
    expect(tokens.deletes, 1,
        reason: 'the device must stop receiving the expired account\'s '
            'pushes even though the server row could not be deleted');
  });
}
