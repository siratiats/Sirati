import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/cv_builder/cv_builder_controller.dart';
import 'package:sirati/features/cv_builder/cv_live_preview_pane.dart';
import 'package:sirati/features/cv_builder/data/cv_repository.dart';
import 'package:sirati/shared/models/cv_document.dart';
import 'package:sirati/shared/models/cv_template.dart';

class _FakeCvRepository implements CvRepository {
  CvDocument? lastSaved;

  @override
  Future<List<CvMetadata>> listCvs() async => const [];

  @override
  Future<CvDocument?> getCv(String id) async => lastSaved;

  @override
  Future<void> saveCv(CvDocument document, {bool touchUpdatedAt = true}) async {
    lastSaved = document;
  }

  @override
  Future<CvDocument> duplicateCv(String id, {String? newTitle}) async =>
      lastSaved!;

  @override
  Future<void> renameCv(String id, String newTitle) async {}

  @override
  Future<void> deleteCv(String id) async {}

  @override
  Future<void> restoreCv(String id) async {}
}

void main() {
  const freeTemplate = CvTemplate(
    id: 1,
    slug: 'ats-classic-professional',
    name: 'كلاسيكي احترافي',
    nameAr: 'كلاسيكي احترافي',
    nameEn: 'ATS Classic Professional',
    previewImageUrl: null,
    languageDirection: 'both',
    supportedLanguages: ['ar', 'en'],
    supportedSections: [],
    isDefault: true,
    isPremium: false,
  );

  const premiumTemplate = CvTemplate(
    id: 2,
    slug: 'executive-leadership-brief',
    name: 'القيادة التنفيذية',
    nameAr: 'القيادة التنفيذية',
    nameEn: 'Executive Leadership Brief',
    previewImageUrl: null,
    languageDirection: 'both',
    supportedLanguages: ['ar', 'en'],
    supportedSections: [],
    isDefault: false,
    isPremium: true,
  );

  const baseDocument = CvDocument(
    id: '1',
    exportLanguage: 'ar',
    personal: PersonalDetails(
      fullName: LocalizedText(ar: 'سارة التميمي', en: 'Sara Al-Tamimi'),
      headline: LocalizedText(ar: 'مهندسة برمجيات', en: 'Software Engineer'),
      email: 'sara@example.com',
      phone: '+966551112233',
      location: LocalizedText(ar: 'الرياض', en: 'Riyadh'),
    ),
    summary: LocalizedText(ar: 'ملخص احترافي', en: 'Professional summary'),
    skills: [
      Skill(name: LocalizedText(ar: 'فلاتر', en: 'Flutter')),
      Skill(name: LocalizedText(ar: 'دارت', en: 'Dart')),
    ],
  );

  group('CvLivePreviewPane', () {
    testWidgets('renders candidate information and sections', (tester) async {
      final repo = _FakeCvRepository();
      final controller =
          CvBuilderController(initialDocument: baseDocument, repository: repo);

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: CvLivePreviewPane(
              controller: controller,
              template: freeTemplate,
            ),
          ),
        ),
      );

      expect(find.text('سارة التميمي'), findsOneWidget);
      expect(find.text('مهندسة برمجيات'), findsOneWidget);
      expect(find.text('sara@example.com'), findsOneWidget);
      expect(find.text('+966551112233'), findsOneWidget);
      expect(find.text('الملخص المهني'), findsOneWidget);
      expect(find.text('ملخص احترافي'), findsOneWidget);
      expect(find.text('فلاتر'), findsOneWidget);

      controller.dispose();
      await tester.pumpAndSettle();
    });

    testWidgets('debounces updates by 300ms', (tester) async {
      final repo = _FakeCvRepository();
      final controller =
          CvBuilderController(initialDocument: baseDocument, repository: repo);

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: CvLivePreviewPane(
              controller: controller,
              template: freeTemplate,
            ),
          ),
        ),
      );

      expect(find.text('سارة التميمي'), findsOneWidget);

      // Mutate document
      final updated = baseDocument.copyWith(
        personal: baseDocument.personal.copyWith(
          fullName: const LocalizedText(
              ar: 'سارة بنت عبد الله', en: 'Sara Bint Abdullah'),
        ),
      );
      controller.updateDocument(updated);

      // Before 300ms debounce fires, old state remains visible
      await tester.pump(const Duration(milliseconds: 100));
      expect(find.text('سارة التميمي'), findsOneWidget);
      expect(find.text('سارة بنت عبد الله'), findsNothing);

      // After 300ms debounce fires, new state is reflected
      await tester.pump(const Duration(milliseconds: 250));
      expect(find.text('سارة بنت عبد الله'), findsOneWidget);

      // Clean up controller and timers
      controller.dispose();
      await tester.pumpAndSettle();
    });

    testWidgets('renders watermark overlay for premium templates',
        (tester) async {
      final repo = _FakeCvRepository();
      final controller =
          CvBuilderController(initialDocument: baseDocument, repository: repo);

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: CvLivePreviewPane(
              controller: controller,
              template: premiumTemplate,
              isWatermarked: true,
            ),
          ),
        ),
      );

      expect(find.text('معاينة سيرتي · للاطلاع فقط'), findsOneWidget);

      controller.dispose();
      await tester.pumpAndSettle();
    });
  });
}
