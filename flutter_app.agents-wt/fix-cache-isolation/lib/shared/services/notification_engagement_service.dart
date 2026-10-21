import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/core/storage/auth_token_store.dart';
import 'package:sirati/core/storage/preference_store.dart';
import 'package:sirati/core/utils/app_locale.dart';

/// Reports activity / open / conversion events for smart notifications.
class NotificationEngagementService {
  NotificationEngagementService._();
  static final instance = NotificationEngagementService._();

  final _prefs = const PreferenceStore();
  DateTime? _lastActivityAt;

  ApiClient get _client => ApiClient(
        tokenProvider: () => const AuthTokenStore().readToken(),
      );

  Future<void> reportActivity({String? event}) async {
    final auth = await const AuthTokenStore().readToken();
    if (auth == null || auth.isEmpty) return;

    final now = DateTime.now();
    if (_lastActivityAt != null &&
        now.difference(_lastActivityAt!) < const Duration(minutes: 2) &&
        event == null) {
      return;
    }
    _lastActivityAt = now;

    try {
      await _client.postJson('/mobile/activity', {
        'language': AppLocale.languageCode.value == 'en' ? 'en' : 'ar',
        'timezone_offset_minutes': now.timeZoneOffset.inMinutes,
        if (event != null) 'event': event,
      });
    } catch (_) {
      // Non-blocking telemetry.
    }
  }

  Future<void> markOpened(String? notificationId) async {
    if (notificationId == null || notificationId.isEmpty) return;
    final auth = await const AuthTokenStore().readToken();
    if (auth == null || auth.isEmpty) return;

    try {
      await _client.postJson(
        '/mobile/notifications/$notificationId/opened',
        const {},
      );
    } catch (_) {}
  }

  Future<void> reportConversion(
    String conversionType, {
    String? decisionId,
    String? notificationId,
  }) async {
    // Fire-and-forget from UI callers: the token read (keychain/keystore) must
    // be inside the try too, or a storage failure escapes as an uncaught error.
    try {
      final auth = await const AuthTokenStore().readToken();
      if (auth == null || auth.isEmpty) return;

      await _client.postJson('/mobile/conversions', {
        'conversion_type': conversionType,
        if (decisionId != null) 'decision_id': int.tryParse(decisionId),
        if (notificationId != null)
          'notification_id': int.tryParse(notificationId),
      });
    } catch (_) {}
  }

  Future<void> syncPreferenceEnabled(bool enabled) async {
    await _prefs.saveNotificationsEnabled(enabled);
    final auth = await const AuthTokenStore().readToken();
    if (auth == null || auth.isEmpty) return;

    try {
      await _client.putJson('/mobile/notification-preferences', {
        'enabled': enabled,
        'language': AppLocale.languageCode.value == 'en' ? 'en' : 'ar',
        'timezone_offset_minutes': DateTime.now().timeZoneOffset.inMinutes,
      });
    } catch (_) {}
  }

  Future<bool?> fetchServerEnabled() async {
    final auth = await const AuthTokenStore().readToken();
    if (auth == null || auth.isEmpty) return null;
    try {
      final response =
          await _client.getJson('/mobile/notification-preferences');
      final data = response['data'];
      if (data is Map && data['enabled'] is bool) {
        final enabled = data['enabled'] as bool;
        await _prefs.saveNotificationsEnabled(enabled);
        return enabled;
      }
    } catch (_) {}
    return null;
  }
}
