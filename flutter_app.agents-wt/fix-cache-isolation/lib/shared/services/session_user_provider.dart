import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/shared/services/session_cache.dart';

/// Id of the signed-in account (null when signed out). Providers that hold
/// account data in memory watch this so they rebuild empty when the account
/// changes instead of carrying one user's data into the next session.
final sessionUserIdProvider = Provider<int?>((ref) {
  final user = SessionCache.instance.user;
  void onChange() => ref.invalidateSelf();
  user.addListener(onChange);
  ref.onDispose(() => user.removeListener(onChange));
  return user.value?.id;
});
