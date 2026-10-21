import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/analytics_service.dart';
import 'package:sirati/core/storage/preference_store.dart';

/// Persistence behind the education "notify me" button; tests replace it.
class EducationInterestStore {
  const EducationInterestStore();

  Future<bool> read() => const PreferenceStore().readEducationInterest();
  Future<void> save() => const PreferenceStore().saveEducationInterest();
}

final educationInterestStoreProvider = Provider<EducationInterestStore>(
  (ref) => const EducationInterestStore(),
);

/// Whether the user registered interest in the education launch. Stored on
/// the device so the choice survives restarts (there is no server-side
/// waitlist yet).
final educationInterestProvider =
    AsyncNotifierProvider.autoDispose<EducationInterestController, bool>(
  EducationInterestController.new,
);

class EducationInterestController extends AsyncNotifier<bool> {
  @override
  Future<bool> build() async {
    try {
      return await ref.read(educationInterestStoreProvider).read();
    } catch (_) {
      return false;
    }
  }

  /// Records interest once. The UI flips immediately; a storage failure only
  /// means the button may reappear after a restart.
  Future<void> register() async {
    if (state.value ?? false) return;
    final store = ref.read(educationInterestStoreProvider);
    state = const AsyncData(true);
    try {
      await store.save();
    } catch (_) {}
    await AnalyticsService.logFeatureInterest(feature: 'education');
  }
}
