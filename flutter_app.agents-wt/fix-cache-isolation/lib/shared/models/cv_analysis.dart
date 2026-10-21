import 'package:sirati/shared/models/ai_status.dart';

class CvAnalysis {
  final int id;
  final String targetJobTitle;
  final String? originalFilename;
  final String inputMethod;
  final int scoreTotal;
  final String grade;
  final int jobMatch;
  final List<ScoreCriterion> criteria;
  final List<String> strengths;
  final List<AnalysisWeakness> weaknesses;
  final List<String> keywordsFound;
  final List<String> keywordsMissing;
  final List<String> quickWins;
  final String aiStatus;
  final Map<String, dynamic>? aiFeedback;
  final String? aiError;
  final String? category;
  final String? categoryLabel;
  final String? categoryLabelEn;
  final bool isGeneralProfile;

  /// 'job_description' when the score was computed against the pasted JD,
  /// 'category' when it fell back to the hardcoded category keyword list.
  /// Drives the badge shown next to the category chip (SIRATI-90).
  final String keywordSource;
  final String? classificationWarning;
  final DateTime? createdAt;

  const CvAnalysis({
    required this.id,
    required this.targetJobTitle,
    required this.originalFilename,
    required this.inputMethod,
    required this.scoreTotal,
    required this.grade,
    required this.jobMatch,
    this.category = 'general',
    this.categoryLabel,
    this.categoryLabelEn,
    this.isGeneralProfile = false,
    this.keywordSource = 'category',
    this.classificationWarning,
    required this.criteria,
    required this.strengths,
    required this.weaknesses,
    required this.keywordsFound,
    required this.keywordsMissing,
    required this.quickWins,
    required this.aiStatus,
    required this.aiFeedback,
    required this.aiError,
    required this.createdAt,
  });

  factory CvAnalysis.fromJson(Map<String, dynamic> json) {
    return CvAnalysis(
      id: _asInt(json['id']),
      targetJobTitle: json['target_job_title']?.toString() ?? '',
      originalFilename: json['original_filename']?.toString(),
      inputMethod: json['input_method']?.toString() ?? 'paste',
      scoreTotal: _asInt(json['score_total']),
      grade: json['grade']?.toString() ?? '-',
      jobMatch: _asInt(json['job_match']),
      category: json['category']?.toString() ?? 'general',
      categoryLabel: json['category_label']?.toString(),
      categoryLabelEn: json['category_label_en']?.toString(),
      isGeneralProfile:
          json['is_general_profile'] == true || (json['category'] == 'general'),
      keywordSource: json['keyword_source']?.toString() == 'job_description'
          ? 'job_description'
          : 'category',
      classificationWarning: json['classification_warning']?.toString(),
      criteria: _asList(json['criteria']).map(ScoreCriterion.fromJson).toList(),
      strengths: _asStringList(json['strengths']),
      weaknesses:
          _asList(json['weaknesses']).map(AnalysisWeakness.fromJson).toList(),
      keywordsFound: _asStringList(json['keywords_found']),
      keywordsMissing: _asStringList(json['keywords_missing']),
      quickWins: _asStringList(json['quick_wins']),
      aiStatus: json['ai_status']?.toString() ?? AiStatus.notConfigured,
      aiFeedback: json['ai_feedback'] is Map<String, dynamic>
          ? json['ai_feedback'] as Map<String, dynamic>
          : null,
      aiError: json['ai_error']?.toString(),
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
    );
  }

  String displayCategoryLabel({required bool english}) {
    if (english) {
      return categoryLabelEn ??
          (category == 'general'
              ? 'General Profile (Transferable Skills)'
              : (category ?? 'General'));
    }
    return categoryLabel ??
        (category == 'general'
            ? 'تقييم عام (مهارات مهنية مشتركة)'
            : (category ?? 'عام'));
  }

  String get inputMethodLabel {
    return switch (inputMethod) {
      'upload' => 'ملف',
      'mixed' => 'ملف ونص',
      _ => 'لصق نص',
    };
  }
}

class ScoreCriterion {
  final String label;
  final int score;
  final int max;

  const ScoreCriterion(
      {required this.label, required this.score, required this.max});

  factory ScoreCriterion.fromJson(Map<String, dynamic> json) {
    return ScoreCriterion(
      label: json['label']?.toString() ?? '',
      score: _asInt(json['score']),
      max: _asInt(json['max'], fallback: 1),
    );
  }
}

class AnalysisWeakness {
  final String priority;
  final String issue;
  final String fix;

  const AnalysisWeakness(
      {required this.priority, required this.issue, required this.fix});

  factory AnalysisWeakness.fromJson(Map<String, dynamic> json) {
    return AnalysisWeakness(
      priority: json['priority']?.toString() ?? 'medium',
      issue: json['issue']?.toString() ?? '',
      fix: json['fix']?.toString() ?? '',
    );
  }
}

List<Map<String, dynamic>> _asList(dynamic value) {
  if (value is! List) return const [];
  return value.whereType<Map<String, dynamic>>().toList();
}

List<String> _asStringList(dynamic value) {
  if (value is! List) return const [];
  return value
      .map((item) => item.toString())
      .where((item) => item.isNotEmpty)
      .toList();
}

int _asInt(dynamic value, {int fallback = 0}) {
  if (value is int) return value;
  if (value is num) return value.round();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}
