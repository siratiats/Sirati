import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/features/dashboard/domain/history_page.dart';
import 'package:sirati/shared/models/cv_analysis.dart';
import 'package:sirati/shared/models/generated_cv.dart';
import 'package:sirati/shared/services/cv_api_service.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';

/// Shared paging behaviour for the two history tabs. Each tab loads and
/// fails independently, so one broken list never hides the other.
abstract class PagedHistoryController<T> extends AsyncNotifier<HistoryPage<T>> {
  /// Bumped on every (re)build so a "load more" that started before a
  /// refresh can't append onto the refreshed list.
  int _generation = 0;

  Future<PagedList<T>> fetch(CvApiService api, int page);
  Object idOf(T item);

  @override
  Future<HistoryPage<T>> build() async {
    _generation++;
    final first = await fetch(ref.read(cvApiServiceProvider), 1);
    return HistoryPage(
      items: first.items,
      page: first.currentPage,
      hasMore: first.hasMore,
    );
  }

  /// Loads the next page. Ignored while one is loading or when there are no
  /// more. A failure keeps the rows and flags [HistoryPage.loadMoreFailed].
  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || current.loadingMore || !current.hasMore) return;
    final generation = _generation;
    final api = ref.read(cvApiServiceProvider);
    state =
        AsyncData(current.copyWith(loadingMore: true, loadMoreFailed: false));
    try {
      final next = await fetch(api, current.page + 1);
      if (!ref.mounted || generation != _generation) return;
      state = AsyncData(current.append(
        next.items,
        nextPage: next.currentPage,
        nextHasMore: next.hasMore,
        idOf: idOf,
      ));
    } catch (_) {
      if (!ref.mounted || generation != _generation) return;
      state = AsyncData(
        current.copyWith(loadingMore: false, loadMoreFailed: true),
      );
    }
  }
}

final analysesHistoryProvider = AsyncNotifierProvider.autoDispose<
    AnalysesHistoryController, HistoryPage<CvAnalysis>>(
  AnalysesHistoryController.new,
  retry: (_, __) => null,
);

class AnalysesHistoryController extends PagedHistoryController<CvAnalysis> {
  @override
  Future<PagedList<CvAnalysis>> fetch(CvApiService api, int page) =>
      api.listAnalysesPage(page: page);

  @override
  Object idOf(CvAnalysis item) => item.id;
}

final generatedCvsHistoryProvider = AsyncNotifierProvider.autoDispose<
    GeneratedCvsHistoryController, HistoryPage<GeneratedCv>>(
  GeneratedCvsHistoryController.new,
  retry: (_, __) => null,
);

class GeneratedCvsHistoryController
    extends PagedHistoryController<GeneratedCv> {
  @override
  Future<PagedList<GeneratedCv>> fetch(CvApiService api, int page) =>
      api.listGeneratedCvsPage(page: page);

  @override
  Object idOf(GeneratedCv item) => item.id;
}
