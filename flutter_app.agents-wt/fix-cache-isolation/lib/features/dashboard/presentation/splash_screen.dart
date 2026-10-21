import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/routing/app_router.dart';
import 'package:sirati/core/utils/root_navigation.dart';
import 'package:sirati/features/auth/presentation/email_verification_screen.dart';
import 'package:sirati/features/auth/presentation/login_screen.dart';
import 'package:sirati/features/auth/presentation/register_screen.dart';
import 'package:sirati/features/dashboard/application/session_bootstrap.dart';
import 'package:sirati/features/dashboard/presentation/splash/splash_boot_body.dart';
import 'package:sirati/features/dashboard/presentation/splash/welcome_body.dart';
import 'package:sirati/features/settings/presentation/onboarding_screen.dart';
import 'package:sirati/features/settings/presentation/privacy_policy_screen.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

enum _SplashPhase { boot, onboarding, welcome }

/// App entry: resolves the stored session, then routes to Home, email
/// verification, onboarding (first run), or the signed-out welcome.
class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  _SplashPhase _phase = _SplashPhase.boot;

  /// Blocks a second "Sign In" tap while the first is still resolving.
  bool _signingIn = false;

  SessionBootstrap get _bootstrap => ref.read(sessionBootstrapProvider);

  @override
  void initState() {
    super.initState();
    unawaited(_bootstrapSession());
  }

  Future<void> _bootstrapSession() async {
    final destination = await _bootstrap.resolve();
    if (mounted) _go(destination);
  }

  void _go(LaunchDestination destination) {
    switch (destination) {
      case LaunchHome():
        AppRouter.openAfterAuth(context);
      case LaunchVerifyEmail(:final email):
        replaceRoot(context, EmailVerificationScreen(email: email));
      case LaunchOnboarding():
        setState(() => _phase = _SplashPhase.onboarding);
      case LaunchWelcome():
        setState(() => _phase = _SplashPhase.welcome);
    }
  }

  /// A session saved since the splash (e.g. restored in the background)
  /// skips the login form.
  Future<void> _goToLogin() async {
    if (_signingIn) return;
    _signingIn = true;
    try {
      final bootstrap = _bootstrap;
      if (await bootstrap.hasToken()) {
        final destination = await bootstrap.authenticated();
        if (!mounted) return;
        if (destination != null) {
          _go(destination);
          return;
        }
      }
      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
    } finally {
      _signingIn = false;
    }
  }

  void _push(Widget screen) {
    unawaited(Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => screen),
    ));
  }

  @override
  Widget build(BuildContext context) {
    final reduceMotion = MotionSettings.reduce(context);
    return Scaffold(
      backgroundColor: context.sirati.background,
      body: AnimatedSwitcher(
        duration: MotionDurations.medium,
        reverseDuration: MotionDurations.fast,
        switchInCurve: MotionCurves.enter,
        switchOutCurve: MotionCurves.exit,
        transitionBuilder: (child, animation) {
          if (reduceMotion) return child;
          if ((child.key as ValueKey?)?.value == 'boot') {
            // The exiting boot circle scales down as it fades.
            return ScaleTransition(
              scale: Tween<double>(begin: 0.85, end: 1.0).animate(animation),
              child: FadeTransition(opacity: animation, child: child),
            );
          }
          return FadeTransition(
            opacity: animation,
            child: ScaleTransition(
              scale: Tween<double>(begin: 0.95, end: 1.0).animate(animation),
              child: child,
            ),
          );
        },
        child: switch (_phase) {
          _SplashPhase.boot => const SplashBootBody(key: ValueKey('boot')),
          _SplashPhase.onboarding => OnboardingScreen(
              key: const ValueKey('onboarding'),
              onFinished: () {
                if (mounted) setState(() => _phase = _SplashPhase.welcome);
              },
            ),
          _SplashPhase.welcome => WelcomeBody(
              key: const ValueKey('welcome'),
              onRegister: () => _push(const RegisterScreen()),
              onLogin: () => unawaited(_goToLogin()),
              onPrivacy: () => _push(const PrivacyPolicyScreen()),
            ),
        },
      ),
    );
  }
}
