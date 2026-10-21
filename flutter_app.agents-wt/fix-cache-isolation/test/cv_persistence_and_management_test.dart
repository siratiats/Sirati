import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/async_state.dart';
import 'package:sirati/features/cv_builder/controllers/cv_manage_controller.dart';
import 'package:sirati/features/cv_builder/data/cv_repository.dart';
import 'package:sirati/features/cv_builder/data/cv_schema_migrator.dart';
import 'package:sirati/features/cv_builder/data/skill_storage_keys.dart';
import 'package:sirati/shared/models/cv_document.dart';

void main() {
  group('SIRATI-34 Schema Migration Invariants', () {
    test(
        'migrates legacy v0 flat CV format into structured v1 schema without data loss',
        () {
      final v0Doc = {
        'id': 'legacy_cv_42',
        'export_language': 'ar',
        'full_name': 'فيصل بن خالد',
        'target_job_title': 'مهندس ذكاء اصطناعي',
        'summary_input': 'خبرة في بناء نماذج تعلم الآلة وتطوير الأنظمة.',
        'experience_input':
            'مهندس بيانات في شركة أرامكو السعودية - العمل على تحليل البيانات الضخمة',
        'education_input':
            'بكالوريوس هندسة حاسب - جامعة الملك فهد للبترول والمعادن',
        'skills_input': 'Python, PyTorch, SQL، فلاتر، معالجة اللغات الطبيعية',
        'certifications_input': 'AWS Certified Machine Learning Specialist',
        'email': 'faisal@example.sa',
        'phone': '+966555123456',
        'location': 'الظهران',
      };

      final result = CvSchemaMigrator.migrate(v0Doc);

      expect(result.wasMigrated, isTrue);
      expect(result.originalVersion, 0);
      expect(result.finalVersion, 1);
      expect(result.appliedMigrations, contains(0));

      final v1 = result.data;
      expect(v1['schema_version'], 1);
      expect(v1['id'], 'legacy_cv_42');
      expect(v1['personal']['full_name']['ar'], 'فيصل بن خالد');
      expect(v1['personal']['headline']['ar'], 'مهندس ذكاء اصطناعي');
      expect(v1['personal']['email'], 'faisal@example.sa');
      expect(v1['summary']['ar'], contains('تعلم الآلة'));
      expect(v1['experience'], hasLength(1));
      expect(v1['education'], hasLength(1));
      expect(v1['skills'], hasLength(5));
      expect(v1['skills'][0]['name']['ar'], 'Python');
      expect(v1['skills'][3]['name']['ar'], 'فلاتر');
      expect(v1['certifications'], hasLength(1));

      // Deserializes cleanly into typed CvDocument
      final parsedDoc = CvDocument.fromJson(v1);
      expect(parsedDoc.fullName.ar, 'فيصل بن خالد');
      expect(parsedDoc.skills, hasLength(5));
    });

    test('migration is idempotent on already v1 documents', () {
      const v1Doc = CvDocument(
        version: 1,
        exportLanguage: 'en',
        personal: PersonalDetails(
          fullName: LocalizedText(en: 'John Doe'),
        ),
        summary: LocalizedText(en: 'Summary'),
      );

      final result = CvSchemaMigrator.migrate(v1Doc.toJson());
      expect(result.wasMigrated, isFalse);
      expect(result.originalVersion, 1);
      expect(result.finalVersion, 1);
      expect(result.appliedMigrations, isEmpty);
    });

    test(
        'H1 zero data loss: preserves all unrecognized scalar and object fields in legacy payload',
        () {
      final legacyPayload = {
        'id': 'cv_legacy_complete',
        'export_language': 'ar',
        'full_name': 'سعد الأحمدي',
        'target_job_title': 'مطور نظم',
        'summary_input': 'نبذة مختصرة',
        'experience_input': 'خبرة سابقة',
        'education_input': 'تعليم سابق',
        'skills_input': 'Dart, Flutter',
        'job_description_input': 'مطلوب مطور واجهات متقدم',
        'generated_markdown': '# سيرة ذاتية\nمطور Flutter',
        'ai_output': {
          'analysis': 'ممتاز',
          'recommendations': ['أضف رابط GitHub']
        },
        'criteria': {'ats_score': 85, 'keyword_matches': 12},
        'score_total': 92,
        'grade': 'A',
        'idempotency_key': 'idem_xyz_123',
        'form_payload': {'step': 4, 'is_complete': true},
        'template_slug': 'modern-rtl',
        'photo_url': 'https://example.com/photo.jpg',
      };

      final result = CvSchemaMigrator.migrate(legacyPayload);
      expect(result.wasMigrated, isTrue);

      final v1Data = result.data;
      expect(v1Data['job_description_input'], 'مطلوب مطور واجهات متقدم');
      expect(v1Data['generated_markdown'], contains('سيرة ذاتية'));
      expect(v1Data['score_total'], 92);
      expect(v1Data['grade'], 'A');
      expect(v1Data['template_slug'], 'modern-rtl');
      expect(v1Data['photo_url'], 'https://example.com/photo.jpg');

      // Round-trip into CvDocument and back to JSON
      final doc = CvDocument.fromJson(v1Data);
      expect(doc.extraFields, isNotNull);
      expect(
          doc.extraFields!['job_description_input'], 'مطلوب مطور واجهات متقدم');
      expect(doc.extraFields!['score_total'], 92);

      final reEncoded = doc.toJson();
      expect(reEncoded['job_description_input'], 'مطلوب مطور واجهات متقدم');
      expect(reEncoded['generated_markdown'], contains('سيرة ذاتية'));
      expect(reEncoded['score_total'], 92);
      expect(reEncoded['grade'], 'A');
    });

    test(
        'H1 structural v1 recognition: does not mangle v1 doc missing schema_version stamp',
        () {
      final unversionedV1 = {
        'id': 'cv_v1_no_stamp',
        'export_language': 'ar',
        'personal': {
          'full_name': {'ar': 'عبدالله', 'en': 'Abdullah'},
          'headline': {'ar': 'مهندس برمجيات', 'en': 'Software Engineer'},
        },
        'summary': {
          'ar': 'نبذة مهنية متقدمة',
          'en': 'Advanced professional summary',
        },
        'skills': [
          {
            'name': {'ar': 'فلاتر', 'en': 'Flutter'},
            'level': {'ar': 'خبير', 'en': 'Expert'},
          }
        ],
      };

      final result = CvSchemaMigrator.migrate(unversionedV1);
      expect(result.wasMigrated, isFalse);
      expect(result.finalVersion, 1);

      final doc = CvDocument.fromJson(result.data);
      expect(doc.fullName.ar, 'عبدالله');
      expect(doc.fullName.en, 'Abdullah');
      expect(doc.summary.ar, 'نبذة مهنية متقدمة');
      expect(doc.summary.ar, isNot(contains('{ar:'))); // NOT stringified Map
      expect(doc.skills, hasLength(1));
    });

    test(
        'backfills empty en on known proficiency keys and leaves everything else',
        () {
      const preserved = 'Keep this English';
      const unknownAr = 'مستوى غير معروف';
      final skills = [
        for (final key in SkillStorageKeys.levels)
          {
            'name': {'ar': 'skill-$key', 'en': ''},
            'level': {'ar': key, 'en': ''},
            'category': {'ar': SkillStorageKeys.technical, 'en': ''},
          },
        {
          'name': {'ar': 'custom', 'en': ''},
          'level': {'ar': unknownAr, 'en': ''},
          'category': {'ar': SkillStorageKeys.soft, 'en': preserved},
        },
      ];
      final languages = [
        for (final key in LanguageLevelStorageKeys.values)
          {
            'name': {'ar': 'lang-$key', 'en': ''},
            'level': {'ar': key, 'en': ''},
          },
      ];

      final v1Doc = {
        'schema_version': 1,
        'export_language': 'en',
        'personal': {
          'full_name': {'ar': 'سارة', 'en': 'Sara'},
        },
        'summary': {'ar': '', 'en': 'Summary'},
        'skills': skills,
        'languages': languages,
      };

      final result = CvSchemaMigrator.migrate(v1Doc);
      expect(result.wasMigrated, isTrue);
      expect(result.originalVersion, 1);
      expect(result.finalVersion, 1);
      expect(result.appliedMigrations, isEmpty);

      final filledSkills = result.data['skills'] as List;
      for (var i = 0; i < SkillStorageKeys.levels.length; i++) {
        final key = SkillStorageKeys.levels[i];
        expect(filledSkills[i]['level']['ar'], key);
        expect(
            filledSkills[i]['level']['en'], SkillStorageKeys.englishFor(key));
        expect(filledSkills[i]['category']['en'],
            SkillStorageKeys.englishFor(SkillStorageKeys.technical));
        expect(filledSkills[i]['name']['en'], isEmpty);
      }

      final custom = filledSkills.last as Map;
      expect(custom['level']['ar'], unknownAr);
      expect(custom['level']['en'], isEmpty);
      expect(custom['category']['en'], preserved);

      final filledLangs = result.data['languages'] as List;
      for (var i = 0; i < LanguageLevelStorageKeys.values.length; i++) {
        final key = LanguageLevelStorageKeys.values[i];
        expect(filledLangs[i]['level']['ar'], key);
        expect(
          filledLangs[i]['level']['en'],
          LanguageLevelStorageKeys.englishFor(key),
        );
        expect(filledLangs[i]['name']['en'], isEmpty);
      }

      final doc = CvDocument.fromJson(result.data);
      for (final skill in doc.skills!) {
        if (skill.level.ar == unknownAr) {
          expect(skill.level.resolve('en'), unknownAr);
          continue;
        }
        if (skill.level.ar.isEmpty) continue;
        expect(skill.level.resolve('en'),
            isNot(contains(RegExp(r'[\u0600-\u06FF]'))));
        expect(skill.category.resolve('en'),
            isNot(contains(RegExp(r'[\u0600-\u06FF]'))));
      }
      for (final language in doc.languages!) {
        expect(language.level.resolve('en'),
            isNot(contains(RegExp(r'[\u0600-\u06FF]'))));
      }

      final again = CvSchemaMigrator.migrate(result.data);
      expect(again.wasMigrated, isFalse);
    });

    test('rejects future schema versions without modifying data', () {
      final futureDoc = {
        'schema_version': 99,
        'title': 'Future CV',
      };

      expect(
        () => CvSchemaMigrator.migrate(futureDoc),
        throwsA(isA<UnsupportedSchemaVersionException>()),
      );
    });
  });

  group('SIRATI-33 Persistence Layer Invariants', () {
    late Directory tempDir;

    setUp(() async {
      tempDir = await Directory.systemTemp.createTemp('sirati_cv_test_');
    });

    tearDown(() async {
      if (await tempDir.exists()) {
        await tempDir.delete(recursive: true);
      }
    });

    test('FileCvStorageEngine writes atomically and cleans up temp files',
        () async {
      final engine = FileCvStorageEngine(baseDirectory: tempDir);
      const cvId = 'cv_atomic_1';
      const content = '{"title":"Test CV"}';

      await engine.writeAtomic(cvId, content);

      expect(await engine.exists(cvId), isTrue);
      final readBack = await engine.read(cvId);
      expect(readBack, content);

      // Verify no orphan .tmp files left in directory
      final files = await tempDir.list().toList();
      final tmpFiles = files.where((f) => f.path.endsWith('.tmp')).toList();
      expect(tmpFiles, isEmpty);
    });

    test('H1 read idempotence: getCv does not write or mutate storage on read',
        () async {
      final memoryEngine = MemoryCvStorageEngine();
      final repo = LocalCvRepository(engine: memoryEngine);

      const legacyId = 'cv_v0_raw';
      final v0Payload = {
        'id': legacyId,
        'candidate_name': 'عمر خالد',
        'export_language': 'ar',
        'summary': 'ملخص عام',
      };
      final originalRaw = jsonEncode(v0Payload);
      await memoryEngine.writeAtomic(legacyId, originalRaw);

      // Retrieve via repo
      final loaded = await repo.getCv(legacyId);
      expect(loaded, isNotNull);
      expect(loaded!.fullName.ar, 'عمر خالد');
      expect(loaded.version, 1);

      // Stored copy in engine was NOT overwritten; remains identical
      final storedAfterRead = await memoryEngine.read(legacyId);
      expect(storedAfterRead, equals(originalRaw));
    });

    test('M3 deleteCv deletes corrupt non-JSON file without throwing',
        () async {
      final memoryEngine = MemoryCvStorageEngine();
      final repo = LocalCvRepository(engine: memoryEngine);

      const corruptId = 'corrupt_cv_doc';
      await memoryEngine.writeAtomic(corruptId, '{invalid-json-payload-###');

      expect(await memoryEngine.exists(corruptId), isTrue);

      // Attempting to delete must succeed and purge from engine
      await repo.deleteCv(corruptId);
      expect(await memoryEngine.exists(corruptId), isFalse);
    });
  });

  group('SIRATI-35 Multi-CV Management Invariants', () {
    late MemoryCvStorageEngine memoryEngine;
    late LocalCvRepository repository;

    setUp(() {
      memoryEngine = MemoryCvStorageEngine();
      repository = LocalCvRepository(engine: memoryEngine);
    });

    test(
        'duplication produces a fully independent deep copy without shared references',
        () async {
      final original = CvDocument.createEmpty(
        id: 'cv_original',
        title: 'Master CV',
        exportLanguage: 'ar',
      ).copyWith(
        personal: const PersonalDetails(
          fullName: LocalizedText(ar: 'محمد علي', en: 'Mohammed Ali'),
        ),
        skills: [
          const Skill(id: 's1', name: LocalizedText(ar: 'دارت', en: 'Dart')),
        ],
      );

      await repository.saveCv(original);

      // Duplicate
      final duplicate =
          await repository.duplicateCv('cv_original', newTitle: 'Frontend CV');
      expect(duplicate.id, isNot('cv_original'));
      expect(duplicate.title, 'Frontend CV');

      // Modifying duplicate in storage does not touch original
      final modifiedDuplicate = duplicate.copyWith(
        personal: duplicate.personal.copyWith(
          fullName: const LocalizedText(ar: 'محمد علي المعدل'),
        ),
      );
      await repository.saveCv(modifiedDuplicate);

      final reloadedOriginal = await repository.getCv('cv_original');
      final reloadedDuplicate = await repository.getCv(duplicate.id!);

      expect(reloadedOriginal!.fullName.ar, 'محمد علي');
      expect(reloadedDuplicate!.fullName.ar, 'محمد علي المعدل');
    });

    test('deleteCv supports soft-delete and undo restore', () async {
      final doc =
          CvDocument.createEmpty(id: 'cv_to_delete', title: 'Temporary');
      await repository.saveCv(doc);

      expect(await repository.listCvs(), hasLength(1));

      // Delete
      await repository.deleteCv('cv_to_delete');
      expect(await repository.listCvs(), isEmpty);

      // Restore
      await repository.restoreCv('cv_to_delete');
      final restored = await repository.listCvs();
      expect(restored, hasLength(1));
      expect(restored.first.id, 'cv_to_delete');
    });

    test(
        'CvManageController enforces free tier limits and allows unlimited for premium',
        () async {
      bool isUserPremium = false;
      final controller = CvManageController(
        repository: repository,
        freeTierLimit: 2,
        isPremiumProvider: () => isUserPremium,
      );

      await controller.load();
      expect(controller.state, isA<AsyncSuccess<List<CvMetadata>>>());

      // Create 1st CV (Allowed)
      await controller.createCv(title: 'CV 1');
      // Create 2nd CV (Allowed - limit is 2)
      await controller.createCv(title: 'CV 2');

      // Create 3rd CV (Should be rejected)
      await expectLater(
        controller.createCv(title: 'CV 3'),
        throwsA(isA<CvLimitReachedException>()),
      );

      // Duplicate should also be rejected
      final cvs = (controller.state as AsyncSuccess<List<CvMetadata>>).data;
      await expectLater(
        controller.duplicateCv(cvs.first.id),
        throwsA(isA<CvLimitReachedException>()),
      );

      // User upgrades to premium
      isUserPremium = true;

      // Now 3rd CV creation succeeds
      final cv3 = await controller.createCv(title: 'CV 3');
      expect(cv3.title, 'CV 3');

      final finalCvs =
          (controller.state as AsyncSuccess<List<CvMetadata>>).data;
      expect(finalCvs, hasLength(3));
    });

    test('M4 free tier limit cannot be bypassed via delete -> create -> undo',
        () async {
      const bool isUserPremium = false;
      final controller = CvManageController(
        repository: repository,
        freeTierLimit: 2,
        isPremiumProvider: () => isUserPremium,
      );

      await controller.createCv(title: 'CV 1');
      await controller.createCv(title: 'CV 2');

      final cvs = (controller.state as AsyncSuccess<List<CvMetadata>>).data;
      expect(cvs, hasLength(2));

      // Delete CV 1 (count goes down to 1)
      await controller.deleteCv(cvs.first.id);
      expect(controller.canUndoDelete, isTrue);

      // Create new CV 3 (count becomes 2)
      await controller.createCv(title: 'CV 3');

      // Attempt undo delete (would make count 3 > limit of 2)
      await expectLater(
        controller.undoDelete(),
        throwsA(isA<CvLimitReachedException>()),
      );
    });

    test('CV list is always sorted by last modified (updatedAt) descending',
        () async {
      final controller = CvManageController(repository: repository);

      final older = CvDocument.createEmpty(
        id: 'cv_older',
        title: 'Older CV',
      ).copyWith(
        updatedAt: DateTime(2025, 1, 1),
      );
      final newer = CvDocument.createEmpty(
        id: 'cv_newer',
        title: 'Newer CV',
      ).copyWith(
        updatedAt: DateTime(2025, 6, 1),
      );

      await repository.saveCv(older, touchUpdatedAt: false);
      await repository.saveCv(newer, touchUpdatedAt: false);

      await controller.load();

      final list = (controller.state as AsyncSuccess<List<CvMetadata>>).data;
      expect(list.first.id, 'cv_newer');
      expect(list.last.id, 'cv_older');
    });
  });
}
