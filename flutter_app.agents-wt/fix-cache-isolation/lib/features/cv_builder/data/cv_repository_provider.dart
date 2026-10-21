import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/features/cv_builder/data/cv_repository.dart';
import 'package:sirati/shared/services/session_user_provider.dart';

/// Storage for builder CVs; tests override it with an in-memory engine.
///
/// Rebuilt per account: the default engine keeps documents in memory, which
/// would otherwise outlive a sign-out within the same app session.
final cvRepositoryProvider = Provider<CvRepository>((ref) {
  ref.watch(sessionUserIdProvider);
  return LocalCvRepository();
});
