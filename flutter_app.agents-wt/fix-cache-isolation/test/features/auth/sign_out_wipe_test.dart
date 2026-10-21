import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/core/storage/auth_token_store.dart';
import 'package:sirati/core/storage/preference_store.dart';
import 'package:sirati/core/storage/user_scoped_storage.dart';
import 'package:sirati/features/auth/data/auth_session_guard.dart';
import 'package:sirati/features/cv_builder/application/cv_draft_store.dart';
import 'package:sirati/features/cv_builder/data/cv_repository_provider.dart';
import 'package:sirati/features/cv_builder/domain/cv_form_data.dart';
import 'package:sirati/shared/models/auth_session.dart';
import 'package:sirati/shared/models/cv_document.dart';
import 'package:sirati/shared/services/auth_api_service.dart';
import 'package:sirati/shared/services/session_cache.dart';

/// In-memory flutter_secure_storage so PreferenceStore runs for real.
final _secure = <String, String>{};

void _mockSecureStorage() {
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
      .setMockMethodCallHandler(
    const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
    (call) async {
      final args = (call.arguments as Map?)?.cast<String, Object?>() ?? {};
      final key = args['key'] as String?;
      switch (call.method) {
        case 'write':
          _secure[key!] = args['value']! as String;
          return null;
        case 'read':
          return _secure[key];
        case 'delete':
          _secure.remove(key);
          return null;
        case 'containsKey':
          return _secure.containsKey(key);
        case 'readAll':
          return Map<String, String>.from(_secure);
        case 'deleteAll':
          _secure.clear();
          return null;
      }
      return null;
    },
  );
}

const _deviceSecure = {
  PreferenceStore.langKey: 'ar',
  PreferenceStore.themeModeKey: 'dark',
  PreferenceStore.onboardingKey: '1',
};

const _deviceShared = {'unrelated_device_flag': 'keep'};

/// Seeds one account's data plus device-level settings.
Future<void> _seedAccountData() async {
  _secure
    ..clear()
    ..addAll(_deviceSecure)
    ..[PreferenceStore.educationInterestKey] = '1'
    ..[PreferenceStore.notificationsKey] = '0';
  SharedPreferences.setMockInitialValues({
    ..._deviceShared,
    '${UserScopedKeys.builderDraftPrefix}cv_1': '{"title":"Sara CV"}',
    '${UserScopedKeys.builderDocPrefix}cv_1': '{"title":"Sara CV"}',
    UserScopedKeys.builderIndexKey: <String>['cv_1'],
  });
  // A real generator draft with personal data, written by the real store.
  await const CvDraftStore(PreferenceStore()).save(
    const CvFormData(
      fullName: 'Sara Ahmed',
      email: 'sara@x.io',
      phone: '+966501234567',
    ),
    step: 1,
  );
}

Future<void> _expectOnlyDeviceDataLeft() async {
  expect(
    await const CvDraftStore(PreferenceStore()).read(stepCount: 4),
    isNull,
    reason: 'the next account must not be offered this draft',
  );
  for (final key in UserScopedKeys.secureKeys) {
    expect(_secure.containsKey(key), isFalse, reason: key);
  }
  for (final MapEntry(:key, :value) in _deviceSecure.entries) {
    expect(_secure[key], value, reason: 'device setting $key kept');
  }
  final shared = await SharedPreferences.getInstance();
  expect(
    shared.getKeys().where(UserScopedKeys.isUserScopedPreference),
    isEmpty,
  );
  expect(shared.getString('unrelated_device_flag'), 'keep');
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(_mockSecureStorage);

  test('the seeded draft is real (sanity)', () async {
    await _seedAccountData();
    final draft =
        await const CvDraftStore(PreferenceStore()).read(stepCount: 4);
    expect(draft?.data.fullName, 'Sara Ahmed');
  });

  test('logout removes account data and keeps device settings', () async {
    await _seedAccountData();
    final tokens = MemoryAuthTokenStore('bearer');
    final auth = AuthApiService(
      apiClient: ApiClient(
        httpClient: MockClient((_) async => http.Response('{}', 200)),
        tokenProvider: tokens.readToken,
      ),
      tokenStore: tokens,
    );
    await auth.logout();
    await _expectOnlyDeviceDataLeft();
  });

  test('session expiry (401) removes the same account data', () async {
    await _seedAccountData();
    final tokens = MemoryAuthTokenStore('bearer');
    AuthSessionGuard.resetForTest(store: tokens);
    addTearDown(AuthSessionGuard.resetForTest);
    await AuthSessionGuard.handleAuthExpired(
      requestToken: 'bearer',
      showMessage: false,
    );
    await _expectOnlyDeviceDataLeft();
  });

  test('the wipe keeps going when one store fails', () async {
    await _seedAccountData();
    await wipeUserScopedStorage(prefs: _ThrowingPrefs());
    final shared = await SharedPreferences.getInstance();
    expect(
      shared.getKeys().where(UserScopedKeys.isUserScopedPreference),
      isEmpty,
    );
  });

  test('builder documents in memory do not follow a new account', () async {
    SessionCache.instance.clear();
    final container = ProviderContainer();
    addTearDown(container.dispose);
    container.listen(cvRepositoryProvider, (_, __) {});

    SessionCache.instance
        .setUser(const AuthUser(id: 1, name: 'A', email: 'a@x.io'));
    await container
        .read(cvRepositoryProvider)
        .saveCv(CvDocument.createEmpty(id: 'cv_a', title: 'A private CV'));
    expect(await container.read(cvRepositoryProvider).listCvs(), isNotEmpty);

    SessionCache.instance.clear();
    SessionCache.instance
        .setUser(const AuthUser(id: 2, name: 'B', email: 'b@x.io'));
    expect(await container.read(cvRepositoryProvider).listCvs(), isEmpty);
    SessionCache.instance.clear();
  });
}

class _ThrowingPrefs extends PreferenceStore {
  @override
  Future<void> deleteKey(String key) async => throw StateError('keystore');
}
