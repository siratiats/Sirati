import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/features/cv_builder/application/cv_enhancement_controller.dart';
import 'package:sirati/features/cv_builder/application/cv_generation_controller.dart';
import 'package:sirati/features/cv_builder/domain/cv_form_data.dart';
import 'package:sirati/shared/models/generated_cv.dart';
import 'package:sirati/shared/services/cv_api_service.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';

GeneratedCv _cv(int id, {String status = 'completed'}) => GeneratedCv.fromJson({
      'id': id,
      'full_name': 'Test User',
      'target_job_title': 'Engineer',
      'language': 'en',
      'ai_status': status,
    });

/// Every call parks on its own completer so tests can interleave requests.
class _FakeApi extends CvApiService {
  final creates = <(Map<String, dynamic>, String?, Completer<GeneratedCv>)>[];
  final updates = <(int, Completer<GeneratedCv>)>[];
  final fieldCalls = <(String, Completer<Map<String, dynamic>>)>[];
  final jdCalls = <Completer<Map<String, dynamic>>>[];
  bool pollTimesOut = false;

  @override
  Future<GeneratedCv> generateCv(
    Map<String, dynamic> payload, {
    String? idempotencyKey,
  }) {
    final c = Completer<GeneratedCv>();
    creates.add((payload, idempotencyKey, c));
    return c.future;
  }

  @override
  Future<GeneratedCv> updateGeneratedCv(int id, Map<String, dynamic> payload) {
    final c = Completer<GeneratedCv>();
    updates.add((id, c));
    return c.future;
  }

  @override
  Future<AiPollResult<GeneratedCv>> pollGeneratedCv(
    GeneratedCv initial, {
    bool Function()? isCancelled,
    bool Function()? isPaused,
    void Function(GeneratedCv value)? onProgress,
  }) async {
    onProgress?.call(initial);
    return AiPollResult(value: initial, timedOut: pollTimesOut);
  }

  @override
  Future<Map<String, dynamic>> enhanceCvField({
    required String field,
    required String draft,
    required String jobTitle,
    required String language,
  }) {
    final c = Completer<Map<String, dynamic>>();
    fieldCalls.add((field, c));
    return c.future;
  }

  @override
  Future<Map<String, dynamic>> enhanceJobDescription({
    required String targetJobTitle,
    required String jobDescription,
    required String language,
  }) {
    final c = Completer<Map<String, dynamic>>();
    jdCalls.add(c);
    return c.future;
  }
}

const _data = CvFormData(
  fullName: 'Salem',
  targetJobTitle: 'Backend Developer',
  skills: 'PHP',
  experience: 'x',
  education: 'BSc',
  language: 'en',
);

const _serverError = ApiException('boom', statusCode: 500);

void main() {
  late _FakeApi api;
  late ProviderContainer container;

  setUp(() {
    api = _FakeApi();
    container = ProviderContainer(
      overrides: [cvApiServiceProvider.overrideWithValue(api)],
    );
    // Keep autoDispose controllers alive for the whole test.
    container.listen(cvGenerationControllerProvider, (_, __) {});
    container.listen(cvFieldEnhancerProvider, (_, __) {});
    container.listen(jobDescriptionEnhancerProvider, (_, __) {});
  });

  tearDown(() => container.dispose());

  group('CvGenerationController', () {
    CvGenerationController gen() =>
        container.read(cvGenerationControllerProvider.notifier);
    bool loading() => container.read(cvGenerationControllerProvider);

    test('create: loading while in flight, idle after, statuses reported',
        () async {
      final statuses = <String>[];
      final future = gen().submit(
        data: _data,
        isCancelled: () => false,
        onStatus: statuses.add,
      );
      expect(loading(), isTrue);

      api.creates.single.$3.complete(_cv(10, status: 'processing'));
      final outcome = await future;

      expect(outcome, isA<CvGenerated>());
      expect((outcome as CvGenerated).cv.id, 10);
      expect(statuses, isNotEmpty);
      expect(loading(), isFalse);
      expect(api.creates.single.$1, _data.toPayload());
    });

    test('edit mode updates the given CV instead of creating', () async {
      final future =
          gen().submit(data: _data, editingId: 77, isCancelled: () => false);
      expect(api.creates, isEmpty);
      expect(api.updates.single.$1, 77);
      api.updates.single.$2.complete(_cv(77));
      expect(await future, isA<CvGenerated>());
    });

    test('idempotency key is reused on retry and reset after success',
        () async {
      final first = gen().submit(data: _data, isCancelled: () => false);
      api.creates[0].$3.completeError(_serverError);
      expect(await first, isA<CvGenerationFailed>());

      final retry = gen().submit(data: _data, isCancelled: () => false);
      api.creates[1].$3.complete(_cv(1));
      expect(await retry, isA<CvGenerated>());
      expect(api.creates[1].$2, api.creates[0].$2,
          reason: 'a retry must not create a duplicate CV server-side');

      final next = gen().submit(data: _data, isCancelled: () => false);
      api.creates[2].$3.complete(_cv(2));
      await next;
      expect(api.creates[2].$2, isNot(api.creates[0].$2),
          reason: 'a new CV after success needs a fresh key');
    });

    test('timeout and AI failure still count as a usable CV', () async {
      api.pollTimesOut = true;
      final timedOut = gen().submit(data: _data, isCancelled: () => false);
      api.creates.last.$3.complete(_cv(1, status: 'processing'));
      expect((await timedOut as CvGenerated).timedOut, isTrue);

      api.pollTimesOut = false;
      final failed = gen().submit(data: _data, isCancelled: () => false);
      api.creates.last.$3.complete(_cv(2, status: 'failed'));
      expect((await failed as CvGenerated).aiFailed, isTrue);
    });

    test('cancel and supersede both discard the late response', () async {
      final cancelled = gen().submit(data: _data, isCancelled: () => false);
      gen().cancel();
      expect(loading(), isFalse);
      api.creates[0].$3.complete(_cv(1));
      expect(await cancelled, isA<CvGenerationCancelled>());
      expect(loading(), isFalse);

      final older = gen().submit(data: _data, isCancelled: () => false);
      final newer = gen().submit(data: _data, isCancelled: () => false);
      api.creates[2].$3.complete(_cv(3));
      expect(await newer, isA<CvGenerated>());
      api.creates[1].$3.complete(_cv(2));
      expect(await older, isA<CvGenerationCancelled>());
    });

    test('errors surface unchanged; dispose mid-flight is safe', () async {
      final failing = gen().submit(data: _data, isCancelled: () => false);
      api.creates.single.$3.completeError(_serverError);
      final outcome = await failing;
      expect((outcome as CvGenerationFailed).error, same(_serverError));
      expect(loading(), isFalse);

      final pending = gen().submit(data: _data, isCancelled: () => false);
      container.dispose();
      api.creates.last.$3.complete(_cv(9));
      expect(await pending, isA<CvGenerationCancelled>());
    });
  });

  group('CvFieldEnhancer', () {
    CvFieldEnhancer enhancer() =>
        container.read(cvFieldEnhancerProvider.notifier);
    String? enhancing() => container.read(cvFieldEnhancerProvider);

    Future<CvEnhanceOutcome> enhance(String field) => enhancer().enhance(
          field: field,
          draft: 'draft text',
          jobTitle: 'Engineer',
          language: 'en',
        );

    test('only the newest request applies, across any fields', () async {
      final fields = ['skills', 'summary', 'experience', 'education'];
      final futures = [for (final f in fields) enhance(f)];
      expect(enhancing(), fields.last);

      // Complete in reverse so an older response arrives after the newest.
      for (var i = fields.length - 1; i >= 0; i--) {
        api.fieldCalls[i].$2.complete({'enhanced_text': 'out $i'});
      }
      final outcomes = await Future.wait(futures);

      expect(outcomes.last, isA<CvEnhanced>());
      expect((outcomes.last as CvEnhanced).text, 'out ${fields.length - 1}');
      for (final stale in outcomes.take(fields.length - 1)) {
        expect(stale, isA<CvEnhanceStale>());
      }
      expect(enhancing(), isNull);
    });

    test('blank or missing enhanced text is a failure, not an empty field',
        () async {
      for (final reply in <Map<String, dynamic>>[
        {},
        {'enhanced_text': ''},
        {'enhanced_text': '   \n'},
        {'enhanced_text': null},
      ]) {
        final future = enhance('skills');
        api.fieldCalls.last.$2.complete(reply);
        expect(await future, isA<CvEnhanceFailed>(), reason: '$reply');
        expect(enhancing(), isNull);
      }
    });

    test('errors reset state and the result is trimmed', () async {
      final failing = enhance('summary');
      api.fieldCalls.last.$2.completeError(StateError('parse'));
      expect(await failing, isA<CvEnhanceFailed>());
      expect(enhancing(), isNull);

      final ok = enhance('summary');
      api.fieldCalls.last.$2.complete({'enhanced_text': '  better  '});
      expect((await ok as CvEnhanced).text, 'better');
    });
  });

  group('JobDescriptionEnhancer', () {
    test('is independent of field enhancement', () async {
      final jd = container.read(jobDescriptionEnhancerProvider.notifier);
      final fields = container.read(cvFieldEnhancerProvider.notifier);

      final jdFuture = jd.enhance(
        targetJobTitle: 'Engineer',
        jobDescription: 'Build APIs',
        language: 'en',
      );
      final fieldFuture = fields.enhance(
        field: 'skills',
        draft: 'PHP',
        jobTitle: 'Engineer',
        language: 'en',
      );
      expect(container.read(jobDescriptionEnhancerProvider), isTrue);

      api.fieldCalls.single.$2.complete({'enhanced_text': 'PHP, Laravel'});
      api.jdCalls.single.complete({'enhanced_description': 'Better JD'});

      expect(await fieldFuture, isA<CvEnhanced>());
      expect((await jdFuture as CvEnhanced).text, 'Better JD');
      expect(container.read(jobDescriptionEnhancerProvider), isFalse);
    });

    test('non-API errors come back as Failed instead of escaping', () async {
      final jd = container.read(jobDescriptionEnhancerProvider.notifier);
      final future = jd.enhance(
        targetJobTitle: 'Engineer',
        jobDescription: 'Build APIs',
        language: 'en',
      );
      api.jdCalls.single.completeError(const FormatException('bad json'));
      expect(await future, isA<CvEnhanceFailed>());
      expect(container.read(jobDescriptionEnhancerProvider), isFalse);
    });
  });
}
