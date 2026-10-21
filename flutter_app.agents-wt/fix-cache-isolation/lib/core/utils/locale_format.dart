import 'package:sirati/core/utils/app_format.dart';
import 'package:sirati/core/utils/bidi_text.dart';

/// Locale-aware copy helpers (plurals, mixed labels, sample data fixes).
class LocaleFormat {
  LocaleFormat._();

  /// Draft/completed CV count summary for My CVs header.
  static String cvFilesSummary(int count, {required bool english}) {
    if (english) {
      if (count <= 0) return 'You have no CVs yet';
      if (count == 1) return 'You have 1 draft or completed file';
      return 'You have $count draft and completed files';
    }

    // Arabic pluralization (simplified, MSA-friendly):
    // 0, 1, 2 special; 3–10: ملفات; 11+: ملف
    if (count <= 0) return 'ليس لديك ملفات بعد';
    if (count == 1) return 'لديك ملف مسودة أو مكتمل واحد';
    if (count == 2) return 'لديك ملفان مسودة أو مكتملان';
    final n = AppFormat.digits(count, english: false);
    if (count >= 3 && count <= 10) {
      return 'لديك $n ملفات مسودة ومكتملة';
    }
    return 'لديك $n ملف مسودة ومكتمل';
  }

  /// Item count label e.g. analyses / CVs chips.
  static String countNoun({
    required int count,
    required bool english,
    required String singularEn,
    required String pluralEn,
    required String singularAr,
    required String dualAr,
    required String fewAr, // 3–10
    required String manyAr, // 11+
  }) {
    if (english) {
      return count == 1 ? '$count $singularEn' : '$count $pluralEn';
    }
    if (count == 0) return 'لا $fewAr';
    if (count == 1) return '$singularAr واحد';
    if (count == 2) return dualAr;
    final n = AppFormat.digits(count, english: false);
    if (count >= 3 && count <= 10) return '$n $fewAr';
    return '$n $manyAr';
  }

  /// Display job / CV titles that may mix AR + EN; protect Latin runs in AR.
  static String mixedTitle(String title, {required bool english}) {
    final cleaned = title.trim();
    if (cleaned.isEmpty) {
      return english ? 'Untitled' : 'بدون عنوان';
    }
    if (english) return cleaned;
    return BidiText.protectLatinTokens(cleaned);
  }

  /// Body paragraphs (job descriptions, long copy) for stable BiDi.
  static String mixedBody(String body, {required bool english}) {
    if (body.isEmpty) return body;
    if (english) return body;
    return BidiText.protectLatinTokens(body);
  }

  /// Normalize common sample typos from demos / mocks.
  static String fixSampleTitle(String title) {
    return title
        .replaceAll(RegExp(r'applicaion', caseSensitive: false), 'Application')
        .replaceAll(RegExp(r'devloper', caseSensitive: false), 'Developer');
  }

  /// ATS badge label that stays stable in RTL layouts.
  static String atsBadge(String scoreLabel, {required bool english}) {
    final cleaned = scoreLabel.trim();
    if (cleaned.isEmpty) return cleaned;
    if (english) return cleaned;
    // e.g. "ATS 47%" → isolate ATS product token
    return BidiText.protectLatinTokens(cleaned);
  }

  /// Relative time for draft banners, e.g. "Saved 5 min ago" / "حُفظت قبل ٥ دقائق".
  static String relativeSavedAt(DateTime savedAt,
      {required bool english, DateTime? now}) {
    final rel = AppFormat.relativeTime(savedAt, english: english, now: now);
    if (english) {
      if (rel == 'just now') return 'Saved just now';
      return 'Saved $rel';
    }
    if (rel == 'الآن') return 'حُفظت للتو';
    if (rel == 'أمس') return 'حُفظت أمس';
    // "قبل …" or short date
    if (rel.startsWith('قبل')) return 'حُفظت $rel';
    return 'حُفظت $rel';
  }

  /// Western digits → Eastern Arabic digits (٠١٢…).
  static String toEasternArabicDigits(String input) =>
      AppFormat.toEasternArabicDigits(input);
}
