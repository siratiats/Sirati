import 'package:sirati/shared/models/ai_status.dart';
import 'package:sirati/shared/models/cv_analysis.dart';

class GeneratedCv {
  final int id;
  final String fullName;
  final String? email;
  final String? phone;
  final String? linkedin;
  final String? location;
  final String targetJobTitle;
  final String? jobDescriptionInput;
  final String language;
  final String? summaryInput;
  final String skillsInput;
  final String experienceInput;
  final String educationInput;
  final String? certificationsInput;
  final String generatedMarkdown;
  final String aiStatus;
  final Map<String, dynamic>? aiOutput;
  final String? aiError;
  final int scoreTotal;
  final String grade;
  final List<ScoreCriterion> criteria;
  final String? pdfUrl;
  final String? templatePdfUrl;
  final DateTime? createdAt;

  const GeneratedCv({
    required this.id,
    required this.fullName,
    required this.email,
    required this.phone,
    required this.linkedin,
    required this.location,
    required this.targetJobTitle,
    required this.jobDescriptionInput,
    required this.language,
    required this.summaryInput,
    required this.skillsInput,
    required this.experienceInput,
    required this.educationInput,
    required this.certificationsInput,
    required this.generatedMarkdown,
    required this.aiStatus,
    required this.aiOutput,
    required this.aiError,
    required this.scoreTotal,
    required this.grade,
    required this.criteria,
    required this.pdfUrl,
    required this.templatePdfUrl,
    required this.createdAt,
  });

  factory GeneratedCv.fromJson(Map<String, dynamic> json) {
    return GeneratedCv(
      id: _asInt(json['id']),
      fullName: json['full_name']?.toString() ?? '',
      email: json['email']?.toString(),
      phone: json['phone']?.toString(),
      linkedin: json['linkedin']?.toString(),
      location: json['location']?.toString(),
      targetJobTitle: json['target_job_title']?.toString() ?? '',
      jobDescriptionInput: json['job_description_input']?.toString(),
      language: json['language']?.toString() ?? 'ar',
      summaryInput: json['summary_input']?.toString(),
      skillsInput: json['skills_input']?.toString() ?? '',
      experienceInput: json['experience_input']?.toString() ?? '',
      educationInput: json['education_input']?.toString() ?? '',
      certificationsInput: json['certifications_input']?.toString(),
      generatedMarkdown: json['generated_markdown']?.toString() ?? '',
      aiStatus: json['ai_status']?.toString() ?? AiStatus.notConfigured,
      aiOutput: json['ai_output'] is Map<String, dynamic>
          ? json['ai_output'] as Map<String, dynamic>
          : null,
      aiError: json['ai_error']?.toString(),
      scoreTotal: _asInt(json['score_total']),
      grade: json['grade']?.toString() ?? '-',
      criteria: _asList(json['criteria']).map(ScoreCriterion.fromJson).toList(),
      pdfUrl: json['pdf_url']?.toString(),
      templatePdfUrl: json['template_pdf_url']?.toString(),
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
    );
  }
}

List<Map<String, dynamic>> _asList(dynamic value) {
  if (value is! List) return const [];
  return value.whereType<Map<String, dynamic>>().toList();
}

int _asInt(dynamic value, {int fallback = 0}) {
  if (value is int) return value;
  if (value is num) return value.round();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}
