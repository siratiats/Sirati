import 'dart:async';

import 'package:flutter/foundation.dart';

import 'package:sirati/core/logging/app_log_event.dart';

export 'package:sirati/core/logging/app_log_event.dart';

/// Structured logger (SIRATI-16, Sprint 1 M2).
///
/// Debug lines are dropped in release. CV / personal fields are redacted
/// so PDPL-sensitive content never hits logcat, Crashlytics, or Sentry
/// breadcrumbs via this API.
///
/// **M2 contract:** the log *message* is an [AppLogEvent] token. Dynamic
/// values go through [data], which is key-filtered. In release, free-text
/// messages are replaced with [AppLogEvent.diagnostic] and never emitted.
enum AppLogLevel { debug, info, warn, error }

class AppLog {
  AppLog._();

  static const _piiKeys = {
    'resume_text',
    'experience_input',
    'education_input',
    'summary_input',
    'skills_input',
    'certifications_input',
    'draft',
    'full_name',
    'fullname',
    'first_name',
    'last_name',
    'name',
    'email',
    'phone',
    'linkedin',
    'location',
    'generated_markdown',
    'markdown',
    'content',
    'resume',
    'cv',
    'body',
    'bio',
    'password',
    'token',
    'authorization',
    'iqama',
    'national_id',
  };

  static const _maxMessageChars = 280;

  static final _knownEventIds = {
    for (final event in AppLogEvent.values) event.eventId,
  };

  static final _email =
      RegExp(r'[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}', caseSensitive: false);
  static final _phone = RegExp(
    r'(?:\+\d{1,4}[\d\s\-().]{6,}\d|\b0[15]\d[\d\s\-().]{6,}\d)',
  );
  static final _labelledPhone = RegExp(
    r'((?:^|[\s,;:({\[])(?:phone|tel|mobile|cell|contact|جوال|هاتف)\s*[:=-]?\s*)([+(\d][\d\s\-().]{5,}\d)',
    caseSensitive: false,
  );
  static final _nationalId = RegExp(
    r'((?:^|[\s,;:({\[])(?:national[_\-\s]?id|iqama|identity[_\-\s]?(?:no|num|number)?|هوية|الإقامة|الهوية)[^\d]{0,10})([12]\d{9})\b',
    caseSensitive: false,
  );
  static final _jsonPiiField = RegExp(
    r'("?(?:resume_text|experience_input|education_input|summary_input|skills_input|certifications_input|full_name|email|phone|linkedin|location|generated_markdown|resume|cv|body|bio)"?\s*[:=]\s*)"[^"]*"',
    caseSensitive: false,
  );

  /// When true, emission follows the release policy even in debug tests.
  @visibleForTesting
  static bool treatAsRelease = false;

  /// Override [debugPrint] in tests. `null` uses the real [debugPrint].
  @visibleForTesting
  static void Function(String? message, {int? wrapWidth})? printOverride;

  static bool get _useReleasePolicy => kReleaseMode || treatAsRelease;

  static void _print(String line) {
    final sink = printOverride;
    if (sink != null) {
      sink(line);
      return;
    }
    debugPrint(line);
  }

  /// Structured event emission (Sprint 1 M2).
  ///
  /// Prevents free-text CV bodies from leaking as the primary log message.
  /// All dynamic context must be passed via [data].
  static void event(
    AppLogEvent event, {
    AppLogLevel level = AppLogLevel.info,
    Map<String, Object?>? data,
    Object? error,
    StackTrace? stackTrace,
  }) {
    final payload = <String, Object?>{
      ...?data,
      if (error != null) 'error': error.toString(),
    };
    if (level == AppLogLevel.error) {
      AppLog.error(
        event.eventId,
        error: error,
        stackTrace: stackTrace,
        data: data,
      );
    } else if (level == AppLogLevel.warn) {
      AppLog.warn(event.eventId, data: payload);
    } else if (level == AppLogLevel.debug) {
      AppLog.debug(event.eventId, data: payload);
    } else {
      AppLog.info(event.eventId, data: payload);
    }
  }

  static void debug(String message, {Map<String, Object?>? data}) {
    _emit(AppLogLevel.debug, message, data);
  }

  static void info(String message, {Map<String, Object?>? data}) {
    _emit(AppLogLevel.info, message, data);
  }

  static void warn(String message, {Map<String, Object?>? data}) {
    _emit(AppLogLevel.warn, message, data);
  }

  static void error(
    String message, {
    Object? error,
    StackTrace? stackTrace,
    Map<String, Object?>? data,
  }) {
    _emit(
      AppLogLevel.error,
      message,
      {
        ...?data,
        if (error != null) 'error': redact(error.toString()),
      },
    );
    if (kDebugMode && !treatAsRelease && stackTrace != null) {
      _print('$stackTrace');
    }
  }

  static void _emit(
    AppLogLevel level,
    String message,
    Map<String, Object?>? data,
  ) {
    if (level == AppLogLevel.debug && _useReleasePolicy) return;

    final isKnownEvent = _knownEventIds.contains(message);
    final String safeMessage;
    if (_useReleasePolicy) {
      // Release: only structured event identifiers. Free-text (including
      // résumé paragraphs) is dropped, not truncated-and-kept.
      safeMessage = isKnownEvent ? message : AppLogEvent.diagnostic.eventId;
    } else {
      safeMessage = isKnownEvent ? message : redact(message);
    }

    final safeData = data == null ? null : redactMap(data);
    final buffer = StringBuffer('[${level.name.toUpperCase()}] $safeMessage');
    if (safeData != null && safeData.isNotEmpty) {
      buffer.write(' ');
      buffer.write(safeData);
    }
    _print(buffer.toString());
  }

  /// Redacts PII patterns in a string. Oversized blobs are omitted entirely
  /// so the first 280 characters of a CV body cannot leak.
  static String redact(String input) {
    var out = input;
    out = out.replaceAll(_email, '[email]');
    out = out.replaceAllMapped(_nationalId, (m) => '${m[1]}[id]');
    out = out.replaceAllMapped(_labelledPhone, (m) => '${m[1]}[phone]');
    out = out.replaceAll(_phone, '[phone]');
    out = out.replaceAll(_jsonPiiField, r'$1"[Filtered]"');
    if (out.length > _maxMessageChars) {
      return '[omitted ${out.length} chars]';
    }
    return out;
  }

  static Map<String, Object?> redactMap(Map<String, Object?> data) {
    final out = <String, Object?>{};
    data.forEach((key, value) {
      if (_piiKeys.contains(key.toLowerCase())) {
        out[key] = '[Filtered]';
        return;
      }
      out[key] = _redactValue(value);
    });
    return out;
  }

  static Object? _redactValue(Object? value) {
    if (value is String) {
      return redact(value);
    }
    if (value is Map<String, Object?>) {
      return redactMap(value);
    }
    if (value is Map) {
      return redactMap(Map<String, Object?>.from(value));
    }
    if (value is List) {
      return [
        for (final item in value) _redactValue(item),
      ];
    }
    return value;
  }

  /// Localized, non-technical copy for users. Never include [error] text.
  static String userMessage({required bool english}) {
    return english
        ? 'Something went wrong. Your data is safe — try again.'
        : 'حدث خطأ. بياناتك بأمان — حاول مرة أخرى.';
  }

  /// Sanitizes an exception while preserving runtime exception types so
  /// Crashlytics and Sentry error grouping remains accurate (N5 & R4).
  static Object sanitizeError(Object error) {
    if (error is SanitizedAppException) {
      return error;
    }
    final sanitized = redact(error.toString());
    if (error is FormatException) {
      return FormatException(sanitized, error.source, error.offset);
    }
    if (error is StateError) {
      return StateError(sanitized);
    }
    if (error is ArgumentError) {
      return ArgumentError.value(error.invalidValue, error.name, sanitized);
    }
    if (error is RangeError) {
      return RangeError(sanitized);
    }
    if (error is UnsupportedError) {
      return UnsupportedError(sanitized);
    }
    if (error is TimeoutException) {
      return TimeoutException(sanitized, error.duration);
    }
    if (error is FlutterError) {
      return FlutterError(sanitized);
    }
    return SanitizedAppException(error.runtimeType.toString(), sanitized);
  }

  @visibleForTesting
  static void resetForTest() {
    treatAsRelease = false;
    printOverride = null;
  }
}

class SanitizedAppException implements Exception {
  final String errorType;
  final String message;

  const SanitizedAppException(this.errorType, this.message);

  @override
  String toString() => '$errorType: $message';
}
