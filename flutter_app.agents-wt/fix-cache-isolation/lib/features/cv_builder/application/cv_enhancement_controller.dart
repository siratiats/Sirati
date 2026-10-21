import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';

sealed class CvEnhanceOutcome {
  const CvEnhanceOutcome();
}

final class CvEnhanced extends CvEnhanceOutcome {
  const CvEnhanced({required this.text, this.details = const {}});

  /// The improved text to put into the field.
  final String text;

  /// Full API response (changes, missing facts, unverified claims) for the
  /// review panel under the field.
  final Map<String, dynamic> details;
}

/// A newer request replaced this one, or the screen went away. Ignore it.
final class CvEnhanceStale extends CvEnhanceOutcome {
  const CvEnhanceStale();
}

final class CvEnhanceFailed extends CvEnhanceOutcome {
  const CvEnhanceFailed(this.error);

  final Object error;
}

/// Name of the CV field currently being enhanced ('skills', 'summary',
/// 'experience', 'education', 'certifications'), or null when idle.
///
/// Only the newest request applies: enhancing a second field while the first
/// is in flight discards the first result.
final cvFieldEnhancerProvider =
    NotifierProvider.autoDispose<CvFieldEnhancer, String?>(CvFieldEnhancer.new);

class CvFieldEnhancer extends Notifier<String?> {
  int _requestGen = 0;

  @override
  String? build() => null;

  bool _isCurrent(int requestId) => ref.mounted && requestId == _requestGen;

  Future<CvEnhanceOutcome> enhance({
    required String field,
    required String draft,
    required String jobTitle,
    required String language,
  }) async {
    final requestId = ++_requestGen;
    state = field;
    try {
      final result = await ref.read(cvApiServiceProvider).enhanceCvField(
            field: field,
            draft: draft.trim(),
            jobTitle: jobTitle,
            language: language,
          );
      if (!_isCurrent(requestId)) return const CvEnhanceStale();

      final text = result['enhanced_text']?.toString().trim() ?? '';
      if (text.isEmpty) {
        return const CvEnhanceFailed(
          ApiException('لم يُرجع التحسين نصاً صالحاً.'),
        );
      }
      return CvEnhanced(text: text, details: result);
    } catch (error) {
      if (!_isCurrent(requestId)) return const CvEnhanceStale();
      return CvEnhanceFailed(error);
    } finally {
      if (_isCurrent(requestId)) state = null;
    }
  }
}

/// `true` while the job-description enhancer runs. Independent of
/// [cvFieldEnhancerProvider] so it never supersedes (or is superseded by) a
/// field enhancement on another step.
final jobDescriptionEnhancerProvider =
    NotifierProvider.autoDispose<JobDescriptionEnhancer, bool>(
  JobDescriptionEnhancer.new,
);

class JobDescriptionEnhancer extends Notifier<bool> {
  int _requestGen = 0;

  @override
  bool build() => false;

  bool _isCurrent(int requestId) => ref.mounted && requestId == _requestGen;

  Future<CvEnhanceOutcome> enhance({
    required String targetJobTitle,
    required String jobDescription,
    required String language,
  }) async {
    final requestId = ++_requestGen;
    state = true;
    try {
      final data = await ref.read(cvApiServiceProvider).enhanceJobDescription(
            targetJobTitle: targetJobTitle,
            jobDescription: jobDescription.trim(),
            language: language,
          );
      if (!_isCurrent(requestId)) return const CvEnhanceStale();

      final text = data['enhanced_description']?.toString().trim() ?? '';
      if (text.isEmpty) {
        return const CvEnhanceFailed(
          ApiException('لم يُرجع التحسين نصاً صالحاً.'),
        );
      }
      return CvEnhanced(text: text, details: data);
    } catch (error) {
      if (!_isCurrent(requestId)) return const CvEnhanceStale();
      return CvEnhanceFailed(error);
    } finally {
      if (_isCurrent(requestId)) state = false;
    }
  }
}
