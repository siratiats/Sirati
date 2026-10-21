/// Canonical async UI state for Sirati (SIRATI-12).
///
/// **Decision:** Dart 3 sealed [AsyncState] plus [ChangeNotifier] controllers.
///
/// Alternatives rejected:
/// * **Riverpod / Bloc** — extra packages and codegen. This app has no
///   existing provider graph; mixing them with the current
///   `StatefulWidget` + service screens would split the codebase.
/// * **Raw `FutureBuilder`** — loading/error/success live in the widget,
///   so the pattern is not unit-testable without pumping frames.
/// * **`setState` + nullable fields** — easy to forget one of the three
///   states; not reusable across features.
///
/// Adopt this for new feature work. Do not introduce a second approach.
library;

/// Loading, success, or failure. Exhaustive `switch` is the API.
sealed class AsyncState<T> {
  const AsyncState();

  bool get isLoading => this is AsyncLoading<T>;
  bool get isSuccess => this is AsyncSuccess<T>;
  bool get isFailure => this is AsyncFailure<T>;

  T? get dataOrNull => switch (this) {
        AsyncSuccess<T>(:final data) => data,
        _ => null,
      };

  Object? get errorOrNull => switch (this) {
        AsyncFailure<T>(:final error) => error,
        _ => null,
      };
}

final class AsyncLoading<T> extends AsyncState<T> {
  const AsyncLoading();
}

final class AsyncSuccess<T> extends AsyncState<T> {
  const AsyncSuccess(this.data);
  final T data;
}

final class AsyncFailure<T> extends AsyncState<T> {
  const AsyncFailure(this.error);
  final Object error;
}
