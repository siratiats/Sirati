import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/shared/services/cv_api_service.dart';

/// Single injection point for [CvApiService].
///
/// Tests replace it with
/// `ProviderScope(overrides: [cvApiServiceProvider.overrideWithValue(fake)])`
/// instead of threading an optional service through widget constructors.
final cvApiServiceProvider = Provider<CvApiService>((ref) => CvApiService());
