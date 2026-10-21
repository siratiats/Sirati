import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/features/cv_builder/controllers/cv_builder_controller.dart';
import 'package:sirati/features/cv_builder/data/cv_repository.dart';
import 'package:sirati/shared/models/cv_document.dart';

/// Repository whose writes the test can hold open or fail.
class _ControlledRepo extends LocalCvRepository {
  _ControlledRepo() : super(engine: MemoryCvStorageEngine());

  final saved = <CvDocument>[];
  final gates = <Completer<void>>[];
  bool holdWrites = false;
  bool failWrites = false;
  int concurrent = 0;
  int maxConcurrent = 0;

  @override
  Future<void> saveCv(CvDocument document, {bool touchUpdatedAt = true}) async {
    concurrent++;
    if (concurrent > maxConcurrent) maxConcurrent = concurrent;
    try {
      if (holdWrites) {
        final gate = Completer<void>();
        gates.add(gate);
        await gate.future;
      }
      if (failWrites) throw StateError('disk full');
      saved.add(document);
    } finally {
      concurrent--;
    }
  }

  void release() {
    for (final g in gates) {
      if (!g.isCompleted) g.complete();
    }
    gates.clear();
  }
}

CvDocument _doc(String title) =>
    CvDocument.createEmpty(id: 'cv1', title: title);

void main() {
  late _ControlledRepo repo;
  late CvBuilderController controller;

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    repo = _ControlledRepo();
    controller = CvBuilderController(
      initialDocument: _doc('v0'),
      repository: repo,
    );
  });

  tearDown(() => controller.dispose());

  Future<void> settle() => Future<void>.delayed(Duration.zero);

  test('an edit made during a save stays dirty and is saved by flush',
      () async {
    controller.updateDocument(_doc('v1'));
    repo.holdWrites = true;
    final first = controller.saveImmediately();
    await settle();

    controller.updateDocument(_doc('v2')); // user keeps typing
    repo.holdWrites = false;
    repo.release();
    expect(await first, isTrue);

    expect(controller.isDirty, isTrue,
        reason: 'v2 was not written yet; marking clean would lose it');
    expect(await controller.flush(), isTrue);
    expect(controller.isDirty, isFalse);
    expect(repo.saved.last.title, 'v2');
  });

  test('saves never overlap, however many are requested', () async {
    controller.updateDocument(_doc('v1'));
    repo.holdWrites = true;
    final calls = [for (var i = 0; i < 4; i++) controller.saveImmediately()];
    for (var i = 0; i < 10; i++) {
      await settle();
      repo.release();
    }
    repo.holdWrites = false;
    repo.release();
    await Future.wait(calls);
    expect(repo.maxConcurrent, 1);
    expect(controller.isSaving, isFalse);
  });

  test('a failed save reports false, keeps the edit dirty and the draft',
      () async {
    controller.updateDocument(_doc('v1'));
    repo.failWrites = true;
    expect(await controller.saveImmediately(), isFalse);
    expect(controller.isDirty, isTrue);
    expect(await controller.flush(), isFalse);

    final draft = await CvBuilderController.checkDraftRecovery('cv1', null);
    expect(draft?.title, 'v1', reason: 'crash draft must survive a failure');

    repo.failWrites = false;
    expect(await controller.flush(), isTrue);
    expect(await CvBuilderController.checkDraftRecovery('cv1', null), isNull,
        reason: 'draft cleared once the current state is durable');
  });

  test('flush on a clean document writes nothing', () async {
    expect(await controller.flush(), isTrue);
    expect(repo.saved, isEmpty);
  });

  group('reorderSections', () {
    List<String> order() =>
        controller.document.sectionOrder ?? CvDocument.defaultSectionOrder;

    test('any (old, new) pair keeps the same set of sections', () {
      final n = CvDocument.defaultSectionOrder.length;
      for (var from = -2; from < n + 2; from++) {
        for (var to = -3; to < n + 3; to++) {
          final before = [...order()];
          controller.reorderSections(from, to);
          expect(order().toSet(), before.toSet(), reason: '$from→$to');
          expect(order().length, before.length, reason: '$from→$to');
        }
      }
    });

    test('dropping an item where it was is not an edit', () async {
      await controller.flush();
      final n = order().length;
      for (var i = 0; i < n; i++) {
        controller
          ..reorderSections(i, i)
          ..reorderSections(i, i + 1); // ReorderableListView "same slot"
      }
      expect(controller.isDirty, isFalse);
    });

    test('step up / step down move exactly one place', () {
      final n = order().length;
      for (var i = 1; i < n; i++) {
        final before = [...order()];
        controller.reorderSections(i, i - 1); // "move up"
        expect(order()[i - 1], before[i]);
        expect(order()[i], before[i - 1]);
      }
      for (var i = 0; i < n - 1; i++) {
        final before = [...order()];
        controller.reorderSections(i, i + 2); // "move down"
        expect(order()[i + 1], before[i]);
        expect(order()[i], before[i + 1]);
      }
    });
  });
}
