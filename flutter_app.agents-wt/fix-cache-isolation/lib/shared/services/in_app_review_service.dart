import 'package:flutter/foundation.dart';
import 'package:in_app_review/in_app_review.dart';

import 'package:sirati/core/storage/preference_store.dart';

/// OS in-app review at peak satisfaction (after successful CV generation).
///
/// Never shows a custom "rate us" dialog. [InAppReview.requestReview] is
/// rate-limited and may no-op silently — that is expected. Failures are
/// swallowed so review never blocks navigation or UX.
class InAppReviewService {
  InAppReviewService._();

  static final InAppReviewService instance = InAppReviewService._();

  static const PreferenceStore _prefs = PreferenceStore();
  static const int _minGenerations = 2;

  /// Call after a **create** (not edit) CV succeeds and [SuccessBeat] finishes.
  ///
  /// Gate: generation count ≥ 2 and review never requested before.
  static Future<void> maybeRequestAfterCvGenerated() async {
    try {
      final already = await _prefs.readReviewRequested();
      if (already) {
        debugPrint('[Review] skip: already requested');
        return;
      }

      final count = await _prefs.incrementCvGenerationCount();
      debugPrint('[Review] generation_count=$count (need ≥$_minGenerations)');

      if (count < _minGenerations) {
        debugPrint('[Review] skip: first generation(s) — no prompt');
        return;
      }

      final review = InAppReview.instance;
      final available = await review.isAvailable();
      debugPrint('[Review] isAvailable=$available → requestReview()');
      // Always attempt; OS may still no-op when rate-limited.
      await review.requestReview();
      await _prefs.saveReviewRequested(true);
      debugPrint('[Review] marked sirati_review_requested');
    } catch (e, st) {
      debugPrint('[Review] suppressed error: $e\n$st');
    }
  }
}
