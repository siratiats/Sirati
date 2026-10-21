/// A display block of a generated CV's markdown.
sealed class CvBlock {
  const CvBlock(this.text);
  final String text;

  @override
  bool operator ==(Object other) =>
      other.runtimeType == runtimeType && (other as CvBlock).text == text;

  @override
  int get hashCode => Object.hash(runtimeType, text);

  @override
  String toString() => '$runtimeType($text)';
}

/// `##`–`####` section heading.
final class CvHeading extends CvBlock {
  const CvHeading(super.text);
}

/// A line that is bold as a whole (role / organisation lines).
final class CvStrongLine extends CvBlock {
  const CvStrongLine(super.text);
}

final class CvBullet extends CvBlock {
  const CvBullet(super.text);
}

/// Any other line. [isContact] lines (email, phone, `a | b` rows) are
/// always laid out left-to-right.
final class CvParagraph extends CvBlock {
  const CvParagraph(super.text, {this.isContact = false});
  final bool isContact;

  @override
  bool operator ==(Object other) =>
      other is CvParagraph &&
      other.text == text &&
      other.isContact == isContact;

  @override
  int get hashCode => Object.hash(text, isContact);
}

final _heading = RegExp(r'^#{1,6}\s+');
final _bullet = RegExp(r'^[-*•]\s+');

/// Parses the subset of markdown the generator emits. Empty lines and
/// horizontal rules are dropped; `**` emphasis markers never reach the UI.
List<CvBlock> parseCvMarkdown(String markdown) {
  final blocks = <CvBlock>[];
  for (final line in markdown.split(RegExp(r'\r?\n'))) {
    final raw = line.trim();
    // Blank lines, horizontal rules, and bare markers ("##", "-") carry no
    // text for the reader.
    if (raw.isEmpty ||
        RegExp(r'^(-{3,}|\*{3,}|_{3,}|#{1,6}|[-*•])$').hasMatch(raw)) {
      continue;
    }
    if (_heading.hasMatch(raw)) {
      final title = _stripEmphasis(raw.replaceFirst(_heading, ''));
      if (title.isNotEmpty) blocks.add(CvHeading(title));
    } else if (_bullet.hasMatch(raw)) {
      final text = _stripEmphasis(raw.replaceFirst(_bullet, ''));
      if (text.isNotEmpty) blocks.add(CvBullet(text));
    } else if (raw.length > 4 && raw.startsWith('**') && raw.endsWith('**')) {
      blocks.add(CvStrongLine(_stripEmphasis(raw)));
    } else {
      final text = _stripEmphasis(raw);
      blocks.add(CvParagraph(text, isContact: _looksLikeContact(text)));
    }
  }
  return blocks;
}

String _stripEmphasis(String s) =>
    s.replaceAll('**', '').replaceAll('__', '').trim();

bool _looksLikeContact(String s) =>
    s.contains('@') ||
    s.contains('|') ||
    s.toLowerCase().contains('email:') ||
    s.toLowerCase().contains('phone:');

/// Plain-text rendering for sharing: headings upper-cased in English, bullets
/// as "•", no markdown syntax.
String cvPlainText({
  required String fullName,
  required String headline,
  required String markdown,
}) {
  final out = StringBuffer();
  if (fullName.trim().isNotEmpty) out.writeln(fullName.trim());
  if (headline.trim().isNotEmpty) out.writeln(headline.trim());
  for (final block in parseCvMarkdown(markdown)) {
    switch (block) {
      case CvHeading(:final text):
        out
          ..writeln()
          ..writeln(text);
      case CvBullet(:final text):
        out.writeln('• $text');
      case CvStrongLine(:final text) || CvParagraph(:final text):
        out.writeln(text);
    }
  }
  return out.toString().trim();
}

/// English headlines are title-cased ("backend developer" → "Backend
/// Developer"); other languages are shown as written.
String displayHeadline(String raw, {required String language}) {
  final trimmed = raw.trim();
  if (trimmed.isEmpty || language != 'en') return trimmed;
  return trimmed
      .split(RegExp(r'\s+'))
      .map((w) => w.isEmpty ? w : w[0].toUpperCase() + w.substring(1))
      .join(' ');
}
