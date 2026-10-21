import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/features/auth/domain/registration_rules.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';

sealed class RegisterOutcome {
  const RegisterOutcome();
}

/// Account created; [email] is where the verification code was sent.
final class RegisterSucceeded extends RegisterOutcome {
  const RegisterSucceeded(this.email);
  final String email;
}

/// The server rejected the request (validation, duplicate email, network).
final class RegisterRejected extends RegisterOutcome {
  const RegisterRejected(this.error);
  final ApiException error;
}

final class RegisterFailedUnexpectedly extends RegisterOutcome {
  const RegisterFailedUnexpectedly();
}

/// A submit arrived while another was in flight and was dropped.
final class RegisterIgnored extends RegisterOutcome {
  const RegisterIgnored();
}

/// Whether a registration request is in flight.
final registerControllerProvider =
    NotifierProvider.autoDispose<RegisterController, bool>(
  RegisterController.new,
);

class RegisterController extends Notifier<bool> {
  @override
  bool build() => false;

  /// Creates the account. Only one request runs at a time: the keyboard's
  /// Done action and the button can both fire, and a duplicate POST would
  /// surface as a confusing "email already taken" error.
  Future<RegisterOutcome> submit(RegistrationInput input) async {
    if (state) return const RegisterIgnored();
    state = true;
    try {
      final session = await ref.read(authApiServiceProvider).register(
            name: input.name,
            email: input.email,
            password: input.password,
            passwordConfirmation: input.password,
            phone: input.phone,
            location: input.location,
            jobTitleId: input.jobTitleId,
            jobTitleOther: input.jobTitleOther,
          );
      final email = session.user.email.trim();
      return RegisterSucceeded(email.isNotEmpty ? email : input.email);
    } on ApiException catch (e) {
      return RegisterRejected(e);
    } catch (_) {
      return const RegisterFailedUnexpectedly();
    } finally {
      if (ref.mounted) state = false;
    }
  }
}
