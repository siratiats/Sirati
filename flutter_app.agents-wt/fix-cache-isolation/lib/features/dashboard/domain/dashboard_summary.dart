import 'package:sirati/core/utils/bidi_text.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';

/// Title / subtitle / button copy for one dashboard action card.
class DashboardAction {
  const DashboardAction({
    required this.title,
    required this.subtitle,
    required this.buttonLabel,
  });

  factory DashboardAction.fromJson(Object? raw) {
    final json = raw is Map<String, dynamic> ? raw : const <String, dynamic>{};
    return DashboardAction(
      title: _text(json['title']),
      subtitle: _text(json['subtitle']),
      buttonLabel: _text(json['button_label']),
    );
  }

  final String title;
  final String subtitle;
  final String buttonLabel;

  /// Static product copy used when the API omits the card. Never contains
  /// user-specific data (trust-critical: no invented stats or activity).
  static DashboardAction createCv({required bool english}) => DashboardAction(
        title: english
            ? 'Create ATS-Optimized CV'
            : 'أنشئ سيرة ذاتية وفق ${BidiText.isolateLtr('ATS')}',
        subtitle: english
            ? 'Build your CV step by step and get a professional design that passes screening systems.'
            : 'ابنِ سيرتك خطوة بخطوة واحصل على تصميم احترافي يتجاوز أنظمة الفرز.',
        buttonLabel: english ? 'Start Now' : 'ابدأ الآن',
      );

  static DashboardAction analyzeCv({required bool english}) => DashboardAction(
        title: english
            ? 'Analyze Your CV with ATS'
            : 'حلّل سيرتك الذاتية بـ ${BidiText.isolateLtr('ATS')}',
        subtitle: english
            ? 'Upload your CV and discover its strengths and match with target jobs.'
            : 'ارفع سيرتك واعرف نقاط قوتها ومدى توافقها مع الوظائف المستهدفة.',
        buttonLabel: english ? 'Analyze Now' : 'تحليل الآن',
      );

  DashboardAction _orElse(DashboardAction fallback) =>
      title.isNotEmpty ? this : fallback;
}

/// Parsed `/mobile/dashboard` payload.
class DashboardSummary {
  const DashboardSummary({
    required this.name,
    required this.status,
    required this.cvCount,
    required this.analysisCount,
    required this.unreadNotifications,
    required this.primaryAction,
    required this.analysisAction,
    required this.latestNewsTitle,
    required this.latestNewsSubtitle,
    required this.offline,
  });

  /// [cachedName] fills the greeting when the payload has no name.
  factory DashboardSummary.fromPayload(
    Map<String, dynamic> data, {
    required bool english,
    String cachedName = '',
  }) {
    final profile = _map(data['profile']);
    final stats = _map(data['stats']);
    final news = _map(data['latest_news']);
    final name = _text(profile['name']);
    return DashboardSummary(
      name: name.isNotEmpty ? name : cachedName.trim(),
      status: _text(profile['status']),
      cvCount: _count(stats['generated_cvs']),
      analysisCount: _count(stats['analyses']),
      unreadNotifications: data.containsKey('unread_notifications')
          ? _count(data['unread_notifications']) ?? 0
          : null,
      primaryAction: DashboardAction.fromJson(data['primary_action'])
          ._orElse(DashboardAction.createCv(english: english)),
      analysisAction: DashboardAction.fromJson(data['analysis_action'])
          ._orElse(DashboardAction.analyzeCv(english: english)),
      latestNewsTitle: _text(news['title']),
      latestNewsSubtitle: _text(news['subtitle']),
      offline: MobileContentService.isOfflinePayload(data),
    );
  }

  final String name;
  final String status;

  /// Real counts only; null when absent or invalid (the card shows nothing).
  final int? cvCount;
  final int? analysisCount;

  /// Null when the payload omits it, so the cached badge count is kept.
  final int? unreadNotifications;

  final DashboardAction primaryAction;
  final DashboardAction analysisAction;
  final String latestNewsTitle;
  final String latestNewsSubtitle;
  final bool offline;

  bool get hasLatestNews => latestNewsTitle.isNotEmpty;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : const {};

String _text(Object? value) => value?.toString().trim() ?? '';

/// Non-negative integer or null. Counts can't be negative; a negative or
/// non-numeric value is treated as missing rather than shown.
int? _count(Object? value) {
  final n = switch (value) {
    num() => value.isFinite ? value.toInt() : null,
    String() => int.tryParse(value.trim()),
    _ => null,
  };
  return n == null || n < 0 ? null : n;
}
