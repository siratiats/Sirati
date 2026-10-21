/// One paginated history list (analyses or generated CVs) as shown so far.
class HistoryPage<T> {
  const HistoryPage({
    required this.items,
    required this.page,
    required this.hasMore,
    this.loadingMore = false,
    this.loadMoreFailed = false,
  });

  final List<T> items;

  /// Last page merged into [items].
  final int page;
  final bool hasMore;
  final bool loadingMore;

  /// The last "load more" failed; the button offers a retry.
  final bool loadMoreFailed;

  HistoryPage<T> copyWith({bool? loadingMore, bool? loadMoreFailed}) =>
      HistoryPage(
        items: items,
        page: page,
        hasMore: hasMore,
        loadingMore: loadingMore ?? this.loadingMore,
        loadMoreFailed: loadMoreFailed ?? this.loadMoreFailed,
      );

  /// Appends page [nextPage]. Rows already shown are skipped: when new
  /// items are created between requests, offset paging shifts and the next
  /// page repeats rows from the previous one.
  HistoryPage<T> append(
    List<T> nextItems, {
    required int nextPage,
    required bool nextHasMore,
    required Object Function(T item) idOf,
  }) {
    final seen = {for (final item in items) idOf(item)};
    return HistoryPage(
      items: [
        ...items,
        for (final item in nextItems)
          if (seen.add(idOf(item))) item,
      ],
      page: nextPage,
      hasMore: nextHasMore,
    );
  }
}

/// Localised label for how an analysis was submitted.
String inputMethodLabel(String? method, {required bool english}) =>
    switch (method) {
      'upload' => english ? 'File' : 'ملف',
      'mixed' => english ? 'File + text' : 'ملف ونص',
      _ => english ? 'Pasted text' : 'لصق نص',
    };
