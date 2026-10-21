/// Parser for bilingual job titles (Arabic primary on top, English secondary).
///
/// Ensures fixed order (Arabic always first when present, English below)
/// across all representations and delimiters: parentheses, hyphens, pipes, or slashes.
class ParsedJobTitle {
  final String? arabic;
  final String? english;

  const ParsedJobTitle({this.arabic, this.english});

  static final RegExp _arabicCharRegex = RegExp(
    r'[\u0590-\u05FF\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]',
  );
  static final RegExp _latinCharRegex = RegExp(r'[A-Za-z]');

  static bool hasArabic(String text) => _arabicCharRegex.hasMatch(text);
  static bool hasLatin(String text) => _latinCharRegex.hasMatch(text);

  /// Primary title: Arabic if available, else English.
  String? get primaryTitle => arabic ?? english;

  /// Secondary subtitle: English if Arabic was primary and English is available.
  String? get secondaryTitle => arabic != null ? english : null;

  /// Whether both Arabic and English versions exist.
  bool get isBilingual =>
      arabic != null &&
      arabic!.isNotEmpty &&
      english != null &&
      english!.isNotEmpty;

  /// Parse any raw job title string into structured Arabic and English components.
  static ParsedJobTitle parse(String? raw) {
    if (raw == null) return const ParsedJobTitle();
    final trimmed = raw.trim();
    if (trimmed.isEmpty) return const ParsedJobTitle();

    final containsAr = hasArabic(trimmed);
    final containsEn = hasLatin(trimmed);

    // Single language fast path
    if (containsAr && !containsEn) {
      return ParsedJobTitle(arabic: trimmed);
    }
    if (!containsAr && containsEn) {
      return ParsedJobTitle(english: trimmed);
    }
    if (!containsAr && !containsEn) {
      return ParsedJobTitle(arabic: trimmed);
    }

    // Both Arabic and English are present.
    // Strategy 1: Check parenthesized groups e.g. "مطور (Developer)" or "Developer (مطور)"
    final parenRegex = RegExp(r'\(([^)]+)\)');
    final parenMatches = parenRegex.allMatches(trimmed);
    for (final match in parenMatches) {
      final inside = match.group(1)?.trim() ?? '';
      final insideAr = hasArabic(inside);
      final insideEn = hasLatin(inside);

      if (insideEn && !insideAr) {
        final outside =
            '${trimmed.substring(0, match.start)} ${trimmed.substring(match.end)}'
                .trim();
        if (hasArabic(outside)) {
          return ParsedJobTitle(
            arabic: _cleanDelimiters(outside),
            english: _cleanDelimiters(inside),
          );
        }
      }

      if (insideAr && !insideEn) {
        final outside =
            '${trimmed.substring(0, match.start)} ${trimmed.substring(match.end)}'
                .trim();
        if (hasLatin(outside)) {
          return ParsedJobTitle(
            arabic: _cleanDelimiters(inside),
            english: _cleanDelimiters(outside),
          );
        }
      }
    }

    // Strategy 2: Check square brackets e.g. "مطور [Developer]"
    final bracketRegex = RegExp(r'\[([^\]]+)\]');
    final bracketMatches = bracketRegex.allMatches(trimmed);
    for (final match in bracketMatches) {
      final inside = match.group(1)?.trim() ?? '';
      final insideAr = hasArabic(inside);
      final insideEn = hasLatin(inside);

      if (insideEn && !insideAr) {
        final outside =
            '${trimmed.substring(0, match.start)} ${trimmed.substring(match.end)}'
                .trim();
        if (hasArabic(outside)) {
          return ParsedJobTitle(
            arabic: _cleanDelimiters(outside),
            english: _cleanDelimiters(inside),
          );
        }
      }

      if (insideAr && !insideEn) {
        final outside =
            '${trimmed.substring(0, match.start)} ${trimmed.substring(match.end)}'
                .trim();
        if (hasLatin(outside)) {
          return ParsedJobTitle(
            arabic: _cleanDelimiters(inside),
            english: _cleanDelimiters(outside),
          );
        }
      }
    }

    // Strategy 3: Check explicit boundary separators e.g. " - ", " | ", " / ", " — "
    final splitRegex = RegExp(r'\s+(?:[-–—|/]|\|)\s+');
    final parts = trimmed
        .split(splitRegex)
        .map((s) => s.trim())
        .where((s) => s.isNotEmpty)
        .toList();
    if (parts.length >= 2) {
      final arParts = parts.where(hasArabic).toList();
      final enParts = parts.where((p) => hasLatin(p) && !hasArabic(p)).toList();

      if (arParts.isNotEmpty && enParts.isNotEmpty) {
        return ParsedJobTitle(
          arabic: _cleanDelimiters(arParts.join(' - ')),
          english: _cleanDelimiters(enParts.join(' - ')),
        );
      }
    }

    // Strategy 4: Boundary between Arabic run and Latin run without delimiter
    final transitionArEn = RegExp(
      r'^([\u0590-\u05FF\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF\s\d]+?)\s+([A-Za-z0-9\s/+#.-]+)$',
    ).firstMatch(trimmed);
    if (transitionArEn != null) {
      final p1 = transitionArEn.group(1)!.trim();
      final p2 = transitionArEn.group(2)!.trim();
      if (hasArabic(p1) && hasLatin(p2) && !hasArabic(p2)) {
        return ParsedJobTitle(
          arabic: _cleanDelimiters(p1),
          english: _cleanDelimiters(p2),
        );
      }
    }

    final transitionEnAr = RegExp(
      r'^([A-Za-z0-9\s/+#.-]+?)\s+([\u0590-\u05FF\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF\s\d]+)$',
    ).firstMatch(trimmed);
    if (transitionEnAr != null) {
      final p1 = transitionEnAr.group(1)!.trim();
      final p2 = transitionEnAr.group(2)!.trim();
      if (hasLatin(p1) && !hasArabic(p1) && hasArabic(p2)) {
        return ParsedJobTitle(
          arabic: _cleanDelimiters(p2),
          english: _cleanDelimiters(p1),
        );
      }
    }

    // Fallback: entire string as Arabic title
    return ParsedJobTitle(arabic: trimmed);
  }

  static String _cleanDelimiters(String text) {
    return text
        .replaceAll(
          RegExp(r'^[\s\-_/|–—()\[\]{}]+|[\s\-_/|–—()\[\]{}]+$'),
          '',
        )
        .replaceAll(RegExp(r'\s+'), ' ')
        .trim();
  }
}
