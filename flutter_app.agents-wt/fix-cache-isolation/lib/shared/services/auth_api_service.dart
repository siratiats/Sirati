import 'dart:async';

import 'package:sirati/core/network/analytics_service.dart';
import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/core/storage/auth_token_store.dart';
import 'package:sirati/core/storage/user_scoped_storage.dart';
import 'package:sirati/shared/models/auth_session.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';
import 'package:sirati/shared/services/notification_service.dart';
import 'package:sirati/shared/services/session_cache.dart';

class AuthApiService {
  AuthApiService({
    ApiClient? apiClient,
    ApiClient? publicApiClient,
    AuthTokenStore tokenStore = const AuthTokenStore(),
  })  : _tokenStore = tokenStore,
        _apiClient =
            apiClient ?? ApiClient(tokenProvider: tokenStore.readToken),
        _publicClient = publicApiClient ?? apiClient ?? ApiClient();

  final ApiClient _apiClient;
  final ApiClient _publicClient;
  final AuthTokenStore _tokenStore;

  Future<AuthSession> login(
      {required String email, required String password}) async {
    final response = await _publicClient.postJson('/auth/login', {
      'email': email,
      'password': password,
      'device_name': 'sirati-mobile',
    });

    final session = AuthSession.fromJson(response);
    await _saveSession(session);
    return session;
  }

  Future<AuthSession> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
    String? location,
    int? jobTitleId,
    String? jobTitleOther,
  }) async {
    final body = <String, dynamic>{
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'device_name': 'sirati-mobile',
    };
    if (phone != null && phone.trim().isNotEmpty) {
      body['phone'] = phone.trim();
    }
    if (location != null && location.trim().isNotEmpty) {
      body['location'] = location.trim();
    }
    if (jobTitleId != null) {
      body['job_title_id'] = jobTitleId;
    }
    if (jobTitleOther != null && jobTitleOther.trim().isNotEmpty) {
      body['job_title_other'] = jobTitleOther.trim();
    }

    final response = await _publicClient.postJson('/auth/register', body);

    final session = AuthSession.fromJson(response);
    await _saveSession(session);
    return session;
  }

  Future<void> _saveSession(AuthSession session) async {
    await _tokenStore.saveToken(session.token);
    SessionCache.instance.setUser(session.user);
    await _bindAnalyticsUser(session.user);
  }

  /// Opaque backend id only — never email/name.
  Future<void> _bindAnalyticsUser(AuthUser user) async {
    if (user.id > 0) {
      await AnalyticsService.setUserId(user.id.toString());
    }
  }

  Future<AuthUser> verifyEmail({required String code}) async {
    final response = await _apiClient.postJson('/auth/email/verify', {
      'code': code.trim(),
    });
    final data = response['data'];
    final user = AuthUser.fromJson(
      data is Map<String, dynamic> ? data : const {},
    );
    SessionCache.instance.setUser(user);
    await _bindAnalyticsUser(user);
    return user;
  }

  Future<String> resendVerification() async {
    final response = await _apiClient.postJson('/auth/email/resend', const {});
    return response['message']?.toString() ??
        'تم إرسال رمز التحقق إلى بريدك الإلكتروني.';
  }

  Future<String> forgotPassword({required String email}) async {
    final response = await _publicClient.postJson('/auth/forgot-password', {
      'email': email,
    });

    return response['message']?.toString() ??
        'تم إرسال رمز استعادة كلمة المرور إذا كان البريد مسجلاً لدينا.';
  }

  Future<String> resetPassword({
    required String email,
    required String code,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await _publicClient.postJson('/auth/reset-password', {
      'email': email.trim(),
      'code': code.trim(),
      'password': password,
      'password_confirmation': passwordConfirmation,
    });

    return response['message']?.toString() ??
        'تم تعيين كلمة المرور الجديدة بنجاح. يمكنك تسجيل الدخول الآن.';
  }

  Future<void> logout() async {
    final token = await _tokenStore.readToken();
    // Read the device token before the local wipe rotates it, so the server
    // cleanup removes the row that actually belongs to this account.
    final fcmToken = await NotificationService.instance.getToken();
    await _clearLocalSession();

    if (token == null || token.isEmpty) return;
    unawaited(_remoteLogoutCleanup(token, fcmToken));
  }

  Future<void> _remoteLogoutCleanup(String token, String? fcmToken) async {
    final remote = ApiClient(tokenProvider: () async => token);
    try {
      if (fcmToken != null && fcmToken.isNotEmpty) {
        await remote.deleteJson('/fcm-tokens', body: {'token': fcmToken});
      }
    } catch (_) {}
    try {
      await remote.postJson('/auth/logout', const {});
    } catch (_) {}
  }

  Future<AuthUser?> me() async {
    final response = await _apiClient.getJson('/auth/me');
    final data = response['data'];
    if (data is! Map<String, dynamic>) return null;
    final user = AuthUser.fromJson(data);
    SessionCache.instance.setUser(user);
    await _bindAnalyticsUser(user);
    return user;
  }

  Future<String> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    final response = await _apiClient.postJson('/auth/change-password', {
      'current_password': currentPassword,
      'password': newPassword,
      'password_confirmation': confirmation,
    });
    return response['message']?.toString() ?? 'تم تغيير كلمة المرور بنجاح.';
  }

  /// Deletes the account after the server verifies [password].
  ///
  /// Nothing local is touched until the server confirms: a wrong password
  /// must leave the session — including push delivery — exactly as it was.
  /// Once the account is gone, local cleanup is best effort and never turns
  /// the success into a reported failure.
  Future<void> deleteAccount({required String password}) async {
    await _apiClient.deleteJson('/auth/account', body: {
      'password': password,
    });
    // No FCM unregister call here: the server already deleted this account's
    // tokens, and calling the API with the now-revoked bearer would 401 and
    // trigger the session-expired flow on top of this one.
    try {
      await _clearLocalSession();
    } catch (_) {
      // The account no longer exists server-side, so a leftover token can't
      // authenticate; the next launch lands on the signed-out flow.
    }
  }

  Future<void> _clearLocalSession() async {
    await _tokenStore.clearToken();
    SessionCache.instance.clear();
    await MobileContentService.clearAllCaches();
    // CV drafts and per-account preferences must not survive into the next
    // account on this device.
    await wipeUserScopedStorage();
    // Detach push from this account (rotates the FCM token).
    await NotificationService.instance.forgetDevice();
    await AnalyticsService.clearUser();
  }

  Future<AuthUser> updateProfile({
    required String name,
    int? jobTitleId,
    String? jobTitleOther,
    bool clearJobTitle = false,
  }) async {
    final body = <String, dynamic>{
      'name': name.trim(),
    };
    if (clearJobTitle) {
      body['job_title_id'] = null;
      body['job_title_other'] = null;
    } else {
      if (jobTitleId != null) {
        body['job_title_id'] = jobTitleId;
      }
      if (jobTitleOther != null) {
        body['job_title_other'] =
            jobTitleOther.trim().isEmpty ? null : jobTitleOther.trim();
      }
    }

    final response = await _apiClient.putJson('/auth/profile', body);
    final data = response['data'];
    final user = AuthUser.fromJson(
      data is Map<String, dynamic> ? data : const {},
    );
    SessionCache.instance.setUser(user);
    return user;
  }
}
