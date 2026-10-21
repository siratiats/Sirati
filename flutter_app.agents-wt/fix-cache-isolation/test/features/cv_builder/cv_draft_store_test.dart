import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/storage/preference_store.dart';
import 'package:sirati/features/cv_builder/application/cv_draft_store.dart';
import 'package:sirati/features/cv_builder/domain/cv_form_data.dart';

/// In-memory store; [failing] makes every call throw like a broken keystore.
class _FakePrefs extends PreferenceStore {
  _FakePrefs({this.failing = false});

  bool failing;
  Map<String, dynamic>? stored;
  int clears = 0;

  void _maybeFail() {
    if (failing) throw StateError('keystore unavailable');
  }

  @override
  Future<Map<String, dynamic>?> readCvDraft() async {
    _maybeFail();
    return stored;
  }

  @override
  Future<void> saveCvDraft(Map<String, dynamic> draft) async {
    _maybeFail();
    stored = draft;
  }

  @override
  Future<void> clearCvDraft() async {
    _maybeFail();
    clears++;
    stored = null;
  }
}

void main() {
  const filled = CvFormData(fullName: 'Salem', skills: 'PHP', language: 'en');

  test('saved drafts read back with the same data and clamped step', () async {
    final prefs = _FakePrefs();
    final store = CvDraftStore(prefs);

    await store.save(filled, step: 3);
    final draft = await store.read(stepCount: 4);

    expect(draft, isNotNull);
    expect(draft!.data.toPayload(), filled.toPayload());
    expect(draft.step, 3);
    expect(draft.savedAt, isNotNull);

    // A draft saved by an older 6-step wizard still opens on a valid step.
    await store.save(filled, step: 5);
    expect((await store.read(stepCount: 4))!.step, 3);
  });

  test('saving an empty form clears instead of storing', () async {
    final prefs = _FakePrefs()..stored = {'full_name': 'old'};
    final store = CvDraftStore(prefs);

    await store.save(const CvFormData(fullName: '   ', language: 'en'),
        step: 1);

    expect(prefs.stored, isNull);
    expect(prefs.clears, 1);
  });

  test('a stored draft with no real content is not offered', () async {
    final prefs = _FakePrefs()
      ..stored = {'full_name': '  ', 'skills_input': '\n', 'language': 'en'};
    expect(await CvDraftStore(prefs).read(stepCount: 4), isNull);
  });

  test('storage failures never escape to the wizard', () async {
    final store = CvDraftStore(_FakePrefs(failing: true));

    await expectLater(store.save(filled, step: 1), completes);
    await expectLater(store.clear(), completes);
    expect(await store.read(stepCount: 4), isNull);
  });
}
