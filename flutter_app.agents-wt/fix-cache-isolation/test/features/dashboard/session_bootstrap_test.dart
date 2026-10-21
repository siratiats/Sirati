import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/features/dashboard/application/session_bootstrap.dart';
import 'package:sirati/shared/models/auth_session.dart';

/// A dependency outcome: a value, or an error to throw.
sealed class _Outcome<T> {
  const _Outcome();
  Future<T> run();
}

final class _Value<T> extends _Outcome<T> {
  const _Value(this.value);
  final T value;
  @override
  Future<T> run() async => value;
  @override
  String toString() => 'value($value)';
}

final class _Throws<T> extends _Outcome<T> {
  const _Throws(this.error);
  final Object error;
  @override
  Future<T> run() async => throw error;
  @override
  String toString() => 'throws($error)';
}

const _verified = AuthUser(
  id: 1,
  name: 'A',
  email: 'a@x.io',
  emailVerified: true,
);
const _unverified = AuthUser(id: 2, name: 'B', email: 'b@x.io');

void main() {
  final tokens = <_Outcome<String?>>[
    const _Value(null),
    const _Value(''),
    const _Value('   '),
    const _Value('tok'),
    _Throws(StateError('keystore')),
  ];
  final users = <_Outcome<AuthUser?>>[
    const _Value(_verified),
    const _Value(_unverified),
    const _Value(null),
    const _Throws(
        ApiException('401', statusCode: 401, type: ApiErrorType.auth)),
    const _Throws(
        ApiException('403', statusCode: 403, type: ApiErrorType.auth)),
    const _Throws(ApiException('down', type: ApiErrorType.network)),
    const _Throws(ApiException('slow', type: ApiErrorType.timeout)),
    const _Throws(
        ApiException('500', statusCode: 500, type: ApiErrorType.server)),
    const _Throws(FormatException('bad json')),
  ];
  final onboarding = <_Outcome<bool>>[
    const _Value(true),
    const _Value(false),
    _Throws(StateError('prefs')),
  ];

  /// The launch rule, stated independently of the implementation.
  Type expected(_Outcome<String?> token, _Outcome<AuthUser?> user,
      _Outcome<bool> onboarded) {
    final signedOut = onboarded is _Value<bool> && onboarded.value
        ? LaunchWelcome
        : LaunchOnboarding;

    final hasToken =
        token is _Value<String?> && (token.value?.trim().isNotEmpty ?? false);
    if (!hasToken) return signedOut;

    if (user case _Throws(:final ApiException error)
        when error.type == ApiErrorType.auth) {
      return signedOut;
    }
    if (user case _Value(value: final AuthUser u) when !u.emailVerified) {
      return LaunchVerifyEmail;
    }
    return LaunchHome;
  }

  test('every combination of dependency outcomes resolves as specified',
      () async {
    for (final token in tokens) {
      for (final user in users) {
        for (final onboarded in onboarding) {
          var userCalls = 0;
          final bootstrap = SessionBootstrap(
            readToken: token.run,
            fetchUser: () {
              userCalls++;
              return user.run();
            },
            onboardingCompleted: onboarded.run,
          );
          final destination = await bootstrap.resolve();
          final reason = 'token=$token user=$user onboarded=$onboarded';
          expect(destination.runtimeType, expected(token, user, onboarded),
              reason: reason);
          if (destination case LaunchVerifyEmail(:final email)) {
            expect(email, _unverified.email, reason: reason);
          }
          // Without a token the server is never asked who we are.
          final hasToken = token is _Value<String?> &&
              (token.value?.trim().isNotEmpty ?? false);
          expect(userCalls, hasToken ? 1 : 0, reason: reason);
        }
      }
    }
  });

  test('a rejected session never stalls: both 401 and 403 fall through',
      () async {
    for (final status in [401, 403]) {
      final bootstrap = SessionBootstrap(
        readToken: () async => 'tok',
        fetchUser: () async => throw ApiException('x',
            statusCode: status, type: ApiErrorType.auth),
        onboardingCompleted: () async => true,
      );
      expect(await bootstrap.authenticated(), isNull);
      expect(await bootstrap.resolve(), isA<LaunchWelcome>());
    }
  });
}
