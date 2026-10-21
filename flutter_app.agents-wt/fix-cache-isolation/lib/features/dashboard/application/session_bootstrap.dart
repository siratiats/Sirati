import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/storage/auth_token_store.dart';
import 'package:sirati/core/storage/preference_store.dart';
import 'package:sirati/shared/models/auth_session.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';

/// Where the app should go after the splash.
sealed class LaunchDestination {
  const LaunchDestination();
}

final class LaunchHome extends LaunchDestination {
  const LaunchHome();
}

/// Signed in, but the email isn't verified yet.
final class LaunchVerifyEmail extends LaunchDestination {
  const LaunchVerifyEmail(this.email);
  final String email;
}

final class LaunchOnboarding extends LaunchDestination {
  const LaunchOnboarding();
}

final class LaunchWelcome extends LaunchDestination {
  const LaunchWelcome();
}

/// Decides the launch destination from the stored token, the server's view
/// of the user, and whether onboarding was completed.
///
/// Every dependency failure resolves to a destination — the splash never
/// waits on something that can't finish.
class SessionBootstrap {
  const SessionBootstrap({
    required this.readToken,
    required this.fetchUser,
    required this.onboardingCompleted,
  });

  final Future<String?> Function() readToken;
  final Future<AuthUser?> Function() fetchUser;
  final Future<bool> Function() onboardingCompleted;

  Future<LaunchDestination> resolve() async {
    if (await hasToken()) {
      final destination = await authenticated();
      if (destination != null) return destination;
    }
    return unauthenticated();
  }

  /// Unreadable storage counts as signed out.
  Future<bool> hasToken() async {
    try {
      final token = await readToken();
      return token != null && token.trim().isNotEmpty;
    } catch (_) {
      return false;
    }
  }

  /// Destination for a stored session, or null when the server rejected it.
  ///
  /// Network, timeout, and server errors still open Home: the token is kept
  /// and every API call is re-authorised server-side, so offline users keep
  /// their cached content.
  Future<LaunchDestination?> authenticated() async {
    try {
      final user = await fetchUser();
      if (user != null && !user.emailVerified) {
        return LaunchVerifyEmail(user.email);
      }
    } on ApiException catch (e) {
      // The 401 handler clears the session; land on the signed-out flow
      // rather than waiting for it to navigate.
      if (e.type == ApiErrorType.auth) return null;
    } catch (_) {
      // Offline or unexpected: continue to Home.
    }
    return const LaunchHome();
  }

  /// Onboarding until it's been completed once; unreadable prefs show it.
  Future<LaunchDestination> unauthenticated() async {
    var completed = false;
    try {
      completed = await onboardingCompleted();
    } catch (_) {}
    return completed ? const LaunchWelcome() : const LaunchOnboarding();
  }
}

final sessionBootstrapProvider = Provider<SessionBootstrap>((ref) {
  const tokens = AuthTokenStore();
  const prefs = PreferenceStore();
  final auth = ref.read(authApiServiceProvider);
  return SessionBootstrap(
    readToken: tokens.readToken,
    fetchUser: auth.me,
    onboardingCompleted: prefs.readOnboardingCompleted,
  );
});
