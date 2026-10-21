import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/ats_scanner/domain/score_band.dart';

void main() {
  group('ScoreBand.of', () {
    test('a higher score never lands in a worse band', () {
      // Enum order is best -> worst, so a larger score must never produce a
      // larger index. Covers out-of-range API values on both sides.
      for (var s = -20; s < 140; s++) {
        expect(
          ScoreBand.of(s + 1).index,
          lessThanOrEqualTo(ScoreBand.of(s).index),
          reason: 'score ${s + 1} ranked below score $s',
        );
      }
    });

    test('every band is reachable and the extremes are pinned', () {
      final bands = {for (var s = 0; s <= 100; s++) ScoreBand.of(s)};
      expect(bands, ScoreBand.values.toSet());
      expect(ScoreBand.of(0), ScoreBand.weak);
      expect(ScoreBand.of(100), ScoreBand.excellent);
    });
  });

  group('criterionRatio', () {
    test('is finite and within 0..1 for any score/max pair', () {
      const values = [-1000, -1, 0, 1, 2, 3, 7, 10, 15, 30, 99, 100, 1000];
      for (final score in values) {
        for (final max in values) {
          final r = criterionRatio(score, max);
          expect(r.isFinite, isTrue, reason: 'score=$score max=$max');
          expect(r, inInclusiveRange(0.0, 1.0),
              reason: 'score=$score max=$max');
          // The UI renders (r * 100).round(); that throws on NaN/Infinity.
          expect(() => (r * 100).round(), returnsNormally);
        }
      }
    });

    test('equals score/max whenever 0 <= score <= max and max > 0', () {
      for (var max = 1; max <= 30; max++) {
        for (var score = 0; score <= max; score++) {
          expect(criterionRatio(score, max), closeTo(score / max, 1e-12));
        }
      }
    });
  });

  group('CriterionBand.of', () {
    test('a higher ratio never lands in a worse band', () {
      for (var i = 0; i < 1000; i++) {
        final a = i / 1000;
        final b = (i + 1) / 1000;
        expect(CriterionBand.of(b).index,
            lessThanOrEqualTo(CriterionBand.of(a).index));
      }
      expect(CriterionBand.of(0), CriterionBand.needsWork);
      expect(CriterionBand.of(1), CriterionBand.strong);
    });
  });
}
