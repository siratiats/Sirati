import 'package:flutter/material.dart';

import 'package:sirati/core/logging/app_log.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/utils/app_locale.dart';

/// Recoverable UI for uncaught Flutter errors (SIRATI-16).
///
/// Lives in `core/` so it must not import `shared/` or `features/`.
const _crashBackground =
    Color(0xFFF4F7F6); // ignore: hardcoded_color -- no_theme
const _crashAccent = Color(0xFFD8403A); // ignore: hardcoded_color -- no_theme

class AppCrashView extends StatelessWidget {
  const AppCrashView({super.key});

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.languageCode.value == 'en';
    return Directionality(
      textDirection: english ? TextDirection.ltr : TextDirection.rtl,
      child: Material(
        // Shown when the app has crashed, possibly before any theme exists;
        // core may not import the shared palette.
        color: _crashBackground,
        child: SafeArea(
          child: _CrashBody(
            english: english,
            title: english ? 'Something went wrong' : 'حدث خطأ',
            message: AppLog.userMessage(english: english),
          ),
        ),
      ),
    );
  }
}

/// In-app recoverable panel (has a real [Navigator]).
class AppRecoverableError extends StatelessWidget {
  const AppRecoverableError({super.key});

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: _CrashBody(
        english: english,
        title: english ? 'Something went wrong' : 'حدث خطأ',
        message: AppLog.userMessage(english: english),
        onRetry: () {
          Navigator.of(context).pushNamedAndRemoveUntil(
            AppRoutes.home,
            (route) => false,
          );
        },
      ),
    );
  }
}

class _CrashBody extends StatelessWidget {
  const _CrashBody({
    required this.english,
    required this.title,
    required this.message,
    this.onRetry,
  });

  final bool english;
  final String title;
  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.error_outline_rounded,
              size: 48,
              color: _crashAccent,
            ),
            const SizedBox(height: 16),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 15, height: 1.45),
            ),
            if (onRetry != null) ...[
              const SizedBox(height: 20),
              FilledButton(
                onPressed: onRetry,
                child: Text(english ? 'Retry' : 'إعادة المحاولة'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
