import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/storage/preference_store.dart';
import 'package:sirati/features/cv_builder/domain/cv_form_data.dart';

/// A locally stored create-mode draft.
class SavedCvDraft {
  const SavedCvDraft({
    required this.data,
    required this.step,
    required this.savedAt,
  });

  final CvFormData data;
  final int step;
  final DateTime? savedAt;
}

/// Best-effort local draft persistence for the create-CV wizard.
///
/// Every method swallows storage errors: a failing keystore or corrupted
/// preferences file must never block or crash the wizard.
class CvDraftStore {
  const CvDraftStore(this._prefs);

  final PreferenceStore _prefs;

  /// The saved draft, or null when there is none, it has no real content,
  /// or it cannot be read.
  Future<SavedCvDraft?> read({required int stepCount}) async {
    try {
      final json = await _prefs.readCvDraft();
      if (json == null) return null;
      final data = CvFormData.fromDraftJson(json);
      if (!data.hasContent) return null;
      final rawSavedAt = json['savedAt']?.toString();
      return SavedCvDraft(
        data: data,
        step: CvFormData.stepFromDraftJson(json, stepCount: stepCount),
        savedAt: rawSavedAt == null || rawSavedAt.isEmpty
            ? null
            : DateTime.tryParse(rawSavedAt)?.toLocal(),
      );
    } catch (_) {
      return null;
    }
  }

  /// Saves [data]; an empty form clears any stored draft instead so the
  /// restore banner is never offered for nothing.
  Future<void> save(CvFormData data, {required int step}) async {
    if (!data.hasContent) {
      await clear();
      return;
    }
    try {
      await _prefs.saveCvDraft(
        data.toDraftJson(step: step, savedAt: DateTime.now()),
      );
    } catch (_) {}
  }

  Future<void> clear() async {
    try {
      await _prefs.clearCvDraft();
    } catch (_) {}
  }
}

final cvDraftStoreProvider = Provider<CvDraftStore>(
  (ref) => const CvDraftStore(PreferenceStore()),
);
