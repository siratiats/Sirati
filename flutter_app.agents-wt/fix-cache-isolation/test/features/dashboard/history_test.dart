import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/dashboard/application/history_controllers.dart';
import 'package:sirati/features/dashboard/domain/history_page.dart';
import 'package:sirati/features/dashboard/presentation/history/history_cards.dart';
import 'package:sirati/features/dashboard/presentation/history_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/cv_analysis.dart';
import 'package:sirati/shared/models/generated_cv.dart';
import 'package:sirati/shared/services/cv_api_service.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

CvAnalysis _analysis(int id, {int score = 70, String method = 'paste'}) =>
    CvAnalysis.fromJson({
      'id': id,
      'target_job_title': 'Role $id',
      'score_total': score,
      'grade': 'B',
      'input_method': method,
    });

GeneratedCv _cv(int id, {int score = 70}) => GeneratedCv.fromJson({
      'id': id,
      'full_name': 'Sara $id',
      'target_job_title': 'Role $id',
      'score_total': score,
      'grade': 'B',
      'language': 'en',
    });

/// Serves pages of ids; each page request can be held or failed.
class _PagedApi extends CvApiService {
  final int lastPage = 3;
  final int perPage = 2;
  final requests = <String>[];
  Object? analysesError;
  Object? cvsError;
  Completer<void>? gate;

  /// Ids per page; overridable to simulate shifting pages.
  List<int> Function(int page)? idsFor;

  List<int> _ids(int page) =>
      idsFor?.call(page) ??
      [for (var i = 0; i < perPage; i++) (page - 1) * perPage + i + 1];

  @override
  Future<PagedList<CvAnalysis>> listAnalysesPage({int page = 1}) async {
    requests.add('a$page');
    await gate?.future;
    if (analysesError != null) throw analysesError!;
    return PagedList(
      items: [for (final id in _ids(page)) _analysis(id)],
      currentPage: page,
      lastPage: lastPage,
    );
  }

  @override
  Future<PagedList<GeneratedCv>> listGeneratedCvsPage({int page = 1}) async {
    requests.add('c$page');
    if (cvsError != null) throw cvsError!;
    return PagedList(
      items: [for (final id in _ids(page)) _cv(id)],
      currentPage: page,
      lastPage: lastPage,
    );
  }
}

void main() {
  group('HistoryPage.append', () {
    test('never duplicates an id and keeps first-seen order', () {
      for (final (first, next) in <(List<int>, List<int>)>[
        ([1, 2], [3, 4]),
        ([1, 2], [2, 3]), // page shifted by one new item
        ([1, 2], [1, 2]),
        ([], [1, 1, 2]),
        ([5], []),
      ]) {
        final page = HistoryPage<int>(items: first, page: 1, hasMore: true)
            .append(next, nextPage: 2, nextHasMore: false, idOf: (i) => i);
        expect(page.items.toSet().length, page.items.length,
            reason: '$first + $next');
        expect(page.items, [
          ...first,
          ...next.where((i) => !first.contains(i)).toSet(),
        ]);
        expect(page.page, 2);
        expect(page.hasMore, isFalse);
        expect(page.loadingMore, isFalse);
        expect(page.loadMoreFailed, isFalse);
      }
    });

    test('input method labels are localised and total', () {
      for (final m in ['upload', 'mixed', 'paste', null, 'other']) {
        expect(inputMethodLabel(m, english: true), isNot(matches(r'[؀-ۿ]')));
        expect(inputMethodLabel(m, english: false), matches(r'[؀-ۿ]'));
      }
    });
  });

  group('PagedHistoryController', () {
    late _PagedApi api;
    late ProviderContainer container;

    setUp(() {
      api = _PagedApi();
      container = ProviderContainer(
        overrides: [cvApiServiceProvider.overrideWithValue(api)],
      );
      container.listen(analysesHistoryProvider, (_, __) {});
    });
    tearDown(() => container.dispose());

    HistoryPage<CvAnalysis> page() =>
        container.read(analysesHistoryProvider).value!;
    AnalysesHistoryController ctrl() =>
        container.read(analysesHistoryProvider.notifier);

    test('pages until the end, then load-more is a no-op', () async {
      await container.read(analysesHistoryProvider.future);
      for (var i = 0; i < 5; i++) {
        await ctrl().loadMore();
      }
      expect(page().items.map((a) => a.id), [1, 2, 3, 4, 5, 6]);
      expect(page().hasMore, isFalse);
      expect(api.requests, ['a1', 'a2', 'a3']);
    });

    test('concurrent load-more calls fetch one page', () async {
      await container.read(analysesHistoryProvider.future);
      api.gate = Completer<void>();
      final calls = [for (var i = 0; i < 3; i++) ctrl().loadMore()];
      api.gate!.complete();
      await Future.wait(calls);
      expect(api.requests, ['a1', 'a2']);
    });

    test('a load-more that finishes after a refresh is discarded', () async {
      await container.read(analysesHistoryProvider.future);
      final oldGate = Completer<void>();
      api.gate = oldGate;
      final stale = ctrl().loadMore();
      await Future<void>.delayed(Duration.zero);

      api.gate = null;
      final refreshed = container.refresh(analysesHistoryProvider.future);
      await refreshed;
      oldGate.complete(); // the pre-refresh page 2 arrives late
      await stale;

      expect(page().items.map((a) => a.id), [1, 2],
          reason: 'page 2 of the old list must not land on the new one');
    });

    test('a failed load-more keeps the rows and can be retried', () async {
      await container.read(analysesHistoryProvider.future);
      api.analysesError = const ApiException('offline');
      await ctrl().loadMore();
      expect(page().items.map((a) => a.id), [1, 2]);
      expect(page().loadMoreFailed, isTrue);
      expect(page().loadingMore, isFalse);

      api.analysesError = null;
      await ctrl().loadMore();
      expect(page().items.map((a) => a.id), [1, 2, 3, 4]);
      expect(page().loadMoreFailed, isFalse);
    });

    test('shifted pages do not duplicate rows', () async {
      api.idsFor = (page) => page == 1 ? [5, 4] : [4, 3];
      container.invalidate(analysesHistoryProvider);
      await container.read(analysesHistoryProvider.future);
      await ctrl().loadMore();
      expect(page().items.map((a) => a.id), [5, 4, 3]);
    });
  });

  group('HistoryScreen', () {
    Widget app(CvApiService api, {ThemeData? theme}) => ProviderScope(
          overrides: [cvApiServiceProvider.overrideWithValue(api)],
          child: MaterialApp(
            theme: theme ?? AppTheme.lightFor(arabic: false),
            locale: const Locale('en'),
            supportedLocales: AppLocalizations.supportedLocales,
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            home: const HistoryScreen(),
          ),
        );

    setUp(() => AppLocale.languageCode.value = 'en');

    testWidgets('one failing tab does not hide the other', (tester) async {
      final api = _PagedApi()..analysesError = const ApiException('down');
      await tester.pumpWidget(app(api));
      await tester.pumpAndSettle();
      expect(find.byType(AnalysisHistoryCard), findsNothing);

      await tester.tap(find.text('Generated CVs'));
      await tester.pumpAndSettle();
      expect(find.byType(GeneratedCvHistoryCard), findsWidgets);
    });

    for (final (mode, theme) in [
      ('light', AppTheme.lightFor(arabic: false)),
      ('dark', AppTheme.darkFor(arabic: false)),
    ]) {
      testWidgets('history cards are AA in every score band ($mode)',
          (tester) async {
        await tester.pumpWidget(MaterialApp(
          theme: theme,
          locale: const Locale('en'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          home: Scaffold(
            body: ListView(
              children: [
                for (final score in [0, 49, 50, 64, 65, 79, 80, 100]) ...[
                  AnalysisHistoryCard(
                    analysis: _analysis(score, score: score, method: 'upload'),
                    onTap: () {},
                  ),
                  GeneratedCvHistoryCard(
                    cv: _cv(score, score: score),
                    onTap: () {},
                  ),
                ],
                LoadMoreFooter(loading: false, failed: true, onPressed: () {}),
              ],
            ),
          ),
        ));
        await tester.pumpAndSettle();
        expectAllTextAa('history $mode');
      });
    }
  });
}
