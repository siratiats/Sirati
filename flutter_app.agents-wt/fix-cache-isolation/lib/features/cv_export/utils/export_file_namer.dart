/// File naming utility for CV PDF exports.
///
/// Satisfies SIRATI-48 acceptance criteria:
/// - Filename derived from candidate name and role, sanitized for filesystem safety
/// - Arabic filenames handled correctly, with a Latin fallback option
/// - Cross-platform illegal character stripping (`/ \ : * ? " < > |`)
class ExportFileNamer {
  static const List<String> _illegalChars = [
    '/',
    '\\',
    ':',
    '*',
    '?',
    '"',
    '<',
    '>',
    '|',
    '\x00',
  ];

  /// Generate a sanitized export filename for the given name and role.
  ///
  /// [language] determines whether to format in Arabic ('ar') or Latin ('en').
  static String formatFilename({
    required String? fullName,
    required String? targetJobTitle,
    String language = 'ar',
    bool latinFallback = false,
  }) {
    final useLatin = latinFallback || language == 'en';
    final name = (fullName ?? '').trim();
    final role = (targetJobTitle ?? '').trim();

    if (useLatin) {
      final safeName =
          _slugifyLatin(name).isNotEmpty ? _slugifyLatin(name) : 'Candidate';
      final safeRole =
          _slugifyLatin(role).isNotEmpty ? _slugifyLatin(role) : 'CV';
      return 'CV_${safeName}_$safeRole.pdf';
    }

    final safeName =
        _sanitizeArabic(name).isNotEmpty ? _sanitizeArabic(name) : 'مرشح';
    final safeRole =
        _sanitizeArabic(role).isNotEmpty ? _sanitizeArabic(role) : 'مهني';
    return 'سيرة_ذاتية_${safeName}_$safeRole.pdf';
  }

  /// Sanitize Arabic text for filesystem storage by converting whitespace to underscores,
  /// removing illegal characters, and trimming.
  static String _sanitizeArabic(String text) {
    var cleaned = text.replaceAll(RegExp(r'\s+'), '_');
    for (final char in _illegalChars) {
      cleaned = cleaned.replaceAll(char, '');
    }
    // Collapse consecutive underscores
    cleaned = cleaned.replaceAll(RegExp(r'_+'), '_');
    // Strip leading/trailing underscores and dots
    cleaned = cleaned.replaceAll(RegExp(r'^[_.]+|[_.]+$'), '');
    return cleaned;
  }

  /// Transliterate or slugify arbitrary text into clean Latin ASCII.
  static String _slugifyLatin(String text) {
    var cleaned = text.replaceAll(RegExp(r'\s+'), '_');
    for (final char in _illegalChars) {
      cleaned = cleaned.replaceAll(char, '');
    }

    // Common Arabic-to-Latin transliterations if Arabic text is provided for Latin export
    const translit = <String, String>{
      'أ': 'A',
      'إ': 'I',
      'آ': 'Aa',
      'ا': 'A',
      'ب': 'B',
      'ت': 'T',
      'ث': 'Th',
      'ج': 'J',
      'ح': 'H',
      'خ': 'Kh',
      'د': 'D',
      'ذ': 'Dh',
      'ر': 'R',
      'ز': 'Z',
      'س': 'S',
      'ش': 'Sh',
      'ص': 'S',
      'ض': 'Dh',
      'ط': 'T',
      'ظ': 'Z',
      'ع': 'A',
      'غ': 'Gh',
      'ف': 'F',
      'ق': 'Q',
      'ك': 'K',
      'ل': 'L',
      'م': 'M',
      'ن': 'N',
      'ه': 'H',
      'و': 'W',
      'ي': 'Y',
      'ى': 'A',
      'ة': 'h',
      'ء': '',
      'ئ': 'Y',
      'ؤ': 'W',
    };

    final buffer = StringBuffer();
    for (int i = 0; i < cleaned.length; i++) {
      final char = cleaned[i];
      if (translit.containsKey(char)) {
        buffer.write(translit[char]);
      } else if (RegExp(r'[a-zA-Z0-9]').hasMatch(char)) {
        buffer.write(char);
      } else if (char == '-') {
        buffer.write('-');
      } else if (char == ' ' || char == '_') {
        buffer.write('_');
      }
    }

    var result = buffer.toString();
    result = result.replaceAll(RegExp(r'_+'), '_');
    result = result.replaceAll(RegExp(r'^[_.]+|[_.]+$'), '');
    return result;
  }
}
