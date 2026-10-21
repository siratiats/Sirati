import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/ai_error_message.dart';

void main() {
  test('truncation code names CV length in English and Arabic', () {
    const error =
        'cv_too_long: The CV is too long to generate in one pass. / السيرة أطول';

    expect(AiErrorMessage.isTooLong(error), isTrue);
    expect(
      AiErrorMessage.generationFailed(error, english: true),
      contains('too long to generate in one pass'),
    );
    expect(
      AiErrorMessage.generationFailed(error, english: false),
      contains('أطول من أن تُولَّد'),
    );
    expect(
      AiErrorMessage.generationFailed(error, english: true),
      isNot(contains('cv_too_long')),
    );
  });

  test('unknown errors keep the server detail and a localized wrapper', () {
    expect(
      AiErrorMessage.generationFailed('provider down', english: true),
      'AI generation could not be completed: provider down',
    );
    expect(
      AiErrorMessage.generationFailed(null, english: false),
      'تعذر إكمال توليد السيرة بالذكاء الاصطناعي: يرجى المحاولة مرة أخرى.',
    );
  });
}
