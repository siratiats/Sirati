import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/features/ats_scanner/application/improved_cv_controller.dart';
import 'package:sirati/shared/models/generated_cv.dart';
import 'package:sirati/shared/services/cv_api_service.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';

GeneratedCv _cv(int id, {String status = 'completed', String? aiError}) =>
    GeneratedCv.fromJson({
      'id': id,
      'full_name': 'Test User',
      'target_job_title': 'Engineer',
      'language': 'en',
      'ai_status': status,
      if (aiError != null) 'ai_error': aiError,
    });

/// Each call to [generateCvFromAnalysis] parks on its own completer so tests
/// can interleave cancels, supersedes, and disposal deterministically.
class _FakeApi extends CvApiService {
  final pending = <Completer<GeneratedCv>>[];
  AiPollResult<GeneratedCv> Function(GeneratedCv cv) pollResult =
      (cv) => AiPollResult(value: cv);

  @override
  Future<GeneratedCv> generateCvFromAnalysis({
    required int analysisId,
    required Map<String, dynamic> overrides,
    String? idempotencyKey,
  }) {
    final c = Completer<GeneratedCv>();
    pending.add(c);
    return c.future;
  }

  @override
  Future<AiPollResult<GeneratedCv>> pollGeneratedCv(
    GeneratedCv initial, {
    bool Function()? isCancelled,
    bool Function()? isPaused,
    void Function(GeneratedCv value)? onProgress,
  }) async {
    return pollResult(initial);
  }
}

void main() {
  late _FakeApi api;
  late ProviderContainer container;
  const analysisId = 7;

  setUp(() {
    api = _FakeApi();
    container = ProviderContainer(
      overrides: [cvApiServiceProvider.overrideWithValue(api)],
    );
  });

  tearDown(() => container.dispose());

  ImprovedCvController controller() {
    // Keep the autoDispose provider alive for the whole test.
    container.listen(improvedCvControllerProvider(analysisId), (_, __) {});
    return container.read(improvedCvControllerProvider(analysisId).notifier);
  }

  bool generating() => container.read(improvedCvControllerProvider(analysisId));

  test('is generating while in flight and idle after success', () async {
    final c = controller();
    expect(generating(), isFalse);

    final future = c.generate(isCancelled: () => false);
    expect(generating(), isTrue);

    api.pending.single.complete(_cv(42));
    final outcome = await future;

    expect(outcome, isA<ImprovedCvReady>());
    final ready = outcome as ImprovedCvReady;
    expect(ready.generatedCvId, 42);
    expect(ready.timedOut, isFalse);
    expect(ready.aiFailed, isFalse);
    expect(generating(), isFalse);
  });

  test('reports server-side AI failure and timeout as usable results',
      () async {
    final c = controller();

    api.pollResult = (cv) => AiPollResult(value: cv, timedOut: true);
    final timedOut = c.generate(isCancelled: () => false);
    api.pending.last.complete(_cv(1));
    expect((await timedOut as ImprovedCvReady).timedOut, isTrue);

    api.pollResult = (cv) => AiPollResult(value: cv);
    final failed = c.generate(isCancelled: () => false);
    api.pending.last.complete(_cv(2, status: 'failed', aiError: 'quota'));
    final ready = await failed as ImprovedCvReady;
    expect(ready.aiFailed, isTrue);
    expect(ready.aiError, 'quota');
    expect(generating(), isFalse);
  });

  test('cancel resets state and the late response is discarded', () async {
    final c = controller();
    final future = c.generate(isCancelled: () => false);

    c.cancel();
    expect(generating(), isFalse);

    api.pending.single.complete(_cv(9));
    expect(await future, isA<ImprovedCvCancelled>());
    expect(generating(), isFalse, reason: 'late response must not re-arm');
  });

  test('user-level cancellation (overlay) yields Cancelled', () async {
    final c = controller();
    var cancelled = false;
    final future = c.generate(isCancelled: () => cancelled);

    cancelled = true;
    api.pending.single.complete(_cv(3));
    expect(await future, isA<ImprovedCvCancelled>());
  });

  test('a newer request supersedes an older one', () async {
    final c = controller();
    final first = c.generate(isCancelled: () => false);
    final second = c.generate(isCancelled: () => false);

    api.pending[1].complete(_cv(2));
    expect(await second, isA<ImprovedCvReady>());

    api.pending[0].complete(_cv(1));
    expect(await first, isA<ImprovedCvCancelled>());
    expect(generating(), isFalse);
  });

  test('errors surface as Failed with the original error', () async {
    final c = controller();
    final future = c.generate(isCancelled: () => false);

    const error = ApiException(
      'boom',
      statusCode: 500,
      type: ApiErrorType.server,
    );
    api.pending.single.completeError(error);
    final outcome = await future;

    expect(outcome, isA<ImprovedCvFailed>());
    expect((outcome as ImprovedCvFailed).error, same(error));
    expect(generating(), isFalse);
  });

  test('disposing the screen mid-flight cancels without throwing', () async {
    final c = controller();
    final future = c.generate(isCancelled: () => false);

    container.dispose();
    api.pending.single.complete(_cv(5));

    expect(await future, isA<ImprovedCvCancelled>());
  });
}
