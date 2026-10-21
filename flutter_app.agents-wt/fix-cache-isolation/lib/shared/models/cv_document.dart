/// Canonical bilingual CV document (schema_version 1).
///
/// Mirrors the Laravel `App\Cv\CvDocument` contract so the ATS engine,
/// PDF templates, and Flutter editors share one typed shape.
library cv_document;

class LocalizedText {
  final String ar;
  final String en;

  const LocalizedText({this.ar = '', this.en = ''});

  factory LocalizedText.fromJson(dynamic json, {String? defaultLanguage}) {
    if (json is String) {
      return defaultLanguage == 'en'
          ? LocalizedText(en: json)
          : LocalizedText(ar: json);
    }
    if (json is Map<String, dynamic>) {
      return LocalizedText(
        ar: json['ar']?.toString() ?? '',
        en: json['en']?.toString() ?? '',
      );
    }
    return const LocalizedText();
  }

  String resolve(String language, {bool fallback = true}) {
    final primary = language == 'en' ? en : ar;
    if (primary.isNotEmpty || !fallback) return primary;
    return language == 'en' ? ar : en;
  }

  bool get isEmpty => ar.isEmpty && en.isEmpty;
  bool get isNotEmpty => !isEmpty;

  /// `ar` or `en` when only one variant is filled.
  String? get missingCounterpart {
    if (ar.isNotEmpty && en.isEmpty) return 'en';
    if (en.isNotEmpty && ar.isEmpty) return 'ar';
    return null;
  }

  Map<String, String> toJson() => {'ar': ar, 'en': en};

  LocalizedText copyWith({String? ar, String? en}) {
    return LocalizedText(ar: ar ?? this.ar, en: en ?? this.en);
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is LocalizedText &&
          runtimeType == other.runtimeType &&
          ar == other.ar &&
          en == other.en;

  @override
  int get hashCode => ar.hashCode ^ en.hashCode;

  @override
  String toString() => 'LocalizedText(ar: "$ar", en: "$en")';
}

class PersonalDetails {
  final LocalizedText fullName;
  final LocalizedText headline;
  final String? email;
  final String? phone;
  final String? linkedin;
  final LocalizedText location;

  const PersonalDetails({
    this.fullName = const LocalizedText(),
    this.headline = const LocalizedText(),
    this.email,
    this.phone,
    this.linkedin,
    this.location = const LocalizedText(),
  });

  factory PersonalDetails.fromJson(
    Map<String, dynamic> json, {
    String? defaultLanguage,
  }) {
    return PersonalDetails(
      fullName: LocalizedText.fromJson(
        json['full_name'],
        defaultLanguage: defaultLanguage,
      ),
      headline: LocalizedText.fromJson(
        json['headline'],
        defaultLanguage: defaultLanguage,
      ),
      email: json['email']?.toString(),
      phone: json['phone']?.toString(),
      linkedin: json['linkedin']?.toString(),
      location: LocalizedText.fromJson(
        json['location'],
        defaultLanguage: defaultLanguage,
      ),
    );
  }

  PersonalDetails copyWith({
    LocalizedText? fullName,
    LocalizedText? headline,
    String? email,
    String? phone,
    String? linkedin,
    LocalizedText? location,
  }) {
    return PersonalDetails(
      fullName: fullName ?? this.fullName,
      headline: headline ?? this.headline,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      linkedin: linkedin ?? this.linkedin,
      location: location ?? this.location,
    );
  }

  PersonalDetails duplicate() => PersonalDetails(
        fullName: fullName.copyWith(),
        headline: headline.copyWith(),
        email: email,
        phone: phone,
        linkedin: linkedin,
        location: location.copyWith(),
      );

  Map<String, dynamic> toJson() => {
        'full_name': fullName.toJson(),
        'headline': headline.toJson(),
        'email': email,
        'phone': phone,
        'linkedin': linkedin,
        'location': location.toJson(),
      };
}

class ExperienceEntry {
  final String? id;
  final LocalizedText company;
  final LocalizedText title;
  final LocalizedText location;
  final String? startDate;
  final String? endDate;
  final bool isCurrent;
  final List<LocalizedText> bullets;
  final LocalizedText narrative;

  const ExperienceEntry({
    this.id,
    this.company = const LocalizedText(),
    this.title = const LocalizedText(),
    this.location = const LocalizedText(),
    this.startDate,
    this.endDate,
    this.isCurrent = false,
    this.bullets = const [],
    this.narrative = const LocalizedText(),
  });

  factory ExperienceEntry.fromJson(
    Map<String, dynamic> json, {
    String? defaultLanguage,
  }) {
    final rawBullets = json['bullets'];
    final bullets = <LocalizedText>[];
    if (rawBullets is List) {
      for (final item in rawBullets) {
        final text =
            LocalizedText.fromJson(item, defaultLanguage: defaultLanguage);
        if (text.isNotEmpty) bullets.add(text);
      }
    }

    return ExperienceEntry(
      id: json['id']?.toString(),
      company: LocalizedText.fromJson(json['company'],
          defaultLanguage: defaultLanguage),
      title: LocalizedText.fromJson(json['title'],
          defaultLanguage: defaultLanguage),
      location: LocalizedText.fromJson(json['location'],
          defaultLanguage: defaultLanguage),
      startDate: json['start_date']?.toString(),
      endDate: json['end_date']?.toString(),
      isCurrent: json['is_current'] == true,
      bullets: bullets,
      narrative: LocalizedText.fromJson(json['narrative'],
          defaultLanguage: defaultLanguage),
    );
  }

  ExperienceEntry copyWith({
    String? id,
    LocalizedText? company,
    LocalizedText? title,
    LocalizedText? location,
    String? startDate,
    String? endDate,
    bool? isCurrent,
    List<LocalizedText>? bullets,
    LocalizedText? narrative,
  }) {
    return ExperienceEntry(
      id: id ?? this.id,
      company: company ?? this.company,
      title: title ?? this.title,
      location: location ?? this.location,
      startDate: startDate ?? this.startDate,
      endDate: endDate ?? this.endDate,
      isCurrent: isCurrent ?? this.isCurrent,
      bullets: bullets ?? this.bullets,
      narrative: narrative ?? this.narrative,
    );
  }

  ExperienceEntry duplicate() => ExperienceEntry(
        id: id,
        company: company.copyWith(),
        title: title.copyWith(),
        location: location.copyWith(),
        startDate: startDate,
        endDate: endDate,
        isCurrent: isCurrent,
        bullets: bullets.map((b) => b.copyWith()).toList(),
        narrative: narrative.copyWith(),
      );

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{
      'company': company.toJson(),
      'title': title.toJson(),
      'location': location.toJson(),
      'start_date': startDate,
      'end_date': endDate,
      'is_current': isCurrent,
      'bullets': bullets.map((b) => b.toJson()).toList(),
      'narrative': narrative.toJson(),
    };
    if (id != null) map['id'] = id;
    return map;
  }

  static List<ExperienceEntry> listFromJson(dynamic json,
      {String? defaultLanguage}) {
    if (json is! List) return [];
    return json
        .whereType<Map<String, dynamic>>()
        .map((e) =>
            ExperienceEntry.fromJson(e, defaultLanguage: defaultLanguage))
        .toList();
  }

  static List<Map<String, dynamic>> listToJson(List<ExperienceEntry> entries) {
    return entries.map((e) => e.toJson()).toList();
  }
}

class EducationEntry {
  final String? id;
  final LocalizedText institution;
  final LocalizedText degree;
  final LocalizedText fieldOfStudy;
  final String? startDate;
  final String? graduationDate;
  final LocalizedText narrative;

  const EducationEntry({
    this.id,
    this.institution = const LocalizedText(),
    this.degree = const LocalizedText(),
    this.fieldOfStudy = const LocalizedText(),
    this.startDate,
    this.graduationDate,
    this.narrative = const LocalizedText(),
  });

  factory EducationEntry.fromJson(
    Map<String, dynamic> json, {
    String? defaultLanguage,
  }) {
    return EducationEntry(
      id: json['id']?.toString(),
      institution: LocalizedText.fromJson(json['institution'],
          defaultLanguage: defaultLanguage),
      degree: LocalizedText.fromJson(json['degree'],
          defaultLanguage: defaultLanguage),
      fieldOfStudy: LocalizedText.fromJson(json['field_of_study'],
          defaultLanguage: defaultLanguage),
      startDate: json['start_date']?.toString(),
      graduationDate: json['graduation_date']?.toString(),
      narrative: LocalizedText.fromJson(json['narrative'],
          defaultLanguage: defaultLanguage),
    );
  }

  EducationEntry copyWith({
    String? id,
    LocalizedText? institution,
    LocalizedText? degree,
    LocalizedText? fieldOfStudy,
    String? startDate,
    String? graduationDate,
    LocalizedText? narrative,
  }) {
    return EducationEntry(
      id: id ?? this.id,
      institution: institution ?? this.institution,
      degree: degree ?? this.degree,
      fieldOfStudy: fieldOfStudy ?? this.fieldOfStudy,
      startDate: startDate ?? this.startDate,
      graduationDate: graduationDate ?? this.graduationDate,
      narrative: narrative ?? this.narrative,
    );
  }

  EducationEntry duplicate() => EducationEntry(
        id: id,
        institution: institution.copyWith(),
        degree: degree.copyWith(),
        fieldOfStudy: fieldOfStudy.copyWith(),
        startDate: startDate,
        graduationDate: graduationDate,
        narrative: narrative.copyWith(),
      );

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{
      'institution': institution.toJson(),
      'degree': degree.toJson(),
      'field_of_study': fieldOfStudy.toJson(),
      'start_date': startDate,
      'graduation_date': graduationDate,
      'narrative': narrative.toJson(),
    };
    if (id != null) map['id'] = id;
    return map;
  }

  static List<EducationEntry> listFromJson(dynamic json,
      {String? defaultLanguage}) {
    if (json is! List) return [];
    return json
        .whereType<Map<String, dynamic>>()
        .map(
            (e) => EducationEntry.fromJson(e, defaultLanguage: defaultLanguage))
        .toList();
  }

  static List<Map<String, dynamic>> listToJson(List<EducationEntry> entries) {
    return entries.map((e) => e.toJson()).toList();
  }
}

class Skill {
  final String? id;
  final LocalizedText name;
  final LocalizedText level;
  final LocalizedText category;

  const Skill({
    this.id,
    this.name = const LocalizedText(),
    this.level = const LocalizedText(),
    this.category = const LocalizedText(),
  });

  factory Skill.fromJson(Map<String, dynamic> json, {String? defaultLanguage}) {
    return Skill(
      id: json['id']?.toString(),
      name: LocalizedText.fromJson(json['name'],
          defaultLanguage: defaultLanguage),
      level: LocalizedText.fromJson(json['level'],
          defaultLanguage: defaultLanguage),
      category: LocalizedText.fromJson(json['category'],
          defaultLanguage: defaultLanguage),
    );
  }

  Skill copyWith({
    String? id,
    LocalizedText? name,
    LocalizedText? level,
    LocalizedText? category,
  }) {
    return Skill(
      id: id ?? this.id,
      name: name ?? this.name,
      level: level ?? this.level,
      category: category ?? this.category,
    );
  }

  Skill duplicate() => Skill(
        id: id,
        name: name.copyWith(),
        level: level.copyWith(),
        category: category.copyWith(),
      );

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{
      'name': name.toJson(),
      'level': level.toJson(),
    };
    if (category.isNotEmpty) {
      map['category'] = category.toJson();
    }
    if (id != null) map['id'] = id;
    return map;
  }

  static List<Skill> listFromJson(dynamic json, {String? defaultLanguage}) {
    if (json is! List) return [];
    return json
        .whereType<Map<String, dynamic>>()
        .map((e) => Skill.fromJson(e, defaultLanguage: defaultLanguage))
        .toList();
  }

  static List<Map<String, dynamic>> listToJson(List<Skill> entries) {
    return entries.map((e) => e.toJson()).toList();
  }
}

class LanguageSkill {
  final String? id;
  final LocalizedText name;
  final LocalizedText level;

  const LanguageSkill({
    this.id,
    this.name = const LocalizedText(),
    this.level = const LocalizedText(),
  });

  factory LanguageSkill.fromJson(Map<String, dynamic> json,
      {String? defaultLanguage}) {
    return LanguageSkill(
      id: json['id']?.toString(),
      name: LocalizedText.fromJson(json['name'],
          defaultLanguage: defaultLanguage),
      level: LocalizedText.fromJson(json['level'],
          defaultLanguage: defaultLanguage),
    );
  }

  LanguageSkill copyWith({
    String? id,
    LocalizedText? name,
    LocalizedText? level,
  }) {
    return LanguageSkill(
      id: id ?? this.id,
      name: name ?? this.name,
      level: level ?? this.level,
    );
  }

  LanguageSkill duplicate() => LanguageSkill(
        id: id,
        name: name.copyWith(),
        level: level.copyWith(),
      );

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{
      'name': name.toJson(),
      'level': level.toJson(),
    };
    if (id != null) map['id'] = id;
    return map;
  }

  static List<LanguageSkill> listFromJson(dynamic json,
      {String? defaultLanguage}) {
    if (json is! List) return [];
    return json
        .whereType<Map<String, dynamic>>()
        .map((e) => LanguageSkill.fromJson(e, defaultLanguage: defaultLanguage))
        .toList();
  }

  static List<Map<String, dynamic>> listToJson(List<LanguageSkill> entries) {
    return entries.map((e) => e.toJson()).toList();
  }
}

class Certification {
  final String? id;
  final LocalizedText name;
  final LocalizedText authority;
  final String? issueDate;
  final String? expiryDate;
  final LocalizedText narrative;

  const Certification({
    this.id,
    this.name = const LocalizedText(),
    this.authority = const LocalizedText(),
    this.issueDate,
    this.expiryDate,
    this.narrative = const LocalizedText(),
  });

  factory Certification.fromJson(Map<String, dynamic> json,
      {String? defaultLanguage}) {
    return Certification(
      id: json['id']?.toString(),
      name: LocalizedText.fromJson(json['name'],
          defaultLanguage: defaultLanguage),
      authority: LocalizedText.fromJson(json['authority'],
          defaultLanguage: defaultLanguage),
      issueDate: json['issue_date']?.toString(),
      expiryDate: json['expiry_date']?.toString(),
      narrative: LocalizedText.fromJson(json['narrative'],
          defaultLanguage: defaultLanguage),
    );
  }

  Certification copyWith({
    String? id,
    LocalizedText? name,
    LocalizedText? authority,
    String? issueDate,
    String? expiryDate,
    LocalizedText? narrative,
  }) {
    return Certification(
      id: id ?? this.id,
      name: name ?? this.name,
      authority: authority ?? this.authority,
      issueDate: issueDate ?? this.issueDate,
      expiryDate: expiryDate ?? this.expiryDate,
      narrative: narrative ?? this.narrative,
    );
  }

  Certification duplicate() => Certification(
        id: id,
        name: name.copyWith(),
        authority: authority.copyWith(),
        issueDate: issueDate,
        expiryDate: expiryDate,
        narrative: narrative.copyWith(),
      );

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{
      'name': name.toJson(),
      'authority': authority.toJson(),
      'issue_date': issueDate,
      'narrative': narrative.toJson(),
    };
    if (expiryDate != null) map['expiry_date'] = expiryDate;
    if (id != null) map['id'] = id;
    return map;
  }

  static List<Certification> listFromJson(dynamic json,
      {String? defaultLanguage}) {
    if (json is! List) return [];
    return json
        .whereType<Map<String, dynamic>>()
        .map((e) => Certification.fromJson(e, defaultLanguage: defaultLanguage))
        .toList();
  }

  static List<Map<String, dynamic>> listToJson(List<Certification> entries) {
    return entries.map((e) => e.toJson()).toList();
  }
}

class Project {
  final String? id;
  final LocalizedText title;
  final LocalizedText role;
  final String? url;
  final LocalizedText narrative;

  const Project({
    this.id,
    this.title = const LocalizedText(),
    this.role = const LocalizedText(),
    this.url,
    this.narrative = const LocalizedText(),
  });

  factory Project.fromJson(Map<String, dynamic> json,
      {String? defaultLanguage}) {
    return Project(
      id: json['id']?.toString(),
      title: LocalizedText.fromJson(json['title'],
          defaultLanguage: defaultLanguage),
      role: LocalizedText.fromJson(json['role'],
          defaultLanguage: defaultLanguage),
      url: json['url']?.toString(),
      narrative: LocalizedText.fromJson(json['narrative'],
          defaultLanguage: defaultLanguage),
    );
  }

  Project copyWith({
    String? id,
    LocalizedText? title,
    LocalizedText? role,
    String? url,
    LocalizedText? narrative,
  }) {
    return Project(
      id: id ?? this.id,
      title: title ?? this.title,
      role: role ?? this.role,
      url: url ?? this.url,
      narrative: narrative ?? this.narrative,
    );
  }

  Project duplicate() => Project(
        id: id,
        title: title.copyWith(),
        role: role.copyWith(),
        url: url,
        narrative: narrative.copyWith(),
      );

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{
      'title': title.toJson(),
      'role': role.toJson(),
      'url': url,
      'narrative': narrative.toJson(),
    };
    if (id != null) map['id'] = id;
    return map;
  }

  static List<Project> listFromJson(dynamic json, {String? defaultLanguage}) {
    if (json is! List) return [];
    return json
        .whereType<Map<String, dynamic>>()
        .map((e) => Project.fromJson(e, defaultLanguage: defaultLanguage))
        .toList();
  }

  static List<Map<String, dynamic>> listToJson(List<Project> entries) {
    return entries.map((e) => e.toJson()).toList();
  }
}

class CustomSection {
  final String? id;
  final LocalizedText title;
  final LocalizedText narrative;
  final List<LocalizedText> items;

  const CustomSection({
    this.id,
    this.title = const LocalizedText(),
    this.narrative = const LocalizedText(),
    this.items = const [],
  });

  factory CustomSection.fromJson(Map<String, dynamic> json,
      {String? defaultLanguage}) {
    final rawItems = json['items'];
    final items = <LocalizedText>[];
    if (rawItems is List) {
      for (final item in rawItems) {
        final text =
            LocalizedText.fromJson(item, defaultLanguage: defaultLanguage);
        if (text.isNotEmpty) items.add(text);
      }
    }

    return CustomSection(
      id: json['id']?.toString(),
      title: LocalizedText.fromJson(json['title'],
          defaultLanguage: defaultLanguage),
      narrative: LocalizedText.fromJson(json['narrative'],
          defaultLanguage: defaultLanguage),
      items: items,
    );
  }

  CustomSection copyWith({
    String? id,
    LocalizedText? title,
    LocalizedText? narrative,
    List<LocalizedText>? items,
  }) {
    return CustomSection(
      id: id ?? this.id,
      title: title ?? this.title,
      narrative: narrative ?? this.narrative,
      items: items ?? this.items,
    );
  }

  CustomSection duplicate() => CustomSection(
        id: id,
        title: title.copyWith(),
        narrative: narrative.copyWith(),
        items: items.map((i) => i.copyWith()).toList(),
      );

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{
      'title': title.toJson(),
      'narrative': narrative.toJson(),
    };
    if (items.isNotEmpty) {
      map['items'] = items.map((i) => i.toJson()).toList();
    }
    if (id != null) map['id'] = id;
    return map;
  }

  static List<CustomSection> listFromJson(dynamic json,
      {String? defaultLanguage}) {
    if (json is! List) return [];
    return json
        .whereType<Map<String, dynamic>>()
        .map((e) => CustomSection.fromJson(e, defaultLanguage: defaultLanguage))
        .toList();
  }

  static List<Map<String, dynamic>> listToJson(List<CustomSection> entries) {
    return entries.map((e) => e.toJson()).toList();
  }
}

class CvDocument {
  static const schemaVersion = 1;

  final String? id;
  final String? title;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final int version;
  final String exportLanguage;
  final PersonalDetails personal;
  final LocalizedText summary;
  final List<ExperienceEntry>? experience;
  final List<EducationEntry>? education;
  final List<Skill>? skills;
  final List<LanguageSkill>? languages;
  final List<Certification>? certifications;
  final List<Project>? projects;
  final List<CustomSection>? customSections;
  final List<String>? sectionOrder;
  final List<String> missingTranslations;
  final Map<String, dynamic>? extraFields;

  static const List<String> defaultSectionOrder = [
    'personal',
    'summary',
    'experience',
    'education',
    'skills',
    'languages',
    'certifications',
    'projects',
    'custom_sections',
  ];

  const CvDocument({
    this.id,
    this.title,
    this.createdAt,
    this.updatedAt,
    this.version = schemaVersion,
    required this.exportLanguage,
    this.personal = const PersonalDetails(),
    required this.summary,
    this.experience,
    this.education,
    this.skills,
    this.languages,
    this.certifications,
    this.projects,
    this.customSections,
    this.sectionOrder,
    this.missingTranslations = const [],
    this.extraFields,
  });

  static int _idSeed = 0;

  /// Generates a collision-resistant unique CV document identifier.
  static String generateId() {
    final now = DateTime.now().microsecondsSinceEpoch;
    _idSeed = (_idSeed + 1) % 10000;
    return 'cv_${now}_$_idSeed';
  }

  /// Factory to initialize a clean new CV draft.
  factory CvDocument.createEmpty({
    String? id,
    String? title,
    String exportLanguage = 'ar',
  }) {
    final now = DateTime.now();
    return CvDocument(
      id: id ?? generateId(),
      title: title ?? (exportLanguage == 'ar' ? 'سيرة ذاتية جديدة' : 'New CV'),
      createdAt: now,
      updatedAt: now,
      exportLanguage: exportLanguage,
      summary: const LocalizedText(),
      experience: const [],
      education: const [],
      skills: const [],
      languages: const [],
      certifications: const [],
      projects: const [],
      customSections: const [],
      sectionOrder: defaultSectionOrder,
    );
  }

  /// Backward-compatibility convenience getters for personal details
  LocalizedText get fullName => personal.fullName;
  LocalizedText get headline => personal.headline;
  String? get email => personal.email;
  String? get phone => personal.phone;
  String? get linkedin => personal.linkedin;
  LocalizedText get location => personal.location;

  factory CvDocument.fromJson(
    Map<String, dynamic> json, {
    List<String>? missingTranslations,
    LocalizedText? fullName,
    LocalizedText? headline,
    String? email,
    String? phone,
    String? linkedin,
    LocalizedText? location,
  }) {
    final lang = json['export_language']?.toString() == 'en' ? 'en' : 'ar';
    final personalMap = json['personal'] is Map<String, dynamic>
        ? json['personal'] as Map<String, dynamic>
        : <String, dynamic>{};

    final personal = PersonalDetails(
      fullName: fullName ??
          LocalizedText.fromJson(personalMap['full_name'],
              defaultLanguage: lang),
      headline: headline ??
          LocalizedText.fromJson(personalMap['headline'],
              defaultLanguage: lang),
      email: email ?? personalMap['email']?.toString(),
      phone: phone ?? personalMap['phone']?.toString(),
      linkedin: linkedin ?? personalMap['linkedin']?.toString(),
      location: location ??
          LocalizedText.fromJson(personalMap['location'],
              defaultLanguage: lang),
    );

    final summary =
        LocalizedText.fromJson(json['summary'], defaultLanguage: lang);

    List<T>? parseList<T>(
        String key, T Function(Map<String, dynamic>) factory) {
      if (!json.containsKey(key) || json[key] == null) return null;
      if (json[key] is! List) return null;
      return (json[key] as List)
          .whereType<Map<String, dynamic>>()
          .map(factory)
          .toList();
    }

    final missing = missingTranslations ??
        ((json['missing_translations'] is List)
            ? (json['missing_translations'] as List)
                .map((e) => e.toString())
                .toList()
            : const <String>[]);

    final parsedVersion = json['schema_version'] is int
        ? json['schema_version'] as int
        : schemaVersion;

    final createdAt = json['created_at'] != null
        ? DateTime.tryParse(json['created_at'].toString())
        : null;
    final updatedAt = json['updated_at'] != null
        ? DateTime.tryParse(json['updated_at'].toString())
        : null;

    final sectionOrder = (json['section_order'] is List)
        ? (json['section_order'] as List).map((e) => e.toString()).toList()
        : null;

    const knownKeys = {
      'schema_version',
      'export_language',
      'id',
      'title',
      'created_at',
      'updated_at',
      'personal',
      'summary',
      'experience',
      'education',
      'skills',
      'languages',
      'certifications',
      'projects',
      'custom_sections',
      'section_order',
      'missing_translations',
    };
    final extra = <String, dynamic>{};
    for (final entry in json.entries) {
      if (!knownKeys.contains(entry.key)) {
        extra[entry.key] = entry.value;
      }
    }

    return CvDocument(
      id: json['id']?.toString(),
      title: json['title']?.toString(),
      createdAt: createdAt,
      updatedAt: updatedAt,
      version: parsedVersion,
      exportLanguage: lang,
      personal: personal,
      summary: summary,
      experience: parseList('experience',
          (m) => ExperienceEntry.fromJson(m, defaultLanguage: lang)),
      education: parseList('education',
          (m) => EducationEntry.fromJson(m, defaultLanguage: lang)),
      skills:
          parseList('skills', (m) => Skill.fromJson(m, defaultLanguage: lang)),
      languages: parseList(
          'languages', (m) => LanguageSkill.fromJson(m, defaultLanguage: lang)),
      certifications: parseList('certifications',
          (m) => Certification.fromJson(m, defaultLanguage: lang)),
      projects: parseList(
          'projects', (m) => Project.fromJson(m, defaultLanguage: lang)),
      customSections: parseList('custom_sections',
          (m) => CustomSection.fromJson(m, defaultLanguage: lang)),
      sectionOrder: sectionOrder,
      missingTranslations: missing,
      extraFields: extra.isNotEmpty ? extra : null,
    );
  }

  factory CvDocument.fromResource(Map<String, dynamic> resourceJson) {
    final docJson = resourceJson['document'] is Map<String, dynamic>
        ? resourceJson['document'] as Map<String, dynamic>
        : resourceJson;
    final missing = (resourceJson['missing_translations'] is List)
        ? (resourceJson['missing_translations'] as List)
            .map((e) => e.toString())
            .toList()
        : null;
    return CvDocument.fromJson(docJson, missingTranslations: missing);
  }

  bool get hasTranslationGaps => missingTranslations.isNotEmpty;

  CvDocument copyWith({
    String? id,
    String? title,
    DateTime? createdAt,
    DateTime? updatedAt,
    int? version,
    String? exportLanguage,
    PersonalDetails? personal,
    LocalizedText? summary,
    List<ExperienceEntry>? experience,
    List<EducationEntry>? education,
    List<Skill>? skills,
    List<LanguageSkill>? languages,
    List<Certification>? certifications,
    List<Project>? projects,
    List<CustomSection>? customSections,
    List<String>? sectionOrder,
    List<String>? missingTranslations,
    Map<String, dynamic>? extraFields,
    bool clearExperience = false,
    bool clearEducation = false,
    bool clearSkills = false,
    bool clearLanguages = false,
    bool clearCertifications = false,
    bool clearProjects = false,
    bool clearCustomSections = false,
    bool clearSectionOrder = false,
    bool clearExtraFields = false,
  }) {
    return CvDocument(
      id: id ?? this.id,
      title: title ?? this.title,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      version: version ?? this.version,
      exportLanguage: exportLanguage ?? this.exportLanguage,
      personal: personal ?? this.personal,
      summary: summary ?? this.summary,
      experience: clearExperience ? null : (experience ?? this.experience),
      education: clearEducation ? null : (education ?? this.education),
      skills: clearSkills ? null : (skills ?? this.skills),
      languages: clearLanguages ? null : (languages ?? this.languages),
      certifications:
          clearCertifications ? null : (certifications ?? this.certifications),
      projects: clearProjects ? null : (projects ?? this.projects),
      customSections:
          clearCustomSections ? null : (customSections ?? this.customSections),
      sectionOrder:
          clearSectionOrder ? null : (sectionOrder ?? this.sectionOrder),
      missingTranslations: missingTranslations ?? this.missingTranslations,
      extraFields: clearExtraFields ? null : (extraFields ?? this.extraFields),
    );
  }

  CvDocument duplicate(
          {String? newId, String? newTitle, DateTime? newUpdatedAt}) =>
      CvDocument(
        id: newId ?? id,
        title: newTitle ?? title,
        createdAt: createdAt,
        updatedAt: newUpdatedAt ?? updatedAt,
        version: version,
        exportLanguage: exportLanguage,
        personal: personal.duplicate(),
        summary: summary.copyWith(),
        experience: experience?.map((e) => e.duplicate()).toList(),
        education: education?.map((e) => e.duplicate()).toList(),
        skills: skills?.map((e) => e.duplicate()).toList(),
        languages: languages?.map((e) => e.duplicate()).toList(),
        certifications: certifications?.map((e) => e.duplicate()).toList(),
        projects: projects?.map((e) => e.duplicate()).toList(),
        customSections: customSections?.map((e) => e.duplicate()).toList(),
        sectionOrder: sectionOrder != null ? List.of(sectionOrder!) : null,
        missingTranslations: List.of(missingTranslations),
        extraFields: extraFields != null
            ? Map<String, dynamic>.from(extraFields!)
            : null,
      );

  Map<String, dynamic> toJson() {
    final data = <String, dynamic>{
      'schema_version': version,
      'export_language': exportLanguage,
      'personal': personal.toJson(),
      'summary': summary.toJson(),
    };

    if (id != null) data['id'] = id;
    if (title != null) data['title'] = title;
    if (createdAt != null) data['created_at'] = createdAt!.toIso8601String();
    if (updatedAt != null) data['updated_at'] = updatedAt!.toIso8601String();

    if (experience != null) {
      data['experience'] = experience!.map((e) => e.toJson()).toList();
    }
    if (education != null) {
      data['education'] = education!.map((e) => e.toJson()).toList();
    }
    if (skills != null) {
      data['skills'] = skills!.map((e) => e.toJson()).toList();
    }
    if (languages != null) {
      data['languages'] = languages!.map((e) => e.toJson()).toList();
    }
    if (certifications != null) {
      data['certifications'] = certifications!.map((e) => e.toJson()).toList();
    }
    if (projects != null) {
      data['projects'] = projects!.map((e) => e.toJson()).toList();
    }
    if (customSections != null) {
      data['custom_sections'] = customSections!.map((e) => e.toJson()).toList();
    }
    if (sectionOrder != null) {
      data['section_order'] = sectionOrder;
    }

    if (extraFields != null && extraFields!.isNotEmpty) {
      data.addAll(extraFields!);
    }

    return data;
  }
}
