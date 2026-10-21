import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/features/jobs/application/job_news_controllers.dart';
import 'package:sirati/features/jobs/domain/job_news_feed.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';
import 'package:sirati/shared/services/mobile_content_service_provider.dart';

class _Call {
  _Call({
    required this.english,
    required this.category,
    required this.preset,
    required this.city,
    required this.isRemote,
    required this.query,
    required this.force,
  });

  final bool english;
  final String? category;
  final String? preset;
  final String? city;
  final bool? isRemote;
  final String? query;
  final bool force;
}

class _FakeContent extends MobileContentService {
  final calls = <_Call>[];
  Object? failWith;

  @override
  Future<Map<String, dynamic>> jobNews(
    bool english, {
    String? category,
    String? preset,
    String? city,
    int? jobTitleId,
    bool? isRemote,
    bool? isVerified,
    String? sourceType,
    String? employmentType,
    String? query,
    bool force = false,
  }) async {
    calls.add(_Call(
      english: english,
      category: category,
      preset: preset,
      city: city,
      isRemote: isRemote,
      query: query,
      force: force,
    ));
    final error = failWith;
    if (error != null) throw error;
    return {
      'title': 'Jobs',
      'items': [
        {'id': calls.length, 'title': 'Job', 'body': 'b', 'category': 'tech'},
      ],
    };
  }
}

void main() {
  late _FakeContent content;
  late ProviderContainer container;

  setUp(() {
    content = _FakeContent();
    container = ProviderContainer(
      overrides: [mobileContentServiceProvider.overrideWithValue(content)],
    );
    container.listen(jobNewsFiltersProvider, (_, __) {});
  });

  tearDown(() => container.dispose());

  // Subscribing lazily lets each test arrange the fake before the first load.
  var subscribed = false;
  setUp(() => subscribed = false);
  Future<JobNewsFeed> feed() {
    if (!subscribed) {
      container.listen(jobNewsFeedProvider(true), (_, __) {});
      subscribed = true;
    }
    return container.read(jobNewsFeedProvider(true).future);
  }

  JobNewsFiltersController filters() =>
      container.read(jobNewsFiltersProvider.notifier);

  test('first load may use cache; every later load bypasses it', () async {
    await feed();
    expect(content.calls.single.force, isFalse);

    filters().setCategory('tech');
    await feed();
    filters().setPreset('matched');
    await feed();
    final next = container.refresh(jobNewsFeedProvider(true).future);
    await next;

    expect(content.calls.length, 4);
    expect(content.calls.skip(1).every((c) => c.force), isTrue);
  });

  test('filters reach the API with "all" omitted and remote derived', () async {
    await feed();
    var call = content.calls.last;
    expect([call.category, call.preset, call.city], [null, null, null]);
    expect(call.isRemote, isFalse);

    filters()
      ..setCity('remote')
      ..setCategory('health')
      ..setQuery('  nurse  ');
    await feed();
    call = content.calls.last;
    expect(call.city, 'remote');
    expect(call.category, 'health');
    expect(call.isRemote, isTrue);
    expect(call.query, 'nurse', reason: 'query is trimmed');
  });

  test('toggleMatched flips between matched and all', () {
    expect(container.read(jobNewsFiltersProvider).preset, 'all');
    filters().toggleMatched();
    expect(container.read(jobNewsFiltersProvider).preset, 'matched');
    filters().toggleMatched();
    expect(container.read(jobNewsFiltersProvider).preset, 'all');
  });

  test('a failure surfaces as an error and is not auto-retried', () async {
    content.failWith = const ApiException('offline');
    await expectLater(feed(), throwsA(isA<ApiException>()));
    await Future<void>.delayed(const Duration(milliseconds: 50));

    expect(container.read(jobNewsFeedProvider(true)).hasError, isTrue);
    expect(content.calls.length, 1,
        reason: 'the screen offers Retry; no silent background retries');
  });
}
