import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/features/dashboard/domain/dashboard_summary.dart';
import 'package:sirati/shared/services/mobile_content_service_provider.dart';
import 'package:sirati/shared/services/session_cache.dart';

/// Dashboard summary keyed by UI language.
///
/// The first load may be served from cache; every later load (retry,
/// pull-to-refresh, returning from notifications) bypasses it.
final dashboardProvider = AsyncNotifierProvider.autoDispose
    .family<DashboardController, DashboardSummary, bool>(
  DashboardController.new,
  // The screen has an explicit Retry; don't silently hammer a failing API.
  retry: (_, __) => null,
);

class DashboardController extends AsyncNotifier<DashboardSummary> {
  DashboardController(this.english);

  final bool english;
  bool _loadedOnce = false;

  @override
  Future<DashboardSummary> build() async {
    final force = _loadedOnce;
    _loadedOnce = true;

    final session = SessionCache.instance;
    final data = await ref
        .read(mobileContentServiceProvider)
        .dashboard(english, force: force);
    final summary = DashboardSummary.fromPayload(
      data,
      english: english,
      cachedName: session.user.value?.name ?? '',
    );
    // Keep the header badge on other tabs in sync with the server count.
    final unread = summary.unreadNotifications;
    if (unread != null && ref.mounted) session.unreadCount = unread;
    return summary;
  }
}

/// Re-fetches (bypassing the cache) and completes once new data or an error
/// is in. Errors surface through the provider state.
Future<void> refreshDashboard(WidgetRef ref, {required bool english}) async {
  try {
    final next = ref.refresh(dashboardProvider(english).future);
    await next;
  } catch (_) {}
}
