import 'package:flutter/material.dart';
import 'package:sirati/features/ats_scanner/domain/score_band.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// Maps pure score bands to palette colours and copy.
extension ScoreBandStyle on ScoreBand {
  Color color(SiratiColors c) => switch (this) {
        ScoreBand.excellent => c.tealDark,
        ScoreBand.veryGood => c.primary,
        ScoreBand.fair => c.amber,
        ScoreBand.weak => c.red,
      };

  /// Badge fill for the score, paired with [onContainer] (a gated AA pair
  /// in both themes). [color] is a fill/accent tone and fails AA as text.
  Color container(SiratiColors c) => switch (this) {
        ScoreBand.excellent => c.successLight,
        ScoreBand.veryGood => c.primaryLight,
        ScoreBand.fair => c.warningLight,
        ScoreBand.weak => c.errorLight,
      };

  Color onContainer(SiratiColors c) => switch (this) {
        ScoreBand.excellent => c.onSuccessContainer,
        ScoreBand.veryGood => c.primaryInk,
        ScoreBand.fair => c.onWarningContainer,
        ScoreBand.weak => c.onErrorContainer,
      };

  String description({required bool english}) => switch (this) {
        ScoreBand.excellent => english
            ? 'Excellent - your CV is strong and ready'
            : 'ممتاز - سيرتك قوية ومؤهلة',
        ScoreBand.veryGood => english
            ? 'Very good - a few improvements will help'
            : 'جيد جداً - مع بعض التحسينات',
        ScoreBand.fair =>
          english ? 'Fair - review and polish needed' : 'مقبول - يحتاج مراجعة',
        ScoreBand.weak =>
          english ? 'Weak - needs restructuring' : 'ضعيف - يحتاج إعادة هيكلة',
      };
}

extension CriterionBandStyle on CriterionBand {
  /// Bar fill (a graphic, not text).
  Color color(SiratiColors c) => switch (this) {
        CriterionBand.strong => c.teal,
        CriterionBand.good => c.primary,
        CriterionBand.needsWork => c.amber,
      };

  /// Percentage chip: gated container / on-container pairs.
  Color container(SiratiColors c) => switch (this) {
        CriterionBand.strong => c.successLight,
        CriterionBand.good => c.primaryLight,
        CriterionBand.needsWork => c.warningLight,
      };

  Color onContainer(SiratiColors c) => switch (this) {
        CriterionBand.strong => c.onSuccessContainer,
        CriterionBand.good => c.primaryInk,
        CriterionBand.needsWork => c.onWarningContainer,
      };
}
