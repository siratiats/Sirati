import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/core/storage/preference_store.dart';

/// Keys for data that belongs to the signed-in account, as opposed to the
/// device (language, theme, onboarding). Features store under these so a
/// sign-out can remove all of it in one place.
abstract final class UserScopedKeys {
  /// CV builder crash-recovery drafts, one per CV id (SharedPreferences).
  static const builderDraftPrefix = 'sirati_cv_draft_';

  /// CV builder documents and their index (SharedPreferences engine).
  static const builderDocPrefix = 'sirati_cv_doc_';
  static const builderIndexKey = 'sirati_cv_ids';

  /// Secure-storage keys holding account data.
  static const secureKeys = [
    PreferenceStore.cvDraftKey,
    PreferenceStore.educationInterestKey,
    PreferenceStore.notificationsKey,
  ];

  static bool isUserScopedPreference(String key) =>
      key.startsWith(builderDraftPrefix) ||
      key.startsWith(builderDocPrefix) ||
      key == builderIndexKey;
}

/// Removes everything account-specific stored on the device, so the next
/// account on a shared phone never sees (or is offered to restore) the
/// previous one's CV drafts or preferences.
///
/// Every step is independent and best effort: one failing store must not
/// leave the others behind.
Future<void> wipeUserScopedStorage({
  PreferenceStore prefs = const PreferenceStore(),
}) async {
  for (final key in UserScopedKeys.secureKeys) {
    try {
      await prefs.deleteKey(key);
    } catch (_) {}
  }
  try {
    final shared = await SharedPreferences.getInstance();
    for (final key in shared.getKeys().toList()) {
      if (UserScopedKeys.isUserScopedPreference(key)) {
        await shared.remove(key);
      }
    }
  } catch (_) {}
}
