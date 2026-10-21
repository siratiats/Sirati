/// Limits for `POST /cv-analyses`, kept in line with
/// `CvAnalysisController` so users see them before the server rejects.
abstract final class AnalysisLimits {
  static const jobTitleMax = 160;
  static const jobDescriptionMax = 4000;

  /// Server rule `max:5120` (kilobytes).
  static const maxUploadBytes = 5 * 1024 * 1024;
  static const allowedExtensions = ['pdf', 'txt'];
}

enum AnalysisFieldError { required, tooLong }

enum UploadRejection { tooLarge, unsupportedType }

abstract final class AnalysisRules {
  static AnalysisFieldError? jobTitle(String? value) {
    final text = value?.trim() ?? '';
    if (text.isEmpty) return AnalysisFieldError.required;
    return text.length > AnalysisLimits.jobTitleMax
        ? AnalysisFieldError.tooLong
        : null;
  }

  /// Optional; measured trimmed because the client trims before sending.
  static AnalysisFieldError? jobDescription(String? value) =>
      (value?.trim().length ?? 0) > AnalysisLimits.jobDescriptionMax
          ? AnalysisFieldError.tooLong
          : null;

  /// A picked file the server would reject, or null when acceptable.
  static UploadRejection? upload({required String name, required int size}) {
    final dot = name.lastIndexOf('.');
    final ext = dot < 0 ? '' : name.substring(dot + 1).toLowerCase();
    if (!AnalysisLimits.allowedExtensions.contains(ext)) {
      return UploadRejection.unsupportedType;
    }
    return size > AnalysisLimits.maxUploadBytes || size < 0
        ? UploadRejection.tooLarge
        : null;
  }
}

/// What the user is about to submit. Its [fingerprint] identifies the
/// request for idempotency: equal fingerprints mean "the same analysis".
class AnalysisDraft {
  const AnalysisDraft({
    required this.jobTitle,
    required this.jobDescription,
    required this.resumeText,
    this.fileName,
    this.fileSize,
  });

  final String jobTitle;
  final String jobDescription;
  final String resumeText;
  final String? fileName;
  final int? fileSize;

  bool get hasFile => fileName != null;

  /// A file or pasted text; one of them is required.
  bool get hasResume => hasFile || resumeText.trim().isNotEmpty;

  String get fingerprint => [
        jobTitle.trim(),
        jobDescription.trim(),
        resumeText.trim(),
        fileName ?? '',
        fileSize?.toString() ?? '',
      ].join('\u0000');
}

/// Hands out idempotency keys so a retry of the *same* draft reuses its key
/// (the server de-duplicates it) while an edited draft gets a fresh one
/// (otherwise the server would replay the old analysis for new inputs).
class IdempotencyKeyRing {
  IdempotencyKeyRing(this._newKey);

  final String Function() _newKey;
  String? _fingerprint;
  String? _key;

  String keyFor(AnalysisDraft draft) {
    final fingerprint = draft.fingerprint;
    if (_key == null || fingerprint != _fingerprint) {
      _fingerprint = fingerprint;
      _key = _newKey();
    }
    return _key!;
  }

  /// The request completed; the next submit is a new analysis.
  void consume() {
    _fingerprint = null;
    _key = null;
  }
}
