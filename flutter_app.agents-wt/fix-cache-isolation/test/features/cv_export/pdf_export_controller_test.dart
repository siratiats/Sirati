import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/features/cv_export/pdf_export_controller.dart';
import 'package:sirati/shared/models/cv_template.dart';
import 'package:sirati/shared/models/generated_cv.dart';

void main() {
  final testCv = GeneratedCv.fromJson({
    'id': 101,
    'full_name': 'أحمد علي الغامدي',
    'target_job_title': 'مهندس برمجيات',
    'email': 'ahmed@example.com',
    'phone': '+966501112233',
    'location': 'الرياض',
    'summary_input': 'ملخص مهني',
    'skills_input': 'Flutter, Dart',
    'experience_input': 'خبرة طويلة',
    'education_input': 'بكالوريوس',
    'language': 'ar',
    'generated_markdown': '# السيرة الذاتية',
    'score_total': 90,
    'grade': 'A',
    'ai_status': 'completed',
  });

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

  group('PdfExportController', () {
    test('initializes with sanitized filename and idle status', () {
      final client = ApiClient(
          httpClient: MockClient((_) async => http.Response('[]', 200)));
      final controller = PdfExportController(
        apiClient: client,
        cv: testCv,
        initialTemplate: freeTemplate,
      );

      expect(controller.state.status, ExportStatus.idle);
      expect(controller.state.exportLanguage, 'ar');
      expect(
          controller.state.filename, contains('سيرة_ذاتية_أحمد_علي_الغامدي'));
      expect(controller.state.isWatermarked, isFalse);
    });

    test('updating export language changes filename and clears stale pdf bytes',
        () {
      final client = ApiClient(
          httpClient: MockClient((_) async => http.Response('[]', 200)));
      final controller = PdfExportController(
        apiClient: client,
        cv: testCv,
        initialTemplate: freeTemplate,
      );

      controller.setExportLanguage('en');
      expect(controller.state.exportLanguage, 'en');
      expect(controller.state.filename, startsWith('CV_'));

      controller.setExportLanguage('ar');
      expect(controller.state.exportLanguage, 'ar');
      expect(controller.state.filename, startsWith('سيرة_ذاتية_'));
    });

    test('downloads pdf bytes and updates status to ready', () async {
      final mockPdfBytes = [37, 80, 68, 70, 45, 49, 46, 52]; // "%PDF-1.4"
      final client = ApiClient(
        httpClient: MockClient((req) async {
          if (req.url.path.contains('/download')) {
            return http.Response.bytes(mockPdfBytes, 200,
                headers: {'content-type': 'application/pdf'});
          }
          return http.Response('Not Found', 404);
        }),
      );

      final controller = PdfExportController(
        apiClient: client,
        cv: testCv,
        initialTemplate: freeTemplate,
      );

      final result = await controller.exportPdf();
      expect(result, isTrue);
      expect(controller.state.status, ExportStatus.ready);
      expect(controller.state.progress, 1.0);
      expect(controller.state.pdfBytes, isNotNull);
      expect(controller.state.pdfBytes!.length, equals(mockPdfBytes.length));
    });

    test('handles 403 premium locked response gracefully', () async {
      final client = ApiClient(
        httpClient: MockClient((req) async {
          return http.Response(
            '{"error":"premium_template_locked","message":"هذا القالب متاح للمشتركين فقط"}',
            403,
            headers: {'content-type': 'application/json'},
          );
        }),
      );

      final controller = PdfExportController(
        apiClient: client,
        cv: testCv,
        initialTemplate: premiumTemplate,
      );

      final result = await controller.exportPdf();
      expect(result, isFalse);
      expect(controller.state.status, ExportStatus.premiumLocked);
      expect(controller.state.errorMessage,
          contains('هذا القالب متاح للمشتركين فقط'));
    });

    test('loads preview and captures watermark state', () async {
      final client = ApiClient(
        httpClient: MockClient((req) async {
          return http.Response(
            '{"data":{"html":"<html>preview</html>","is_watermarked":true,"is_premium":true}}',
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
      );

      final controller = PdfExportController(
        apiClient: client,
        cv: testCv,
        initialTemplate: premiumTemplate,
      );

      await controller.loadPreview();
      expect(controller.state.status, ExportStatus.previewReady);
      expect(controller.state.previewHtml, '<html>preview</html>');
      expect(controller.state.isWatermarked, isTrue);
    });
  });
}
