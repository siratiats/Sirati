import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/cv_export/export_file_namer.dart';

void main() {
  group('ExportFileNamer', () {
    test('formats Arabic filename correctly with safe underscores', () {
      final name = ExportFileNamer.formatFilename(
        fullName: 'فيصل بن ناصر القحطاني',
        targetJobTitle: 'مدير مشروعات تقنية',
        language: 'ar',
      );

      expect(name, 'سيرة_ذاتية_فيصل_بن_ناصر_القحطاني_مدير_مشروعات_تقنية.pdf');
    });

    test('strips illegal filesystem characters from Arabic filename', () {
      final name = ExportFileNamer.formatFilename(
        fullName: 'أحمد/علي: *الغامدي?|',
        targetJobTitle: 'مطور<ويب>"أول"',
        language: 'ar',
      );

      expect(name.contains('/'), isFalse);
      expect(name.contains('\\'), isFalse);
      expect(name.contains(':'), isFalse);
      expect(name.contains('*'), isFalse);
      expect(name.contains('?'), isFalse);
      expect(name.contains('"'), isFalse);
      expect(name.contains('<'), isFalse);
      expect(name.contains('>'), isFalse);
      expect(name.contains('|'), isFalse);
      expect(name, startsWith('سيرة_ذاتية_'));
      expect(name, endsWith('.pdf'));
    });

    test('formats Latin filename correctly', () {
      final name = ExportFileNamer.formatFilename(
        fullName: 'Sarah Al-Otaibi',
        targetJobTitle: 'Senior Data Scientist',
        language: 'en',
      );

      expect(name, 'CV_Sarah_Al-Otaibi_Senior_Data_Scientist.pdf');
    });

    test('provides Latin fallback transliteration when requested', () {
      final name = ExportFileNamer.formatFilename(
        fullName: 'طارق الزهراني',
        targetJobTitle: 'مستشار مالي',
        language: 'ar',
        latinFallback: true,
      );

      expect(name, startsWith('CV_'));
      expect(name, endsWith('.pdf'));
      expect(name.contains(RegExp(r'[a-zA-Z]')), isTrue);
    });

    test('falls back gracefully on empty or null inputs', () {
      final arabicFallback = ExportFileNamer.formatFilename(
        fullName: null,
        targetJobTitle: '',
        language: 'ar',
      );
      expect(arabicFallback, 'سيرة_ذاتية_مرشح_مهني.pdf');

      final latinFallback = ExportFileNamer.formatFilename(
        fullName: '   ',
        targetJobTitle: null,
        language: 'en',
      );
      expect(latinFallback, 'CV_Candidate_CV.pdf');
    });

    test(
        'handles boundary invariant: control characters and consecutive spaces',
        () {
      final name = ExportFileNamer.formatFilename(
        fullName: '\t\n  محمد   سالم   \r',
        targetJobTitle: 'مهندس\nنظم',
        language: 'ar',
      );

      expect(name, 'سيرة_ذاتية_محمد_سالم_مهندس_نظم.pdf');
    });
  });
}
