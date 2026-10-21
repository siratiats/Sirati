import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/shared/models/cv_document.dart';
import 'package:sirati/shared/models/cv_template.dart';

void main() {
  test('round-trips bilingual fields and reports missing translations', () {
    const original = CvDocument(
      exportLanguage: 'en',
      personal: PersonalDetails(
        fullName: LocalizedText(ar: 'سارة أحمد', en: 'Sara Ahmed'),
        headline: LocalizedText(en: 'Backend Developer'),
        email: 'sara@example.com',
        phone: '+966500000000',
        linkedin: null,
        location: LocalizedText(ar: 'الرياض', en: 'Riyadh'),
      ),
      summary: LocalizedText(en: 'Backend developer'),
      missingTranslations: ['headline.ar', 'summary.ar'],
    );

    final decoded = CvDocument.fromJson(original.toJson());

    expect(decoded.fullName.ar, 'سارة أحمد');
    expect(decoded.fullName.en, 'Sara Ahmed');
    expect(decoded.fullName.resolve('en'), 'Sara Ahmed');
    expect(decoded.fullName.resolve('ar'), 'سارة أحمد');
    expect(decoded.headline.resolve('ar'), 'Backend Developer');
    expect(decoded.headline.missingCounterpart, 'ar');
    expect(decoded.duplicate().fullName.en, 'Sara Ahmed');
    expect(decoded.hasTranslationGaps, isFalse);
  });

  test('fromJson reads missing_translations for UI badges', () {
    final document = CvDocument.fromJson({
      'export_language': 'ar',
      'personal': {
        'full_name': {'ar': 'سارة', 'en': ''},
      },
      'summary': {'ar': 'ملخص', 'en': ''},
      'missing_translations': ['personal.full_name.en', 'summary.en'],
    });

    expect(document.hasTranslationGaps, isTrue);
    expect(
        document.missingTranslations, ['personal.full_name.en', 'summary.en']);
  });

  test('template switch keeps content and warns about hidden sections', () {
    const template = CvTemplate(
      id: 1,
      slug: 'classic',
      name: 'Classic',
      nameAr: 'كلاسيكي',
      nameEn: 'Classic',
      previewImageUrl: null,
      languageDirection: 'rtl',
      supportedLanguages: ['ar', 'en'],
      supportedSections: ['summary', 'skills', 'experience', 'education'],
      isDefault: true,
    );

    expect(
      template.omittedCanonicalSections(['summary', 'projects']),
      ['projects'],
    );
    expect(template.omittedCanonicalSections(['summary', 'skills']), isEmpty);
  });

  test(
      'lossless JSON round-trip across all sections (experience, education, skills, etc.)',
      () {
    final serverJson = {
      'schema_version': 1,
      'export_language': 'ar',
      'personal': {
        'full_name': {'ar': 'أحمد علي', 'en': 'Ahmed Ali'},
        'headline': {'ar': 'مطور برمجيات', 'en': 'Software Developer'},
        'email': 'ahmed@example.com',
        'phone': '+966500000000',
        'linkedin': 'linkedin.com/in/ahmed',
        'location': {'ar': 'الرياض', 'en': 'Riyadh'},
      },
      'summary': {'ar': 'ملخص تنفيذي', 'en': 'Executive summary'},
      'experience': [
        {
          'company': {'ar': 'شركة التقنية', 'en': 'Tech Co'},
          'title': {'ar': 'مهندس أول', 'en': 'Senior Engineer'},
          'location': {'ar': 'الرياض', 'en': 'Riyadh'},
          'start_date': '2020-01-01',
          'end_date': '2023-12-31',
          'is_current': false,
          'bullets': [
            {'ar': 'بناء واجهات برمجية', 'en': 'Built APIs'},
          ],
          'narrative': {'ar': 'سرد الخبرة', 'en': 'Experience narrative'},
        },
      ],
      'education': [
        {
          'institution': {
            'ar': 'جامعة الملك سعود',
            'en': 'King Saud University'
          },
          'degree': {'ar': 'بكالوريوس', 'en': 'BSc'},
          'field_of_study': {'ar': 'علوم حاسب', 'en': 'Computer Science'},
          'start_date': '2015-09-01',
          'graduation_date': '2019-06-01',
          'narrative': {'ar': 'سرد التعليم', 'en': 'Education narrative'},
        },
      ],
      'skills': [
        {
          'name': {'ar': 'دارت', 'en': 'Dart'},
          'level': {'ar': 'متقدم', 'en': 'Advanced'},
        },
      ],
      'languages': [
        {
          'name': {'ar': 'العربية', 'en': 'Arabic'},
          'level': {'ar': 'اللغة الأم', 'en': 'Native'},
        },
      ],
      'certifications': [
        {
          'name': {'ar': 'شهادة سحابية', 'en': 'Cloud Certificate'},
          'authority': {'ar': 'أمازون', 'en': 'AWS'},
          'issue_date': '2022-05-01',
          'narrative': {'ar': 'سرد الشهادة', 'en': 'Cert narrative'},
        },
      ],
      'projects': [
        {
          'title': {'ar': 'مشروع سيرتي', 'en': 'Sirati Project'},
          'role': {'ar': 'مؤسس تقني', 'en': 'Tech Lead'},
          'url': 'https://siratie.com',
          'narrative': {'ar': 'سرد المشروع', 'en': 'Project narrative'},
        },
      ],
      'custom_sections': [
        {
          'title': {'ar': 'الجوائز', 'en': 'Awards'},
          'narrative': {'ar': 'جائزة الابتكار', 'en': 'Innovation Award'},
        },
      ],
    };

    final doc = CvDocument.fromJson(serverJson);
    final reEncoded = doc.toJson();

    expect(reEncoded['schema_version'], 1);
    expect(reEncoded['export_language'], 'ar');
    expect(reEncoded['experience'], hasLength(1));
    expect(reEncoded['education'], hasLength(1));
    expect(reEncoded['skills'], hasLength(1));
    expect(reEncoded['languages'], hasLength(1));
    expect(reEncoded['certifications'], hasLength(1));
    expect(reEncoded['projects'], hasLength(1));
    expect(reEncoded['custom_sections'], hasLength(1));

    // Full round trip equality
    expect(reEncoded, equals(serverJson));

    // Duplicate preserves all sections
    final dup = doc.duplicate();
    expect(dup.toJson(), equals(serverJson));
  });

  test('fromResource parses document and missing_translations when siblings',
      () {
    final resourcePayload = {
      'document': {
        'schema_version': 1,
        'export_language': 'en',
        'personal': {
          'full_name': {'ar': 'سارة', 'en': 'Sara'},
        },
        'summary': {'ar': 'ملخص', 'en': ''},
      },
      'missing_translations': ['summary.en'],
    };

    final doc = CvDocument.fromResource(resourcePayload);
    expect(doc.fullName.en, 'Sara');
    expect(doc.missingTranslations, ['summary.en']);
    expect(doc.hasTranslationGaps, isTrue);
  });

  group('SIRATI-31 Section Model Invariants', () {
    test('sections support being omitted entirely vs present but empty', () {
      // 1. Omitted sections (null)
      const docWithOmitted = CvDocument(
        exportLanguage: 'ar',
        summary: LocalizedText(ar: 'ملخص'),
        experience: null,
        education: null,
      );
      final jsonOmitted = docWithOmitted.toJson();
      expect(jsonOmitted.containsKey('experience'), isFalse);
      expect(jsonOmitted.containsKey('education'), isFalse);

      final parsedOmitted = CvDocument.fromJson(jsonOmitted);
      expect(parsedOmitted.experience, isNull);
      expect(parsedOmitted.education, isNull);

      // 2. Present but empty sections ([])
      const docWithEmpty = CvDocument(
        exportLanguage: 'ar',
        summary: LocalizedText(ar: 'ملخص'),
        experience: [],
        education: [],
        skills: [],
      );
      final jsonEmpty = docWithEmpty.toJson();
      expect(jsonEmpty.containsKey('experience'), isTrue);
      expect(jsonEmpty['experience'], isEmpty);
      expect(jsonEmpty.containsKey('education'), isTrue);
      expect(jsonEmpty['education'], isEmpty);
      expect(jsonEmpty.containsKey('skills'), isTrue);
      expect(jsonEmpty['skills'], isEmpty);

      final parsedEmpty = CvDocument.fromJson(jsonEmpty);
      expect(parsedEmpty.experience, isNotNull);
      expect(parsedEmpty.experience, isEmpty);
      expect(parsedEmpty.education, isNotNull);
      expect(parsedEmpty.education, isEmpty);
      expect(parsedEmpty.skills, isNotNull);
      expect(parsedEmpty.skills, isEmpty);
    });

    test('each section model is independently serializable to and from JSON',
        () {
      // ExperienceEntry independent serialization
      const exp = ExperienceEntry(
        id: 'exp_1',
        company: LocalizedText(ar: 'أرامكو', en: 'Aramco'),
        title: LocalizedText(ar: 'مهندس', en: 'Engineer'),
        bullets: [LocalizedText(ar: 'إنجاز 1', en: 'Achievement 1')],
        isCurrent: true,
      );
      final expJson = exp.toJson();
      expect(expJson['id'], 'exp_1');
      expect(expJson['is_current'], isTrue);
      final restoredExp = ExperienceEntry.fromJson(expJson);
      expect(restoredExp.id, 'exp_1');
      expect(restoredExp.company.en, 'Aramco');
      expect(restoredExp.bullets, hasLength(1));

      // Skill independent serialization with category
      const skill = Skill(
        id: 'sk_1',
        name: LocalizedText(ar: 'فلاتر', en: 'Flutter'),
        level: LocalizedText(ar: 'خبير', en: 'Expert'),
        category: LocalizedText(ar: 'تطوير الجوال', en: 'Mobile Development'),
      );
      final skillJson = skill.toJson();
      expect(skillJson['id'], 'sk_1');
      expect(skillJson['category']['en'], 'Mobile Development');
      final restoredSkill = Skill.fromJson(skillJson);
      expect(restoredSkill.category.ar, 'تطوير الجوال');

      // Certification with optional expiry
      const cert = Certification(
        id: 'cert_1',
        name: LocalizedText(ar: 'PMP'),
        authority: LocalizedText(en: 'PMI'),
        issueDate: '2024-01-01',
        expiryDate: '2027-01-01',
      );
      final certJson = cert.toJson();
      expect(certJson['expiry_date'], '2027-01-01');
      final restoredCert = Certification.fromJson(certJson);
      expect(restoredCert.expiryDate, '2027-01-01');

      // CustomSection with items list
      const custom = CustomSection(
        id: 'custom_1',
        title: LocalizedText(ar: 'التطوع', en: 'Volunteering'),
        items: [
          LocalizedText(ar: 'متطوع تقني', en: 'Tech Volunteer'),
        ],
      );
      final customJson = custom.toJson();
      expect(customJson['items'], hasLength(1));
      final restoredCustom = CustomSection.fromJson(customJson);
      expect(restoredCustom.items.first.en, 'Tech Volunteer');

      // Static list helpers
      final skillList = Skill.listFromJson([skillJson]);
      expect(skillList, hasLength(1));
      expect(Skill.listToJson(skillList), equals([skillJson]));
    });

    test('copyWith allows granular updates and explicit clearing of sections',
        () {
      final initial = CvDocument.createEmpty(id: 'doc_1', title: 'Original');
      expect(initial.experience, isEmpty);

      // Add experience
      final withExp = initial.copyWith(
        experience: [
          const ExperienceEntry(company: LocalizedText(en: 'Google')),
        ],
      );
      expect(withExp.experience, hasLength(1));
      expect(withExp.title, 'Original');

      // Clear experience back to omitted (null)
      final clearedExp = withExp.copyWith(clearExperience: true);
      expect(clearedExp.experience, isNull);

      // Updating personal details
      final withPersonal = initial.copyWith(
        personal: initial.personal.copyWith(email: 'dev@sirati.com'),
      );
      expect(withPersonal.email, 'dev@sirati.com');
    });
  });
}
