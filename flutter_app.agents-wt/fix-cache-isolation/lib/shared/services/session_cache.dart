import 'package:flutter/foundation.dart';

import 'package:sirati/shared/models/auth_session.dart';

/// In-memory session identity for headers / Settings.
///
/// Populated by splash bootstrap, login, register, and profile updates.
class SessionCache {
  SessionCache._();

  static final SessionCache instance = SessionCache._();

  final ValueNotifier<AuthUser?> user = ValueNotifier<AuthUser?>(null);

  int unreadCount = 0;

  void setUser(AuthUser? value) {
    user.value = value;
  }

  void clear() {
    user.value = null;
    unreadCount = 0;
  }
}
