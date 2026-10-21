import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/cv_builder/domain/cv_list.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';

void main() {
  group('CvList.fromPayload', () {
    test('only object rows become cards, in order', () {
      final list = CvList.fromPayload({
        'items': [
          {'id': 1},
          null,
          'x',
          7,
          [],
          {'id': 2},
          {'id': 3},
        ],
      }, english: true);
      expect(list.items.map((i) => i.id), [1, 2, 3]);
    });

    test('malformed payloads yield an empty list, never throw', () {
      for (final payload in <Map<String, dynamic>>[
        {},
        {'items': null},
        {'items': 'nope'},
        {
          'items': {'id': 1}
        },
      ]) {
        expect(CvList.fromPayload(payload, english: true).items, isEmpty);
      }
    });

    test('blank titles fall back to the localized default', () {
      for (final raw in [null, '', '   ', 5]) {
        final title = raw == 5 ? '5' : null;
        expect(
          CvList.fromPayload({'title': raw}, english: true).title,
          title ?? 'My CVs',
        );
        expect(
          CvList.fromPayload({'title': raw}, english: false).title,
          title ?? 'سيرتي الذاتية',
        );
      }
    });

    test('offline follows the cache marker', () {
      expect(
        CvList.fromPayload(
          {MobileContentService.offlineMetaKey: true},
          english: true,
        ).offline,
        isTrue,
      );
      expect(CvList.fromPayload({}, english: true).offline, isFalse);
    });

    test('without(id) removes exactly the matching rows', () {
      final list = CvList.fromPayload({
        'items': [
          for (final id in [1, 2, 3, 2]) {'id': id},
        ],
      }, english: true);
      for (final id in [1, 2, 3, 99]) {
        final after = list.without(id);
        expect(after.items.every((i) => i.id != id), isTrue);
        expect(
          after.items.length,
          list.items.where((i) => i.id != id).length,
        );
      }
    });
  });

  group('CvListItem.fromJson', () {
    test('ids must be positive integers', () {
      const cases = <(Object?, int?)>[
        (1, 1),
        ('42', 42),
        (' 7 ', 7),
        (0, null),
        (-1, null),
        ('abc', null),
        (1.5, null),
        (null, null),
      ];
      for (final (raw, expected) in cases) {
        expect(CvListItem.fromJson({'id': raw}).id, expected, reason: '$raw');
      }
    });

    test('flags accept JSON and 0/1 forms, otherwise use the default', () {
      const cases = <(Object?, bool?)>[
        (true, true),
        (false, false),
        (1, true),
        (0, false),
        ('1', true),
        ('0', false),
        ('true', true),
        ('false', false),
        (null, null),
        ('yes', null),
      ];
      for (final (raw, expected) in cases) {
        final item =
            CvListItem.fromJson({'is_draft': raw, 'can_download': raw});
        expect(item.isDraft, expected ?? false, reason: 'is_draft=$raw');
        expect(item.canDownload, expected ?? true, reason: 'can_download=$raw');
      }
    });

    test('updated time falls back to created time, then the label', () {
      final both = CvListItem.fromJson({
        'updated_at': '2026-01-02T00:00:00Z',
        'created_at': '2025-01-01T00:00:00Z',
      });
      expect(both.updatedAt, DateTime.utc(2026, 1, 2));

      final badUpdated = CvListItem.fromJson({
        'updated_at': 'garbage',
        'created_at': '2025-01-01T00:00:00Z',
      });
      expect(badUpdated.updatedAt, DateTime.utc(2025));

      final none = CvListItem.fromJson({'updated_label': ' 3 days ago '});
      expect(none.updatedAt, isNull);
      expect(none.updatedLabel, '3 days ago');
    });

    test('stored link prefers the template PDF', () {
      expect(
        CvListItem.fromJson({'pdf_url': 'a', 'template_pdf_url': 'b'})
            .storedPdfUrl,
        'b',
      );
      expect(CvListItem.fromJson({'pdf_url': 'a'}).storedPdfUrl, 'a');
      expect(CvListItem.fromJson({}).storedPdfUrl, '');
    });

    test('template choice is offered when there is anything to re-render', () {
      expect(CvListItem.fromJson({'id': 1}).offersTemplateChoice, isTrue);
      expect(
        CvListItem.fromJson({'template_pdf_url': 'x'}).offersTemplateChoice,
        isTrue,
      );
      expect(
        CvListItem.fromJson({'pdf_url': 'x'}).offersTemplateChoice,
        isFalse,
      );
    });
  });

  group('withTemplateParam', () {
    const urls = [
      'https://api.sirati.app/cv/1.pdf',
      'https://api.sirati.app/cv/1.pdf?token=abc&lang=ar',
      'https://api.sirati.app/cv/1.pdf?template=old',
    ];

    test('sets template and preserves every other query parameter', () {
      for (final url in urls) {
        for (final slug in ['modern', 'classic-2']) {
          final out = Uri.parse(withTemplateParam(url, slug));
          final original = Uri.parse(url);
          expect(out.queryParameters['template'], slug);
          for (final MapEntry(:key, :value)
              in original.queryParameters.entries) {
            if (key != 'template') expect(out.queryParameters[key], value);
          }
          expect(out.path, original.path);
          expect(out.host, original.host);
        }
      }
    });

    test('no slug, empty url, or unparsable url leave the url unchanged', () {
      for (final url in [...urls, '', 'http://[bad']) {
        expect(withTemplateParam(url, null), url);
        expect(withTemplateParam(url, ''), url);
      }
      expect(withTemplateParam('', 'modern'), '');
      expect(withTemplateParam('http://[bad', 'modern'), 'http://[bad');
    });
  });
}
