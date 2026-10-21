import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:sirati/core/routing/entitlement_store.dart';
import 'package:sirati/core/utils/async_state.dart';
import 'package:sirati/features/cv_builder/data/cv_repository.dart';
import 'package:sirati/shared/models/cv_document.dart';

/// Thrown when a free user reaches the maximum allowed number of saved CVs.
class CvLimitReachedException implements Exception {
  final int currentCount;
  final int limit;

  const CvLimitReachedException({
    required this.currentCount,
    required this.limit,
  });

  @override
  String toString() =>
      'CvLimitReachedException: Maximum free CV limit reached ($currentCount/$limit). Upgrade to premium for unlimited CVs.';
}

/// Controller managing multi-CV creation, duplication, renaming, and deletion (SIRATI-35).
///
/// Follows the established [AsyncState] + [ChangeNotifier] pattern (SIRATI-12).
class CvManageController extends ChangeNotifier {
  static const int defaultFreeTierLimit = 3;

  CvManageController({
    required CvRepository repository,
    int freeTierLimit = defaultFreeTierLimit,
    bool Function()? isPremiumProvider,
  })  : _repository = repository,
        _freeTierLimit = freeTierLimit,
        _isPremium = isPremiumProvider ?? (() => EntitlementStore.hasPremium);

  final CvRepository _repository;
  final int _freeTierLimit;
  final bool Function() _isPremium;

  AsyncState<List<CvMetadata>> _state = const AsyncLoading();
  String? _lastDeletedId;
  Timer? _undoTimer;
  int _generation = 0;
  bool _disposed = false;

  AsyncState<List<CvMetadata>> get state => _state;
  bool get canUndoDelete => _lastDeletedId != null;
  int get freeTierLimit => _freeTierLimit;

  @override
  void dispose() {
    _disposed = true;
    _undoTimer?.cancel();
    super.dispose();
  }

  /// Refreshes the list of CVs from repository, sorted by last modified descending.
  Future<void> load() async {
    final generation = ++_generation;
    _state = const AsyncLoading();
    notifyListeners();

    try {
      final items = await _repository.listCvs();
      if (_disposed || generation != _generation) return;
      _state = AsyncSuccess(List<CvMetadata>.unmodifiable(items));
    } catch (e) {
      if (_disposed || generation != _generation) return;
      _state = AsyncFailure(e);
    }
    if (_disposed) return;
    notifyListeners();
  }

  /// Creates a new empty CV, checking free tier limits first.
  Future<CvDocument> createCv({
    String? title,
    String exportLanguage = 'ar',
  }) async {
    final currentList = await _repository.listCvs();
    if (!_isPremium() && currentList.length >= _freeTierLimit) {
      throw CvLimitReachedException(
        currentCount: currentList.length,
        limit: _freeTierLimit,
      );
    }

    final newDoc = CvDocument.createEmpty(
      title: title,
      exportLanguage: exportLanguage,
    );
    await _repository.saveCv(newDoc);
    await load();
    return newDoc;
  }

  /// Duplicates a CV into an independent copy (deep clone), checking tier limits.
  Future<CvDocument> duplicateCv(String id, {String? newTitle}) async {
    final currentList = await _repository.listCvs();
    if (!_isPremium() && currentList.length >= _freeTierLimit) {
      throw CvLimitReachedException(
        currentCount: currentList.length,
        limit: _freeTierLimit,
      );
    }

    final cloned = await _repository.duplicateCv(id, newTitle: newTitle);
    await load();
    return cloned;
  }

  /// Renames an existing CV.
  Future<void> renameCv(String id, String newTitle) async {
    await _repository.renameCv(id, newTitle);
    await load();
  }

  /// Soft-deletes a CV and records it for undo.
  Future<void> deleteCv(String id) async {
    await _repository.deleteCv(id);
    _lastDeletedId = id;
    _undoTimer?.cancel();
    _undoTimer = Timer(const Duration(seconds: 15), () {
      _lastDeletedId = null;
      if (!_disposed) notifyListeners();
    });
    await load();
  }

  /// Undoes the last deletion if within grace period.
  Future<void> undoDelete() async {
    final id = _lastDeletedId;
    if (id == null) return;

    final currentList = await _repository.listCvs();
    if (!_isPremium() && currentList.length >= _freeTierLimit) {
      throw CvLimitReachedException(
        currentCount: currentList.length,
        limit: _freeTierLimit,
      );
    }

    _undoTimer?.cancel();
    _lastDeletedId = null;
    await _repository.restoreCv(id);
    await load();
  }
}
