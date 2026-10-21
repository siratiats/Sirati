import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/shared/models/job_title.dart';
import 'package:sirati/shared/services/mobile_content_service_provider.dart';

/// Job-title catalogue for pickers. Always fetched fresh so newly added
/// titles appear without an app update; a failure yields an error state the
/// picker can show (users can still continue without a title).
final jobTitlesProvider = FutureProvider.autoDispose<List<JobTitle>>(
  (ref) => ref.read(mobileContentServiceProvider).jobTitles(force: true),
  retry: (_, __) => null,
);
