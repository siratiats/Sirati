import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Lightweight secure preferences (language, notification opt-in, CV draft).
///
/// Reuses [FlutterSecureStorage] — no new packages.
class PreferenceStore {
  const PreferenceStore({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const langKey = 'sirati_lang';
  static const notificationsKey = 'sirati_notifications_enabled';
  static const onboardingKey = 'sirati_onboarding_completed';
  static const themeModeKey = 'sirati_theme_mode';
  static const cvDraftKey = 'sirati_cv_draft';
  static const reviewRequestedKey = 'sirati_review_requested';
  static const cvGenerationCountKey = 'sirati_cv_generation_count';
  static const educationInterestKey = 'sirati_education_interest';

  /// Bump when the draft JSON shape changes incompatibly.
  static const cvDraftSchemaVersion = 1;

  final FlutterSecureStorage _storage;

  /// Removes one key; used by the sign-out wipe of account data.
  Future<void> deleteKey(String key) => _storage.delete(key: key);

  Future<String?> readLanguage() => _storage.read(key: langKey);

  Future<void> saveLanguage(String code) =>
      _storage.write(key: langKey, value: code == 'en' ? 'en' : 'ar');

  /// Defaults to enabled when unset.
  Future<bool> readNotificationsEnabled() async {
    final raw = await _storage.read(key: notificationsKey);
    if (raw == null) return true;
    return raw != '0' && raw.toLowerCase() != 'false';
  }

  Future<void> saveNotificationsEnabled(bool enabled) =>
      _storage.write(key: notificationsKey, value: enabled ? '1' : '0');

  /// Whether the user asked to hear about the education section's launch.
  Future<bool> readEducationInterest() async =>
      (await _storage.read(key: educationInterestKey)) == '1';

  Future<void> saveEducationInterest() =>
      _storage.write(key: educationInterestKey, value: '1');

  /// First-run product tour. Missing key → not completed.
  Future<bool> readOnboardingCompleted() async {
    final raw = await _storage.read(key: onboardingKey);
    if (raw == null) return false;
    return raw == '1' || raw.toLowerCase() == 'true';
  }

  Future<void> saveOnboardingCompleted(bool completed) =>
      _storage.write(key: onboardingKey, value: completed ? '1' : '0');

  /// Appearance: `system` | `light` | `dark`. Missing → system.
  Future<String?> readThemeMode() => _storage.read(key: themeModeKey);

  Future<void> saveThemeMode(String mode) {
    final normalized = (mode == 'light' || mode == 'dark' || mode == 'system')
        ? mode
        : 'system';
    return _storage.write(key: themeModeKey, value: normalized);
  }

  /// CV create-wizard draft. Schema mismatch → silently discarded.
  Future<Map<String, dynamic>?> readCvDraft() async {
    final raw = await _storage.read(key: cvDraftKey);
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      final map = Map<String, dynamic>.from(decoded);
      final version = map['schemaVersion'];
      final versionInt =
          version is int ? version : int.tryParse(version?.toString() ?? '');
      if (versionInt != cvDraftSchemaVersion) {
        await clearCvDraft();
        return null;
      }
      return map;
    } catch (_) {
      return null;
    }
  }

  Future<void> saveCvDraft(Map<String, dynamic> draft) =>
      _storage.write(key: cvDraftKey, value: jsonEncode(draft));

  Future<void> clearCvDraft() => _storage.delete(key: cvDraftKey);

  /// Whether we already asked the OS for an in-app review.
  Future<bool> readReviewRequested() async {
    final raw = await _storage.read(key: reviewRequestedKey);
    if (raw == null) return false;
    return raw == '1' || raw.toLowerCase() == 'true';
  }

  Future<void> saveReviewRequested(bool requested) =>
      _storage.write(key: reviewRequestedKey, value: requested ? '1' : '0');

  /// Successful create-mode CV generations on this device (for review gating).
  Future<int> readCvGenerationCount() async {
    final raw = await _storage.read(key: cvGenerationCountKey);
    return int.tryParse(raw ?? '') ?? 0;
  }

  Future<void> saveCvGenerationCount(int count) => _storage.write(
      key: cvGenerationCountKey, value: '${count < 0 ? 0 : count}');

  /// Increments and returns the new generation count.
  Future<int> incrementCvGenerationCount() async {
    final next = (await readCvGenerationCount()) + 1;
    await saveCvGenerationCount(next);
    return next;
  }
}
