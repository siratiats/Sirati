import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/core/utils/arabic_date_format.dart';
import 'package:sirati/features/cv_builder/cv_builder_controller.dart';
import 'package:sirati/features/cv_builder/cv_builder_screen.dart';
import 'package:sirati/features/cv_builder/data/cv_repository.dart';
import 'package:sirati/features/cv_builder/data/cv_repository_provider.dart';
import 'package:sirati/features/cv_builder/experience_education_editor.dart';
import 'package:sirati/features/cv_builder/personal_details_editor.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/cv_document.dart';
import 'package:sirati/shared/theme/app_theme.dart';

void main() {
  late AppLocalizations en;
  late AppLocalizations ar;

  setUpAll(() async {
    en = await AppLocalizations.delegate.load(const Locale('en'));
    ar = await AppLocalizations.delegate.load(const Locale('ar'));
  });

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  group('SIRATI-37: ContactValidators & Summary Guidance', () {
    test('validates arbitrary Gulf and international phone formats', () {
      final validGulfPhones = [
        '+966501234567',
        '0501234567',
        '+966 55 987 6543',
        '+966112345678', // Riyadh landline
        '0112345678',
        '+971501234567',
        '0521234567',
        '+97143214321', // Dubai landline
        '043214321',
        '+96599123456',
        '+97433123456',
        '+97339123456',
        '+96891123456',
        '+14155552671', // International E.164
      ];

      for (final phone in validGulfPhones) {
        expect(
          ContactValidators.validateGulfPhone(phone, en),
          isNull,
          reason: 'Expected $phone to be valid',
        );
      }

      final invalidPhones = [
        '12345',
        'not-a-phone',
        '+966401234567', // Invalid Saudi mobile prefix
        '++966501234567',
        '0601234567',
        '12345678', // Bare 8-digit numbers without country prefix
        '99999999',
        '34123456',
        '22123456',
      ];

      for (final phone in invalidPhones) {
        final english = ContactValidators.validateGulfPhone(phone, en);
        final arabic = ContactValidators.validateGulfPhone(phone, ar);
        expect(english, isNotNull, reason: 'Expected $phone to be rejected');
        expect(arabic, isNotNull, reason: 'Expected $phone to be rejected');
        expect(english, isNot(contains(RegExp(r'[\u0600-\u06FF]'))));
        expect(arabic, contains(RegExp(r'[\u0600-\u06FF]')));
      }
    });

    test('validates RFC-compliant email formats and rejects malformed inputs',
        () {
      final validEmails = [
        'candidate@example.com',
        'first.last@company.sa',
        'user+tag@domain.co.uk',
        "o'brien@example.com",
        'ahmed.o’reilly@domain.sa',
      ];

      for (final email in validEmails) {
        expect(ContactValidators.validateEmail(email, en), isNull);
      }

      final invalidEmails = [
        'plainaddress',
        '@missingusername.com',
        'username@.com',
        'username@domain',
        'user name@domain.com',
      ];

      for (final email in invalidEmails) {
        expect(ContactValidators.validateEmail(email, en), isNotNull);
        expect(
          ContactValidators.validateEmail(email, en),
          isNot(contains(RegExp(r'[\u0600-\u06FF]'))),
        );
        expect(
          ContactValidators.validateEmail(email, ar),
          contains(RegExp(r'[\u0600-\u06FF]')),
        );
      }
    });
  });

  group('SIRATI-38: Experience & Education Validation and Sorting', () {
    test(
        'validateDateRange enforces chronological invariant unless current position',
        () {
      String? range(
        String start,
        String end,
        bool isCurrent, [
        AppLocalizations? l10n,
      ]) {
        return ExperienceEducationEditor.validateDateRange(
          start,
          end,
          isCurrent,
          l10n ?? en,
        );
      }

      // When isCurrent = true, end date is ignored
      expect(range('2024-01', '2020-01', true), isNull);

      // When isCurrent = false, end date < start date must strictly fail
      expect(range('2024-01', '2022-01', false), isNotNull);

      // Same or later date passes
      expect(range('2021-05', '2024-05', false), isNull);
      expect(range('2023', '2023', false), isNull);

      // Unpadded months (e.g. 2021-3 to 2021-11)
      expect(range('2021-3', '2021-11', false), isNull);

      // Slashes format
      expect(range('2021/03', '2021/05', false), isNull);

      // Arabic month names
      expect(range('مارس 2021', 'يناير 2020', false), isNotNull);
      expect(range('يناير 2020', 'مارس 2021', false), isNull);

      // Mixed Hijri and Gregorian dates
      // ربيع الأول 1442 (≈ Oct 2020) vs مارس 2020 (March 2020) -> End precedes start, must fail!
      expect(range('ربيع الأول 1442', 'مارس 2020', false), isNotNull);
      // مارس 2020 (start) vs ربيع الأول 1442 (end) -> Valid chronological sequence
      expect(range('مارس 2020', 'ربيع الأول 1442', false), isNull);
      // Two Hijri dates: ربيع الأول 1442 (Oct 2020) vs رجب 1442 (Feb 2021) -> Valid
      expect(range('ربيع الأول 1442', 'رجب 1442', false), isNull);

      final englishError = range('2024-01', '2020-01', false, en);
      final arabicError = range('2024-01', '2020-01', false, ar);
      expect(englishError, isNotNull);
      expect(arabicError, isNotNull);
      expect(englishError, isNot(contains(RegExp(r'[\u0600-\u06FF]'))));
      expect(arabicError, contains(RegExp(r'[\u0600-\u06FF]')));
    });

    test(
        'chronological sorting orders entries newest first with current at the top across date formats',
        () {
      final entries = [
        const ExperienceEntry(
          id: '1',
          startDate: '2019-3',
          endDate: '2021-1',
          isCurrent: false,
        ),
        const ExperienceEntry(
          id: '2',
          startDate: 'مارس 2022',
          isCurrent: true,
        ),
        const ExperienceEntry(
          id: '3',
          startDate: '2021-11',
          endDate: '2022-01',
          isCurrent: false,
        ),
      ];

      entries.sort((a, b) {
        if (a.isCurrent && !b.isCurrent) return -1;
        if (!a.isCurrent && b.isCurrent) return 1;
        final aDate = a.startDate ?? '';
        final bDate = b.startDate ?? '';
        return ArabicDateFormat.compareCvDates(bDate, aDate);
      });

      expect(entries.map((e) => e.id).toList(), ['2', '3', '1']);
    });
  });

  group('SIRATI-39 & SIRATI-40: Discrete Sections and Reordering', () {
    test(
        'section reordering updates document sectionOrder and preserves all elements',
        () {
      final initialDoc = CvDocument.createEmpty();
      final engine = MemoryCvStorageEngine();
      final repo = LocalCvRepository(engine: engine);
      final controller = CvBuilderController(
        initialDocument: initialDoc,
        repository: repo,
      );

      final initialOrder = List<String>.from(
        initialDoc.sectionOrder ?? CvDocument.defaultSectionOrder,
      );
      expect(initialOrder, contains('experience'));
      expect(initialOrder, contains('skills'));

      // Move experience (index 2) to top (index 0)
      controller.reorderSections(2, 0);

      final updatedOrder = controller.document.sectionOrder!;
      expect(updatedOrder.first, 'experience');
      expect(updatedOrder.length, initialOrder.length);
      expect(
          updatedOrder.toSet(), initialOrder.toSet()); // Set equality invariant
    });

    test('sectionOrder persists cleanly through serialization round-trip', () {
      const doc = CvDocument(
        exportLanguage: 'ar',
        summary: LocalizedText(ar: 'ملخص'),
        sectionOrder: ['skills', 'experience', 'education'],
      );

      final jsonMap = doc.toJson();
      expect(jsonMap['section_order'], ['skills', 'experience', 'education']);

      final restored = CvDocument.fromJson(jsonMap);
      expect(restored.sectionOrder, ['skills', 'experience', 'education']);
    });
  });

  group('SIRATI-42: Autosave and Draft Recovery', () {
    test(
        'saveImmediately writes atomic file and stages then clears draft backup',
        () async {
      final engine = MemoryCvStorageEngine();
      final repo = LocalCvRepository(engine: engine);
      final doc =
          CvDocument.createEmpty(id: 'cv_test_42', title: 'السيرة الهندسية');

      final controller = CvBuilderController(
        initialDocument: doc,
        repository: repo,
      );

      expect(controller.isDirty, isFalse);

      // Mutate
      controller.updateSummary(const LocalizedText(ar: 'ملخص مهني متقدم'));
      expect(controller.isDirty, isTrue);

      // Verify draft is staged in SharedPreferences before durable save (debounced by 200ms)
      await Future<void>.delayed(const Duration(milliseconds: 250));
      final prefs = await SharedPreferences.getInstance();
      final rawDraftBefore = prefs.getString('sirati_cv_draft_cv_test_42');
      expect(rawDraftBefore, isNotNull);
      final decodedDraft = jsonDecode(rawDraftBefore!);
      expect(decodedDraft['summary']['ar'], 'ملخص مهني متقدم');

      await controller.saveImmediately();
      expect(controller.isDirty, isFalse);

      // Confirm saved in repo
      final savedInRepo = await repo.getCv('cv_test_42');
      expect(savedInRepo, isNotNull);
      expect(savedInRepo!.summary.ar, 'ملخص مهني متقدم');

      // Draft is cleared on confirmed repo save
      final rawDraftAfter = prefs.getString('sirati_cv_draft_cv_test_42');
      expect(rawDraftAfter, isNull);
    });

    test(
        'checkDraftRecovery identifies newer draft after crash and clearDraft removes it',
        () async {
      final baseDocTime = DateTime.now().subtract(const Duration(minutes: 10));
      final draftDocTime = DateTime.now();

      final baseDoc = CvDocument(
        id: 'cv_crash_test',
        exportLanguage: 'ar',
        summary: const LocalizedText(ar: 'النسخة الأصلية'),
        updatedAt: baseDocTime,
      );

      final draftDoc = baseDoc.copyWith(
        summary:
            const LocalizedText(ar: 'المسودة المستعادة بعد الإغلاق المفاجئ'),
        updatedAt: draftDocTime,
      );

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        'sirati_cv_draft_cv_crash_test',
        jsonEncode(draftDoc.toJson()),
      );

      final recovered = await CvBuilderController.checkDraftRecovery(
        'cv_crash_test',
        baseDocTime,
      );

      expect(recovered, isNotNull);
      expect(recovered!.summary.ar, 'المسودة المستعادة بعد الإغلاق المفاجئ');

      // Clear draft
      await CvBuilderController.clearDraft('cv_crash_test');
      final cleared = await CvBuilderController.checkDraftRecovery(
        'cv_crash_test',
        baseDocTime,
      );
      expect(cleared, isNull);
    });
  });

  group('CV Builder Screen UI Integration', () {
    testWidgets(
        'CvBuilderScreen mounts tabs, editors, and handles section switching',
        (tester) async {
      final engine = MemoryCvStorageEngine();
      final repo = LocalCvRepository(engine: engine);
      final doc = CvDocument.createEmpty(
          id: 'cv_ui_test', title: 'سيرتي الذاتية للتجربة');
      await repo.saveCv(doc);

      await tester.pumpWidget(ProviderScope(
        overrides: [cvRepositoryProvider.overrideWithValue(repo)],
        child: MaterialApp(
          locale: const Locale('ar'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          theme: AppTheme.light,
          home: const CvBuilderScreen(cvId: 'cv_ui_test'),
        ),
      ));

      await tester.pumpAndSettle();

      final l10n = await AppLocalizations.delegate.load(const Locale('ar'));

      // App bar & document title
      expect(find.text('سيرتي الذاتية للتجربة'), findsOneWidget);
      expect(find.text(l10n.saved), findsOneWidget);

      // Verify 4 tabs exist
      expect(find.text(l10n.tabPersonal), findsOneWidget);
      expect(find.text(l10n.tabExperience), findsOneWidget);
      expect(find.text(l10n.tabSkills), findsOneWidget);
      expect(find.text(l10n.tabOrder), findsOneWidget);

      // Verify Tab 1 contents (Personal Details & Summary)
      expect(find.text(l10n.personalDetailsTitle), findsOneWidget);
      expect(find.text(l10n.professionalSummaryTitle), findsOneWidget);

      await tester.tap(find.text(l10n.tabOrder));
      await tester.pumpAndSettle();

      expect(find.text(l10n.sectionOrderTitle), findsOneWidget);
      expect(find.text(l10n.sectionExperience), findsWidgets);
    });
  });
}
