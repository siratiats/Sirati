class JobNews {
  final int id;
  final String language;
  final String title;
  final String? company;
  final String? location;
  final String? city;
  final bool isRemote;
  final String body;
  final String category;
  final int? jobTitleId;
  final String? url;
  final String? applyUrl;
  final DateTime? validFrom;
  final DateTime? validUntil;
  final String? validUntilLabel;
  final String? publishedLabel;
  final DateTime? publishedAt;
  final bool isVerified;
  final String sourceType;
  final String? sourceTypeLabel;
  final String? sourceBadge;
  final String employmentType;
  final String? experienceLevel;
  final int? salaryMin;
  final int? salaryMax;
  final String? salaryRange;
  final List<String> skillsRequired;
  final int? matchScore;
  final List<String> matchReasons;
  final List<String> matchedSkills;
  final List<String> missingSkills;

  const JobNews({
    required this.id,
    required this.language,
    required this.title,
    required this.company,
    required this.location,
    this.city,
    this.isRemote = false,
    required this.body,
    required this.category,
    this.jobTitleId,
    required this.url,
    required this.applyUrl,
    required this.validFrom,
    required this.validUntil,
    required this.validUntilLabel,
    required this.publishedLabel,
    required this.publishedAt,
    this.isVerified = false,
    this.sourceType = 'general',
    this.sourceTypeLabel,
    this.sourceBadge,
    this.employmentType = 'full_time',
    this.experienceLevel,
    this.salaryMin,
    this.salaryMax,
    this.salaryRange,
    this.skillsRequired = const [],
    this.matchScore,
    this.matchReasons = const [],
    this.matchedSkills = const [],
    this.missingSkills = const [],
  });

  factory JobNews.fromJson(Map<String, dynamic> json) {
    final rawSkills = json['skills_required'];
    final rawReasons = json['match_reasons'];
    final rawMatchedSkills = json['matched_skills'];
    final rawMissingSkills = json['missing_skills'];

    return JobNews(
      id: _asInt(json['id']),
      language: json['language']?.toString() ?? 'ar',
      title: json['title']?.toString() ?? '',
      company: _nullable(json['company']),
      location: _nullable(json['location']),
      city: _nullable(json['city']),
      isRemote: _asBool(json['is_remote']),
      body: json['body']?.toString() ?? '',
      category: json['category']?.toString() ?? 'general',
      jobTitleId:
          json['job_title_id'] != null ? _asInt(json['job_title_id']) : null,
      url: _nullable(json['url']),
      applyUrl: _nullable(json['apply_url']),
      validFrom: _date(json['valid_from']),
      validUntil: _date(json['valid_until']),
      validUntilLabel: _nullable(json['valid_until_label']),
      publishedLabel: _nullable(json['published_label']),
      publishedAt: _date(json['published_at']),
      isVerified: _asBool(json['is_verified']),
      sourceType: json['source_type']?.toString() ?? 'general',
      sourceTypeLabel: _nullable(json['source_type_label']),
      sourceBadge: _nullable(json['source_badge']),
      employmentType: json['employment_type']?.toString() ?? 'full_time',
      experienceLevel: _nullable(json['experience_level']),
      salaryMin: json['salary_min'] != null ? _asInt(json['salary_min']) : null,
      salaryMax: json['salary_max'] != null ? _asInt(json['salary_max']) : null,
      salaryRange: _nullable(json['salary_range']),
      skillsRequired: rawSkills is List
          ? rawSkills.map((e) => e.toString()).toList(growable: false)
          : const [],
      matchScore:
          json['match_score'] != null ? _asInt(json['match_score']) : null,
      matchReasons: rawReasons is List
          ? rawReasons.map((e) => e.toString()).toList(growable: false)
          : const [],
      matchedSkills: rawMatchedSkills is List
          ? rawMatchedSkills.map((e) => e.toString()).toList(growable: false)
          : const [],
      missingSkills: rawMissingSkills is List
          ? rawMissingSkills.map((e) => e.toString()).toList(growable: false)
          : const [],
    );
  }

  String? get actionUrl {
    if (applyUrl != null && applyUrl!.isNotEmpty) return applyUrl;
    if (url != null && url!.isNotEmpty) return url;
    return null;
  }

  bool get isGov => sourceType == 'gov';
  bool get isTamheer => sourceType == 'tamheer' || employmentType == 'tamheer';
  bool get isEnterprise => sourceType == 'enterprise';
  bool get hasHighMatch => (matchScore ?? 0) >= 80;
}

int _asInt(dynamic value, {int fallback = 0}) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

String? _nullable(dynamic value) {
  final str = value?.toString();
  if (str == null || str.isEmpty) return null;
  return str;
}

DateTime? _date(dynamic value) {
  if (value == null) return null;
  return DateTime.tryParse(value.toString());
}

bool _asBool(dynamic value, {bool fallback = false}) {
  if (value is bool) return value;
  if (value is num) return value != 0;
  if (value is String) {
    final lower = value.trim().toLowerCase();
    if (lower == '1' || lower == 'true') return true;
    if (lower == '0' || lower == 'false') return false;
  }
  return fallback;
}
