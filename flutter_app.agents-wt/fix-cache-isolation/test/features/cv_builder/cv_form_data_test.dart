import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/cv_builder/domain/cv_form_data.dart';

/// Field setters by name so invariants can be checked for every field
/// instead of for hand-picked examples.
const _fieldNames = [
  'fullName',
  'email',
  'phone',
  'linkedin',
  'location',
  'targetJobTitle',
  'jobDescription',
  'summary',
  'skills',
  'experience',
  'education',
  'certifications',
];

CvFormData _withField(String name, String value, {String language = 'ar'}) =>
    switch (name) {
      'fullName' => CvFormData(fullName: value, language: language),
      'email' => CvFormData(email: value, language: language),
      'phone' => CvFormData(phone: value, language: language),
      'linkedin' => CvFormData(linkedin: value, language: language),
      'location' => CvFormData(location: value, language: language),
      'targetJobTitle' => CvFormData(targetJobTitle: value, language: language),
      'jobDescription' => CvFormData(jobDescription: value, language: language),
      'summary' => CvFormData(summary: value, language: language),
      'skills' => CvFormData(skills: value, language: language),
      'experience' => CvFormData(experience: value, language: language),
      'education' => CvFormData(education: value, language: language),
      'certifications' => CvFormData(certifications: value, language: language),
      _ => throw ArgumentError(name),
    };

const _samples = [
  'Salem Sayer',
  'سالم سيار',
  '  padded  ',
  'Line one\nLine two\n- bullet',
  'Laravel، PHP، REST APIs',
  'مطوّر Laravel Backend — 2022 حتى الآن',
  '😀 emoji and ‏ RLM mark',
];

const _blanks = ['', ' ', '\n', '\t \n  '];

void main() {
  final savedAt = DateTime.utc(2026, 9, 22, 10, 30);

  group('draft round-trip', () {
    test('every field survives toDraftJson -> fromDraftJson unchanged', () {
      for (final name in _fieldNames) {
        for (final value in [..._samples, ..._blanks]) {
          for (final lang in ['ar', 'en']) {
            final data = _withField(name, value, language: lang);
            final json = data.toDraftJson(step: 2, savedAt: savedAt);
            final back = CvFormData.fromDraftJson(json);
            expect(
              back.toDraftJson(step: 2, savedAt: savedAt),
              json,
              reason: '$name=${value.codeUnits} lang=$lang',
            );
          }
        }
      }
    });

    test('corrupted drafts degrade to an empty form instead of throwing', () {
      final corrupted = <Map<String, dynamic>>[
        {},
        {'full_name': null, 'email': 42, 'step': 'x', 'language': 7},
        {
          'skills_input': ['a', 'b'],
          'education_input': {'k': 'v'}
        },
      ];
      for (final json in corrupted) {
        expect(() => CvFormData.fromDraftJson(json), returnsNormally);
        expect(
          () => CvFormData.stepFromDraftJson(json, stepCount: 4),
          returnsNormally,
        );
      }
    });
  });

  group('language', () {
    test('anything other than "en" normalizes to "ar"', () {
      for (final raw in ['ar', 'AR', 'En', '', 'fr', 'english', null, 0]) {
        final data = CvFormData.fromDraftJson({'language': raw});
        expect(data.language, 'ar', reason: 'raw=$raw');
      }
      expect(CvFormData.fromDraftJson({'language': 'en'}).language, 'en');
    });
  });

  group('stepFromDraftJson', () {
    test('always lands inside 0..stepCount-1', () {
      final raws = <Object?>[
        -100,
        -1,
        0,
        1,
        2,
        3,
        4,
        99,
        '2',
        '-5',
        '999',
        'abc',
        null,
        2.7,
      ];
      for (var count = 1; count <= 6; count++) {
        for (final raw in raws) {
          final step =
              CvFormData.stepFromDraftJson({'step': raw}, stepCount: count);
          expect(step, inInclusiveRange(0, count - 1),
              reason: 'raw=$raw count=$count');
        }
      }
    });
  });

  group('hasContent', () {
    test('false when every field is blank, regardless of language', () {
      for (final blank in _blanks) {
        for (final name in _fieldNames) {
          expect(_withField(name, blank).hasContent, isFalse);
          expect(_withField(name, blank, language: 'en').hasContent, isFalse);
        }
      }
      expect(const CvFormData(language: 'en').hasContent, isFalse);
    });

    test('true when any single field has real text', () {
      for (final name in _fieldNames) {
        for (final value in _samples) {
          expect(_withField(name, value).hasContent, isTrue,
              reason: '$name=$value');
        }
      }
    });
  });

  group('toPayload', () {
    const optional = {
      'email': 'email',
      'phone': 'phone',
      'linkedin': 'linkedin',
      'location': 'location',
      'jobDescription': 'job_description_input',
      'summary': 'summary_input',
      'certifications': 'certifications_input',
    };
    const required = {
      'fullName': 'full_name',
      'targetJobTitle': 'target_job_title',
      'skills': 'skills_input',
      'experience': 'experience_input',
      'education': 'education_input',
    };

    test('blank optional fields are sent as null, never as whitespace', () {
      for (final entry in optional.entries) {
        for (final blank in _blanks) {
          final payload = _withField(entry.key, blank).toPayload();
          expect(payload[entry.value], isNull, reason: entry.key);
        }
      }
    });

    test('every text value is trimmed and otherwise unchanged', () {
      for (final entry in {...optional, ...required}.entries) {
        for (final value in _samples) {
          final payload = _withField(entry.key, value).toPayload();
          expect(payload[entry.value], value.trim(), reason: entry.key);
        }
      }
    });

    test('carries the language and exactly the API keys', () {
      final payload = const CvFormData(language: 'en').toPayload();
      expect(payload['language'], 'en');
      expect(payload.keys.toSet(), {
        ...optional.values,
        ...required.values,
        'language',
      });
    });
  });
}
