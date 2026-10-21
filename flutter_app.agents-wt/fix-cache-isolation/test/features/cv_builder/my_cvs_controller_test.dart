import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/features/cv_builder/application/my_cvs_controller.dart';
import 'package:sirati/features/cv_builder/domain/cv_list.dart';
import 'package:sirati/shared/models/generated_cv.dart';
import 'package:sirati/shared/services/cv_api_service.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';
import 'package:sirati/shared/services/mobile_content_service_provider.dart';

class _FakeContent extends MobileContentService {
  final forces = <bool>[];
  List<int> ids = [1, 2, 3];

  @override
  Future<Map<String, dynamic>> myCvs(bool english, {bool force = false}) async {
    forces.add(force);
    return {
      'items': [
        for (final id in ids) {'id': id, 'title': 'CV $id'},
      ],
    };
  }
}

class _FakeCvApi extends CvApiService {
  final deleted = <int>[];
  Object? deleteError;
  Completer<void>? deleteGate;

  Object? getError;
  Map<String, dynamic> cvJson = {};

  @override
  Future<void> deleteGeneratedCv(int id) async {
    deleted.add(id);
    await deleteGate?.future;
    final error = deleteError;
    if (error != null) throw error;
  }

  @override
  Future<GeneratedCv> getGeneratedCv(int id) async {
    final error = getError;
    if (error != null) throw error;
    return GeneratedCv.fromJson({'id': id, ...cvJson});
  }
}

void main() {
  late _FakeContent content;
  late _FakeCvApi api;
  late ProviderContainer container;

  setUp(() {
    content = _FakeContent();
    api = _FakeCvApi();
    container = ProviderContainer(overrides: [
      mobileContentServiceProvider.overrideWithValue(content),
      cvApiServiceProvider.overrideWithValue(api),
    ]);
    container.listen(myCvsProvider(true), (_, __) {});
  });

  tearDown(() => container.dispose());

  Future<CvList> load() => container.read(myCvsProvider(true).future);
  MyCvsController controller() => container.read(myCvsProvider(true).notifier);
  List<int?> shownIds() => container
      .read(myCvsProvider(true))
      .value!
      .items
      .map((i) => i.id)
      .toList();

  test('first load may use cache; later loads bypass it', () async {
    await load();
    final next = container.refresh(myCvsProvider(true).future);
    await next;
    expect(content.forces, [false, true]);
  });

  test('delete removes the row at once and refetches bypassing cache',
      () async {
    await load();
    for (final id in [2, 1, 3]) {
      content.ids = [...content.ids]..remove(id);
      await controller().delete(id);
      expect(shownIds(), isNot(contains(id)), reason: 'right after delete');
      await load();
      expect(shownIds(), content.ids);
    }
    expect(api.deleted, [2, 1, 3]);
    expect(content.forces.skip(1).every((f) => f), isTrue);
  });

  test('a second delete of the same id while in flight is ignored', () async {
    await load();
    api.deleteGate = Completer<void>();
    final first = controller().delete(2);
    final second = controller().delete(2);
    api.deleteGate!.complete();
    await Future.wait([first, second]);
    expect(api.deleted, [2]);

    // Once finished, the id may be deleted again (e.g. after a failure).
    await controller().delete(2);
    expect(api.deleted, [2, 2]);
  });

  test('a failed delete throws and leaves the list untouched', () async {
    await load();
    api.deleteError = const ApiException('nope');
    await expectLater(controller().delete(2), throwsA(isA<ApiException>()));
    expect(shownIds(), [1, 2, 3]);

    // The in-flight guard is released after a failure.
    api.deleteError = null;
    await controller().delete(2);
    expect(api.deleted, [2, 2]);
  });

  group('resolveCvDownloadUrl', () {
    CvListItem item(Map<String, dynamic> json) => CvListItem.fromJson(json);

    test('prefers the fresh link from the CV record', () async {
      api.cvJson = {'pdf_url': 'https://x/fresh.pdf'};
      final url = await resolveCvDownloadUrl(
        api,
        item({'id': 1, 'pdf_url': 'https://x/stored.pdf'}),
        templateSlug: 'modern',
      );
      expect(Uri.parse(url).path, '/fresh.pdf');
      expect(Uri.parse(url).queryParameters['template'], 'modern');
    });

    test('falls back to the stored link on any fetch failure or empty link',
        () async {
      for (final setup in <void Function()>[
        () => api.getError = const ApiException('offline'),
        () => api.getError = StateError('boom'),
        () => api.cvJson = {'pdf_url': 'http://[bad'},
        () => api.cvJson = {},
      ]) {
        api
          ..getError = null
          ..cvJson = {};
        setup();
        final url = await resolveCvDownloadUrl(
          api,
          item({'id': 1, 'template_pdf_url': 'https://x/stored.pdf'}),
          templateSlug: 'modern',
        );
        expect(Uri.parse(url).path, '/stored.pdf');
        expect(Uri.parse(url).queryParameters['template'], 'modern');
      }
    });

    test('empty when there is nothing to open', () async {
      api.cvJson = {};
      expect(await resolveCvDownloadUrl(api, item({'id': 1})), '');
      expect(await resolveCvDownloadUrl(api, item({})), '');
    });
  });
}
