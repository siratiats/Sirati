class JobTitle {
  final int id;
  final String slug;
  final String nameAr;
  final String nameEn;
  final String category;
  final List<String> keywords;
  final int sortOrder;

  const JobTitle({
    required this.id,
    required this.slug,
    required this.nameAr,
    required this.nameEn,
    required this.category,
    required this.keywords,
    required this.sortOrder,
  });

  bool get isOther => slug == 'other';

  /// Primary label is always Arabic; English builds show [nameEn] as subtitle.
  String label({required bool english}) => nameAr;

  String? subtitle({required bool english}) {
    if (!english) return null;
    final en = nameEn.trim();
    if (en.isEmpty || en == nameAr) return null;
    return en;
  }

  String searchBlob() =>
      '$nameAr $nameEn $slug $category ${keywords.join(' ')}'.toLowerCase();

  factory JobTitle.fromJson(Map<String, dynamic> json) {
    final rawKeywords = json['keywords'];
    final keywords = <String>[];
    if (rawKeywords is List) {
      for (final item in rawKeywords) {
        final s = item?.toString().trim() ?? '';
        if (s.isNotEmpty) keywords.add(s);
      }
    }

    return JobTitle(
      id: _asInt(json['id']),
      slug: json['slug']?.toString() ?? '',
      nameAr: json['name_ar']?.toString() ?? '',
      nameEn: json['name_en']?.toString() ?? '',
      category: json['category']?.toString() ?? '',
      keywords: keywords,
      sortOrder: _asInt(json['sort_order']),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'slug': slug,
        'name_ar': nameAr,
        'name_en': nameEn,
        'category': category,
        'keywords': keywords,
        'sort_order': sortOrder,
      };
}

int _asInt(dynamic value, {int fallback = 0}) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}
