/// Arabic date and numeral formatting utilities for CV date ranges (SIRATI-28).
///
/// Supports Gregorian and Hijri (Islamic civil) date representations,
/// localized Arabic month names (Gulf standard), and Western vs Arabic-Indic numerals.
class ArabicDateFormat {
  ArabicDateFormat._();

  /// Standard Arabic month names used across Gulf region CVs
  static const List<String> arabicMonths = [
    'يناير',
    'فبراير',
    'مارس',
    'أبريل',
    'مايو',
    'يونيو',
    'يوليو',
    'أغسطس',
    'سبتمبر',
    'أكتوبر',
    'نوفمبر',
    'ديسمبر',
  ];

  static const List<String> englishMonths = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];

  static const List<String> hijriMonths = [
    'محرم',
    'صفر',
    'ربيع الأول',
    'ربيع الثاني',
    'جمادى الأولى',
    'جمادى الآخرة',
    'رجب',
    'شعبان',
    'رمضان',
    'شوال',
    'ذو القعدة',
    'ذو الحجة',
  ];

  static const Map<String, String> _westernToArabicIndicDigits = {
    '0': '٠',
    '1': '١',
    '2': '٢',
    '3': '٣',
    '4': '٤',
    '5': '٥',
    '6': '٦',
    '7': '٧',
    '8': '٨',
    '9': '٩',
  };

  static const Map<String, String> _arabicIndicToWesternDigits = {
    '٠': '0',
    '١': '1',
    '٢': '2',
    '٣': '3',
    '٤': '4',
    '٥': '5',
    '٦': '6',
    '٧': '7',
    '٨': '8',
    '٩': '9',
  };

  /// Converts digits in [input] to Arabic-Indic digits (٠-٩).
  static String toArabicIndic(String input) {
    var result = input;
    _westernToArabicIndicDigits.forEach((key, value) {
      result = result.replaceAll(key, value);
    });
    return result;
  }

  /// Converts Arabic-Indic digits (٠-٩) in [input] to Western digits (0-9).
  static String toWesternDigits(String input) {
    var result = input;
    _arabicIndicToWesternDigits.forEach((key, value) {
      result = result.replaceAll(key, value);
    });
    return result;
  }

  /// Formats a number with either Western or Arabic-Indic digits.
  static String formatNumber(num value, {bool arabicIndic = false}) {
    final str = value.toString();
    return arabicIndic ? toArabicIndic(str) : str;
  }

  /// Converts a Gregorian date to an approximate civil Hijri date (year, month 1-12, day).
  ///
  /// Uses the standard Kuwaiti / Umm al-Qura civil algorithm for astrological approximation.
  static ({int year, int month, int day}) gregorianToHijri(DateTime date) {
    int m = date.month;
    int y = date.year;
    final int d = date.day;

    if (m < 3) {
      y -= 1;
      m += 12;
    }

    final a = (y / 100).floor();
    final b = 2 - a + (a / 4).floor();
    final jd = (365.25 * (y + 4716)).floor() +
        (30.6001 * (m + 1)).floor() +
        d +
        b -
        1524;

    final l = jd - 1948440 + 10632;
    final n = ((l - 1) / 10631).floor();
    final l2 = l - 10631 * n + 354;
    final j = ((10985 - l2) / 5316).floor() * ((50 * l2) / 17719).floor() +
        (l2 / 5670).floor() * ((43 * l2) / 15238).floor();
    final l3 = l2 -
        ((30 - j) / 15).floor() * ((17719 * j) / 50).floor() -
        (j / 16).floor() * ((15238 * j) / 43).floor() +
        29;
    final hijriMonth = ((24 * l3) / 709).floor();
    final hijriDay = l3 - ((709 * hijriMonth) / 24).floor();
    final hijriYear = (30 * n) + j - 30;

    return (
      year: hijriYear,
      month: hijriMonth.clamp(1, 12),
      day: hijriDay.clamp(1, 30),
    );
  }

  /// Converts a civil Hijri date (year, month 1-12, day) to Gregorian (year, month, day).
  ///
  /// Invertible companion to [gregorianToHijri].
  static ({int year, int month, int day}) hijriToGregorian(
    int hYear,
    int hMonth, [
    int hDay = 1,
  ]) {
    final jd = ((11 * hYear + 3) ~/ 30) +
        354 * hYear +
        30 * hMonth -
        ((hMonth - 1) ~/ 2) +
        hDay +
        1948440 -
        385;

    final l = jd + 68569;
    final n = (4 * l) ~/ 146097;
    final l1 = l - ((146097 * n + 3) ~/ 4);
    final i = (4000 * (l1 + 1)) ~/ 1461001;
    final l2 = l1 - ((1461 * i) ~/ 4) + 31;
    final j = (80 * l2) ~/ 2447;
    final d = l2 - ((2447 * j) ~/ 80);
    final l3 = j ~/ 11;
    final m = j + 2 - 12 * l3;
    final y = 100 * (n - 49) + i + l3;

    return (
      year: y,
      month: m.clamp(1, 12),
      day: d.clamp(1, 31),
    );
  }

  /// Localized single date formatting e.g. "يناير 2024" or "Jan 2024".
  static String formatDate(
    DateTime date, {
    String locale = 'ar',
    bool arabicIndic = false,
    bool isHijri = false,
  }) {
    if (isHijri) {
      final h = gregorianToHijri(date);
      final monthName = hijriMonths[h.month - 1];
      final formattedYear = formatNumber(h.year, arabicIndic: arabicIndic);
      return '$monthName $formattedYear';
    }

    final monthName = locale == 'ar'
        ? arabicMonths[date.month - 1]
        : englishMonths[date.month - 1];
    final formattedYear = formatNumber(date.year, arabicIndic: arabicIndic);
    return '$monthName $formattedYear';
  }

  /// Formats a CV experience or education date range e.g.
  /// "يناير 2020 – حتى الآن" or "Jan 2020 – Present"
  static String formatDateRange({
    required DateTime? start,
    DateTime? end,
    bool isCurrent = false,
    String locale = 'ar',
    bool arabicIndic = false,
    bool isHijri = false,
    bool dualCalendar = false,
  }) {
    if (start == null) return '';

    final startStr = formatDate(
      start,
      locale: locale,
      arabicIndic: arabicIndic,
      isHijri: isHijri,
    );

    String endStr;
    if (isCurrent) {
      endStr = locale == 'ar' ? 'حتى الآن' : 'Present';
    } else if (end != null) {
      endStr = formatDate(
        end,
        locale: locale,
        arabicIndic: arabicIndic,
        isHijri: isHijri,
      );
    } else {
      endStr = '';
    }

    final dash = locale == 'ar' ? ' – ' : ' - ';
    var result = endStr.isNotEmpty ? '$startStr$dash$endStr' : startStr;

    if (dualCalendar && !isHijri) {
      // Append Hijri equivalent in parentheses
      final hStart = gregorianToHijri(start);
      final hEnd = (end != null && !isCurrent) ? gregorianToHijri(end) : null;
      final hStartStr =
          '${hijriMonths[hStart.month - 1]} ${formatNumber(hStart.year, arabicIndic: arabicIndic)}';
      final hEndStr = hEnd != null
          ? '${hijriMonths[hEnd.month - 1]} ${formatNumber(hEnd.year, arabicIndic: arabicIndic)}'
          : (locale == 'ar' ? 'الآن' : 'Now');
      result += ' ($hStartStr$dash$hEndStr)';
    }

    return result;
  }

  static const Set<String> _hijriMonthNames = {
    'محرم',
    'صفر',
    'ربيع الأول',
    'ربيع الثاني',
    'جمادى الأولى',
    'جمادى الآخرة',
    'رجب',
    'شعبان',
    'رمضان',
    'شوال',
    'ذو القعدة',
    'ذو الحجة',
  };

  static const Map<String, int> _monthNameToNumber = {
    'يناير': 1,
    'فبراير': 2,
    'مارس': 3,
    'أبريل': 4,
    'مايو': 5,
    'يونيو': 6,
    'يوليو': 7,
    'أغسطس': 8,
    'سبتمبر': 9,
    'أكتوبر': 10,
    'نوفمبر': 11,
    'ديسمبر': 12,
    'jan': 1,
    'january': 1,
    'feb': 2,
    'february': 2,
    'mar': 3,
    'march': 3,
    'apr': 4,
    'april': 4,
    'may': 5,
    'jun': 6,
    'june': 6,
    'jul': 7,
    'july': 7,
    'aug': 8,
    'august': 8,
    'sep': 9,
    'september': 9,
    'oct': 10,
    'october': 10,
    'nov': 11,
    'november': 11,
    'dec': 12,
    'december': 12,
    'محرم': 1,
    'صفر': 2,
    'ربيع الأول': 3,
    'ربيع الثاني': 4,
    'جمادى الأولى': 5,
    'جمادى الآخرة': 6,
    'رجب': 7,
    'شعبان': 8,
    'رمضان': 9,
    'شوال': 10,
    'ذو القعدة': 11,
    'ذو الحجة': 12,
  };

  /// Parses arbitrary CV date strings into structured year/month record normalized
  /// to the common Gregorian era for consistent chronological comparison.
  ///
  /// Supports:
  /// - ISO format: `YYYY-MM` or `YYYY-M` (e.g. `2021-03`, `2021-3`, `2021-11`)
  /// - Slash format: `MM/YYYY`, `M/YYYY`, or `YYYY/MM` (e.g. `03/2021`, `11/2020`)
  /// - Localized month names + year: `مارس 2021`, `يناير 2020`, `Jan 2021`
  /// - Hijri month names & years: `ربيع الأول 1442`, `1442-03`
  /// - Plain years: `2019`, `2021`, `1442`
  /// - Works seamlessly with Arabic-Indic digits (`٢٠٢١-٣`)
  static ({int year, int month})? parseCvDate(String? raw) {
    if (raw == null || raw.trim().isEmpty) return null;

    // Normalize Arabic-Indic digits to Western digits
    final text = toWesternDigits(raw.trim()).toLowerCase();

    // 1. Check for Month name + Year: e.g. "مارس 2021", "March 2021", "ربيع الأول 1442"
    for (final entry in _monthNameToNumber.entries) {
      final name = entry.key;
      // Word-boundary check: ensure month name is isolated by whitespace/punctuation/boundaries
      final escaped = RegExp.escape(name);
      final pattern = RegExp(
          '(^|[^a-zA-Z0-9\u0600-\u06FF])$escaped(\$|[^a-zA-Z0-9\u0600-\u06FF])');
      if (pattern.hasMatch(text)) {
        final yearMatch = RegExp(r'\b(\d{3,4})\b').firstMatch(text);
        if (yearMatch != null) {
          final year = int.tryParse(yearMatch.group(1)!);
          if (year != null) {
            final isHijri = _hijriMonthNames.contains(name) ||
                year < 1700 ||
                text.contains('هـ') ||
                text.contains('ه');
            if (isHijri) {
              final g = hijriToGregorian(year, entry.value);
              return (year: g.year, month: g.month);
            }
            return (year: year, month: entry.value);
          }
        }
      }
    }

    // 2. Check for YYYY-MM or YYYY-M or YYYY/MM or YYYY/M
    final yFirstMatch = RegExp(r'^(\d{3,4})[-/.](\d{1,2})$').firstMatch(text);
    if (yFirstMatch != null) {
      final y = int.tryParse(yFirstMatch.group(1)!);
      final m = int.tryParse(yFirstMatch.group(2)!);
      if (y != null && m != null) {
        if (y < 1700) {
          final g = hijriToGregorian(y, m.clamp(1, 12));
          return (year: g.year, month: g.month);
        }
        return (year: y, month: m.clamp(1, 12));
      }
    }

    // 3. Check for MM/YYYY or M/YYYY or MM-YYYY or M-YYYY
    final mFirstMatch = RegExp(r'^(\d{1,2})[-/.](\d{3,4})$').firstMatch(text);
    if (mFirstMatch != null) {
      final m = int.tryParse(mFirstMatch.group(1)!);
      final y = int.tryParse(mFirstMatch.group(2)!);
      if (y != null && m != null) {
        if (y < 1700) {
          final g = hijriToGregorian(y, m.clamp(1, 12));
          return (year: g.year, month: g.month);
        }
        return (year: y, month: m.clamp(1, 12));
      }
    }

    // 4. Plain year: YYYY
    final yearOnlyMatch = RegExp(r'^\b(\d{3,4})\b$').firstMatch(text);
    if (yearOnlyMatch != null) {
      final y = int.tryParse(yearOnlyMatch.group(1)!);
      if (y != null) {
        if (y < 1700) {
          final g = hijriToGregorian(y, 1);
          return (year: g.year, month: g.month);
        }
        return (year: y, month: 1);
      }
    }

    return null;
  }

  /// Compares two arbitrary CV dates chronologically.
  ///
  /// Returns a negative integer if [a] is before [b],
  /// zero if [a] equals [b],
  /// and a positive integer if [a] is after [b].
  static int compareCvDates(String? a, String? b) {
    final parsedA = parseCvDate(a);
    final parsedB = parseCvDate(b);

    if (parsedA == null && parsedB == null) {
      return (a ?? '').compareTo(b ?? '');
    }
    if (parsedA == null) return -1;
    if (parsedB == null) return 1;

    final scoreA = parsedA.year * 12 + parsedA.month;
    final scoreB = parsedB.year * 12 + parsedB.month;
    return scoreA.compareTo(scoreB);
  }
}
