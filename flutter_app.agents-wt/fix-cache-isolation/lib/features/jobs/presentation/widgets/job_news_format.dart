import 'package:sirati/core/utils/app_format.dart';
import 'package:sirati/shared/models/job_news.dart';

/// Date labels shared by job cards and the detail screen.
/// Prefer computed relative time from [JobNews.publishedAt]; fall back to API label digits.
String jobPublishedText(JobNews item, {required bool english}) {
  if (item.publishedAt != null) {
    return AppFormat.relativeTime(item.publishedAt!, english: english);
  }
  final label = item.publishedLabel?.trim() ?? '';
  if (label.isEmpty) return '';
  return AppFormat.digits(label, english: english);
}

String jobValidUntilText(JobNews item, {required bool english}) {
  if (item.validUntil != null) {
    return AppFormat.shortDate(item.validUntil!, english: english);
  }
  final label = item.validUntilLabel?.trim() ?? '';
  if (label.isEmpty) return '';
  return AppFormat.digits(label, english: english);
}
