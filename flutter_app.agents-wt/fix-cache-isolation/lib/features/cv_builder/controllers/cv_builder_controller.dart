import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/core/logging/app_log.dart';
import 'package:sirati/core/storage/user_scoped_storage.dart';
import 'package:sirati/features/cv_builder/data/cv_repository.dart';
import 'package:sirati/shared/models/cv_document.dart';

/// Controller coordinating the interactive CV builder, section state,
/// debounced autosave, and crash recovery (SIRATI-42).
class CvBuilderController extends ChangeNotifier {
  static const draftPrefix = UserScopedKeys.builderDraftPrefix;
  static const debounceDuration = Duration(milliseconds: 600);

  CvBuilderController({
    required CvDocument initialDocument,
    required CvRepository repository,
  })  : _document = initialDocument,
        _repository = repository;

  CvDocument _document;
  final CvRepository _repository;

  Timer? _debounceTimer;
  Timer? _draftTimer;
  int _draftSequence = 0;
  bool _isSaving = false;
  DateTime? _lastSavedAt;
  bool _disposed = false;
  bool _isDirty = false;
  Future<void>? _inFlight;

  CvDocument get document => _document;
  bool get isSaving => _isSaving;
  DateTime? get lastSavedAt => _lastSavedAt;
  bool get isDirty => _isDirty;

  @override
  void dispose() {
    _disposed = true;
    _debounceTimer?.cancel();
    _draftTimer?.cancel();
    super.dispose();
  }

  /// Updates the active document state and triggers a debounced autosave.
  void updateDocument(CvDocument updated) {
    _document = updated.copyWith(updatedAt: DateTime.now());
    _isDirty = true;
    notifyListeners();
    _scheduleDraftBackup();
    _scheduleAutosave();
  }

  void _scheduleDraftBackup() {
    _draftTimer?.cancel();
    _draftTimer = Timer(const Duration(milliseconds: 200), _stageDraftBackup);
  }

  Future<void> _stageDraftBackup() async {
    if (_document.id != null) {
      final id = _document.id!;
      final seq = ++_draftSequence;
      final payload = jsonEncode(_document.toJson());
      try {
        final prefs = await SharedPreferences.getInstance();
        if (!_disposed && seq == _draftSequence && _isDirty) {
          await prefs.setString('$draftPrefix$id', payload);
        }
      } catch (e) {
        AppLog.event(
          AppLogEvent.cvDraftBackupFailed,
          level: AppLogLevel.warn,
          data: {'cv_id': id},
          error: e,
        );
      }
    }
  }

  void _scheduleAutosave() {
    _debounceTimer?.cancel();
    _debounceTimer =
        Timer(debounceDuration, () => unawaited(saveImmediately()));
  }

  /// Commits the current document to the repository.
  ///
  /// Saves never overlap: a call made while one is running waits for it,
  /// then saves whatever is current. The document is only marked clean when
  /// the saved snapshot is still the current one — an edit made during the
  /// write stays dirty and is saved next.
  ///
  /// Returns whether this call's write succeeded. Safe to call on app
  /// backgrounding, screen pop, or from the Save button.
  Future<bool> saveImmediately() async {
    if (_disposed) return !_isDirty;
    _debounceTimer?.cancel();

    while (_inFlight != null) {
      await _inFlight;
      if (_disposed) return !_isDirty;
    }

    final done = Completer<void>();
    _inFlight = done.future;
    final snapshot = _document;
    _draftTimer?.cancel();
    _isSaving = true;
    notifyListeners();

    // Stage the draft first so uncommitted state survives a failed or
    // interrupted repository write.
    await _stageDraftBackup();

    var ok = false;
    try {
      await _repository.saveCv(snapshot);
      ok = true;
      _lastSavedAt = DateTime.now();
      if (identical(_document, snapshot)) {
        _isDirty = false;
        // Durable save of the current state: the crash draft is obsolete.
        if (snapshot.id != null) await clearDraft(snapshot.id!);
      }
    } catch (e, stack) {
      AppLog.event(
        AppLogEvent.cvAutosaveFailed,
        level: AppLogLevel.error,
        data: {'cv_id': snapshot.id},
        error: e,
        stackTrace: stack,
      );
    } finally {
      _inFlight = null;
      done.complete();
      if (!_disposed) {
        _isSaving = false;
        notifyListeners();
      }
    }
    return ok;
  }

  /// Saves until the document is clean, stopping at the first failure.
  /// Returns whether everything is persisted.
  Future<bool> flush() async {
    // Bounded: each pass only repeats if the user edited during the write.
    for (var pass = 0; pass < 3 && _isDirty; pass++) {
      if (!await saveImmediately()) return false;
    }
    return !_isDirty;
  }

  /// Checks if a crashed / uncommitted draft exists for [cvId] that is newer
  /// than the saved version.
  static Future<CvDocument?> checkDraftRecovery(
      String cvId, DateTime? lastSavedAt) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final rawDraft = prefs.getString('$draftPrefix$cvId');
      if (rawDraft == null || rawDraft.isEmpty) return null;

      final decoded = jsonDecode(rawDraft);
      if (decoded is! Map<String, dynamic>) return null;

      final draftDoc = CvDocument.fromJson(decoded);
      if (draftDoc.updatedAt == null) return null;

      if (lastSavedAt == null) {
        return draftDoc;
      }

      // If draft is strictly newer than the last durable save, offer recovery
      if (draftDoc.updatedAt!.isAfter(lastSavedAt)) {
        return draftDoc;
      }
      return null;
    } catch (e) {
      AppLog.event(
        AppLogEvent.cvDraftRecovered,
        level: AppLogLevel.error,
        data: {'cv_id': cvId, 'phase': 'check'},
        error: e,
      );
      return null;
    }
  }

  /// Discards any saved draft backup after explicit user save or discard.
  static Future<void> clearDraft(String cvId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('$draftPrefix$cvId');
    } catch (_) {}
  }

  // ── Granular Section Mutation Helpers ────────────────────────────────────────

  void updatePersonal(PersonalDetails personal) {
    updateDocument(_document.copyWith(personal: personal));
  }

  void updateSummary(LocalizedText summary) {
    updateDocument(_document.copyWith(summary: summary));
  }

  void setExperience(List<ExperienceEntry> entries) {
    updateDocument(_document.copyWith(experience: entries));
  }

  void setEducation(List<EducationEntry> entries) {
    updateDocument(_document.copyWith(education: entries));
  }

  void setSkills(List<Skill> skills) {
    updateDocument(_document.copyWith(skills: skills));
  }

  void setLanguages(List<LanguageSkill> languages) {
    updateDocument(_document.copyWith(languages: languages));
  }

  void setCertifications(List<Certification> certifications) {
    updateDocument(_document.copyWith(certifications: certifications));
  }

  void setProjects(List<Project> projects) {
    updateDocument(_document.copyWith(projects: projects));
  }

  void setCustomSections(List<CustomSection> sections) {
    updateDocument(_document.copyWith(customSections: sections));
  }

  void reorderSections(int oldIndex, int newIndex) {
    final currentOrder = List<String>.from(
      _document.sectionOrder ?? CvDocument.defaultSectionOrder,
    );
    if (oldIndex < 0 || oldIndex >= currentOrder.length) return;
    newIndex = newIndex.clamp(0, currentOrder.length);
    if (oldIndex < newIndex) {
      newIndex -= 1;
    }
    // Dropping an item where it was is not an edit.
    if (newIndex == oldIndex) return;
    final item = currentOrder.removeAt(oldIndex);
    currentOrder.insert(newIndex, item);
    updateDocument(_document.copyWith(sectionOrder: currentOrder));
  }
}
