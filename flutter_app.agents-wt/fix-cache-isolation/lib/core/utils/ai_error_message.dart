/// Maps machine-readable AI error codes onto bilingual user copy.
///
/// Backend [AiTruncationException] prefixes the stored `ai_error` with
/// [tooLongCode] so every surface can name CV length as the cause in the
/// active language, instead of interpolating the raw exception string.
class AiErrorMessage {
  static const tooLongCode = 'cv_too_long';

  static bool isTooLong(String? error) =>
      error != null && error.startsWith(tooLongCode);

  static String describe(String? error, {required bool english}) {
    if (isTooLong(error)) {
      return english
          ? 'The CV is too long to generate in one pass. Shorten the experience, education, or skills text and try again.'
          : 'السيرة أطول من أن تُولَّد في تمريرة واحدة. اختصر نص الخبرات أو التعليم أو المهارات ثم أعد المحاولة.';
    }
    if (error == null || error.trim().isEmpty) {
      return english ? 'Please try again.' : 'يرجى المحاولة مرة أخرى.';
    }
    return error;
  }

  static String generationFailed(String? error, {required bool english}) {
    if (isTooLong(error)) {
      return describe(error, english: english);
    }
    final detail = describe(error, english: english);
    return english
        ? 'AI generation could not be completed: $detail'
        : 'تعذر إكمال توليد السيرة بالذكاء الاصطناعي: $detail';
  }

  static String suggestionsFailed(String? error, {required bool english}) {
    if (isTooLong(error)) {
      return describe(error, english: english);
    }
    final detail = describe(error, english: english);
    return english
        ? 'AI suggestions could not be completed: $detail'
        : 'تعذر إكمال توصيات الذكاء الاصطناعي: $detail';
  }

  static String localFallback(String? error, {required bool english}) {
    if (isTooLong(error)) {
      return describe(error, english: english);
    }
    final detail = describe(error, english: english);
    return english
        ? 'A local version was created because AI generation did not complete: $detail'
        : 'تم إنشاء نسخة محلية لأن الذكاء الاصطناعي لم يكتمل: $detail';
  }
}
