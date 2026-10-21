import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/utils/idempotency_key.dart';
import 'package:sirati/features/cv_builder/domain/cv_form_data.dart';
import 'package:sirati/shared/models/ai_status.dart';
import 'package:sirati/shared/models/generated_cv.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';

sealed class CvGenerationOutcome {
  const CvGenerationOutcome();
}

/// The CV record exists. [timedOut] means AI is still running server-side;
/// [aiFailed] means the server fell back to a non-AI version.
final class CvGenerated extends CvGenerationOutcome {
  const CvGenerated({required this.cv, required this.timedOut});

  final GeneratedCv cv;
  final bool timedOut;

  bool get aiFailed => cv.aiStatus == AiStatus.failed;
}

/// Cancelled by the user, superseded by a newer submit, or the screen left.
final class CvGenerationCancelled extends CvGenerationOutcome {
  const CvGenerationCancelled();
}

final class CvGenerationFailed extends CvGenerationOutcome {
  const CvGenerationFailed(this.error);

  final Object error;
}

/// `true` while a create/update + poll cycle is running.
final cvGenerationControllerProvider =
    NotifierProvider.autoDispose<CvGenerationController, bool>(
  CvGenerationController.new,
);

class CvGenerationController extends Notifier<bool> {
  int _requestGen = 0;

  /// Reused across retries of the same create so a network retry cannot
  /// produce a duplicate CV server-side; cleared once a create succeeds.
  String? _idempotencyKey;

  @override
  bool build() => false;

  void cancel() {
    _requestGen++;
    state = false;
  }

  /// Creates a CV, or updates [editingId] when set, then polls the AI job.
  /// [onStatus] receives each AI status for the progress overlay.
  Future<CvGenerationOutcome> submit({
    required CvFormData data,
    int? editingId,
    required bool Function() isCancelled,
    void Function(String aiStatus)? onStatus,
  }) async {
    final api = ref.read(cvApiServiceProvider);
    final requestId = ++_requestGen;
    _idempotencyKey ??= newIdempotencyKey();
    state = true;

    bool superseded() =>
        !ref.mounted || isCancelled() || requestId != _requestGen;

    try {
      final payload = data.toPayload();
      final created = editingId != null
          ? await api.updateGeneratedCv(editingId, payload)
          : await api.generateCv(payload, idempotencyKey: _idempotencyKey);
      if (superseded()) return const CvGenerationCancelled();
      onStatus?.call(created.aiStatus);

      final poll = await api.pollGeneratedCv(
        created,
        isCancelled: superseded,
        onProgress: (value) => onStatus?.call(value.aiStatus),
      );
      if (poll.cancelled || superseded()) return const CvGenerationCancelled();

      _idempotencyKey = null;
      MobileContentService.invalidateCvRelated();
      return CvGenerated(cv: poll.value, timedOut: poll.timedOut);
    } catch (error) {
      if (superseded()) return const CvGenerationCancelled();
      return CvGenerationFailed(error);
    } finally {
      if (ref.mounted && requestId == _requestGen) state = false;
    }
  }
}
