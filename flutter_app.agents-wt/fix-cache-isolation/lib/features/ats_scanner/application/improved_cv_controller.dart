import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/shared/models/ai_status.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';

/// Result of asking the backend for an improved CV from an analysis.
///
/// The controller never touches BuildContext; the screen maps each outcome to
/// snackbars and navigation.
sealed class ImprovedCvOutcome {
  const ImprovedCvOutcome();
}

/// A generated CV record exists. [timedOut] / [aiFailed] mean the server
/// fell back to a usable non-AI version, which the user can still open.
final class ImprovedCvReady extends ImprovedCvOutcome {
  const ImprovedCvReady({
    required this.generatedCvId,
    required this.timedOut,
    required this.aiFailed,
    this.aiError,
  });

  final int generatedCvId;
  final bool timedOut;
  final bool aiFailed;
  final String? aiError;
}

/// The user cancelled, a newer request superseded this one, or the screen
/// went away. The UI should do nothing.
final class ImprovedCvCancelled extends ImprovedCvOutcome {
  const ImprovedCvCancelled();
}

final class ImprovedCvFailed extends ImprovedCvOutcome {
  const ImprovedCvFailed(this.error);

  final Object error;
}

/// `true` while an improved CV is being generated for the given analysis id.
final improvedCvControllerProvider =
    NotifierProvider.autoDispose.family<ImprovedCvController, bool, int>(
  ImprovedCvController.new,
);

class ImprovedCvController extends Notifier<bool> {
  ImprovedCvController(this.analysisId);

  final int analysisId;

  /// Bumped on every start and cancel so a late response from an older
  /// request can never overwrite state or trigger navigation.
  int _requestGen = 0;

  @override
  bool build() => false;

  void cancel() {
    _requestGen++;
    state = false;
  }

  Future<ImprovedCvOutcome> generate({
    required bool Function() isCancelled,
  }) async {
    final api = ref.read(cvApiServiceProvider);
    final requestId = ++_requestGen;
    state = true;

    bool superseded() =>
        !ref.mounted || isCancelled() || requestId != _requestGen;

    try {
      final created = await api.generateCvFromAnalysis(
        analysisId: analysisId,
        overrides: const {},
      );
      final poll = await api.pollGeneratedCv(
        created,
        isCancelled: superseded,
      );
      if (poll.cancelled || superseded()) return const ImprovedCvCancelled();

      final generatedCv = poll.value;
      MobileContentService.invalidateCvRelated();
      return ImprovedCvReady(
        generatedCvId: generatedCv.id,
        timedOut: poll.timedOut,
        aiFailed: generatedCv.aiStatus == AiStatus.failed,
        aiError: generatedCv.aiError,
      );
    } catch (error) {
      if (superseded()) return const ImprovedCvCancelled();
      return ImprovedCvFailed(error);
    } finally {
      if (ref.mounted && requestId == _requestGen) state = false;
    }
  }
}
