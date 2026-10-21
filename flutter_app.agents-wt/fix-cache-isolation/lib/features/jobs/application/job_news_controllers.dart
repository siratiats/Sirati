import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/features/jobs/domain/job_news_feed.dart';
import 'package:sirati/features/jobs/domain/job_news_filters.dart';
import 'package:sirati/shared/services/mobile_content_service_provider.dart';

/// Chip / search selections for the job-news list.
final jobNewsFiltersProvider =
    NotifierProvider.autoDispose<JobNewsFiltersController, JobNewsFilters>(
  JobNewsFiltersController.new,
);

class JobNewsFiltersController extends Notifier<JobNewsFilters> {
  @override
  JobNewsFilters build() => const JobNewsFilters();

  void setPreset(String preset) => state = state.copyWith(preset: preset);
  void setCategory(String category) =>
      state = state.copyWith(category: category);
  void setCity(String city) => state = state.copyWith(city: city);
  void setQuery(String query) => state = state.copyWith(query: query.trim());

  /// Toggles the "matched to my profile" preset from the profile banner.
  void toggleMatched() => setPreset(
        state.preset == 'matched' ? JobNewsFilters.all : 'matched',
      );
}

/// Job-news feed for the current filters, keyed by UI language.
///
/// The first load may be served from cache; every later load (filter change,
/// pull-to-refresh, returning to the tab) bypasses it so the list reflects
/// backend changes immediately.
final jobNewsFeedProvider = AsyncNotifierProvider.autoDispose
    .family<JobNewsFeedController, JobNewsFeed, bool>(
  JobNewsFeedController.new,
  // The screen has an explicit Retry; don't silently hammer a failing API.
  retry: (_, __) => null,
);

class JobNewsFeedController extends AsyncNotifier<JobNewsFeed> {
  JobNewsFeedController(this.english);

  final bool english;
  bool _loadedOnce = false;

  @override
  Future<JobNewsFeed> build() async {
    final filters = ref.watch(jobNewsFiltersProvider);
    final force = _loadedOnce;
    _loadedOnce = true;

    final data = await ref.read(mobileContentServiceProvider).jobNews(
          english,
          category: filters.categoryParam,
          preset: filters.presetParam,
          city: filters.cityParam,
          isRemote: filters.isRemote,
          query: filters.query,
          force: force,
        );
    return JobNewsFeed.fromPayload(data, english: english);
  }
}

/// Re-fetches the feed (bypassing the cache) and completes once new data or
/// an error is in. Errors surface through the provider state.
Future<void> refreshJobNews(WidgetRef ref, {required bool english}) async {
  try {
    final next = ref.refresh(jobNewsFeedProvider(english).future);
    await next;
  } catch (_) {}
}
