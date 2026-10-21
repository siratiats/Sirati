import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared_preferences_platform_interface/shared_preferences_platform_interface.dart';
import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/storage/disk_cache.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';

typedef _Load = Future<Object> Function(MobileContentService service);

// Delay the actual platform operation, rather than just its acknowledgement.
class _DelayedPreferences extends InMemorySharedPreferencesStore {
  _DelayedPreferences() : super.empty();

  String? delayedKey;
  final writeStarted = Completer<void>();
  final releaseWrite = Completer<void>();
  final writeFinished = Completer<void>();
  final cacheWrites = <String>[];
  bool delayRead = false;
  final readStarted = Completer<void>();
  final releaseRead = Completer<void>();

  @override
  Future<bool> setValue(String valueType, String key, Object value) async {
    if (key.startsWith('flutter.${DiskCache.keyPrefix}')) cacheWrites.add(key);
    if (key == delayedKey && !writeStarted.isCompleted) {
      writeStarted.complete();
      await releaseWrite.future;
      final result = await super.setValue(valueType, key, value);
      writeFinished.complete();
      return result;
    }
    return super.setValue(valueType, key, value);
  }

  @override
  Future<Map<String, Object>> getAll() async {
    final snapshot = await super.getAll();
    if (delayRead && !readStarted.isCompleted) {
      readStarted.complete();
      await releaseRead.future;
    }
    return snapshot;
  }
}

MobileContentService _service(
  Future<http.Response> Function(http.Request) respond,
) =>
    MobileContentService(apiClient: ApiClient(httpClient: MockClient(respond)));

MobileContentService _offlineService() =>
    _service((_) async => throw http.ClientException('offline'));

http.Response _response(Object data) =>
    http.Response(jsonEncode({'data': data}), 200);

const _payload = <String, dynamic>{
  'owner': 731,
  'items': [
    {'id': 87, 'title': 'Private content'},
  ],
};

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  late _DelayedPreferences preferences;

  setUp(() async {
    SharedPreferences.resetStatic();
    preferences = _DelayedPreferences();
    SharedPreferencesStorePlatform.instance = preferences;
    await MobileContentService.clearAllCaches();
  });

  tearDown(() async {
    if (!preferences.releaseWrite.isCompleted) {
      preferences.releaseWrite.complete();
    }
    if (!preferences.releaseRead.isCompleted) {
      preferences.releaseRead.complete();
    }
    await MobileContentService.clearAllCaches();
    SharedPreferences.setMockInitialValues({});
  });

  final cases = <({String name, _Load load, String? diskKey, Object data})>[
    (
      name: 'dashboard',
      load: (service) => service.dashboard(false),
      diskKey: DiskCache.dashboardKey('ar'),
      data: _payload,
    ),
    (
      name: 'CV list',
      load: (service) => service.myCvs(true),
      diskKey: DiskCache.cvsKey('en'),
      data: _payload,
    ),
    (
      name: 'education',
      load: (service) => service.education(false, type: 'training'),
      diskKey: null,
      data: _payload,
    ),
    (
      name: 'job news',
      load: (service) => service.jobNews(false),
      diskKey: DiskCache.newsKey('ar'),
      data: _payload,
    ),
    (
      name: 'filtered job news',
      load: (service) => service.jobNews(true, query: 'developer'),
      diskKey: null,
      data: _payload,
    ),
    (
      name: 'notifications',
      load: (service) => service.notifications(),
      diskKey: null,
      data: _payload,
    ),
    (
      name: 'job titles',
      load: (service) => service.jobTitles(),
      diskKey: DiskCache.jobTitlesKey(),
      data: [
        {'id': 87, 'slug': 'developer', 'name_en': 'Developer'},
      ],
    ),
  ];

  for (final cacheCase in cases) {
    test('${cacheCase.name}: response after clear is never cached', () async {
      final started = Completer<void>();
      final response = Completer<http.Response>();
      final previous = _service((_) {
        started.complete();
        return response.future;
      });
      final pending = cacheCase.load(previous);
      await started.future;

      // clearAllCaches is the boundary used by logout and session expiry.
      await MobileContentService.clearAllCaches();
      response.complete(_response(cacheCase.data));
      final result = await pending;
      if (cacheCase.name == 'job titles') {
        expect(result, isNotEmpty);
      } else {
        expect(result, _payload);
      }

      if (cacheCase.diskKey != null) {
        expect(await DiskCache.instance.get(cacheCase.diskKey!), isNull);
      }
      await expectLater(
        cacheCase.load(_offlineService()),
        throwsA(isA<ApiException>()),
        reason: 'the next session must see neither memory nor disk content',
      );
      final shared = await SharedPreferences.getInstance();
      expect(shared.getKeys().where((k) => k.startsWith(DiskCache.keyPrefix)),
          isEmpty);
    });
  }

  for (final cacheCase in cases.where((c) => c.diskKey != null)) {
    final diskKey = cacheCase.diskKey!;
    test('$diskKey: platform write after wipe is unreadable', () async {
      preferences.delayedKey = 'flutter.$diskKey';
      await cacheCase.load(_service((_) async => _response(cacheCase.data)));
      await preferences.writeStarted.future;

      await MobileContentService.clearAllCaches();
      preferences.releaseWrite.complete();
      await preferences.writeFinished.future;

      // Reload the actual persisted snapshot, as an app restart would do.
      SharedPreferences.resetStatic();
      final shared = await SharedPreferences.getInstance();
      expect(shared.getString(diskKey), isNotNull,
          reason: 'the delayed old write really landed after the wipe');
      expect(await DiskCache.instance.get(diskKey), isNull);
      await expectLater(
        cacheCase.load(_offlineService()),
        throwsA(isA<ApiException>()),
      );
    });
  }

  test('disk write awaiting preferences is discarded on clear', () async {
    SharedPreferences.resetStatic();
    preferences.delayRead = true;
    final key = DiskCache.newsKey('ar');
    final writing = DiskCache.instance.put(key, _payload);
    await preferences.readStarted.future;

    final clearing = MobileContentService.clearAllCaches();
    preferences.releaseRead.complete();
    await Future.wait([writing, clearing]);
    final shared = await SharedPreferences.getInstance();
    expect(preferences.cacheWrites, isEmpty,
        reason: 'a stale put must not even dispatch a platform write');
    expect(shared.getString(key), isNull);
    expect(await DiskCache.instance.get(key), isNull);
  });

  test('failed old request cannot use its captured memory fallback', () async {
    final online = _service((_) async => _response(_payload));
    await online.jobNews(false);
    final started = Completer<void>();
    final response = Completer<http.Response>();
    final failing = _service((_) {
      started.complete();
      return response.future;
    });
    final pending = failing.jobNews(false, force: true);
    final expectation = expectLater(pending, throwsA(isA<ApiException>()));
    await started.future;
    await MobileContentService.clearAllCaches();
    response.completeError(http.ClientException('offline'));
    await expectation;
  });

  test('disk read awaiting preferences is rejected on clear', () async {
    final key = DiskCache.newsKey('ar');
    await DiskCache.instance.put(key, _payload);
    SharedPreferences.resetStatic();
    preferences.delayRead = true;
    final reading = DiskCache.instance.get(key);
    await preferences.readStarted.future;

    final clearing = MobileContentService.clearAllCaches();
    preferences.releaseRead.complete();
    expect(await reading, isNull);
    await clearing;
  });

  test('disk fallback started before clear cannot refill memory', () async {
    final key = DiskCache.newsKey('ar');
    await DiskCache.instance.put(key, _payload);
    SharedPreferences.resetStatic();
    preferences.delayRead = true;
    final pending = _offlineService().jobNews(false);
    final expectation = expectLater(pending, throwsA(isA<ApiException>()));
    await preferences.readStarted.future;

    final clearing = MobileContentService.clearAllCaches();
    preferences.releaseRead.complete();
    await clearing;
    await expectation;
    await expectLater(
        _offlineService().jobNews(false), throwsA(isA<ApiException>()));
  });

  test('normal memory hit and same-session disk fallback still work', () async {
    preferences.delayedKey = 'flutter.${DiskCache.newsKey('ar')}';
    preferences.releaseWrite.complete();
    var requests = 0;
    final online = _service((_) async {
      requests++;
      return _response(_payload);
    });
    expect(await online.jobNews(false), _payload);
    await preferences.writeFinished.future;
    expect(await online.jobNews(false), _payload);
    expect(requests, 1);
    final memory = await _offlineService().jobNews(false, force: true);
    expect(memory['owner'], _payload['owner']);
    expect(MobileContentService.isOfflinePayload(memory), isTrue);

    MobileContentService.invalidate();
    SharedPreferences.resetStatic();
    final disk = await _offlineService().jobNews(false);
    expect(disk['owner'], _payload['owner']);
    expect(MobileContentService.isOfflinePayload(disk), isTrue);
    expect(disk[MobileContentService.cachedAtMetaKey], isA<String>());
    final hit = await online.jobNews(false);
    expect(hit, _payload);
    expect(MobileContentService.isOfflinePayload(hit), isFalse);
    expect(requests, 1);
  });

  test('clear removes legacy unscoped entries and keeps device settings',
      () async {
    final shared = await SharedPreferences.getInstance();
    for (final key in [
      DiskCache.dashboardKey('ar'),
      DiskCache.cvsKey('en'),
      DiskCache.newsKey('ar'),
      DiskCache.jobTitlesKey(),
      '${DiskCache.keyPrefix}legacy',
    ]) {
      await shared.setString(
          key, jsonEncode({'schemaVersion': 1, 'data': _payload}));
    }
    await shared.setString('device_setting', 'keep');
    await MobileContentService.clearAllCaches();
    expect(shared.getKeys().where((k) => k.startsWith(DiskCache.keyPrefix)),
        isEmpty);
    expect(shared.getString('device_setting'), 'keep');
  });
}
