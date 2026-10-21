import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/arabic_date_format.dart';
import 'package:sirati/core/utils/bidi_text_utils.dart';

void main() {
  group('SIRATI-27 Bidirectional Text Handling Invariants', () {
    test(
        'detectBaseDirection follows first strong directional character (UAX #9)',
        () {
      // Arabic first -> RTL
      expect(
        BidiTextUtils.detectBaseDirection('مهندس برمجيات (Software Engineer)'),
        TextDirection.rtl,
      );

      // Neutral numbers / punctuation preceding Arabic -> RTL
      expect(
        BidiTextUtils.detectBaseDirection('1. تطوير الواجهات الخلفية'),
        TextDirection.rtl,
      );

      // Latin first -> LTR
      expect(
        BidiTextUtils.detectBaseDirection(
            'Senior Software Engineer (مهندس أول)'),
        TextDirection.ltr,
      );

      // Neutral numbers preceding Latin -> LTR
      expect(
        BidiTextUtils.detectBaseDirection('+966 50 123 4567 (Mobile)'),
        TextDirection.ltr,
      );

      // Purely neutral symbols fallback to specified default
      expect(
        BidiTextUtils.detectBaseDirection('--- /// ---',
            fallback: TextDirection.rtl),
        TextDirection.rtl,
      );
      expect(
        BidiTextUtils.detectBaseDirection('--- /// ---',
            fallback: TextDirection.ltr),
        TextDirection.ltr,
      );
    });

    test(
        'isolateEmbeddedLtrInArabic wraps technical terms, emails, URLs, and phones without reversing',
        () {
      const sample =
          'يرجى التواصل عبر info@sirati.sa أو زيارة https://sirati.sa للمزيد من التفاصيل عن API';
      final isolated = BidiTextUtils.isolateEmbeddedLtrInArabic(sample);

      // Verify each LTR island is wrapped with LRI (\u2066) and PDI (\u2069)
      expect(isolated,
          contains('${BidiTextUtils.lri}info@sirati.sa${BidiTextUtils.pdi}'));
      expect(
          isolated,
          contains(
              '${BidiTextUtils.lri}https://sirati.sa${BidiTextUtils.pdi}'));
      expect(isolated, contains('${BidiTextUtils.lri}API${BidiTextUtils.pdi}'));

      // Strip controls restores original logical string exactly
      expect(BidiTextUtils.sanitizeBidiControls(isolated), sample);
    });

    test(
        'toLogicalUnicode converts presentation forms to canonical Arabic code points',
        () {
      // \uFE8D (Alef isolated) -> \u0627 (Alef)
      // \uFE91 (Beh initial) -> \u0628 (Beh)
      // \uFEE4 (Meem medial) -> \u0645 (Meem)
      const presentationFormString = '\uFE8D\uFE91\uFEE4';
      final logical = BidiTextUtils.toLogicalUnicode(presentationFormString);

      expect(logical, '\u0627\u0628\u0645'); // ابم
      // Involutive: running on logical Arabic is an exact no-op
      expect(BidiTextUtils.toLogicalUnicode('مهندس برمجيات'), 'مهندس برمجيات');
    });

    test(
        'H3 toLogicalUnicode decomposes hamza forms, lam-alef ligatures, tanween, and Forms-A',
        () {
      // Hamza forms:
      // U+FE81 آ Alef with Madda (آدم)
      // U+FE83 أ Alef with Hamza above (أحمد)
      // U+FE87 إ Alef with Hamza below (إبراهيم)
      // U+FE8B ئ Yeh with Hamza (رئيس)
      // U+FE80 ء Isolated Hamza
      expect(BidiTextUtils.toLogicalUnicode('\uFE81دم'), 'آدم');
      expect(BidiTextUtils.toLogicalUnicode('\uFE83حمد'), 'أحمد');
      expect(BidiTextUtils.toLogicalUnicode('\uFE87براهيم'), 'إبراهيم');
      expect(BidiTextUtils.toLogicalUnicode('ر\uFE8Bيس'), 'رئيس');

      // Lam-Alef ligatures (expand 1 char to 2 characters):
      // U+FEFB ﻻ -> لا
      // U+FEF7 ﻷ -> لأ
      // U+FEF9 ﻹ -> لإ
      // U+FEF5 ﻵ -> لآ
      expect(BidiTextUtils.toLogicalUnicode('\uFEFB شكر على واجب'),
          'لا شكر على واجب');
      expect(BidiTextUtils.toLogicalUnicode('\uFEF7مر'), 'لأمر');

      // Tanween mark
      expect(BidiTextUtils.toLogicalUnicode('شكراً'), contains('شكراً'));

      // Presentation Forms-A:
      // U+FDF2 Allah ligature -> الله
      expect(BidiTextUtils.toLogicalUnicode('\uFDF2'), 'الله');
    });

    test(
        'H3 LogicalBidiInputFormatter preserves selection, composing range, and handles expanding ligatures',
        () {
      const formatter = LogicalBidiInputFormatter();

      // 1. Expanding ligature: \uFEFB (1 char) -> لا (2 chars)
      // Cursor was at offset 1; after decomposition should be at offset 2
      const ligatureInput = TextEditingValue(
        text: '\uFEFB',
        selection: TextSelection.collapsed(offset: 1),
      );
      final ligatureResult = formatter.formatEditUpdate(
          const TextEditingValue(text: ''), ligatureInput);
      expect(ligatureResult.text, 'لا');
      expect(ligatureResult.selection.baseOffset, 2);

      // 2. Selection range preservation across decomposition
      const selectionInput = TextEditingValue(
        text: 'أحمد \uFEFB',
        selection:
            TextSelection(baseOffset: 0, extentOffset: 4), // Selecting "أحمد"
      );
      final selectionResult = formatter.formatEditUpdate(
          const TextEditingValue(text: ''), selectionInput);
      expect(selectionResult.selection.baseOffset, 0);
      expect(selectionResult.selection.extentOffset, 4);

      // 3. Composing range preservation during predictive typing
      const composingInput = TextEditingValue(
        text: 'محم\uFE8D',
        selection: TextSelection.collapsed(offset: 4),
        composing: TextRange(start: 2, end: 4),
      );
      final composingResult = formatter.formatEditUpdate(
          const TextEditingValue(text: ''), composingInput);
      expect(composingResult.text, 'محما');
      expect(composingResult.composing.start, 2);
      expect(composingResult.composing.end, 4);
    });
  });

  group('SIRATI-28 Arabic Date and Numeral Formatting Invariants', () {
    test(
        'numeral conversion is an exact involutive round-trip for arbitrary numbers',
        () {
      for (int i = 0; i <= 9999; i += 137) {
        final original = i.toString();
        final arabicIndic = ArabicDateFormat.toArabicIndic(original);
        final restored = ArabicDateFormat.toWesternDigits(arabicIndic);
        expect(restored, original);
      }
    });

    test(
        'formatDate correctly maps 12 Gregorian months in Gulf Arabic without offset errors',
        () {
      const year = 2024;
      for (int m = 1; m <= 12; m++) {
        final date = DateTime(year, m, 15);
        final formattedAr = ArabicDateFormat.formatDate(date, locale: 'ar');
        expect(formattedAr, '${ArabicDateFormat.arabicMonths[m - 1]} 2024');

        final formattedEn = ArabicDateFormat.formatDate(date, locale: 'en');
        expect(formattedEn, '${ArabicDateFormat.englishMonths[m - 1]} 2024');
      }
    });

    test('gregorianToHijri produces valid Islamic civil dates', () {
      // 2024-03-11 was approximately 1 Ramadan 1445
      final ramadanStart = DateTime(2024, 3, 11);
      final h = ArabicDateFormat.gregorianToHijri(ramadanStart);

      expect(h.year, 1445);
      expect(h.month, 9); // Ramadan is month 9
      expect(h.day, inInclusiveRange(1, 2));

      // Month name
      final hijriFormatted =
          ArabicDateFormat.formatDate(ramadanStart, isHijri: true);
      expect(hijriFormatted, contains('رمضان 1445'));
    });

    test(
        'formatDateRange handles open-ended present jobs and dual calendar displays',
        () {
      final start = DateTime(2021, 6, 1);
      final end = DateTime(2023, 12, 31);

      // Arabic closed range
      final arRange = ArabicDateFormat.formatDateRange(
        start: start,
        end: end,
        locale: 'ar',
      );
      expect(arRange, 'يونيو 2021 – ديسمبر 2023');

      // Arabic ongoing ("حتى الآن")
      final ongoingRange = ArabicDateFormat.formatDateRange(
        start: start,
        isCurrent: true,
        locale: 'ar',
      );
      expect(ongoingRange, 'يونيو 2021 – حتى الآن');

      // English ongoing ("Present")
      final ongoingEn = ArabicDateFormat.formatDateRange(
        start: start,
        isCurrent: true,
        locale: 'en',
      );
      expect(ongoingEn, 'Jun 2021 - Present');

      // Dual calendar output
      final dual = ArabicDateFormat.formatDateRange(
        start: start,
        isCurrent: true,
        locale: 'ar',
        dualCalendar: true,
      );
      expect(dual, contains('يونيو 2021 – حتى الآن ('));
      expect(dual, contains('1442'));
    });

    test(
        'H4 parseCvDate and compareCvDates handle unpadded months, slashes, and Arabic month names',
        () {
      // 1. Unpadded month vs zero-padded month (2021-3 vs 2021-11)
      expect(ArabicDateFormat.compareCvDates('2021-3', '2021-11'), isNegative);
      expect(ArabicDateFormat.compareCvDates('2021-11', '2021-3'), isPositive);

      // 2. MM/YYYY format (03/2021 vs 11/2020)
      expect(ArabicDateFormat.compareCvDates('03/2021', '11/2020'), isPositive);
      expect(ArabicDateFormat.compareCvDates('11/2020', '03/2021'), isNegative);

      // 3. Arabic month names (مارس 2021 vs يناير 2020)
      expect(ArabicDateFormat.compareCvDates('مارس 2021', 'يناير 2020'),
          isPositive);
      expect(ArabicDateFormat.compareCvDates('يناير 2020', 'مارس 2021'),
          isNegative);

      // 4. Equal dates
      expect(ArabicDateFormat.compareCvDates('مارس 2021', '2021-03'), 0);
      expect(ArabicDateFormat.compareCvDates('يناير 2020', '01/2020'), 0);

      // 5. Eastern Arabic numerals (٢٠٢١-٣ vs ٢٠٢١-١١)
      expect(ArabicDateFormat.compareCvDates('٢٠٢١-٣', '٢٠٢١-١١'), isNegative);

      // 6. Cross-calendar comparison (Hijri vs Gregorian on common timeline)
      // ربيع الأول 1442 (≈ Oct 2020) is after مارس 2020 (March 2020)
      expect(ArabicDateFormat.compareCvDates('ربيع الأول 1442', 'مارس 2020'),
          isPositive);
      expect(ArabicDateFormat.compareCvDates('مارس 2020', 'ربيع الأول 1442'),
          isNegative);

      // Two Hijri dates: ربيع الأول 1442 (Oct 2020) vs رجب 1442 (Feb 2021)
      expect(ArabicDateFormat.compareCvDates('ربيع الأول 1442', 'رجب 1442'),
          isNegative);

      // 7. Word-boundary month matching: '2021 mayer' must not falsely match 'may'
      expect(ArabicDateFormat.parseCvDate('2021 mayer'), isNull);
      final validMay = ArabicDateFormat.parseCvDate('may 2021');
      expect(validMay, isNotNull);
      expect(validMay!.year, 2021);
      expect(validMay.month, 5);

      // 8. Invertible hijriToGregorian conversion
      final gregDate = DateTime(2020, 10, 15);
      final h = ArabicDateFormat.gregorianToHijri(gregDate);
      final gRestored =
          ArabicDateFormat.hijriToGregorian(h.year, h.month, h.day);
      expect(gRestored.year, 2020);
      expect(gRestored.month, 10);
      expect(gRestored.day, 15);
    });
  });
}
