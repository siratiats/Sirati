/// Pure scoring rules for the analysis result screen.
///
/// Kept free of Flutter imports so the thresholds are unit-testable and the
/// UI only maps a band to colours and copy.
library;

/// Overall ATS score band (0-100).
enum ScoreBand {
  excellent,
  veryGood,
  fair,
  weak;

  static ScoreBand of(int score) {
    if (score >= 80) return excellent;
    if (score >= 65) return veryGood;
    if (score >= 50) return fair;
    return weak;
  }
}

/// Band for a single criterion row, from its score / max ratio.
enum CriterionBand {
  strong,
  good,
  needsWork;

  static CriterionBand of(double ratio) {
    if (ratio >= 0.8) return strong;
    if (ratio >= 0.6) return good;
    return needsWork;
  }
}

/// Fill ratio for a criterion, always within 0..1.
///
/// The API can send `max: 0` or a score above max; a raw `score / max` would
/// then be NaN/Infinity, and `(ratio * 100).round()` throws on those.
double criterionRatio(int score, int max) {
  if (max <= 0) return 0;
  return (score / max).clamp(0.0, 1.0);
}
