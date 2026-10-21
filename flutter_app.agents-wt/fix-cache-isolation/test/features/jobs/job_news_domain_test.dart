import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/jobs/domain/job_news_feed.dart';
import 'package:sirati/features/jobs/domain/job_news_filters.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';

Map<String, dynamic> _job(int id) => {
      'id': id,
      'language': 'en',
      'title': 'Job $id',
      'body': 'Body $id',
      'category': 'tech',
    };

void main() {
  group('JobNewsFeed featured / latest', () {
    test('every item appears exactly once across featured + latest', () {
      for (var n = 0; n <= 6; n++) {
        final feed = JobNewsFeed.fromPayload(
          {
            'items': [for (var i = 1; i <= n; i++) _job(i)]
          },
          english: true,
        );
        final shown = [
          if (feed.featured != null) feed.featured!.id,
          ...feed.latest.map((j) => j.id),
        ];
        expect(shown, [for (var i = 1; i <= n; i++) i], reason: 'n=$n');
        expect(shown.toSet().length, shown.length,
            reason: 'n=$n: a job was rendered twice');
      }
    });
  });

  group('JobNewsFeed.fromPayload', () {
    test('skips malformed entries instead of rendering blank jobs', () {
      final feed = JobNewsFeed.fromPayload({
        'items': [_job(1), null, 'x', 42, [], _job(2), <String, dynamic>{}],
      }, english: true);
      // The empty map is still a map and parses; non-maps are dropped.
      expect(feed.items.where((j) => j.id != 0).map((j) => j.id), [1, 2]);
      expect(feed.items.length, 3);
    });

    test('non-list items, missing keys and wrong types never throw', () {
      for (final payload in <Map<String, dynamic>>[
        {},
        {'items': 'nope', 'profile_context': 'nope', 'title': 7},
        {'items': null, 'profile_context': null, 'title': null},
      ]) {
        expect(
          () => JobNewsFeed.fromPayload(payload, english: false),
          returnsNormally,
        );
      }
    });

    test('blank titles fall back to the localized default', () {
      for (final title in [null, '', '   ']) {
        expect(
          JobNewsFeed.fromPayload({'title': title}, english: true).title,
          'Job News',
        );
        expect(
          JobNewsFeed.fromPayload({'title': title}, english: false).title,
          'أخبار الوظائف',
        );
      }
      expect(
        JobNewsFeed.fromPayload({'title': 'Custom'}, english: true).title,
        'Custom',
      );
    });

    test('offline flag follows the cache marker', () {
      expect(
        JobNewsFeed.fromPayload(
          {MobileContentService.offlineMetaKey: true},
          english: true,
        ).offline,
        isTrue,
      );
      expect(JobNewsFeed.fromPayload({}, english: true).offline, isFalse);
    });
  });

  group('JobNewsProfileContext', () {
    test('reads the keys the API actually sends', () {
      final p = JobNewsProfileContext.fromJson({
        'has_profile': true,
        'job_title_name': 'Backend Developer',
        'location': 'Riyadh',
      });
      expect(p.hasProfile, isTrue);
      expect(p.careerField, 'Backend Developer');
      expect(p.city, 'Riyadh');
    });

    test('accepts the legacy keys and prefers the API ones', () {
      final legacy = JobNewsProfileContext.fromJson(
          {'career_field': 'HR', 'city': 'Jeddah'});
      expect(legacy.careerField, 'HR');
      expect(legacy.city, 'Jeddah');

      final both = JobNewsProfileContext.fromJson({
        'job_title_name': 'API',
        'career_field': 'Legacy',
        'location': 'Dammam',
        'city': 'Legacy',
      });
      expect(both.careerField, 'API');
      expect(both.city, 'Dammam');
    });

    test('only has_profile == true enables the banner', () {
      for (final raw in [null, false, 'true', 1, 'yes']) {
        expect(
          JobNewsProfileContext.fromJson({'has_profile': raw}).hasProfile,
          isFalse,
          reason: 'raw=$raw',
        );
      }
    });
  });

  group('isHighMatch', () {
    test('is monotonic in the score and false for null', () {
      expect(isHighMatch(null), isFalse);
      for (var s = -10; s < 120; s++) {
        if (isHighMatch(s)) {
          expect(isHighMatch(s + 1), isTrue, reason: 'score ${s + 1}');
        }
      }
      expect(isHighMatch(highMatchThreshold), isTrue);
      expect(isHighMatch(highMatchThreshold - 1), isFalse);
    });
  });

  group('JobNewsFilters', () {
    const values = ['all', 'remote', 'matched', 'tech', 'riyadh'];

    test('"all" is never sent to the API; other values pass through', () {
      for (final v in values) {
        final f = JobNewsFilters(preset: v, category: v, city: v);
        final expected = v == JobNewsFilters.all ? null : v;
        expect(f.presetParam, expected);
        expect(f.categoryParam, expected);
        expect(f.cityParam, expected);
      }
    });

    test('isRemote is true exactly when preset or city is remote', () {
      for (final preset in values) {
        for (final city in values) {
          final f = JobNewsFilters(preset: preset, city: city);
          expect(f.isRemote, preset == 'remote' || city == 'remote',
              reason: 'preset=$preset city=$city');
        }
      }
    });

    test('equality is by value and copyWith changes only what it names', () {
      const base = JobNewsFilters(preset: 'matched', city: 'riyadh');
      expect(base, const JobNewsFilters(preset: 'matched', city: 'riyadh'));
      expect(base.hashCode,
          const JobNewsFilters(preset: 'matched', city: 'riyadh').hashCode);
      final changed = base.copyWith(category: 'tech');
      expect(changed.preset, 'matched');
      expect(changed.city, 'riyadh');
      expect(changed.category, 'tech');
      expect(changed, isNot(base));
    });
  });
}
