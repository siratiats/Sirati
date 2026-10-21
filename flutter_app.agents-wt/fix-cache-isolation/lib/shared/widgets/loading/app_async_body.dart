import 'package:flutter/material.dart';

import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/shared/widgets/empty_state.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Maps a [AsyncSnapshot] into loading / error / data without restructuring
/// the parent scaffold (header/nav stay mounted when [keepShell] content is used).
class AppAsyncBody<T> extends StatelessWidget {
  final AsyncSnapshot<T> snapshot;
  final Widget loading;
  final Widget Function(T data) builder;
  final bool Function(T data)? isEmpty;
  final Widget? empty;
  final String Function(Object error)? errorMessage;

  /// Optional custom error UI (e.g. show navigated fallback content).
  final Widget Function(Object error, String message)? errorBuilder;
  final VoidCallback? onRetry;
  final bool english;

  /// When true and snapshot has no data yet but has an error, show error.
  /// When false and waiting with no data, show [loading].
  final T? fallbackOnEmptyError;

  const AppAsyncBody({
    super.key,
    required this.snapshot,
    required this.loading,
    required this.builder,
    required this.english,
    this.isEmpty,
    this.empty,
    this.errorMessage,
    this.errorBuilder,
    this.onRetry,
    this.fallbackOnEmptyError,
  });

  @override
  Widget build(BuildContext context) {
    final waiting = snapshot.connectionState == ConnectionState.waiting &&
        !snapshot.hasData;

    if (waiting) {
      return MotionStateSwitcher(stateKey: 'loading', child: loading);
    }

    if (snapshot.hasError && !snapshot.hasData) {
      final error = snapshot.error!;
      final message = _resolveError(error);
      if (errorBuilder != null) {
        return MotionStateSwitcher(
          stateKey: 'custom-error',
          child: errorBuilder!(error, message),
        );
      }
      return MotionStateSwitcher(
        stateKey: 'error',
        child: AppErrorState(
          message: message,
          english: english,
          onRetry: onRetry,
          exception: error is ApiException ? error : null,
        ),
      );
    }

    final data = snapshot.data ?? fallbackOnEmptyError;
    if (data == null) {
      return MotionStateSwitcher(
        stateKey: 'no-data',
        child: AppErrorState(
          message: english ? 'No data available.' : 'لا توجد بيانات.',
          english: english,
          onRetry: onRetry,
          errorType: ApiErrorType.unknown,
        ),
      );
    }

    if (isEmpty != null && isEmpty!(data) && empty != null) {
      return MotionStateSwitcher(stateKey: 'empty', child: empty!);
    }

    return MotionStateSwitcher(stateKey: 'data', child: builder(data));
  }

  String _resolveError(Object error) {
    if (errorMessage != null) return errorMessage!(error);
    if (error is ApiException) return error.displayMessage;
    return english ? 'Something went wrong.' : 'حدث خطأ ما.';
  }
}
