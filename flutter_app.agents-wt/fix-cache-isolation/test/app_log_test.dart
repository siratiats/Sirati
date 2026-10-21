import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/logging/app_log.dart';

void main() {
  tearDown(AppLog.resetForTest);

  test('redacts emails and phone numbers in free text', () {
    final out = AppLog.redact(
      'Contact nora@example.com or +966 50 123 4567 please',
    );
    expect(out, isNot(contains('nora@example.com')));
    expect(out, contains('[email]'));
    expect(out, contains('[phone]'));
    expect(out, isNot(contains('501234567')));
  });

  test('filters CV and identity keys', () {
    final out = AppLog.redactMap({
      'resume_text': 'Senior engineer at ACME, email hidden@x.com',
      'full_name': 'Nora Example',
      'email': 'nora@example.com',
      'phone': '+966501234567',
      'generated_markdown': '# CV\nsecret',
      'template': 'ats-classic-professional',
      'cv_id': 12,
    });

    expect(out['resume_text'], '[Filtered]');
    expect(out['full_name'], '[Filtered]');
    expect(out['email'], '[Filtered]');
    expect(out['phone'], '[Filtered]');
    expect(out['generated_markdown'], '[Filtered]');
    expect(out['template'], 'ats-classic-professional');
    expect(out['cv_id'], 12);
  });

  test('PII key matching is case-insensitive for arbitrary key spellings', () {
    final out = AppLog.redactMap({
      'Resume_Text': 'hidden career history',
      'EMAIL': 'person@example.com',
      'Full_Name': 'Hidden Person',
      'cv_id': 'keep-me',
    });
    expect(out['Resume_Text'], '[Filtered]');
    expect(out['EMAIL'], '[Filtered]');
    expect(out['Full_Name'], '[Filtered]');
    expect(out['cv_id'], 'keep-me');
  });

  test('redacts emails nested in non-PII string fields', () {
    final out = AppLog.redactMap({
      'note': 'Wrote to person@sirati.app yesterday',
    });
    expect(out['note'], contains('[email]'));
    expect(out['note'], isNot(contains('person@sirati.app')));
  });

  test('omits oversized blobs so CV bodies cannot leak via truncation prefix',
      () {
    const marker = 'Lead backend engineer at Qiddiya Investment Company';
    final blob = '$marker. ${'implemented ATS parsers. ' * 40}';
    expect(blob.length, greaterThan(280));
    final out = AppLog.redact(blob);
    expect(out, isNot(contains('Qiddiya')));
    expect(out, isNot(contains('Lead backend')));
    expect(out, isNot(contains(blob.substring(0, 40))));
    expect(out, startsWith('[omitted '));
    expect(out.length, lessThan(blob.length));
  });

  test('user-facing copy is localized and non-technical', () {
    expect(AppLog.userMessage(english: true), isNot(contains('Exception')));
    expect(AppLog.userMessage(english: true), isNot(contains('null')));
    expect(AppLog.userMessage(english: false), contains('خطأ'));
  });

  test(
      'preserves valid diagnostic strings and bullets without prose guessing corruption',
      () {
    const diagnostic1 = '- retrying request 3 of 5';
    const diagnostic2 = 'summary: upload failed with 502';
    expect(AppLog.redact(diagnostic1), diagnostic1);
    expect(AppLog.redact(diagnostic2), diagnostic2);
  });

  test('does not falsely redact 10-digit epoch timestamps or entity IDs', () {
    const logWithTimestamp =
        'request at epoch 1788532429 with cv_id 1788532429';
    final out = AppLog.redact(logWithTimestamp);
    expect(out, contains('1788532429'));
    expect(out, isNot(contains('[id]')));
  });

  test('redacts embedded JSON CV and personal fields in exception strings', () {
    const rawException =
        'FormatException: {"email": "user@domain.com", "full_name": "Ahmad Ali", "phone": "0501234567"}';
    final out = AppLog.redact(rawException);
    expect(out, isNot(contains('Ahmad Ali')));
    expect(out, isNot(contains('user@domain.com')));
    expect(out, contains('[Filtered]'));
  });

  test('redacts labelled Saudi National ID and Iqama numbers', () {
    final out = AppLog.redact(
        'National ID: 1012345678 or Iqama: 2012345678 or هوية: 1012345678');
    expect(out, isNot(contains('1012345678')));
    expect(out, isNot(contains('2012345678')));
    expect(out, contains('National ID: [id]'));
    expect(out, contains('Iqama: [id]'));
    expect(out, contains('هوية: [id]'));
  });

  test(
      'does not falsely redact date sequences, timing diagnostics, or matrix coordinates (R3)',
      () {
    const buildLog = 'build 2026 09 04 ok';
    const timingLog = 'took 120 - 240 - 360 ms';
    const matrixLog = 'matrix 10 20 30';

    expect(AppLog.redact(buildLog), buildLog);
    expect(AppLog.redact(timingLog), timingLog);
    expect(AppLog.redact(matrixLog), matrixLog);
  });

  test('redacts international, Saudi mobile, and labelled phone numbers (R3)',
      () {
    expect(AppLog.redact('Call +966 50 123 4567'), contains('[phone]'));
    expect(AppLog.redact('Mobile 0501234567'), contains('[phone]'));
    expect(AppLog.redact('Riyadh office 011 234 5678'), contains('[phone]'));
    expect(AppLog.redact('phone: (555) 123-4567'), 'phone: [phone]');
    expect(AppLog.redact('جوال: 0551234567'), 'جوال: [phone]');
  });

  test(
      'sanitizeError is idempotent and preserves custom exception type across multiple passes (R4)',
      () {
    final originalError =
        _CustomApiException('secret email user@sirati.app in payload');
    final pass1 = AppLog.sanitizeError(originalError);

    expect(pass1, isA<SanitizedAppException>());
    final sanitized1 = pass1 as SanitizedAppException;
    expect(sanitized1.errorType, '_CustomApiException');
    expect(sanitized1.message, isNot(contains('user@sirati.app')));
    expect(sanitized1.message, contains('[email]'));

    final pass2 = AppLog.sanitizeError(pass1);
    expect(pass2, isA<SanitizedAppException>());
    final sanitized2 = pass2 as SanitizedAppException;
    expect(sanitized2.errorType, '_CustomApiException');
    expect(sanitized2.toString(), startsWith('_CustomApiException:'));
  });

  test(
      'AppLogEvent structured event emission routes dynamic payload via redactMap (M2)',
      () {
    expect(AppLogEvent.cvAutosaved.eventId, 'cv_autosaved');
    expect(AppLogEvent.cvParseFailed.eventId, 'cv_parse_failed');

    final payload = <String, Object?>{
      'cv_id': 'uuid-12345',
      'resume_text': 'Detailed career history of John Doe',
      'summary_input': 'Experienced software architect',
      'full_name': 'John Doe',
      'phone': '+966501234567',
      'metadata': {
        'candidate_email': 'candidate@sirati.app',
        'sections_count': 5,
      },
      'experience': [
        {
          'title': 'ok-title',
          'email': 'nested@x.com',
          'resume_text': 'should vanish',
        },
      ],
    };

    final sanitized = AppLog.redactMap(payload);
    expect(sanitized['cv_id'], 'uuid-12345');
    expect(sanitized['resume_text'], '[Filtered]');
    expect(sanitized['summary_input'], '[Filtered]');
    expect(sanitized['full_name'], '[Filtered]');
    expect(sanitized['phone'], '[Filtered]');

    final meta = sanitized['metadata'] as Map<String, Object?>;
    expect(meta['candidate_email'], contains('[email]'));
    expect(meta['candidate_email'], isNot(contains('candidate@sirati.app')));
    expect(meta['sections_count'], 5);

    final experience = sanitized['experience'] as List;
    final first = experience.first as Map<String, Object?>;
    expect(first['title'], 'ok-title');
    expect(first['resume_text'], '[Filtered]');
    expect(first['email'], '[Filtered]');

    expect(
      () => AppLog.event(
        AppLogEvent.cvAutosaved,
        data: payload,
      ),
      returnsNormally,
    );
  });

  test('release policy emits only event ids and never free-text CV bodies (M2)',
      () {
    AppLog.treatAsRelease = true;
    final lines = <String>[];
    AppLog.printOverride = (message, {wrapWidth}) {
      if (message != null) lines.add(message);
    };

    const resume =
        'Curriculum vitae: 8 years leading payments at STC Pay, Laravel, SQL, iqama 1012345678';
    AppLog.info(resume);
    AppLog.event(
      AppLogEvent.cvAutosaved,
      data: {
        'cv_id': 'doc-99',
        'resume_text': resume,
        'full_name': 'Maha Al-Qahtani',
      },
    );

    final joined = lines.join('\n');
    expect(joined, isNot(contains('STC Pay')));
    expect(joined, isNot(contains('Curriculum')));
    expect(joined, isNot(contains('Maha')));
    expect(joined, isNot(contains('1012345678')));
    expect(joined, isNot(contains('Laravel')));
    expect(joined, contains('diagnostic'));
    expect(joined, contains('cv_autosaved'));
    expect(joined, contains('[Filtered]'));
    expect(joined, contains('doc-99'));
  });
}

class _CustomApiException implements Exception {
  final String message;
  _CustomApiException(this.message);
  @override
  String toString() => '_CustomApiException: $message';
}
