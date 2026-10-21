import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';

sealed class LoginOutcome {
  const LoginOutcome();
}

/// Signed in; [emailVerified] decides between Home and the OTP screen.
final class LoginSucceeded extends LoginOutcome {
  const LoginSucceeded({required this.email, required this.emailVerified});
  final String email;
  final bool emailVerified;
}

final class LoginRejected extends LoginOutcome {
  const LoginRejected(this.error);
  final ApiException error;
}

final class LoginFailedUnexpectedly extends LoginOutcome {
  const LoginFailedUnexpectedly();
}

/// Dropped because a sign-in was already running.
final class LoginIgnored extends LoginOutcome {
  const LoginIgnored();
}

/// Login form rule: the server only requires a password to be present, so
/// the client must not impose a length that could lock out valid accounts.
String? loginPasswordError(String? value) =>
    (value == null || value.isEmpty) ? 'required' : null;

/// Whether a sign-in request is in flight.
final loginControllerProvider =
    NotifierProvider.autoDispose<LoginController, bool>(LoginController.new);

class LoginController extends Notifier<bool> {
  @override
  bool build() => false;

  /// One request at a time: keyboard Send, the button, and the snackbar
  /// Retry can all fire while a sign-in is running.
  Future<LoginOutcome> signIn({
    required String email,
    required String password,
  }) async {
    if (state) return const LoginIgnored();
    state = true;
    try {
      final session = await ref
          .read(authApiServiceProvider)
          .login(email: email.trim(), password: password);
      return LoginSucceeded(
        email: session.user.email,
        emailVerified: session.user.emailVerified,
      );
    } on ApiException catch (e) {
      return LoginRejected(e);
    } catch (_) {
      return const LoginFailedUnexpectedly();
    } finally {
      if (ref.mounted) state = false;
    }
  }
}
