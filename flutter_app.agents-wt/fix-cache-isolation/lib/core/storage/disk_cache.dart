import 'dart:convert';
import 'dart:math';

import 'package:shared_preferences/shared_preferences.dart';

/// Disk-backed JSON entry for offline last-known mobile content.
class DiskCacheEntry {
  final Map<String, dynamic> data;
  final DateTime savedAt;

  const DiskCacheEntry({required this.data, required this.savedAt});
}

/// Small JSON cache over [SharedPreferences].
///
/// Used only for non-auth content (dashboard, CVs list, news). Never store
/// tokens or session payloads here.
///
/// Envelope: `{ schemaVersion, cacheEpoch, savedAt, data }`.
/// Schema or session epoch mismatch → empty.
class DiskCache {
  DiskCache._();

  static final DiskCache instance = DiskCache._();
  int _generation = 0;
  Future<void>? _pendingClear;

  // Kept outside keyPrefix so wiping entries preserves the session boundary.
  static const _epochKey = 'sirati_content_cache_epoch';

  /// Bump when the envelope or expected payload shape changes incompatibly.
  static const schemaVersion = 2;

  /// Skip write when serialized envelope exceeds this size (~200KB).
  static const maxBytes = 200 * 1024;

  static const keyPrefix = 'sirati_cache_';

  // ── Namespaced keys ──────────────────────────────────────────────────

  static String dashboardKey(String lang) =>
      '${keyPrefix}dashboard_${_normLang(lang)}';

  /// CVs list is language-scoped so AR/EN payloads never overwrite each other.
  static String cvsKey(String lang) => '${keyPrefix}cvs_${_normLang(lang)}';

  static String newsKey(String lang) => '${keyPrefix}news_${_normLang(lang)}';

  /// Job title taxonomy is language-agnostic (payload includes AR + EN names).
  static String jobTitlesKey() => '${keyPrefix}job_titles';

  static String _normLang(String lang) => lang == 'en' ? 'en' : 'ar';

  Future<SharedPreferences> get _prefs => SharedPreferences.getInstance();

  /// Returns `{data, savedAt}` or null when missing / corrupt / schema mismatch.
  Future<DiskCacheEntry?> get(String key) async {
    final generation = _generation;
    try {
      await _pendingClear;
      final prefs = await _prefs;
      if (generation != _generation) return null;
      final raw = prefs.getString(key);
      if (raw == null || raw.isEmpty) return null;

      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      final map = Map<String, dynamic>.from(decoded);

      final version = map['schemaVersion'];
      final versionInt =
          version is int ? version : int.tryParse(version?.toString() ?? '');
      if (versionInt != schemaVersion) {
        await prefs.remove(key);
        return null;
      }
      // A platform write dispatched before clear may finish after the wipe.
      // Its old epoch stays unreadable, including after preferences reload.
      if (map['cacheEpoch'] != (prefs.getString(_epochKey) ?? '')) return null;

      final data = map['data'];
      if (data is! Map) return null;

      final savedAt = DateTime.tryParse(map['savedAt']?.toString() ?? '') ??
          DateTime.fromMillisecondsSinceEpoch(0);

      return DiskCacheEntry(
        data: Map<String, dynamic>.from(data),
        savedAt: savedAt.toLocal(),
      );
    } catch (_) {
      return null;
    }
  }

  /// Persists [data] under [key]. Oversized or failed writes are swallowed.
  Future<void> put(String key, Map<String, dynamic> data) async {
    final generation = _generation;
    try {
      await _pendingClear;
      final prefs = await _prefs;
      if (generation != _generation) return;
      final envelope = <String, dynamic>{
        'schemaVersion': schemaVersion,
        'cacheEpoch': prefs.getString(_epochKey) ?? '',
        'savedAt': DateTime.now().toUtc().toIso8601String(),
        'data': data,
      };
      final encoded = jsonEncode(envelope);
      // utf8 length ≈ code-unit length for JSON ASCII; reject large blobs.
      if (encoded.length > maxBytes) return;

      await prefs.setString(key, encoded);
    } catch (_) {
      // Best-effort offline cache — never block the UI.
    }
  }

  /// Removes every `sirati_cache_*` key (logout / session expiry).
  Future<void> clear() {
    _generation++;
    final clearing = _clear(_pendingClear);
    _pendingClear = clearing;
    return clearing;
  }

  Future<void> _clear(Future<void>? previous) async {
    try {
      await previous;
      final prefs = await _prefs;
      final random = Random.secure();
      final epoch = List.generate(
        16,
        (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0'),
      ).join();
      await prefs.setString(_epochKey, epoch);
      final keys =
          prefs.getKeys().where((k) => k.startsWith(keyPrefix)).toList();
      for (final key in keys) {
        await prefs.remove(key);
      }
    } catch (_) {}
  }
}
