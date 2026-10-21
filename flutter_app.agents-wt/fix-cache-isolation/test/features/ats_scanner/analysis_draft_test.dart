import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/ats_scanner/domain/analysis_draft.dart';
import 'package:sirati/features/ats_scanner/presentation/cv_analysis_screen.dart';

AnalysisDraft _draft({
  String title = 'Backend Developer',
  String jd = '',
  String text = 'resume body',
  String? file,
  int? size,
}) =>
    AnalysisDraft(
      jobTitle: title,
      jobDescription: jd,
      resumeText: text,
      fileName: file,
      fileSize: size,
    );

void main() {
  group('limits mirror CvAnalysisController', () {
    test('values', () {
      expect(AnalysisLimits.jobTitleMax, 160);
      expect(AnalysisLimits.jobDescriptionMax, 4000);
      expect(AnalysisLimits.maxUploadBytes, 5120 * 1024);
      expect(AnalysisLimits.allowedExtensions, ['pdf', 'txt']);
    });

    test('job title: required, trimmed, boundary at the max', () {
      for (final blank in [null, '', '   ']) {
        expect(AnalysisRules.jobTitle(blank), AnalysisFieldError.required);
      }
      const max = AnalysisLimits.jobTitleMax;
      expect(AnalysisRules.jobTitle('  ${'a' * max}  '), isNull);
      expect(
          AnalysisRules.jobTitle('a' * (max + 1)), AnalysisFieldError.tooLong);
    });

    test('job description: optional, trimmed, boundary at the max', () {
      const max = AnalysisLimits.jobDescriptionMax;
      for (final ok in [null, '', '  ', 'x' * max, '  ${'x' * max}  ']) {
        expect(AnalysisRules.jobDescription(ok), isNull);
      }
      expect(AnalysisRules.jobDescription('x' * (max + 1)),
          AnalysisFieldError.tooLong);
    });
  });

  group('upload', () {
    test('only pdf/txt, any case, within 5 MB', () {
      const max = AnalysisLimits.maxUploadBytes;
      for (final name in ['cv.pdf', 'CV.PDF', 'a.b.txt', 'r.Txt']) {
        for (final size in [0, 1, max]) {
          expect(AnalysisRules.upload(name: name, size: size), isNull,
              reason: '$name $size');
        }
        expect(AnalysisRules.upload(name: name, size: max + 1),
            UploadRejection.tooLarge);
      }
      for (final name in ['cv.docx', 'cv', 'pdf', 'cv.pdf.exe', '.pdfx']) {
        expect(AnalysisRules.upload(name: name, size: 10),
            UploadRejection.unsupportedType,
            reason: name);
      }
    });
  });

  group('AnalysisDraft', () {
    test('a file or non-blank text is required', () {
      expect(_draft(text: '').hasResume, isFalse);
      expect(_draft(text: '  \n ').hasResume, isFalse);
      expect(_draft(text: 'x').hasResume, isTrue);
      expect(_draft(text: '', file: 'cv.pdf', size: 1).hasResume, isTrue);
    });

    test('fingerprint ignores surrounding whitespace only', () {
      expect(
        _draft(title: ' A ', jd: ' J ', text: ' T ').fingerprint,
        _draft(title: 'A', jd: 'J', text: 'T').fingerprint,
      );
      final base = _draft(file: 'cv.pdf', size: 10);
      for (final changed in [
        _draft(title: 'Other', file: 'cv.pdf', size: 10),
        _draft(jd: 'new jd', file: 'cv.pdf', size: 10),
        _draft(text: 'edited', file: 'cv.pdf', size: 10),
        _draft(file: 'other.pdf', size: 10),
        _draft(file: 'cv.pdf', size: 11),
        _draft(),
      ]) {
        expect(changed.fingerprint, isNot(base.fingerprint));
      }
    });

    test('field boundaries cannot collide', () {
      expect(
        _draft(title: 'ab', jd: 'c').fingerprint,
        isNot(_draft(title: 'a', jd: 'bc').fingerprint),
      );
    });
  });

  group('IdempotencyKeyRing', () {
    late int minted;
    late IdempotencyKeyRing ring;
    setUp(() {
      minted = 0;
      ring = IdempotencyKeyRing(() => 'k${++minted}');
    });

    test('retrying the same draft reuses its key', () {
      final key = ring.keyFor(_draft());
      for (var i = 0; i < 5; i++) {
        expect(ring.keyFor(_draft()), key);
      }
      expect(minted, 1);
    });

    test('an edited draft gets a fresh key (no replay of old results)', () {
      final first = ring.keyFor(_draft(text: 'v1'));
      final second = ring.keyFor(_draft(text: 'v2'));
      expect(second, isNot(first));
      // Going back to v1 is a new request too — v1's key was superseded.
      expect(ring.keyFor(_draft(text: 'v1')), isNot(first));
    });

    test('after consume, even an identical draft is a new analysis', () {
      final first = ring.keyFor(_draft());
      ring.consume();
      expect(ring.keyFor(_draft()), isNot(first));
    });
  });

  test('polling pauses exactly when the app is not in the foreground', () {
    for (final state in AppLifecycleState.values) {
      expect(
        pausesPolling(state),
        state == AppLifecycleState.paused ||
            state == AppLifecycleState.hidden ||
            state == AppLifecycleState.detached,
        reason: '$state',
      );
    }
    expect(pausesPolling(AppLifecycleState.resumed), isFalse);
  });
}
