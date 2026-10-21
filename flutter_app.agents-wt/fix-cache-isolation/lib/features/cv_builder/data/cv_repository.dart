import 'dart:convert';
import 'dart:io';

import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/core/logging/app_log.dart';
import 'package:sirati/core/storage/user_scoped_storage.dart';
import 'package:sirati/features/cv_builder/data/cv_schema_migrator.dart';
import 'package:sirati/shared/models/cv_document.dart';

/// Lightweight CV descriptor used for lists, summaries, and dashboard cards.
class CvMetadata {
  final String id;
  final String title;
  final String exportLanguage;
  final DateTime updatedAt;
  final DateTime createdAt;
  final bool hasTranslationGaps;

  const CvMetadata({
    required this.id,
    required this.title,
    required this.exportLanguage,
    required this.updatedAt,
    required this.createdAt,
    this.hasTranslationGaps = false,
  });

  factory CvMetadata.fromDocument(CvDocument doc) {
    final now = DateTime.now();
    return CvMetadata(
      id: doc.id ?? 'cv_${now.millisecondsSinceEpoch}',
      title: doc.title?.trim().isNotEmpty == true
          ? doc.title!
          : (doc.exportLanguage == 'en'
              ? 'Untitled CV'
              : 'سيرة ذاتية بدون عنوان'),
      exportLanguage: doc.exportLanguage,
      updatedAt: doc.updatedAt ?? now,
      createdAt: doc.createdAt ?? now,
      hasTranslationGaps: doc.hasTranslationGaps,
    );
  }

  factory CvMetadata.fromJson(Map<String, dynamic> json) {
    return CvMetadata(
      id: json['id']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      exportLanguage: json['export_language']?.toString() ?? 'ar',
      updatedAt: DateTime.tryParse(json['updated_at']?.toString() ?? '') ??
          DateTime.now(),
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? '') ??
          DateTime.now(),
      hasTranslationGaps: json['has_translation_gaps'] == true,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'title': title,
        'export_language': exportLanguage,
        'updated_at': updatedAt.toIso8601String(),
        'created_at': createdAt.toIso8601String(),
        'has_translation_gaps': hasTranslationGaps,
      };

  CvMetadata copyWith({
    String? id,
    String? title,
    String? exportLanguage,
    DateTime? updatedAt,
    DateTime? createdAt,
    bool? hasTranslationGaps,
  }) {
    return CvMetadata(
      id: id ?? this.id,
      title: title ?? this.title,
      exportLanguage: exportLanguage ?? this.exportLanguage,
      updatedAt: updatedAt ?? this.updatedAt,
      createdAt: createdAt ?? this.createdAt,
      hasTranslationGaps: hasTranslationGaps ?? this.hasTranslationGaps,
    );
  }
}

/// Abstract storage engine interface enabling atomic file, memory, or prefs operations.
abstract class CvStorageEngine {
  Future<List<String>> listIds();
  Future<String?> read(String id);
  Future<void> writeAtomic(String id, String content);
  Future<void> delete(String id);
  Future<bool> exists(String id);
}

/// In-memory storage engine primarily used for fast, deterministic unit tests.
class MemoryCvStorageEngine implements CvStorageEngine {
  final Map<String, String> _store = {};
  final Map<String, String> _staging = {};

  @override
  Future<List<String>> listIds() async => _store.keys.toList();

  @override
  Future<String?> read(String id) async => _store[id];

  @override
  Future<void> writeAtomic(String id, String content) async {
    // Stage then commit
    _staging[id] = content;
    _store[id] = _staging.remove(id)!;
  }

  @override
  Future<void> delete(String id) async {
    _store.remove(id);
    _staging.remove(id);
  }

  @override
  Future<bool> exists(String id) async => _store.containsKey(id);

  void clear() {
    _store.clear();
    _staging.clear();
  }
}

/// Atomic file storage engine for mobile platforms.
///
/// Ensures an interrupted save never corrupts an existing document by writing
/// to a `.tmp` file and atomically renaming to `.json`.
class FileCvStorageEngine implements CvStorageEngine {
  final Directory baseDirectory;

  const FileCvStorageEngine({required this.baseDirectory});

  File _fileFor(String id) => File('${baseDirectory.path}/$id.json');
  static int _writeCounter = 0;

  @override
  Future<List<String>> listIds() async {
    if (!await baseDirectory.exists()) return [];
    final files = await baseDirectory.list().toList();
    final ids = <String>[];
    for (final entity in files) {
      if (entity is File && entity.path.endsWith('.json')) {
        final name = entity.uri.pathSegments.last;
        ids.add(name.substring(0, name.length - 5));
      }
    }
    return ids;
  }

  @override
  Future<String?> read(String id) async {
    final file = _fileFor(id);
    if (!await file.exists()) return null;
    try {
      return await file.readAsString();
    } catch (e) {
      AppLog.event(
        AppLogEvent.cvLoadFailed,
        level: AppLogLevel.error,
        data: {'cv_id': id, 'phase': 'read'},
        error: e,
      );
      return null;
    }
  }

  @override
  Future<void> writeAtomic(String id, String content) async {
    if (!await baseDirectory.exists()) {
      await baseDirectory.create(recursive: true);
    }
    final uniqueSuffix =
        '${DateTime.now().microsecondsSinceEpoch}_${_writeCounter++}';
    final tmp = File('${baseDirectory.path}/${id}_$uniqueSuffix.tmp');
    final target = _fileFor(id);

    try {
      // Write to unique temporary staging file with explicit flush
      await tmp.writeAsString(content, flush: true);

      // Atomic rename replaces target safely
      await tmp.rename(target.path);
    } catch (e) {
      // Clean up orphaned temp file if rename or write failed
      if (await tmp.exists()) {
        await tmp.delete().catchError((_) => tmp);
      }
      rethrow;
    }
  }

  @override
  Future<void> delete(String id) async {
    final file = _fileFor(id);
    if (await file.exists()) {
      await file.delete();
    }
  }

  @override
  Future<bool> exists(String id) async => await _fileFor(id).exists();
}

/// SharedPreferences storage engine for web or fallback persistence.
class SharedPreferencesCvStorageEngine implements CvStorageEngine {
  static const prefix = UserScopedKeys.builderDocPrefix;
  static const indexKey = UserScopedKeys.builderIndexKey;

  Future<SharedPreferences> get _prefs => SharedPreferences.getInstance();

  @override
  Future<List<String>> listIds() async {
    final p = await _prefs;
    return p.getStringList(indexKey) ?? [];
  }

  @override
  Future<String?> read(String id) async {
    final p = await _prefs;
    return p.getString('$prefix$id');
  }

  @override
  Future<void> writeAtomic(String id, String content) async {
    final p = await _prefs;
    // Direct atomic write at SharedPreferences layer
    await p.setString('$prefix$id', content);

    // Update index
    final ids = (p.getStringList(indexKey) ?? []).toSet()..add(id);
    await p.setStringList(indexKey, ids.toList());
  }

  @override
  Future<void> delete(String id) async {
    final p = await _prefs;
    await p.remove('$prefix$id');

    final ids = (p.getStringList(indexKey) ?? []).toSet()..remove(id);
    await p.setStringList(indexKey, ids.toList());
  }

  @override
  Future<bool> exists(String id) async {
    final p = await _prefs;
    return p.containsKey('$prefix$id');
  }
}

/// Repository contract for CV storage and lifecycle (SIRATI-33 & SIRATI-35).
abstract class CvRepository {
  Future<List<CvMetadata>> listCvs();
  Future<CvDocument?> getCv(String id);
  Future<void> saveCv(CvDocument document, {bool touchUpdatedAt = true});
  Future<CvDocument> duplicateCv(String id, {String? newTitle});
  Future<void> renameCv(String id, String newTitle);
  Future<void> deleteCv(String id);
  Future<void> restoreCv(String id);
}

/// Offline-first CV repository with automatic schema migration and soft-delete undo.
class LocalCvRepository implements CvRepository {
  LocalCvRepository({CvStorageEngine? engine})
      : _engine = engine ?? MemoryCvStorageEngine();

  final CvStorageEngine _engine;
  final Map<String, CvDocument> _softDeleted = {};

  @override
  Future<List<CvMetadata>> listCvs() async {
    final ids = await _engine.listIds();
    final list = <CvMetadata>[];

    for (final id in ids) {
      try {
        final doc = await getCv(id);
        if (doc != null) {
          list.add(CvMetadata.fromDocument(doc));
        }
      } catch (e) {
        AppLog.event(
          AppLogEvent.cvLoadFailed,
          level: AppLogLevel.error,
          data: {'cv_id': id, 'phase': 'metadata'},
          error: e,
        );
      }
    }

    // Sort descending by updated date
    list.sort((a, b) => b.updatedAt.compareTo(a.updatedAt));
    return list;
  }

  @override
  Future<CvDocument?> getCv(String id) async {
    final rawString = await _engine.read(id);
    if (rawString == null || rawString.trim().isEmpty) return null;

    try {
      final decoded = jsonDecode(rawString);
      if (decoded is! Map<String, dynamic>) return null;

      // Automated forward migration (SIRATI-34) in memory without mutating disk
      final migrationResult = CvSchemaMigrator.migrate(decoded);
      final doc = CvDocument.fromJson(migrationResult.data);

      if (migrationResult.wasMigrated) {
        AppLog.event(
          AppLogEvent.cvMigrated,
          data: {
            'cv_id': id,
            'from_version': migrationResult.originalVersion,
            'to_version': migrationResult.finalVersion,
          },
        );
      }

      return doc;
    } catch (e, stack) {
      AppLog.event(
        AppLogEvent.cvParseFailed,
        level: AppLogLevel.error,
        data: {'cv_id': id},
        error: e,
        stackTrace: stack,
      );
      rethrow;
    }
  }

  @override
  Future<void> saveCv(CvDocument document, {bool touchUpdatedAt = true}) async {
    final now = DateTime.now();
    final id = document.id ?? CvDocument.generateId();
    final toSave = document.copyWith(
      id: id,
      updatedAt: touchUpdatedAt ? now : (document.updatedAt ?? now),
      createdAt: document.createdAt ?? now,
    );

    final rawJson = jsonEncode(toSave.toJson());
    await _engine.writeAtomic(id, rawJson);

    // Remove from soft-deleted if re-saved
    _softDeleted.remove(id);
  }

  @override
  Future<CvDocument> duplicateCv(String id, {String? newTitle}) async {
    final original = await getCv(id);
    if (original == null) {
      throw ArgumentError('Cannot duplicate non-existent CV with id: $id');
    }

    final now = DateTime.now();
    final newId = CvDocument.generateId();
    final fallbackTitle = original.exportLanguage == 'en'
        ? 'Copy of ${original.title ?? 'CV'}'
        : 'نسخة من ${original.title ?? 'السيرة الذاتية'}';

    final clone = original
        .duplicate(
          newId: newId,
          newTitle: newTitle ?? fallbackTitle,
          newUpdatedAt: now,
        )
        .copyWith(
          createdAt: now,
          updatedAt: now,
        );

    await saveCv(clone);
    return clone;
  }

  @override
  Future<void> renameCv(String id, String newTitle) async {
    final doc = await getCv(id);
    if (doc == null) {
      throw ArgumentError('Cannot rename non-existent CV with id: $id');
    }
    final renamed = doc.copyWith(
      title: newTitle.trim(),
      updatedAt: DateTime.now(),
    );
    await saveCv(renamed);
  }

  @override
  Future<void> deleteCv(String id) async {
    try {
      final doc = await getCv(id);
      if (doc != null) {
        _softDeleted[id] = doc;
      }
    } catch (e) {
      AppLog.event(
        AppLogEvent.cvDeleteFailed,
        level: AppLogLevel.warn,
        data: {'cv_id': id, 'phase': 'undo_cache'},
        error: e,
      );
    }
    await _engine.delete(id);
  }

  @override
  Future<void> restoreCv(String id) async {
    final cached = _softDeleted.remove(id);
    if (cached != null) {
      await saveCv(cached, touchUpdatedAt: false);
    }
  }
}
