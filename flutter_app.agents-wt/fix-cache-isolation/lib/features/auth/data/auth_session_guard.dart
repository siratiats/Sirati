import 'dart:async';
import 'package:flutter/material.dart';
import 'package:sirati/core/network/analytics_service.dart';
import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/core/storage/auth_token_store.dart';
import 'package:sirati/core/storage/user_scoped_storage.dart';
import 'package:sirati/features/auth/presentation/login_screen.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';
import 'package:sirati/shared/services/notification_service.dart';
import 'package:sirati/shared/services/session_cache.dart';

/// Handles mid-session auth expiry (401) with a single redirect.
class AuthSessionGuard {
  AuthSessionGuard._();

  static bool _handling = false;
  static GlobalKey<NavigatorState>? _navigatorKey;
  static AuthTokenStore tokenStore = const AuthTokenStore();

  static void install({
    required GlobalKey<NavigatorState> navigatorKey,
    AuthTokenStore? store,
  }) {
    _navigatorKey = navigatorKey;
    if (store != null) tokenStore = store;
    ApiClient.onAuthExpired = handleAuthExpired;
  }

  /// Test hook — restores debounce and the default token store.
  static void resetForTest({AuthTokenStore? store}) {
    _handling = false;
    _navigatorKey = null;
    tokenStore = store ?? const AuthTokenStore();
    ApiClient.onAuthExpired = null;
  }

  /// Clear local session and push Login once (parallel 401s debounce).
  ///
  /// [requestToken] is the bearer token that received the 401. A delayed
  /// 401 for an older token is ignored when a newer session is already saved.
  static Future<void> handleAuthExpired({
    String? requestToken,
    bool showMessage = true,
  }) async {
    final current = await tokenStore.readToken();
    if (_isStaleRequest(requestToken, current)) return;
    if (_handling) return;
    _handling = true;
    try {
      try {
        await NotificationService.instance.unregisterToken();
      } catch (_) {}
      await tokenStore.clearToken();
      SessionCache.instance.clear();
      // Drop offline content so the next account never sees prior user data.
      await MobileContentService.clearAllCaches();
      await wipeUserScopedStorage();
      // The unregister above ran with a revoked bearer and usually 401s;
      // rotating the device token is what actually stops this account's
      // pushes reaching the device.
      await NotificationService.instance.forgetDevice();
      await AnalyticsService.clearUser();

      final nav = _navigatorKey?.currentState;
      if (nav == null) return;

      unawaited(nav.pushAndRemoveUntil(
        MaterialPageRoute(
          builder: (_) => LoginScreen(
            sessionExpiredNotice: showMessage,
          ),
        ),
        (route) => false,
      ));
    } finally {
      // Allow a future expiry after the user logs in again.
      Future<void>.delayed(const Duration(seconds: 2), () {
        _handling = false;
      });
    }
  }

  static bool _isStaleRequest(String? requestToken, String? currentToken) {
    final request = _bareToken(requestToken);
    final current = _bareToken(currentToken);

    if (request == null || request.isEmpty) {
      // No snapshot of the failing request. Do not kill a token that was
      // saved after this call started (login racing a startup 401).
      return current != null && current.isNotEmpty;
    }
    if (current == null || current.isEmpty) return false;
    return request != current;
  }

  static String? _bareToken(String? value) {
    if (value == null) return null;
    final trimmed = value.trim();
    const prefix = 'Bearer ';
    if (trimmed.startsWith(prefix)) {
      return trimmed.substring(prefix.length).trim();
    }
    return trimmed;
  }
}
