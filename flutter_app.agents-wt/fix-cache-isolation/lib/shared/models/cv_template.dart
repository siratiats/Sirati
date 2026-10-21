class CvTemplate {
  final int id;
  final String slug;
  final String name;
  final String nameAr;
  final String nameEn;
  final String? previewImageUrl;
  final String languageDirection;
  final List<String> supportedLanguages;
  final List<String> supportedSections;
  final bool isDefault;
  final bool isPremium;

  const CvTemplate({
    required this.id,
    required this.slug,
    required this.name,
    required this.nameAr,
    required this.nameEn,
    required this.previewImageUrl,
    required this.languageDirection,
    required this.supportedLanguages,
    required this.supportedSections,
    required this.isDefault,
    this.isPremium = false,
  });

  factory CvTemplate.fromJson(Map<String, dynamic> json) {
    return CvTemplate(
      id: _asInt(json['id']),
      slug: json['slug']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      nameAr: json['name_ar']?.toString() ?? '',
      nameEn: json['name_en']?.toString() ?? '',
      previewImageUrl: _nullable(json['preview_image_url']),
      languageDirection: json['language_direction']?.toString() ?? 'rtl',
      supportedLanguages: _stringList(json['supported_languages']),
      supportedSections: _stringList(json['supported_sections']),
      isDefault: json['is_default'] == true,
      isPremium: json['is_premium'] == true,
    );
  }

  /// Canonical sections this layout will not display.
  List<String> omittedCanonicalSections(Iterable<String> populated) {
    if (supportedSections.isEmpty) return const [];
    const aliases = <String, List<String>>{
      'summary': ['summary', 'career_objective', 'executive_profile'],
      'skills': ['skills', 'technical_skills', 'core_competencies', 'tools'],
      'experience': ['experience', 'leadership_experience'],
      'education': ['education'],
      'certifications': ['certifications'],
      'projects': ['projects', 'open_source', 'internships'],
      'languages': ['languages'],
      'custom_sections': [
        'custom_sections',
        'achievements',
        'board_roles',
        'activities',
        'volunteering',
        'revenue_highlights',
      ],
    };

    return populated.where((section) {
      final names = aliases[section] ?? [section];
      return names.every((name) => !supportedSections.contains(name));
    }).toList();
  }
}

int _asInt(dynamic value, {int fallback = 0}) {
  if (value is int) return value;
  if (value is num) return value.round();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

String? _nullable(dynamic value) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? null : text;
}

List<String> _stringList(dynamic value) {
  if (value is! List) return const [];
  return value
      .map((item) => item.toString())
      .where((item) => item.isNotEmpty)
      .toList();
}
