import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/storage/preference_store.dart';
import 'package:sirati/features/settings/domain/notification_item.dart';
import 'package:sirati/features/settings/domain/profile_update.dart';
import 'package:sirati/shared/models/auth_session.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';
import 'package:sirati/shared/services/mobile_content_service_provider.dart';
import 'package:sirati/shared/services/notification_engagement_service.dart';
import 'package:sirati/shared/services/notification_service.dart';
import 'package:sirati/shared/services/session_cache.dart';

/// Signed-in user for the settings header: fresh from the server when
/// reachable, otherwise the cached session user. Errors only when neither
/// exists.
final settingsUserProvider = FutureProvider.autoDispose<AuthUser?>(
  (ref) async {
    final cached = SessionCache.instance.user.value;
    try {
      return await ref.read(authApiServiceProvider).me() ?? cached;
    } on ApiException {
      if (cached != null) return cached;
      rethrow;
    }
  },
  retry: (_, __) => null,
);

/// Side effects behind the push-notification switch; tests replace it.
class NotificationPrefsGateway {
  const NotificationPrefsGateway();

  Future<bool?> fetchServer() =>
      NotificationEngagementService.instance.fetchServerEnabled();
  Future<bool> readLocal() =>
      const PreferenceStore().readNotificationsEnabled();
  Future<void> saveLocal(bool enabled) =>
      const PreferenceStore().saveNotificationsEnabled(enabled);

  /// Records the choice server-side so automation respects an opt-out even
  /// when the device token can't be removed.
  Future<void> syncServer(bool enabled) =>
      NotificationEngagementService.instance.syncPreferenceEnabled(enabled);

  Future<void> enableDevicePush() async {
    await NotificationService.instance.requestPermission();
    await NotificationService.instance.registerToken();
  }

  Future<void> disableDevicePush() =>
      NotificationService.instance.unregisterToken(optOut: true);
}

final notificationPrefsGatewayProvider = Provider<NotificationPrefsGateway>(
    (ref) => const NotificationPrefsGateway());

/// The push-notification preference shown by the settings switch.
final notificationPreferenceProvider =
    AsyncNotifierProvider.autoDispose<NotificationPreferenceController, bool>(
  NotificationPreferenceController.new,
  retry: (_, __) => null,
);

class NotificationPreferenceController extends AsyncNotifier<bool> {
  /// Latest value the user asked for while a change was still applying.
  bool? _pending;
  bool _applying = false;

  @override
  Future<bool> build() async {
    final gateway = ref.read(notificationPrefsGatewayProvider);
    try {
      return await gateway.fetchServer() ?? await gateway.readLocal();
    } catch (_) {
      // Unreadable storage: show the default (on) rather than an error.
      return true;
    }
  }

  /// Applies [enabled]. Rapid toggles collapse: changes apply one at a time
  /// and only the latest requested value is applied after the current one.
  ///
  /// Returns false when a step failed; the switch keeps the chosen value
  /// because the server-side preference (which automation honours) and the
  /// local preference are written before the device step.
  Future<bool> setEnabled(bool enabled) async {
    state = AsyncData(enabled);
    if (_applying) {
      _pending = enabled;
      return true;
    }
    _applying = true;
    // Read once: the screen may close (disposing this provider) mid-apply,
    // and the change must still finish.
    final gateway = ref.read(notificationPrefsGatewayProvider);
    var ok = true;
    var next = enabled;
    try {
      while (true) {
        ok = await _apply(gateway, next) && ok;
        final queued = _pending;
        _pending = null;
        if (queued == null || queued == next) break;
        next = queued;
      }
    } finally {
      _applying = false;
    }
    return ok;
  }

  Future<bool> _apply(NotificationPrefsGateway gateway, bool enabled) async {
    var ok = true;
    try {
      await gateway.saveLocal(enabled);
    } catch (_) {
      ok = false;
    }
    try {
      await gateway.syncServer(enabled);
    } catch (_) {
      ok = false;
    }
    // The device step runs even if the server step failed: turning push off
    // must stop deliveries to this device regardless.
    try {
      if (enabled) {
        await gateway.enableDevicePush();
      } else {
        await gateway.disableDevicePush();
      }
    } catch (_) {
      ok = false;
    }
    return ok;
  }
}

sealed class ProfileSaveOutcome {
  const ProfileSaveOutcome();
}

final class ProfileSaved extends ProfileSaveOutcome {
  const ProfileSaved();
}

final class ProfileRejected extends ProfileSaveOutcome {
  const ProfileRejected(this.error);
  final ApiException error;
}

final class ProfileSaveFailed extends ProfileSaveOutcome {
  const ProfileSaveFailed();
}

/// A save was already running.
final class ProfileSaveIgnored extends ProfileSaveOutcome {
  const ProfileSaveIgnored();
}

/// Whether a profile save is in flight.
final profileSaveProvider =
    NotifierProvider.autoDispose<ProfileSaveController, bool>(
  ProfileSaveController.new,
);

class ProfileSaveController extends Notifier<bool> {
  @override
  bool build() => false;

  Future<ProfileSaveOutcome> save(ProfileUpdate update) async {
    if (state) return const ProfileSaveIgnored();
    state = true;
    try {
      final auth = ref.read(authApiServiceProvider);
      switch (update.jobTitle) {
        case JobTitleUnchanged():
          await auth.updateProfile(name: update.name);
        case JobTitleCleared():
          await auth.updateProfile(name: update.name, clearJobTitle: true);
        case JobTitleSelected(:final id, :final otherText):
          await auth.updateProfile(
            name: update.name,
            jobTitleId: id,
            jobTitleOther: otherText?.trim(),
          );
      }
      return const ProfileSaved();
    } on ApiException catch (e) {
      return ProfileRejected(e);
    } catch (_) {
      return const ProfileSaveFailed();
    } finally {
      if (ref.mounted) state = false;
    }
  }
}

/// The in-app notification inbox.
final notificationsInboxProvider = AsyncNotifierProvider.autoDispose<
    NotificationsInboxController, List<NotificationItem>>(
  NotificationsInboxController.new,
  retry: (_, __) => null,
);

class NotificationsInboxController
    extends AsyncNotifier<List<NotificationItem>> {
  bool _loadedOnce = false;

  @override
  Future<List<NotificationItem>> build() async {
    final force = _loadedOnce;
    _loadedOnce = true;
    final payload = await ref
        .read(mobileContentServiceProvider)
        .notifications(force: force);
    return NotificationItem.listFrom(payload);
  }

  void _syncBadge(List<NotificationItem> items) {
    SessionCache.instance.unreadCount = items.where((i) => !i.isRead).length;
  }

  /// Marks [item] read on the server; the row updates immediately and is
  /// restored if the request fails. Returns whether it succeeded.
  Future<bool> markRead(NotificationItem item) async {
    final id = item.id;
    final before = state.value;
    if (id == null || item.isRead || before == null) return true;
    final after = [
      for (final i in before) i.id == id ? i.asRead() : i,
    ];
    state = AsyncData(after);
    _syncBadge(after);
    try {
      await ref.read(mobileContentServiceProvider).markNotificationRead(id);
      return true;
    } catch (_) {
      if (ref.mounted) {
        state = AsyncData(before);
        _syncBadge(before);
      }
      return false;
    }
  }

  Future<bool> markAllRead() async {
    final before = state.value;
    if (before == null || before.every((i) => i.isRead)) return true;
    final after = [for (final i in before) i.asRead()];
    state = AsyncData(after);
    _syncBadge(after);
    try {
      await ref.read(mobileContentServiceProvider).markAllNotificationsRead();
      return true;
    } catch (_) {
      if (ref.mounted) {
        state = AsyncData(before);
        _syncBadge(before);
      }
      return false;
    }
  }
}
