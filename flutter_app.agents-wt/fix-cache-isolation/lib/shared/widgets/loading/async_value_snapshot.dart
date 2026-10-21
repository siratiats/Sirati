import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Bridges Riverpod's [AsyncValue] to the [AsyncSnapshot] shape that
/// `AppAsyncBody` and older FutureBuilder-based widgets expect, so screens
/// can move to providers without rewriting their loading/error UI.
extension AsyncValueSnapshot<T> on AsyncValue<T> {
  AsyncSnapshot<T> toSnapshot() {
    if (hasValue) {
      // A reload keeps the previous data visible, like FutureBuilder does
      // when its future is swapped.
      return AsyncSnapshot<T>.withData(
        isLoading ? ConnectionState.waiting : ConnectionState.done,
        value as T,
      );
    }
    if (hasError) {
      return AsyncSnapshot<T>.withError(
        ConnectionState.done,
        error!,
        stackTrace ?? StackTrace.empty,
      );
    }
    return AsyncSnapshot<T>.waiting();
  }
}
