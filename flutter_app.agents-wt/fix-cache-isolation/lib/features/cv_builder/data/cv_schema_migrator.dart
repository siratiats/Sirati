import 'dart:convert';
import 'package:sirati/core/logging/app_log.dart';
import 'package:sirati/features/cv_builder/data/skill_storage_keys.dart';

/// Result of running schema migrations on a raw CV payload.
class MigrationResult {
  final Map<String, dynamic> data;
  final bool wasMigrated;
  final int originalVersion;
  final int finalVersion;
  final List<int> appliedMigrations;

  const MigrationResult({
    required this.data,
    required this.wasMigrated,
    required this.originalVersion,
    required this.finalVersion,
    this.appliedMigrations = const [],
  });
}

/// Thrown when loading a document produced by a future app version.
class UnsupportedSchemaVersionException implements Exception {
  final int documentVersion;
  final int supportedVersion;

  const UnsupportedSchemaVersionException({
    required this.documentVersion,
    required this.supportedVersion,
  });

  @override
  String toString() =>
      'UnsupportedSchemaVersionException: Document version $documentVersion is newer than current supported version $supportedVersion';
}

/// Thrown when a migration step fails to transform a payload.
class MigrationFailureException implements Exception {
  final int fromVersion;
  final int toVersion;
  final dynamic cause;

  const MigrationFailureException({
    required this.fromVersion,
    required this.toVersion,
    required this.cause,
  });

  @override
  String toString() =>
      'MigrationFailureException: Failed migrating from v$fromVersion to v$toVersion: $cause';
}

/// Forward schema migration chain for CV documents (SIRATI-34).
///
/// Guarantees that documents saved with older schemas open cleanly without data loss.
/// If any step in the migration fails, the original raw data is never mutated or deleted.
///
/// Documents already at [currentVersion] still pass through
/// [_backfillProficiencyEnglish]: empty `en` on known skill/language storage
/// keys is filled from [SkillStorageKeys.english] /
/// [LanguageLevelStorageKeys.english]. That is a data repair, not a schema
/// bump — unrecognised `ar` values and already-populated `en` are left alone.
class CvSchemaMigrator {
  static const int currentVersion = 1;

  /// Migrates [rawJson] forward to [currentVersion] if needed.
  static MigrationResult migrate(Map<String, dynamic> rawJson) {
    // 1. Determine incoming schema version (unversioned payloads default to 0)
    final dynamic rawVer = rawJson['schema_version'];
    final int version =
        rawVer is int ? rawVer : (int.tryParse(rawVer?.toString() ?? '') ?? 0);

    if (version > currentVersion) {
      throw UnsupportedSchemaVersionException(
        documentVersion: version,
        supportedVersion: currentVersion,
      );
    }

    final unversionedV1 = version == 0 &&
        (rawJson['personal'] is Map ||
            (rawJson['summary'] is Map && rawJson['summary'] is! String));

    // Deep copy so failed migrations never taint input
    Map<String, dynamic> currentData =
        jsonDecode(jsonEncode(rawJson)) as Map<String, dynamic>;
    final applied = <int>[];
    final originalVersion = unversionedV1 ? currentVersion : version;
    var currentV = originalVersion;

    try {
      if (unversionedV1) {
        currentData['schema_version'] = currentVersion;
      }

      while (currentV < currentVersion) {
        switch (currentV) {
          case 0:
            currentData = _migrateV0ToV1(currentData);
            applied.add(0);
            currentV = 1;
            break;
          default:
            throw MigrationFailureException(
              fromVersion: currentV,
              toVersion: currentV + 1,
              cause: 'No migration handler defined for schema v$currentV',
            );
        }
      }

      currentData['schema_version'] = currentVersion;
      final filled = _backfillProficiencyEnglish(currentData);

      return MigrationResult(
        data: currentData,
        wasMigrated: applied.isNotEmpty || filled,
        originalVersion: originalVersion,
        finalVersion: currentVersion,
        appliedMigrations: applied,
      );
    } catch (e, stack) {
      AppLog.event(
        AppLogEvent.cvMigrationFailed,
        level: AppLogLevel.error,
        data: {
          'from_version': version,
          'to_version': currentVersion,
        },
        error: e,
        stackTrace: stack,
      );
      if (e is MigrationFailureException) rethrow;
      throw MigrationFailureException(
        fromVersion: version,
        toVersion: currentVersion,
        cause: e,
      );
    }
  }

  /// Fills empty `en` on known proficiency/category keys. Idempotent.
  static bool _backfillProficiencyEnglish(Map<String, dynamic> data) {
    var changed = false;
    changed = _backfillSection(
          data['skills'],
          SkillStorageKeys.english,
          const ['level', 'category'],
        ) ||
        changed;
    changed = _backfillSection(
          data['languages'],
          LanguageLevelStorageKeys.english,
          const ['level'],
        ) ||
        changed;
    return changed;
  }

  static bool _backfillSection(
    dynamic section,
    Map<String, String> english,
    List<String> fields,
  ) {
    if (section is! List) return false;
    var changed = false;
    for (final item in section) {
      if (item is! Map) continue;
      for (final field in fields) {
        if (_fillEmptyEn(item[field], english)) changed = true;
      }
    }
    return changed;
  }

  static bool _fillEmptyEn(dynamic field, Map<String, String> english) {
    if (field is! Map) return false;
    final ar = field['ar']?.toString() ?? '';
    final en = field['en']?.toString() ?? '';
    if (en.trim().isNotEmpty) return false;
    final mapped = english[ar];
    if (mapped == null || mapped.isEmpty) return false;
    field['en'] = mapped;
    return true;
  }

  /// Migrates legacy unversioned CV (v0 flat form fields) into structured v1 schema.
  static Map<String, dynamic> _migrateV0ToV1(Map<String, dynamic> v0) {
    final lang = v0['export_language']?.toString() == 'en' ? 'en' : 'ar';

    // Start with a clone of v0 so all unrecognized fields (generated_markdown, ai_output, etc.) are retained
    final v1 = Map<String, dynamic>.from(v0);

    // Personal details mapping
    final fullName =
        v0['full_name']?.toString() ?? v0['candidate_name']?.toString() ?? '';
    final headline = v0['headline']?.toString() ??
        v0['target_job_title']?.toString() ??
        v0['job_title']?.toString() ??
        '';
    final email = v0['email']?.toString();
    final phone = v0['phone']?.toString();
    final linkedin = v0['linkedin']?.toString();
    final location = v0['location']?.toString() ?? '';

    final personal = <String, dynamic>{
      'full_name': lang == 'en'
          ? {'ar': '', 'en': fullName}
          : {'ar': fullName, 'en': ''},
      'headline': lang == 'en'
          ? {'ar': '', 'en': headline}
          : {'ar': headline, 'en': ''},
      'email': email,
      'phone': phone,
      'linkedin': linkedin,
      'location': lang == 'en'
          ? {'ar': '', 'en': location}
          : {'ar': location, 'en': ''},
    };

    // Summary mapping
    final rawSummary =
        v0['summary_input']?.toString() ?? v0['summary']?.toString() ?? '';
    final summary = lang == 'en'
        ? {'ar': '', 'en': rawSummary}
        : {'ar': rawSummary, 'en': ''};

    // Experience mapping (if array of structured objects already, preserve; if raw string, convert)
    final experience = <Map<String, dynamic>>[];
    if (v0['experience'] is List) {
      for (final item in (v0['experience'] as List)) {
        if (item is Map) {
          experience.add(Map<String, dynamic>.from(item));
        }
      }
    } else if (v0['experience_input'] != null &&
        v0['experience_input'].toString().trim().isNotEmpty) {
      final expText = v0['experience_input'].toString().trim();
      experience.add({
        'company': {'ar': '', 'en': ''},
        'title': {'ar': '', 'en': ''},
        'location': {'ar': '', 'en': ''},
        'start_date': null,
        'end_date': null,
        'is_current': false,
        'bullets': <Map<String, String>>[],
        'narrative': lang == 'en'
            ? {'ar': '', 'en': expText}
            : {'ar': expText, 'en': ''},
      });
    }

    // Education mapping
    final education = <Map<String, dynamic>>[];
    if (v0['education'] is List) {
      for (final item in (v0['education'] as List)) {
        if (item is Map) {
          education.add(Map<String, dynamic>.from(item));
        }
      }
    } else if (v0['education_input'] != null &&
        v0['education_input'].toString().trim().isNotEmpty) {
      final eduText = v0['education_input'].toString().trim();
      education.add({
        'institution': {'ar': '', 'en': ''},
        'degree': {'ar': '', 'en': ''},
        'field_of_study': {'ar': '', 'en': ''},
        'start_date': null,
        'graduation_date': null,
        'narrative': lang == 'en'
            ? {'ar': '', 'en': eduText}
            : {'ar': eduText, 'en': ''},
      });
    }

    // Skills mapping
    final skills = <Map<String, dynamic>>[];
    if (v0['skills'] is List) {
      for (final item in (v0['skills'] as List)) {
        if (item is Map) {
          skills.add(Map<String, dynamic>.from(item));
        }
      }
    } else if (v0['skills_input'] != null &&
        v0['skills_input'].toString().trim().isNotEmpty) {
      final rawTokens = v0['skills_input'].toString().split(RegExp(r'[,،\n]+'));
      for (final token in rawTokens) {
        final t = token.trim();
        if (t.isNotEmpty) {
          skills.add({
            'name': lang == 'en' ? {'ar': '', 'en': t} : {'ar': t, 'en': ''},
            'level': {'ar': '', 'en': ''},
          });
        }
      }
    }

    // Certifications mapping
    final certifications = <Map<String, dynamic>>[];
    if (v0['certifications'] is List) {
      for (final item in (v0['certifications'] as List)) {
        if (item is Map) {
          certifications.add(Map<String, dynamic>.from(item));
        }
      }
    } else if (v0['certifications_input'] != null &&
        v0['certifications_input'].toString().trim().isNotEmpty) {
      final certText = v0['certifications_input'].toString().trim();
      certifications.add({
        'name': lang == 'en'
            ? {'ar': '', 'en': certText}
            : {'ar': certText, 'en': ''},
        'authority': {'ar': '', 'en': ''},
        'issue_date': null,
        'narrative': {'ar': '', 'en': ''},
      });
    }

    v1['schema_version'] = 1;
    v1['export_language'] = lang;
    v1['personal'] = personal;
    v1['summary'] = summary;
    v1['experience'] = experience;
    v1['education'] = education;
    v1['skills'] = skills;
    v1['languages'] =
        (v0['languages'] is List) ? v0['languages'] : <Map<String, dynamic>>[];
    v1['certifications'] = certifications;
    v1['projects'] =
        (v0['projects'] is List) ? v0['projects'] : <Map<String, dynamic>>[];
    v1['custom_sections'] = (v0['custom_sections'] is List)
        ? v0['custom_sections']
        : <Map<String, dynamic>>[];

    // Remove legacy flat keys that were transformed into structured sections
    v1.remove('full_name');
    v1.remove('candidate_name');
    v1.remove('headline');
    v1.remove('target_job_title');
    v1.remove('job_title');
    v1.remove('email');
    v1.remove('phone');
    v1.remove('linkedin');
    v1.remove('location');
    v1.remove('summary_input');
    v1.remove('experience_input');
    v1.remove('education_input');
    v1.remove('skills_input');
    v1.remove('certifications_input');

    return v1;
  }
}
