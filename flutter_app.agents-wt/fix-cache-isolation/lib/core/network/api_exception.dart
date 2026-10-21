/// Categorises an [ApiException] so UI layers can pick appropriate
/// copy, iconography, and retry affordances without inspecting status codes
/// or message strings directly.
enum ApiErrorType {
  /// Connectivity issue (offline, DNS, socket) — always retryable.
  network,

  /// Request timed out — retryable.
  timeout,

  /// 401 unauthorized (invalid/expired token) or 403 forbidden.
  /// Session logout is driven by HTTP 401 only — see [ApiClient._send].
  auth,

  /// 422 — validation failed. Use [ApiException.errors] for field-level info.
  validation,

  /// 429 — a server-enforced request quota has been reached.
  rateLimited,

  /// 404 — resource missing.
  notFound,

  /// 5xx — server failure — retryable.
  server,

  /// Anything else (4xx that doesn't fit above, malformed response, etc.).
  unknown,
}

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final Map<String, List<String>> errors;
  final ApiErrorType type;

  const ApiException(
    this.message, {
    this.statusCode,
    this.errors = const {},
    this.type = ApiErrorType.unknown,
  });

  /// Builds an exception from an HTTP response, inferring [type] from
  /// [statusCode]. Keeps the previous [ApiException] shape intact so
  /// existing screens keep working.
  factory ApiException.fromStatus(
    int statusCode, {
    required String message,
    Map<String, List<String>> errors = const {},
  }) {
    return ApiException(
      message,
      statusCode: statusCode,
      errors: errors,
      type: _typeFromStatus(statusCode),
    );
  }

  /// Network / connectivity failure.
  const ApiException.network(String message)
      : this(message, type: ApiErrorType.network);

  /// Request timed out.
  const ApiException.timeout(String message)
      : this(message, type: ApiErrorType.timeout);

  static ApiErrorType _typeFromStatus(int status) {
    if (status == 401 || status == 403) return ApiErrorType.auth;
    if (status == 404) return ApiErrorType.notFound;
    if (status == 422) return ApiErrorType.validation;
    if (status == 429) return ApiErrorType.rateLimited;
    if (status >= 500 && status < 600) return ApiErrorType.server;
    return ApiErrorType.unknown;
  }

  /// True when the operation is safe to retry without user intervention.
  bool get isRetryable {
    switch (type) {
      case ApiErrorType.network:
      case ApiErrorType.timeout:
      case ApiErrorType.server:
        return true;
      case ApiErrorType.auth:
      case ApiErrorType.validation:
      case ApiErrorType.rateLimited:
      case ApiErrorType.notFound:
      case ApiErrorType.unknown:
        return false;
    }
  }

  String get displayMessage {
    if (errors.isEmpty) return message;

    final firstMessages = errors.values.where((items) => items.isNotEmpty);
    if (firstMessages.isEmpty) return message;

    return firstMessages.first.first;
  }

  @override
  String toString() => displayMessage;
}
