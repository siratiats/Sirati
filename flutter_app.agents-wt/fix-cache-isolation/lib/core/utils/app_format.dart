/// Locale-aware display formatting (relative time, digit shapes).
///
/// Not ARB/intl — keep lightweight and consistent with Sirati bilingual UI.
class AppFormat {
  AppFormat._();

  /// Always returns Latin digits (0-9) across both locales (SIRATI-63).
  static String digits(Object value, {required bool english}) {
    final s = value is String ? value : value.toString();
    return s;
  }

  /// Relative time for lists, banners, and history.
  ///
  /// English: "just now", "5 min ago", "2 h ago", "yesterday", or short date.
  /// Arabic: "الآن", "قبل ٥ دقائق", "قبل ساعتين", "أمس", or short date with
  /// Arabic-Indic digits.
  static String relativeTime(
    DateTime when, {
    required bool english,
    DateTime? now,
  }) {
    final n = now ?? DateTime.now();
    final local = when.isUtc ? when.toLocal() : when;
    var seconds = n.difference(local).inSeconds;
    if (seconds < 0) seconds = 0;

    if (seconds < 45) {
      return english ? 'just now' : 'الآن';
    }

    final minutes = (seconds / 60).round();
    if (minutes < 60) {
      if (english) {
        return minutes <= 1 ? '1 min ago' : '$minutes min ago';
      }
      return 'قبل ${_arUnit(minutes, 'دقيقة', 'دقيقتين', 'دقائق', 'دقيقة')}';
    }

    final hours = (minutes / 60).round();
    if (hours < 24) {
      if (english) {
        return hours == 1 ? '1 h ago' : '$hours h ago';
      }
      return 'قبل ${_arUnit(hours, 'ساعة', 'ساعتين', 'ساعات', 'ساعة')}';
    }

    final today = DateTime(n.year, n.month, n.day);
    final day = DateTime(local.year, local.month, local.day);
    if (today.difference(day).inDays == 1) {
      return english ? 'yesterday' : 'أمس';
    }

    return shortDate(local, english: english);
  }

  /// Compact `d/M/yyyy` with locale-appropriate digits.
  static String shortDate(DateTime date, {required bool english}) {
    final local = date.isUtc ? date.toLocal() : date;
    final raw = '${local.day}/${local.month}/${local.year}';
    return digits(raw, english: english);
  }

  /// Arabic dual/plural unit with Latin digits for counts ≥ 3 (SIRATI-63).
  static String _arUnit(
    int n,
    String singular,
    String dual,
    String few,
    String many,
  ) {
    if (n == 1) return singular;
    if (n == 2) return dual;
    if (n >= 3 && n <= 10) return '$n $few';
    return '$n $many';
  }

  /// Western digits → Eastern Arabic digits (٠١٢…).
  static String toEasternArabicDigits(String input) {
    const western = '0123456789';
    const eastern = '٠١٢٣٤٥٦٧٨٩';
    final buf = StringBuffer();
    for (final rune in input.runes) {
      final ch = String.fromCharCode(rune);
      final i = western.indexOf(ch);
      buf.write(i >= 0 ? eastern[i] : ch);
    }
    return buf.toString();
  }
}
