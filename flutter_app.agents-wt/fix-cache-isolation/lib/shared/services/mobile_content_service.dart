import 'dart:async';

import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/core/storage/auth_token_store.dart';
import 'package:sirati/core/storage/disk_cache.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/models/job_title.dart';

/// Short-lived in-memory cache for mobile content endpoints.
/// TTL keeps data feeling instant on tab revisit without going stale for long.
class _CacheEntry {
  final Map<String, dynamic> data;
  final DateTime at;

  const _CacheEntry(this.data, this.at);

  bool isFresh([Duration ttl = MobileContentService.cacheTtl]) =>
      DateTime.now().difference(at) < ttl;
}

class MobileContentService {
  MobileContentService({ApiClient? apiClient})
      : _apiClient = apiClient ??
            ApiClient(tokenProvider: const AuthTokenStore().readToken);

  final ApiClient _apiClient;

  /// Shared process-wide cache (service is often re-created per call site).
  static final Map<String, _CacheEntry> _cache = {};
  static int _cacheGeneration = 0;

  static const cacheTtl = Duration(seconds: 60);

  /// Taxonomy list changes almost never — keep warm for a full day.
  static const jobTitlesTtl = Duration(hours: 24);

  /// Marker keys attached when a payload is served after network failure.
  /// UI should read these and not treat them as API fields.
  static const offlineMetaKey = '__sirati_offline';
  static const cachedAtMetaKey = '__sirati_cached_at';

  static bool isOfflinePayload(Map<String, dynamic>? data) =>
      data != null && data[offlineMetaKey] == true;

  /// Drop memory cache entries. Pass a key prefix like `dashboard:` or `my-cvs:`.
  static void invalidate([String? keyPrefix]) {
    if (keyPrefix == null || keyPrefix.isEmpty) {
      _cache.clear();
      return;
    }
    _cache.removeWhere((key, _) => key.startsWith(keyPrefix));
  }

  /// After create / update / delete of CVs so Home + My CVs don't show stale counts.
  static void invalidateCvRelated() {
    invalidate('my-cvs');
    invalidate('dashboard');
  }

  /// Clear memory + disk content caches (logout / session expiry).
  static Future<void> clearAllCaches() async {
    _cacheGeneration++;
    invalidate();
    await DiskCache.instance.clear();
  }

  Future<Map<String, dynamic>> dashboard(
    bool english, {
    bool force = false,
  }) {
    final lang = _lang(english);
    return _cached(
      'dashboard:$lang',
      force: force,
      diskKey: DiskCache.dashboardKey(lang),
      fetch: () async {
        final response =
            await _apiClient.getJson('/mobile/dashboard?lang=$lang');
        return _data(response);
      },
    );
  }

  Future<Map<String, dynamic>> myCvs(
    bool english, {
    bool force = false,
  }) {
    final lang = _lang(english);
    return _cached(
      'my-cvs:$lang',
      force: force,
      diskKey: DiskCache.cvsKey(lang),
      fetch: () async {
        final response = await _apiClient.getJson('/mobile/my-cvs?lang=$lang');
        return _data(response);
      },
    );
  }

  Future<Map<String, dynamic>> education(
    bool english, {
    String type = 'study',
    bool force = false,
  }) {
    return _cached(
      'education:${_lang(english)}:$type',
      force: force,
      // Education is not disk-cached (not in offline critical path).
      fetch: () async {
        final response = await _apiClient
            .getJson('/mobile/education?lang=${_lang(english)}&type=$type');
        return _data(response);
      },
    );
  }

  Future<Map<String, dynamic>> educationContent(int id, bool english) async {
    final response = await _apiClient
        .getJson('/mobile/education/$id?lang=${_lang(english)}');
    return _data(response);
  }

  /// Public job-title taxonomy for registration / profile (pre-auth safe).
  Future<List<JobTitle>> jobTitles({bool force = false}) async {
    final payload = await _cached(
      'job-titles',
      force: force,
      ttl: jobTitlesTtl,
      diskKey: DiskCache.jobTitlesKey(),
      fetch: () async {
        final response = await _apiClient.getJson('/mobile/job-titles');
        final data = response['data'];
        final items = data is List ? data : const [];
        return {'items': items};
      },
    );

    final raw = payload['items'];
    if (raw is! List) return const [];

    return raw
        .whereType<Map>()
        .map((item) => JobTitle.fromJson(Map<String, dynamic>.from(item)))
        .toList(growable: false);
  }

  Future<Map<String, dynamic>> jobNews(
    bool english, {
    String? category,
    String? preset,
    String? city,
    int? jobTitleId,
    bool? isRemote,
    bool? isVerified,
    String? sourceType,
    String? employmentType,
    String? query,
    bool force = false,
  }) {
    final lang = _lang(english);
    final cat = category ?? '';
    final pre = preset ?? '';
    final c = city ?? '';
    final jt = jobTitleId?.toString() ?? '';
    final rem = isRemote == true ? '1' : '';
    final ver = isVerified == true ? '1' : '';
    final src = sourceType ?? '';
    final emp = employmentType ?? '';
    final q = query?.trim() ?? '';
    final isDefault = cat.isEmpty &&
        pre.isEmpty &&
        c.isEmpty &&
        jt.isEmpty &&
        rem.isEmpty &&
        ver.isEmpty &&
        src.isEmpty &&
        emp.isEmpty &&
        q.isEmpty;
    final diskKey = isDefault ? DiskCache.newsKey(lang) : null;
    return _cached(
      'job-news:$lang:$cat:$pre:$c:$jt:$rem:$ver:$src:$emp:$q',
      force: force,
      diskKey: diskKey,
      ttl: q.isEmpty ? cacheTtl : const Duration(seconds: 30),
      fetch: () async {
        final params = <String, String>{'lang': lang};
        if (cat.isNotEmpty && cat != 'all') params['category'] = cat;
        if (pre.isNotEmpty && pre != 'all') params['preset'] = pre;
        if (c.isNotEmpty && c != 'all') params['city'] = c;
        if (jt.isNotEmpty) params['job_title_id'] = jt;
        if (rem.isNotEmpty) params['is_remote'] = '1';
        if (ver.isNotEmpty) params['is_verified'] = '1';
        if (src.isNotEmpty && src != 'all') params['source_type'] = src;
        if (emp.isNotEmpty && emp != 'all') params['employment_type'] = emp;
        if (q.isNotEmpty) params['q'] = q;
        final response = await _apiClient
            .getJson('/mobile/job-news?${Uri(queryParameters: params).query}');
        return _data(response);
      },
    );
  }

  Future<Map<String, dynamic>> jobNewsItem(int id, bool english) async {
    final response =
        await _apiClient.getJson('/mobile/job-news/$id?lang=${_lang(english)}');
    return _data(response);
  }

  Future<Map<String, dynamic>> notifications({bool force = false}) {
    return _cached(
      'notifications',
      force: force,
      ttl: const Duration(seconds: 30),
      // Never disk-cache notifications (sensitive / user-specific ephemeral).
      fetch: () async {
        final response = await _apiClient.getJson('/mobile/notifications');
        return _data(response);
      },
    );
  }

  Future<Map<String, dynamic>> markNotificationRead(int id) async {
    final response =
        await _apiClient.postJson('/mobile/notifications/$id/read', const {});
    invalidate('notifications');
    return _data(response);
  }

  Future<void> markAllNotificationsRead() async {
    await _apiClient.postJson('/mobile/notifications/read-all', const {});
    invalidate('notifications');
  }

  /// Network-first with memory TTL short-circuit.
  ///
  /// On network / timeout / server failure: fall back to memory, then disk.
  /// Fresh network responses write through to disk (fire-and-forget).
  /// Online first load still shows the usual skeleton (no double-render).
  Future<Map<String, dynamic>> _cached(
    String key, {
    required Future<Map<String, dynamic>> Function() fetch,
    bool force = false,
    Duration ttl = cacheTtl,
    String? diskKey,
  }) async {
    final generation = _cacheGeneration;
    final mem = _cache[key];
    if (!force && mem != null && mem.isFresh(ttl)) {
      return _withoutOfflineMeta(mem.data);
    }

    try {
      final data = await fetch();
      final clean = _withoutOfflineMeta(data);
      // Return a late response to its caller without warming the next session.
      if (generation != _cacheGeneration) return clean;
      _cache[key] = _CacheEntry(clean, DateTime.now());
      if (diskKey != null) {
        unawaited(DiskCache.instance.put(diskKey, clean));
      }
      return clean;
    } catch (error) {
      if (generation != _cacheGeneration) rethrow;
      // Prefer last good payload over a hard fail when offline.
      if (mem != null) {
        return _withOfflineMeta(mem.data, mem.at);
      }

      if (diskKey != null) {
        final disk = await DiskCache.instance.get(diskKey);
        if (generation != _cacheGeneration) rethrow;
        if (disk != null) {
          _cache[key] = _CacheEntry(disk.data, disk.savedAt);
          return _withOfflineMeta(disk.data, disk.savedAt);
        }
      }
      rethrow;
    }
  }

  Map<String, dynamic> _withOfflineMeta(
    Map<String, dynamic> data,
    DateTime savedAt,
  ) {
    return {
      ..._withoutOfflineMeta(data),
      offlineMetaKey: true,
      cachedAtMetaKey: savedAt.toIso8601String(),
    };
  }

  Map<String, dynamic> _withoutOfflineMeta(Map<String, dynamic> data) {
    if (!data.containsKey(offlineMetaKey) &&
        !data.containsKey(cachedAtMetaKey)) {
      return data;
    }
    final copy = Map<String, dynamic>.from(data);
    copy.remove(offlineMetaKey);
    copy.remove(cachedAtMetaKey);
    return copy;
  }

  String _lang(bool english) => english ? 'en' : 'ar';

  Map<String, dynamic> _data(Map<String, dynamic> response) {
    final data = response['data'];
    return data is Map<String, dynamic> ? data : const {};
  }
}

bool mobileContentEnglishFromContext(context) => AppLocale.isEnglish(context);
