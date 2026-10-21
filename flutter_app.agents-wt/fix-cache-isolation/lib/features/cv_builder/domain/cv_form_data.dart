import 'package:sirati/core/storage/preference_store.dart';
import 'package:sirati/shared/models/generated_cv.dart';

/// Immutable snapshot of everything the CV wizard collects.
///
/// Single source for the field list: seeding from an existing CV, the local
/// draft format, and the API payload all go through this type, so adding a
/// field is one change instead of four parallel lists.
class CvFormData {
  const CvFormData({
    this.fullName = '',
    this.email = '',
    this.phone = '',
    this.linkedin = '',
    this.location = '',
    this.targetJobTitle = '',
    this.jobDescription = '',
    this.summary = '',
    this.skills = '',
    this.experience = '',
    this.education = '',
    this.certifications = '',
    this.language = 'ar',
  });

  /// Minimum experience text accepted by the wizard and the AI enhancer.
  static const minExperienceChars = 80;

  final String fullName;
  final String email;
  final String phone;
  final String linkedin;
  final String location;
  final String targetJobTitle;
  final String jobDescription;
  final String summary;
  final String skills;
  final String experience;
  final String education;
  final String certifications;

  /// CV output language: 'ar' or 'en'.
  final String language;

  factory CvFormData.fromGeneratedCv(GeneratedCv cv) => CvFormData(
        fullName: cv.fullName,
        email: cv.email ?? '',
        phone: cv.phone ?? '',
        linkedin: cv.linkedin ?? '',
        location: cv.location ?? '',
        targetJobTitle: cv.targetJobTitle,
        jobDescription: cv.jobDescriptionInput ?? '',
        summary: cv.summaryInput ?? '',
        skills: cv.skillsInput,
        experience: cv.experienceInput,
        education: cv.educationInput,
        certifications: cv.certificationsInput ?? '',
        language: _normalizeLanguage(cv.language),
      );

  /// Reads a locally stored draft. Unknown or missing values fall back to
  /// empty strings so a corrupted draft degrades to an empty form.
  factory CvFormData.fromDraftJson(Map<String, dynamic> json) {
    String read(String key) => json[key]?.toString() ?? '';
    return CvFormData(
      fullName: read('full_name'),
      email: read('email'),
      phone: read('phone'),
      linkedin: read('linkedin'),
      location: read('location'),
      targetJobTitle: read('target_job_title'),
      jobDescription: read('job_description_input'),
      summary: read('summary_input'),
      skills: read('skills_input'),
      experience: read('experience_input'),
      education: read('education_input'),
      certifications: read('certifications_input'),
      language: _normalizeLanguage(json['language']?.toString()),
    );
  }

  /// Wizard step stored with a draft, clamped to `0..stepCount-1`.
  static int stepFromDraftJson(
    Map<String, dynamic> json, {
    required int stepCount,
  }) {
    final raw = json['step'];
    final step = raw is int ? raw : int.tryParse(raw?.toString() ?? '') ?? 0;
    return step.clamp(0, stepCount - 1);
  }

  Map<String, dynamic> toDraftJson({
    required int step,
    required DateTime savedAt,
  }) =>
      {
        'schemaVersion': PreferenceStore.cvDraftSchemaVersion,
        'savedAt': savedAt.toUtc().toIso8601String(),
        'step': step,
        'language': language,
        'full_name': fullName,
        'email': email,
        'phone': phone,
        'linkedin': linkedin,
        'location': location,
        'target_job_title': targetJobTitle,
        'job_description_input': jobDescription,
        'summary_input': summary,
        'skills_input': skills,
        'experience_input': experience,
        'education_input': education,
        'certifications_input': certifications,
      };

  /// Request body for create/update. Required fields are trimmed; optional
  /// fields become null when blank so the API stores "not provided".
  Map<String, dynamic> toPayload() => {
        'full_name': fullName.trim(),
        'email': _nullable(email),
        'phone': _nullable(phone),
        'linkedin': _nullable(linkedin),
        'location': _nullable(location),
        'target_job_title': targetJobTitle.trim(),
        'job_description_input': _nullable(jobDescription),
        'language': language,
        'summary_input': _nullable(summary),
        'skills_input': skills.trim(),
        'experience_input': experience.trim(),
        'education_input': education.trim(),
        'certifications_input': _nullable(certifications),
      };

  /// True when any text field has non-whitespace content. Language alone
  /// does not make a draft worth keeping.
  bool get hasContent => [
        fullName,
        email,
        phone,
        linkedin,
        location,
        targetJobTitle,
        jobDescription,
        summary,
        skills,
        experience,
        education,
        certifications,
      ].any((v) => v.trim().isNotEmpty);

  static String? _nullable(String value) {
    final trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }

  static String _normalizeLanguage(String? value) =>
      value == 'en' ? 'en' : 'ar';
}
